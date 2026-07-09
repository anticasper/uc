(function () {
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

  function getActivities(item) {
    if (Array.isArray(item?.atividades)) {
      return item.atividades;
    }

    if (Array.isArray(item?.activities)) {
      return item.activities;
    }

    return [];
  }

  function getUfLabel(stateName, itemUf = "") {
    if (itemUf) {
      return itemUf;
    }

    const key = Object.keys(UF_BY_STATE).find((name) => normalize(name) === normalize(stateName));
    return key ? UF_BY_STATE[key] : String(stateName || "").slice(0, 2).toUpperCase();
  }

  function getItemImageUrl(item) {
    return firstValue(
      item?.image_url,
      item?.thumbnail,
      item?.image?.sizes?.medium_large,
      item?.image?.sizes?.large,
      item?.image?.sizes?.full,
      item?.image?.url,
      item?.atividades?.[0]?.image_url,
      item?.atividades?.[0]?.thumbnail,
      item?.atividades?.[0]?.image?.sizes?.medium_large,
      item?.atividades?.[0]?.image?.sizes?.large,
      item?.atividades?.[0]?.image?.url,
      window.UCListConfig?.defaultImageUrl
    );
  }

  function activitySummary(activity) {
    return [
      firstValue(activity.data, activity.date) ? `Data: ${firstValue(activity.data, activity.date)}` : "",
      firstValue(activity.horario, activity.time) ? `Horario: ${firstValue(activity.horario, activity.time)}` : "",
      firstValue(activity.publico, activity.audience) ? `Publico: ${firstValue(activity.publico, activity.audience)}` : "",
      firstValue(activity.dificuldade, activity.difficulty) ? `Dificuldade: ${firstValue(activity.dificuldade, activity.difficulty)}` : "",
    ].filter(Boolean);
  }

  function normalizeItem(item) {
    const atividades = getActivities(item);

    return {
      ...item,
      id: item.id ?? item.source_id,
      name: firstValue(item.name, item.title, "UC sem nome"),
      state: firstValue(item.state, item.estado, item.uf),
      uf: firstValue(item.uf),
      city: firstValue(item.city, item.municipio),
      biome: firstValue(item.biome, item.bioma),
      activity: firstValue(item.activity, atividades[0]?.title, atividades[0]?.titulo, atividades[0]?.tipo),
      description: firstValue(item.description, item.excerpt, item.content, item.breve_descricao),
      endereco: firstValue(item.endereco),
      cep: firstValue(item.cep),
      url: firstValue(item.url, item.link),
      slug: firstValue(item.slug, item.uc_slug),
      image_url: getItemImageUrl({ ...item, atividades }),
      atividades,
    };
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
        uf: getUfLabel(stateName, parks[0]?.uf),
        parks: [...parks].sort((a, b) => a.name.localeCompare(b.name, "pt-BR")),
      }))
      .sort((a, b) => a.stateName.localeCompare(b.stateName, "pt-BR"));
  }

  function summarizeState(parks) {
    const biomes = [...new Set(parks.map((park) => park.biome).filter(Boolean))].slice(0, 3);
    return `${parks.length} UCs${biomes.length ? ` - ${biomes.join(" / ")}` : ""}`;
  }

  function splitBiomes(value) {
    return String(value || "")
      .split(/[\/,;|]+/)
      .map((item) => decodeHtml(item).trim())
      .filter(Boolean);
  }

  function itemBiomes(item) {
    return splitBiomes(firstValue(item?.biome, item?.bioma));
  }

  function itemMatchesBiomes(item, selectedBiomes) {
    if (!selectedBiomes || !selectedBiomes.size) {
      return true;
    }

    const biomes = itemBiomes(item).map(normalize);
    return [...selectedBiomes].some((biome) => biomes.includes(normalize(biome)));
  }

  function getBiomeOptions(items) {
    const byKey = new Map();

    items.forEach((item) => {
      itemBiomes(item).forEach((biome) => {
        const key = normalize(biome);
        if (key && !byKey.has(key)) {
          byKey.set(key, biome);
        }
      });
    });

    return [...byKey.values()].sort((a, b) => a.localeCompare(b, "pt-BR"));
  }

  function stateNameByUf(uf) {
    const entry = Object.entries(UF_BY_STATE).find(([, value]) => normalize(value) === normalize(uf));
    return entry ? entry[0] : String(uf || "Sem estado");
  }

  function cardDescription(item) {
    const firstActivity = item.atividades[0] || {};
    return firstValue(
      item.description,
      firstActivity.description,
      firstActivity.descricao,
      item.activity,
      "Conhecida por suas areas naturais e atividades de visitação."
    );
  }

  async function loadConfiguredItems() {
    const urls = [
      window.UCListConfig?.dataUrl,
      window.UCListConfig?.fallbackDataUrl,
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

    return (payload.items || [])
      .filter(Boolean)
      .map(normalizeItem)
      .filter((item) => item && item.name)
      .sort((a, b) => a.name.localeCompare(b.name, "pt-BR"));
  }

  function initList(root) {
    const state = {
      items: [],
      activeState: "",
      stateSearch: "",
      activeBiomes: new Set(),
    };

    const searchInput = root.querySelector("[data-list-search]");
    const biomeList = root.querySelector("[data-uc-biome-list]");
    const clearBiomes = root.querySelector("[data-clear-biomes]");
    const stateList = root.querySelector("[data-uc-state-list]");
    const stateCount = root.querySelector("[data-uc-state-count]");
    const resultsCount = root.querySelector("[data-uc-results-count]");
    const cardsGrid = root.querySelector("[data-uc-cards-grid]");
    const emptyState = root.querySelector("[data-uc-empty]");
    const cardTemplate = root.querySelector("[data-uc-card-template]");
    const stateTemplate = root.querySelector("[data-uc-state-template]");
    const modal = root.querySelector("[data-activity-modal]");
    const modalTitle = root.querySelector("[data-activity-modal-title]");
    const modalMeta = root.querySelector("[data-activity-modal-meta]");
    const modalBody = root.querySelector("[data-activity-modal-body]");

    function visibleGroups() {
      const search = normalize(state.stateSearch);
      const groups = groupItemsByState(state.items.filter((item) => itemMatchesBiomes(item, state.activeBiomes)));

      if (!search) {
        return groups;
      }

      return groups.filter((group) => {
        return normalize(group.stateName).includes(search) || normalize(group.uf).includes(search);
      });
    }

    function filteredItems() {
      return state.items.filter((item) => {
        const matchesBiome = itemMatchesBiomes(item, state.activeBiomes);
        const matchesState = !state.activeState || item.state === state.activeState;
        return matchesBiome && matchesState;
      });
    }

    function renderBiomes() {
      if (!biomeList) {
        return;
      }

      const options = getBiomeOptions(state.items);
      const fragment = document.createDocumentFragment();
      biomeList.innerHTML = "";

      options.forEach((biome) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "uc-list-biome";
        button.classList.toggle("is-active", state.activeBiomes.has(biome));
        button.textContent = biome;
        button.addEventListener("click", () => {
          if (state.activeBiomes.has(biome)) {
            state.activeBiomes.delete(biome);
          } else {
            state.activeBiomes.add(biome);
          }
          renderBiomes();
          renderStates();
          renderCards();
        });
        fragment.appendChild(button);
      });

      biomeList.appendChild(fragment);

      if (clearBiomes) {
        clearBiomes.hidden = state.activeBiomes.size === 0;
      }
    }

    function renderStates() {
      const groups = visibleGroups();
      stateList.innerHTML = "";
      stateCount.textContent = String(groups.length);

      if (state.activeState && !groups.some((group) => group.stateName === state.activeState)) {
        state.activeState = "";
      }

      const fragment = document.createDocumentFragment();

      groups.forEach((group) => {
        const row = stateTemplate.content.firstElementChild.cloneNode(true);
        row.classList.toggle("is-active", group.stateName === state.activeState);
        row.querySelector("[data-uf]").textContent = group.uf;
        row.querySelector("[data-state-name]").textContent = group.stateName;
        row.querySelector("[data-state-meta]").textContent = summarizeState(group.parks);
        row.addEventListener("click", () => {
          state.activeState = state.activeState === group.stateName ? "" : group.stateName;
          renderStates();
          renderCards();
        });
        fragment.appendChild(row);
      });

      stateList.appendChild(fragment);
    }

    function createCard(item) {
      const card = cardTemplate.content.firstElementChild.cloneNode(true);
      const imageUrl = getItemImageUrl(item);
      const kicker = [item.city, item.state].filter(Boolean).join(" - ");
      const description = cardDescription(item);

      if (imageUrl) {
        card.style.setProperty("--uc-card-bg", `url("${imageUrl.replace(/"/g, "%22")}")`);
      }

      card.querySelector("[data-uc-kicker]").textContent = kicker || item.biome || "Unidade de Conservacao";
      card.querySelector("[data-uc-name]").textContent = item.name || "Unidade de Conservacao";
      card.querySelector("[data-uc-description]").textContent = description;
      card.querySelector("[data-open-activities]").addEventListener("click", () => openActivities(item));

      return card;
    }

    function renderCards() {
      const items = filteredItems();
      cardsGrid.innerHTML = "";
      resultsCount.textContent = String(items.length);
      emptyState.hidden = items.length > 0;

      const fragment = document.createDocumentFragment();
      items.forEach((item) => fragment.appendChild(createCard(item)));
      cardsGrid.appendChild(fragment);
    }

    function openActivities(item) {
      const activities = getActivities(item);
      modalTitle.textContent = item.name || "Atividades";
      modalMeta.textContent = [item.city, item.state, item.biome].filter(Boolean).join(" | ");
      modalBody.innerHTML = "";

      if (!activities.length) {
        const fallback = document.createElement("p");
        fallback.className = "activity-modal__empty";
        fallback.textContent = item.description || "Nenhuma atividade cadastrada para esta UC.";
        modalBody.appendChild(fallback);
      } else {
        activities.forEach((activity, index) => {
          const article = document.createElement("article");
          article.className = "activity-item";

          const title = document.createElement("h3");
          title.textContent = firstValue(activity.titulo, activity.title, activity.tipo, `Atividade ${index + 1}`);

          const description = document.createElement("p");
          description.textContent = firstValue(activity.descricao, activity.description, activity.excerpt, activity.content, activity.texto_completo, "Sem descricao.");

          const details = document.createElement("ul");
          activitySummary(activity).forEach((line) => {
            const detail = document.createElement("li");
            detail.textContent = line;
            details.appendChild(detail);
          });

          article.append(title);
          if (details.childElementCount) {
            article.appendChild(details);
          }
          article.appendChild(description);
          modalBody.appendChild(article);
        });
      }

      modal.hidden = false;
      document.documentElement.classList.add("uc-modal-open");
    }

    function closeActivities() {
      modal.hidden = true;
      document.documentElement.classList.remove("uc-modal-open");
    }

    root.querySelectorAll("[data-close-activity-modal]").forEach((button) => {
      button.addEventListener("click", closeActivities);
    });

    searchInput.addEventListener("input", () => {
      state.stateSearch = searchInput.value;
      renderStates();
      renderCards();
    });

    clearBiomes?.addEventListener("click", () => {
      state.activeBiomes.clear();
      renderBiomes();
      renderStates();
      renderCards();
    });

    async function loadItems() {
      state.items = await loadConfiguredItems();

      renderBiomes();
      renderStates();
      renderCards();
    }

    loadItems().catch((error) => {
      console.error(error);
      stateList.innerHTML = "";
      cardsGrid.innerHTML = "";
      stateCount.textContent = "0";
      resultsCount.textContent = "0";
      emptyState.hidden = false;
      emptyState.textContent = "Nao foi possivel carregar a lista de UCs.";
    });
  }

  function initLegacyExplore(root) {
    if (root.dataset.ucListLegacyReady === "1") {
      return;
    }

    const sidebar = root.querySelector(".umdnp-explorar-sidebar");
    const grid = root.querySelector(".umdnp-explorar-grid");
    const originalSearch = root.querySelector(".umdnp-explorar-search-input");
    const cards = [...root.querySelectorAll(".umdnp-explorar-card")];

    if (!sidebar || !grid || !cards.length) {
      return;
    }

    root.dataset.ucListLegacyReady = "1";
    root.classList.add("uc-list-legacy-ready");

    const searchInput = originalSearch ? originalSearch.cloneNode(true) : document.createElement("input");
    searchInput.className = "umdnp-explorar-search-input";
    searchInput.placeholder = "Buscar estado...";
    searchInput.value = "";
    searchInput.type = "text";
    searchInput.autocomplete = "off";

    if (originalSearch) {
      originalSearch.replaceWith(searchInput);
    }

    const statePanel = document.createElement("div");
    statePanel.className = "uc-list-legacy-state-panel";
    statePanel.innerHTML = `
      <div class="uc-list-legacy-biome-filter">
        <div class="uc-list-filter-title">
          <span>Bioma</span>
          <button type="button" data-legacy-clear-biomes hidden>Limpar</button>
        </div>
        <div class="uc-list-biomes" data-legacy-biome-list></div>
      </div>
      <div class="uc-list-legacy-summary">
        <span><strong data-legacy-state-count>0</strong> estados</span>
        <strong><span data-legacy-result-count>${cards.length}</span> UCs previstas</strong>
      </div>
      <div class="uc-list-legacy-state-scroll" data-legacy-state-list></div>
    `;

    const searchWrap = sidebar.querySelector(".umdnp-explorar-search");
    if (searchWrap) {
      searchWrap.insertAdjacentElement("afterend", statePanel);
    } else {
      sidebar.prepend(statePanel);
    }

    const stateList = statePanel.querySelector("[data-legacy-state-list]");
    const biomeList = statePanel.querySelector("[data-legacy-biome-list]");
    const clearBiomes = statePanel.querySelector("[data-legacy-clear-biomes]");
    const stateCount = statePanel.querySelector("[data-legacy-state-count]");
    const resultCount = statePanel.querySelector("[data-legacy-result-count]");
    const state = {
      activeUf: "",
      stateSearch: "",
      activeBiomes: new Set(),
      cardItems: [],
    };

    function slugFromHref(card) {
      const href = card.querySelector("a[href]")?.href || "";
      const parts = href.replace(/\/$/, "").split("/");
      return parts[parts.length - 1] || "";
    }

    function titleFromCard(card) {
      return firstValue(card.querySelector(".umdnp-explorar-card-title")?.textContent);
    }

    function createFallbackItem(card) {
      const uf = firstValue(card.dataset.uf).split(",")[0] || "";

      return {
        name: titleFromCard(card),
        uf,
        state: stateNameByUf(uf),
        biome: firstValue(card.dataset.bioma),
        slug: slugFromHref(card),
        description: "",
        image_url: "",
        atividades: [],
      };
    }

    function decorateCards(items) {
      const byName = new Map(items.map((item) => [normalize(item.name), item]));
      const bySlug = new Map(items.map((item) => [normalize(item.slug), item]));

      state.cardItems = cards.map((card) => {
        const fallback = createFallbackItem(card);
        const item = bySlug.get(normalize(fallback.slug)) || byName.get(normalize(fallback.name)) || fallback;
        const imageUrl = getItemImageUrl(item);
        const title = card.querySelector(".umdnp-explorar-card-title");
        const desc = card.querySelector(".umdnp-explorar-card-desc") || document.createElement("p");
        const button = card.querySelector(".umdnp-explorar-card-btn");

        card.classList.add("uc-list-legacy-card-ready");
        card.__ucListItem = item;

        if (imageUrl) {
          card.style.setProperty("--uc-card-bg", `url("${imageUrl.replace(/"/g, "%22")}")`);
          card.classList.add("umdnp-explorar-card--bg-image");
        }

        desc.className = "umdnp-explorar-card-desc";
        desc.textContent = cardDescription(item);

        if (!desc.parentElement && title) {
          title.insertAdjacentElement("afterend", desc);
        }

        if (button) {
          const icon = document.createElementNS("http://www.w3.org/2000/svg", "svg");
          const line = document.createElementNS("http://www.w3.org/2000/svg", "path");
          const arrow = document.createElementNS("http://www.w3.org/2000/svg", "path");

          icon.setAttribute("width", "14");
          icon.setAttribute("height", "14");
          icon.setAttribute("viewBox", "0 0 24 24");
          icon.setAttribute("fill", "none");
          icon.setAttribute("stroke", "currentColor");
          icon.setAttribute("stroke-width", "2");
          icon.setAttribute("aria-hidden", "true");
          line.setAttribute("d", "M5 12h14");
          arrow.setAttribute("d", "M12 5l7 7-7 7");
          icon.append(line, arrow);
          button.replaceChildren(document.createTextNode("Ver Atividades "), icon);
        }

        return {
          card,
          item,
          uf: firstValue(item.uf, fallback.uf).split(",")[0],
          stateName: firstValue(item.state, fallback.state),
          biomes: itemBiomes(item),
        };
      });
    }

    function groupedStates() {
      const groups = new Map();

      state.cardItems.forEach((entry) => {
        if (!itemMatchesBiomes(entry.item, state.activeBiomes)) {
          return;
        }

        const uf = firstValue(entry.uf, entry.card.dataset.uf).split(",")[0] || "UF";
        const stateName = firstValue(entry.stateName, stateNameByUf(uf));

        if (!groups.has(uf)) {
          groups.set(uf, {
            uf,
            stateName,
            parks: [],
          });
        }

        groups.get(uf).parks.push(entry.item);
      });

      return [...groups.values()].sort((a, b) => a.stateName.localeCompare(b.stateName, "pt-BR"));
    }

    function renderLegacyBiomes() {
      const options = getBiomeOptions(state.cardItems.map((entry) => entry.item));
      const fragment = document.createDocumentFragment();

      biomeList.innerHTML = "";

      options.forEach((biome) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "uc-list-biome";
        button.classList.toggle("is-active", state.activeBiomes.has(biome));
        button.textContent = biome;
        button.addEventListener("click", () => {
          if (state.activeBiomes.has(biome)) {
            state.activeBiomes.delete(biome);
          } else {
            state.activeBiomes.add(biome);
          }
          renderLegacyBiomes();
          renderLegacyStates();
          applyLegacyFilters();
        });
        fragment.appendChild(button);
      });

      biomeList.appendChild(fragment);
      clearBiomes.hidden = state.activeBiomes.size === 0;
    }

    function visibleGroups() {
      const search = normalize(state.stateSearch);
      const groups = groupedStates();

      if (!search) {
        return groups;
      }

      return groups.filter((group) => normalize(group.uf).includes(search) || normalize(group.stateName).includes(search));
    }

    function applyLegacyFilters() {
      let visible = 0;

      state.cardItems.forEach((entry) => {
        const matchState = !state.activeUf || normalize(entry.uf) === normalize(state.activeUf);
        const matchBiome = itemMatchesBiomes(entry.item, state.activeBiomes);
        const isVisible = matchState && matchBiome;
        entry.card.classList.toggle("umdnp-hidden", !isVisible);
        entry.card.hidden = !isVisible;

        if (isVisible) {
          visible += 1;
        }
      });

      resultCount.textContent = String(visible);
    }

    function renderLegacyStates() {
      const groups = visibleGroups();
      const fragment = document.createDocumentFragment();

      stateList.innerHTML = "";
      stateCount.textContent = String(groups.length);

      if (state.activeUf && !groups.some((group) => normalize(group.uf) === normalize(state.activeUf))) {
        state.activeUf = "";
      }

      groups.forEach((group) => {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "uc-list-legacy-state";
        button.classList.toggle("is-active", normalize(group.uf) === normalize(state.activeUf));
        button.innerHTML = `
          <span class="uc-list-state__badge">${group.uf}</span>
          <span class="uc-list-state__copy">
            <span class="uc-list-state__name">${group.stateName}</span>
            <span class="uc-list-state__meta">${summarizeState(group.parks)}</span>
          </span>
          <svg class="uc-list-state__arrow" viewBox="0 0 24 24" aria-hidden="true">
            <path d="m9 18 6-6-6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        `;

        button.addEventListener("click", () => {
          state.activeUf = normalize(state.activeUf) === normalize(group.uf) ? "" : group.uf;
          renderLegacyStates();
          applyLegacyFilters();
        });

        fragment.appendChild(button);
      });

      stateList.appendChild(fragment);
      applyLegacyFilters();
    }

    searchInput.addEventListener("input", () => {
      state.stateSearch = searchInput.value;
      renderLegacyStates();
    });

    clearBiomes.addEventListener("click", () => {
      state.activeBiomes.clear();
      renderLegacyBiomes();
      renderLegacyStates();
      applyLegacyFilters();
    });

    decorateCards([]);
    renderLegacyBiomes();
    renderLegacyStates();

    loadConfiguredItems()
      .then((items) => {
        decorateCards(items);
        renderLegacyBiomes();
        renderLegacyStates();
      })
      .catch((error) => {
        console.error(error);
      });
  }

  document.querySelectorAll(".uc-list-root").forEach(initList);
  document.querySelectorAll(".umdnp-explorar-wrapper").forEach(initLegacyExplore);
})();
