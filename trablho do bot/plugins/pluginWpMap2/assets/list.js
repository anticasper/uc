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
      image_url: firstValue(item.image_url, item.thumbnail, item.image?.url, atividades[0]?.image_url, atividades[0]?.thumbnail),
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

  function initList(root) {
    const state = {
      items: [],
      activeState: "",
      stateSearch: "",
    };

    const searchInput = root.querySelector("[data-list-search]");
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
      const groups = groupItemsByState(state.items);

      if (!search) {
        return groups;
      }

      return groups.filter((group) => {
        return normalize(group.stateName).includes(search) || normalize(group.uf).includes(search);
      });
    }

    function filteredItems() {
      if (!state.activeState) {
        return state.items;
      }

      return state.items.filter((item) => item.state === state.activeState);
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
      const imageUrl = firstValue(item.image_url);
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

    async function loadItems() {
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

      state.items = (payload.items || [])
        .filter(Boolean)
        .map(normalizeItem)
        .filter((item) => item && item.name)
        .sort((a, b) => a.name.localeCompare(b.name, "pt-BR"));

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

  document.querySelectorAll(".uc-list-root").forEach(initList);
})();
