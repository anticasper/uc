(function () {
const root = document.querySelector(".uc-map-root");

if (!root) {
  return;
}

let allParks = [];

const UF_BY_STATE = {
  Acre: "AC",
  Alagoas: "AL",
  Amapa: "AP",
  Amazonas: "AM",
  Bahia: "BA",
  Ceara: "CE",
  "Distrito Federal": "DF",
  "Espirito Santo": "ES",
  Goias: "GO",
  Maranhao: "MA",
  "Mato Grosso": "MT",
  "Mato Grosso do Sul": "MS",
  "Minas Gerais": "MG",
  Para: "PA",
  Paraiba: "PB",
  Parana: "PR",
  Pernambuco: "PE",
  Piaui: "PI",
  "Rio de Janeiro": "RJ",
  "Rio Grande do Norte": "RN",
  "Rio Grande do Sul": "RS",
  Rondonia: "RO",
  Roraima: "RR",
  "Santa Catarina": "SC",
  "Sao Paulo": "SP",
  Sergipe: "SE",
  Tocantins: "TO",
};

const state = {
  items: [],
  activeId: null,
  activeStateName: null,
  stateSearch: "",
  markers: new Map(),
};

const resultsCount = root.querySelector("#results-count");
const stateCount = root.querySelector("#state-count");
const dialogCount = root.querySelector("#dialog-count");
const resultsList = root.querySelector("#results-list");
const stateGroupTemplate = root.querySelector("#state-group-template");
const parkItemTemplate = root.querySelector("#park-item-template");
const form = root.querySelector("#filters-form");
const searchInput = root.querySelector("#search");
const stateSearchInput = root.querySelector("#state-search");
const markerLogoUrl = window.UCMapConfig?.markerLogoUrl || "";

const map = L.map(root.querySelector("#map"), {
  zoomControl: true,
  minZoom: 4,
  maxZoom: 18,
}).setView([-14.235, -51.9253], 4);

L.tileLayer("https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png", {
  maxZoom: 19,
  attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
}).addTo(map);

const brazilBounds = L.latLngBounds(
  L.latLng(-34.2, -74.5),
  L.latLng(5.7, -30.8)
);

map.setMaxBounds(brazilBounds.pad(0.18));

function normalize(text) {
  return String(text || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase();
}

function decodeHtml(value) {
  const textarea = document.createElement("textarea");
  textarea.innerHTML = String(value ?? "");
  return textarea.value;
}

function firstValue(...values) {
  const value = values.find((item) => item !== undefined && item !== null && String(item).trim() !== "");
  return value === undefined ? "" : decodeHtml(value);
}

function toNumber(value) {
  const number = Number(value);
  return Number.isFinite(number) ? number : null;
}

function getActivities(item) {
  if (Array.isArray(item?.atividades)) {
    return item.atividades;
  }

  if (Array.isArray(item?.activities)) {
    return item.activities;
  }

  return [];
}

function escapeAttribute(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/"/g, "&quot;")
    .replace(/</g, "&lt;");
}

function createMarkerIcon(tone = "default") {
  const toneClass = tone === "active" ? "pin-marker--active" : tone === "selected" ? "pin-marker--selected" : "";
  const logo = markerLogoUrl
    ? `<img src="${escapeAttribute(markerLogoUrl)}" alt="" width="12" height="12" loading="lazy" style="display:block;width:12px!important;height:12px!important;max-width:12px!important;max-height:12px!important;object-fit:contain!important;">`
    : `<span aria-hidden="true"></span>`;

  return L.divIcon({
    className: "",
    html: `
      <div class="pin-marker ${toneClass}" style="width:12px!important;height:12px!important;overflow:hidden!important;">
        ${logo}
      </div>
    `,
    iconSize: [12, 12],
    iconAnchor: [6, 6],
    popupAnchor: [0, -6],
  });
}

function getMarkerTone(item) {
  if (item.id === state.activeId) {
    return "active";
  }

  if (state.activeStateName && item.state === state.activeStateName) {
    return "selected";
  }

  return "default";
}

function setStatus(message, tone = "default") {
  const color = tone === "error" ? "#b42318" : "#8fa59a";
  resultsList.innerHTML = `
    <div style="color:${color};font-size:12px;font-weight:800;padding:18px 6px;">
      ${message}
    </div>
  `;
}

function getUfLabel(stateName) {
  const key = Object.keys(UF_BY_STATE).find((name) => normalize(name) === normalize(stateName));
  return key ? UF_BY_STATE[key] : stateName.slice(0, 2).toUpperCase();
}

function groupItemsByState(items) {
  const grouped = new Map();

  items.forEach((item) => {
    const key = item.state || "Sem estado";
    if (!grouped.has(key)) {
      grouped.set(key, []);
    }
    grouped.get(key).push(item);
  });

  return [...grouped.entries()]
    .map(([stateName, parks]) => ({
      stateName,
      parks: [...parks].sort((a, b) => a.name.localeCompare(b.name, "pt-BR")),
    }))
    .sort((a, b) => a.stateName.localeCompare(b.stateName, "pt-BR"));
}

function getVisibleGroups(items) {
  const search = normalize(state.stateSearch);
  const groups = groupItemsByState(items);

  if (!search) {
    return groups;
  }

  return groups.filter((group) => normalize(group.stateName).includes(search) || normalize(getUfLabel(group.stateName)).includes(search));
}

function summarizeState(parks) {
  const biomes = [...new Set(parks.map((park) => park.biome).filter(Boolean))].slice(0, 3);
  return `${parks.length} UCs - ${biomes.join(" / ")}`;
}

function populateFilters() {
  const filterMap = [
    { id: "park-filter", key: "name" },
    { id: "biome-filter", key: "biome" },
    { id: "city-filter", key: "city" },
    { id: "activity-filter", key: "activity" },
  ];

  filterMap.forEach(({ id, key }) => {
    const select = root.querySelector(`#${id}`);
    const placeholder = select.options[0];
    const currentValue = select.value;

    select.innerHTML = "";
    select.appendChild(placeholder);

    const values = [...new Set(allParks.map((park) => park[key]).filter(Boolean))].sort((a, b) => a.localeCompare(b, "pt-BR"));

    values.forEach((value) => {
      const option = document.createElement("option");
      option.value = value;
      option.textContent = value;
      select.appendChild(option);
    });

    select.value = values.includes(currentValue) ? currentValue : "";
  });
}

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function getUcUrl(item) {
  if (item.url) {
    return item.url;
  }

  const slug = item.slug || item.uc_slug || "";

  if (!slug) {
    return window.UCMapConfig?.fallbackUcUrl || "/modelo-de-unidades-de-conservacao/";
  }

  const cleanSlug = String(slug).replace(/^\/+|\/+$/g, "");
  const siteUrl = window.UCMapConfig?.siteUrl || "/";

  return `${siteUrl.replace(/\/+$/g, "")}/${cleanSlug}/`;
}

function buildPopup(item) {
  const meta = [item.city, item.state].filter(Boolean).join(" | ");
  const details = [
    item.biome ? `<span>${escapeHtml(item.biome)}</span>` : "",
    item.activity ? `<span>${escapeHtml(item.activity)}</span>` : "",
  ].filter(Boolean).join(" | ");
  const description = item.description || item.endereco || "Sem descricao.";
  const ucUrl = getUcUrl(item);

  return `
    <div class="map-popup">
      <p class="map-popup__meta">${escapeHtml(meta)}</p>
      <h3 class="map-popup__title">${escapeHtml(item.name)}</h3>
      ${details ? `<p class="map-popup__details">${details}</p>` : ""}
      <p class="map-popup__text">${escapeHtml(description)}</p>
      <a class="map-popup__button" href="${escapeHtml(ucUrl)}">Ver UC</a>
    </div>
  `;
}

function updateMarkerIcons() {
  state.markers.forEach((marker, markerId) => {
    const item = state.items.find((park) => park.id === markerId);
    if (item) {
      marker.setIcon(createMarkerIcon(getMarkerTone(item)));
    }
  });
}

function updateActiveCard() {
  root.querySelectorAll(".result-card").forEach((card) => {
    card.classList.toggle("is-active", String(card.dataset.id) === String(state.activeId));
  });
}

function fitItemsOnMap(items, maxZoom = 7) {
  if (!items.length) {
    map.fitBounds(brazilBounds, { padding: [24, 24] });
    return;
  }

  const bounds = L.latLngBounds(items.map((item) => [item.lat, item.lng]));
  map.fitBounds(bounds.pad(0.18), { maxZoom });
}

function setActiveItem(id, flyTo = false) {
  const item = state.items.find((park) => park.id === id);
  const marker = state.markers.get(id);

  if (!item || !marker) {
    return;
  }

  const previousStateName = state.activeStateName;
  state.activeId = id;
  state.activeStateName = item.state;

  if (previousStateName !== state.activeStateName) {
    renderGroupedList(state.items);
  }

  updateActiveCard();
  updateMarkerIcons();

  if (flyTo) {
    map.flyTo([item.lat, item.lng], 8, { duration: 0.8 });
  }

  marker.openPopup();
}

function toggleStateGroup(stateName, parks) {
  const nextStateName = state.activeStateName === stateName ? null : stateName;
  state.activeStateName = nextStateName;
  state.activeId = null;

  renderGroupedList(state.items);
  updateMarkerIcons();

  if (nextStateName) {
    fitItemsOnMap(parks, 7);
  } else {
    fitItemsOnMap(state.items, 5);
  }
}

function createParkCard(item) {
  const card = parkItemTemplate.content.firstElementChild.cloneNode(true);
  card.dataset.id = item.id;
  card.querySelector("[data-name]").textContent = item.name;
  card.querySelector("[data-location]").textContent = item.city || item.state;
  card.querySelector("[data-description]").textContent = item.description || item.endereco || "Sem descricao disponivel.";
  card.addEventListener("click", () => setActiveItem(item.id, true));
  return card;
}

function renderGroupedList(items) {
  resultsList.innerHTML = "";

  const allGroups = groupItemsByState(items);
  const visibleGroups = getVisibleGroups(items);
  const totalItems = items.length;

  resultsCount.textContent = String(totalItems);
  dialogCount.textContent = String(totalItems);
  stateCount.textContent = `${visibleGroups.length} ${visibleGroups.length === 1 ? "estado" : "estados"}`;

  if (!visibleGroups.length) {
    setStatus("Nenhum estado encontrado.");
    return;
  }

  if (state.activeStateName && !allGroups.some((group) => group.stateName === state.activeStateName)) {
    state.activeStateName = null;
    state.activeId = null;
  }

  const fragment = document.createDocumentFragment();

  visibleGroups.forEach((group) => {
    const section = stateGroupTemplate.content.firstElementChild.cloneNode(true);
    const header = section.querySelector(".state-group__header");
    const body = section.querySelector("[data-state-body]");
    const isOpen = group.stateName === state.activeStateName;

    section.dataset.state = group.stateName;
    section.classList.toggle("is-open", isOpen);
    section.querySelector("[data-uf]").textContent = getUfLabel(group.stateName);
    section.querySelector("[data-state-name]").textContent = group.stateName;
    section.querySelector("[data-state-meta]").textContent = summarizeState(group.parks);

    if (isOpen) {
      group.parks.forEach((item) => {
        body.appendChild(createParkCard(item));
      });
    }

    header.addEventListener("click", () => toggleStateGroup(group.stateName, group.parks));
    fragment.appendChild(section);
  });

  resultsList.appendChild(fragment);
  updateActiveCard();
}

function renderMarkers(items) {
  state.markers.forEach((marker) => marker.remove());
  state.markers.clear();

  items.forEach((item) => {
    const marker = L.marker([item.lat, item.lng], {
      icon: createMarkerIcon(getMarkerTone(item)),
      title: item.name,
    }).addTo(map);

    marker.bindPopup(buildPopup(item));
    marker.on("click", () => setActiveItem(item.id, false));
    state.markers.set(item.id, marker);
  });

  fitItemsOnMap(items, 5);
}

function filterItems() {
  const formData = new FormData(form);
  const search = normalize(formData.get("search"));
  const park = String(formData.get("park") || "");
  const biome = String(formData.get("biome") || "");
  const city = String(formData.get("city") || "");
  const activity = String(formData.get("activity") || "");

  state.items = allParks.filter((item) => {
    const searchSource = [
      item.name,
      item.city,
      item.state,
      item.biome,
      item.activity,
      item.endereco,
      item.cep,
    ].join(" ");

    return (
      (!search || normalize(searchSource).includes(search)) &&
      (!park || item.name === park) &&
      (!biome || item.biome === biome) &&
      (!city || item.city === city) &&
      (!activity || item.activity === activity)
    );
  });

  if (!state.items.some((item) => item.id === state.activeId)) {
    state.activeId = null;
  }

  if (state.activeStateName && !state.items.some((item) => item.state === state.activeStateName)) {
    state.activeStateName = null;
  }

  renderGroupedList(state.items);
  renderMarkers(state.items);
}

function applyPins(nextPins) {
  allParks = nextPins
    .map((item) => {
      const atividades = getActivities(item);
      const lat = toNumber(item?.lat ?? item?.latitude);
      const lng = toNumber(item?.lng ?? item?.longitude);

      return {
        id: item?.id ?? item?.source_id,
        source_id: item?.source_id ?? "",
        slug: firstValue(item?.slug, item?.uc_slug),
        url: firstValue(item?.url, item?.link),
        name: firstValue(item?.name, item?.title, "Local sem nome"),
        state: firstValue(item?.state, item?.estado, item?.uf, "--"),
        uf: firstValue(item?.uf),
        city: firstValue(item?.city, item?.municipio, "Cidade"),
        biome: firstValue(item?.biome, item?.bioma, "Bioma"),
        activity: firstValue(item?.activity, atividades[0]?.title, atividades[0]?.titulo, atividades[0]?.tipo, "Atividade"),
        lat,
        lng,
        description: firstValue(item?.description, item?.excerpt, item?.content, item?.breve_descricao),
        tags: Array.isArray(item?.tags) ? item.tags.filter(Boolean).map(decodeHtml) : [],
        responsavel: firstValue(item?.responsavel),
        email: firstValue(item?.email),
        whatsapp: firstValue(item?.whatsapp),
        social: firstValue(item?.social),
        cep: firstValue(item?.cep),
        endereco: firstValue(item?.endereco),
        link_do_endereco: firstValue(item?.link_do_endereco),
        image: item?.image ?? null,
        image_url: firstValue(item?.image_url, item?.thumbnail),
        atividades,
        location_meta: item?.location_meta ?? null,
      };
    })
    .filter((item) => typeof item.lat === "number" && typeof item.lng === "number");

  populateFilters();
  state.activeId = null;
  state.activeStateName = null;
  filterItems();
}

async function loadMapData() {
  resultsCount.textContent = "0";
  dialogCount.textContent = "0";
  setStatus("Carregando os locais da API...");

  try {
    const urls = [
      window.UCMapConfig?.dataUrl,
      window.UCMapConfig?.fallbackDataUrl,
      "./map-data.json",
    ].filter(Boolean);
    let payload = null;
    let lastError = null;

    for (const url of [...new Set(urls)]) {
      try {
        const response = await fetch(url, { cache: "no-store" });

        if (!response.ok) {
          throw new Error(`Falha ao carregar ${url} (${response.status})`);
        }

        payload = await response.json();
        break;
      } catch (error) {
        lastError = error;
      }
    }

    if (!payload) {
      throw lastError || new Error("Nenhuma fonte de dados configurada.");
    }

    applyPins(payload.items || []);
  } catch (error) {
    console.error(error);
    resultsCount.textContent = "0";
    dialogCount.textContent = "0";
    setStatus("Nao foi possivel carregar os pins do mapa pela API.", "error");
  }
}

form.addEventListener("submit", (event) => {
  event.preventDefault();
  filterItems();
});

["park-filter", "biome-filter", "city-filter", "activity-filter"].forEach((id) => {
  root.querySelector(`#${id}`).addEventListener("change", filterItems);
});

searchInput.addEventListener("input", () => {
  window.clearTimeout(window.__dashboardSearchTimer);
  window.__dashboardSearchTimer = window.setTimeout(filterItems, 180);
});

stateSearchInput.addEventListener("input", () => {
  state.stateSearch = stateSearchInput.value;
  renderGroupedList(state.items);
});

window.dashboardMap = {
  setPins(nextPins) {
    if (Array.isArray(nextPins)) {
      applyPins(nextPins);
    }
  },
  addPin(pin) {
    const lat = toNumber(pin?.lat);
    const lng = toNumber(pin?.lng);

    if (!pin || typeof lat !== "number" || typeof lng !== "number") {
      return;
    }

    const nextId = Math.max(0, ...allParks.map((item) => item.id)) + 1;
    allParks.push({
      id: pin.id ?? nextId,
      name: pin.name ?? "Novo ponto",
      state: pin.state ?? "--",
      city: pin.city ?? "Cidade",
      biome: pin.biome ?? "Bioma",
      activity: pin.activity ?? "Atividade",
      lat,
      lng,
      description: pin.description ?? "",
      tags: Array.isArray(pin.tags) ? pin.tags.filter(Boolean) : [],
      responsavel: pin.responsavel ?? "",
      email: pin.email ?? "",
      whatsapp: pin.whatsapp ?? "",
      social: pin.social ?? "",
      cep: pin.cep ?? "",
      endereco: pin.endereco ?? "",
      link_do_endereco: pin.link_do_endereco ?? "",
      atividades: Array.isArray(pin.atividades) ? pin.atividades : [],
      location_meta: pin.location_meta ?? null,
    });

    populateFilters();
    filterItems();
  },
  clearPins() {
    allParks = [];
    state.activeId = null;
    state.activeStateName = null;
    populateFilters();
    filterItems();
  },
  focusPin(id) {
    setActiveItem(id, true);
  },
};

loadMapData();
})();
