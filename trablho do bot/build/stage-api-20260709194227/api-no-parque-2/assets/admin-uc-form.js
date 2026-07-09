(function () {
  const root = document.querySelector("[data-api-np-uc-form], [data-api-np-activity-form]");

  if (!root || !window.ApiNpUcAdmin) {
    return;
  }

  const labels = window.ApiNpUcAdmin.labels || {};
  const stateSelect = root.querySelector("[data-api-np-state]");
  const citySelect = root.querySelector("[data-api-np-city]");
  const modal = root.querySelector("[data-api-np-activity-modal]");
  const selectedBody = root.querySelector("[data-api-np-selected-body]");
  const selectedEmpty = root.querySelector("[data-api-np-selected-empty]");
  const searchInput = root.querySelector("[data-api-np-activity-search]");

  function cssEscape(value) {
    if (window.CSS?.escape) {
      return window.CSS.escape(String(value));
    }

    return String(value).replace(/"/g, '\\"');
  }

  function option(value, text, selected) {
    const item = document.createElement("option");
    item.value = value;
    item.textContent = text;
    item.selected = Boolean(selected);
    return item;
  }

  function setCityOptions(items, selectedCity) {
    citySelect.replaceChildren(option("", labels.selectCity || "Selecione a cidade", false));

    items.forEach((city) => {
      citySelect.appendChild(option(city, city, city === selectedCity));
    });
  }

  function loadCities() {
    if (!stateSelect || !citySelect) {
      return;
    }

    const ufId = stateSelect.value;
    const selectedCity = citySelect.dataset.currentCity || "";

    if (!ufId) {
      citySelect.replaceChildren(option("", labels.selectStateFirst || "Selecione o estado primeiro", false));
      return;
    }

    citySelect.replaceChildren(option("", labels.loading || "Carregando...", false));

    const data = new FormData();
    data.append("action", "umdnp_get_cidades_por_uf");
    data.append("nonce", window.ApiNpUcAdmin.citiesNonce);
    data.append("uf_id", ufId);

    fetch(window.ApiNpUcAdmin.ajaxUrl, {
      method: "POST",
      credentials: "same-origin",
      body: data,
    })
      .then((response) => response.json())
      .then((response) => {
        const cities = response?.data?.cidades || [];

        if (!response?.success || !cities.length) {
          citySelect.replaceChildren(option("", labels.noCity || "Nenhuma cidade encontrada", false));
          return;
        }

        setCityOptions(cities, selectedCity);
      })
      .catch(() => {
        citySelect.replaceChildren(option("", labels.loadError || "Erro ao carregar", false));
      });
  }

  function updateEmptyState() {
    if (!selectedEmpty || !selectedBody) {
      return;
    }

    selectedEmpty.hidden = Boolean(selectedBody.querySelector("[data-api-np-selected-activity]"));
  }

  function syncCheckbox(activityId, checked) {
    const checkbox = root.querySelector(`[data-api-np-activity-checkbox][value="${cssEscape(activityId)}"]`);

    if (checkbox) {
      checkbox.checked = checked;
    }
  }

  function removeActivity(activityId) {
    const row = selectedBody?.querySelector(`[data-api-np-selected-activity="${cssEscape(activityId)}"]`);

    if (row) {
      row.remove();
    }

    syncCheckbox(activityId, false);
    updateEmptyState();
  }

  function buildSelectedRow(checkbox) {
    const optionRow = checkbox.closest("[data-api-np-activity-option]");
    const cells = Array.from(optionRow.querySelectorAll("span"));
    const id = checkbox.value;
    const title = cells[0]?.textContent || "";
    const date = cells[1]?.textContent || "-";
    const time = cells[2]?.textContent || "-";
    const description = cells[3]?.textContent || "-";

    const tr = document.createElement("tr");
    tr.dataset.apiNpSelectedActivity = id;
    tr.innerHTML = `
      <td>
        <input type="hidden" name="uc_atividade_ids[]" value="${id}">
        <strong></strong>
        <small>#${id}</small>
      </td>
      <td></td>
      <td></td>
      <td></td>
      <td class="api-np-activity-actions">
        <button type="button" class="button-link-delete" data-api-np-remove-activity="${id}">${labels.remove || "Remover"}</button>
      </td>
    `;
    tr.children[0].querySelector("strong").textContent = title;
    tr.children[1].textContent = date;
    tr.children[2].textContent = time;
    tr.children[3].textContent = description;

    return tr;
  }

  function applyActivities() {
    if (!selectedBody) {
      return;
    }

    selectedBody.replaceChildren();
    root.querySelectorAll("[data-api-np-activity-checkbox]:checked").forEach((checkbox) => {
      selectedBody.appendChild(buildSelectedRow(checkbox));
    });
    updateEmptyState();
    closeModal();
  }

  function openModal() {
    if (!modal) {
      return;
    }

    syncModalFromTable();
    modal.hidden = false;
    document.body.classList.add("api-np-modal-open");
    searchInput?.focus();
  }

  function syncModalFromTable() {
    const selected = new Set();
    selectedBody?.querySelectorAll("[data-api-np-selected-activity]").forEach((row) => {
      selected.add(row.dataset.apiNpSelectedActivity);
    });

    root.querySelectorAll("[data-api-np-activity-checkbox]").forEach((checkbox) => {
      checkbox.checked = selected.has(checkbox.value);
    });
  }

  function closeModal() {
    if (!modal) {
      return;
    }

    modal.hidden = true;
    document.body.classList.remove("api-np-modal-open");
  }

  function filterActivities() {
    const query = (searchInput?.value || "").trim().toLowerCase();

    root.querySelectorAll("[data-api-np-activity-option]").forEach((item) => {
      const haystack = item.dataset.search || "";
      item.hidden = Boolean(query) && !haystack.includes(query);
    });
  }

  function setupImageField() {
    const field = root.querySelector("[data-api-np-image-field]");

    if (!field || !window.wp?.media) {
      return;
    }

    const input = field.querySelector("[data-api-np-image-id]");
    const preview = field.querySelector("[data-api-np-image-preview]");
    const selectButton = field.querySelector("[data-api-np-select-image]");
    const removeButton = field.querySelector("[data-api-np-remove-image]");
    let frame = null;

    selectButton?.addEventListener("click", () => {
      if (!frame) {
        frame = window.wp.media({
          title: "Selecionar imagem da UC",
          button: { text: "Usar esta imagem" },
          multiple: false,
          library: { type: "image" },
        });

        frame.on("select", () => {
          const attachment = frame.state().get("selection").first().toJSON();
          const url = attachment.sizes?.medium?.url || attachment.url;
          input.value = attachment.id;
          preview.innerHTML = "";
          const img = document.createElement("img");
          img.src = url;
          img.alt = "";
          preview.appendChild(img);
          removeButton.hidden = false;
        });
      }

      frame.open();
    });

    removeButton?.addEventListener("click", () => {
      input.value = "";
      preview.innerHTML = `<em>Nenhuma imagem selecionada.</em>`;
      removeButton.hidden = true;
    });
  }

  function setupActivityTypeCards() {
    root.querySelectorAll(".api-np-type-card input").forEach((checkbox) => {
      const card = checkbox.closest(".api-np-type-card");
      if (!card) {
        return;
      }

      function sync() {
        card.classList.toggle("is-selected", checkbox.checked);
      }

      checkbox.addEventListener("change", sync);
      sync();
    });
  }

  stateSelect?.addEventListener("change", () => {
    if (citySelect) {
      citySelect.dataset.currentCity = "";
    }
    loadCities();
  });

  citySelect?.addEventListener("change", () => {
    citySelect.dataset.currentCity = citySelect.value;
  });

  root.querySelector("[data-api-np-open-activity-modal]")?.addEventListener("click", openModal);
  root.querySelectorAll("[data-api-np-close-activity-modal]").forEach((button) => {
    button.addEventListener("click", closeModal);
  });
  root.querySelector("[data-api-np-apply-activities]")?.addEventListener("click", applyActivities);
  searchInput?.addEventListener("input", filterActivities);

  root.addEventListener("click", (event) => {
    const removeButton = event.target.closest("[data-api-np-remove-activity]");
    if (removeButton) {
      removeActivity(removeButton.dataset.apiNpRemoveActivity);
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && modal && !modal.hidden) {
      closeModal();
    }
  });

  loadCities();
  setupImageField();
  setupActivityTypeCards();
  updateEmptyState();
})();
