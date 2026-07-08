(function () {
  const config = window.UCSingleConfig || {};

  if (!config.apiUrl || !document.body.classList.contains("single-uc")) {
    return;
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

  function textOf(node) {
    return String(node?.textContent || "").replace(/\s+/g, " ").trim();
  }

  function setText(node, value) {
    if (node && value) {
      node.textContent = value;
    }
  }

  function getActivities(item) {
    return Array.isArray(item?.atividades) ? item.atividades : [];
  }

  function getMapUrl(item) {
    const directUrl = firstValue(item.link_do_endereco);
    const address = firstValue(item.endereco);

    if (directUrl) {
      return directUrl;
    }

    if (/^https?:\/\//i.test(address)) {
      return address;
    }

    if (Number.isFinite(Number(item.lat)) && Number.isFinite(Number(item.lng))) {
      return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${item.lat},${item.lng}`)}`;
    }

    if (address) {
      return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`;
    }

    return "";
  }

  function allElements(selector) {
    return Array.from(document.querySelectorAll(selector));
  }

  function findByText(selector, value) {
    return allElements(selector).find((node) => textOf(node) === value);
  }

  function findAllByText(selector, value) {
    return allElements(selector).filter((node) => textOf(node) === value);
  }

  function findTextWidgetAfter(labelNode) {
    let cursor = labelNode?.closest(".elementor-element");

    while (cursor) {
      cursor = cursor.nextElementSibling;

      if (!cursor) {
        return null;
      }

      const text = cursor.querySelector(".elementor-widget-text-editor p, .elementor-widget-text-editor");
      if (text) {
        return text;
      }
    }

    return null;
  }

  function setValueAfterLabel(root, label, value) {
    if (!value) {
      return;
    }

    const labelNode = Array.from(root.querySelectorAll(".elementor-heading-title, h1, h2, h3, span"))
      .find((node) => textOf(node) === label);

    const valueNode = findTextWidgetAfter(labelNode);
    setText(valueNode, value);
  }

  function activitySummary(activity) {
    return [
      firstValue(activity.data, activity.date),
      firstValue(activity.horario, activity.time),
    ].filter(Boolean).join(" - ");
  }

  function hydrateActivityCards(activities) {
    const titleNodes = findAllByText(".elementor-heading-title", "Nome da atividade");
    const usedSlides = new Set();

    titleNodes.forEach((titleNode, index) => {
      const activity = activities[index];
      const slide = titleNode.closest(".swiper-slide") || titleNode.closest(".e-con") || titleNode.closest(".elementor-element");

      if (slide) {
        usedSlides.add(slide);
      }

      if (!activity) {
        setText(titleNode, "");

        if (slide) {
          Array.from(slide.querySelectorAll(".elementor-widget-text-editor p")).forEach((node) => {
            node.textContent = "";
          });
          slide.hidden = true;
          slide.setAttribute("aria-hidden", "true");
        }
        return;
      }

      const title = firstValue(activity.titulo, activity.title, activity.tipo);
      const description = firstValue(activity.descricao, activity.description, activity.excerpt, activity.content);
      const difficulty = firstValue(activity.dificuldade, activity.difficulty);
      const audience = firstValue(activity.publico, activity.audience);
      const url = firstValue(activity.url);

      setText(titleNode, title);

      if (slide) {
        slide.hidden = false;
        slide.removeAttribute("aria-hidden");
        setValueAfterLabel(slide, "Data e Horário", activitySummary(activity));
        setValueAfterLabel(slide, "Descrição curta", description);
        setValueAfterLabel(slide, "Dificuldade", difficulty);
        setValueAfterLabel(slide, "Público", audience);

        const button = Array.from(slide.querySelectorAll("a.elementor-button, a"))
          .find((link) => /inscreva-se/i.test(textOf(link)) || link.getAttribute("href") === "#");

        if (button && url) {
          button.href = url;
        }
      }
    });

    usedSlides.forEach((slide) => {
      const parentSwiper = slide.closest(".swiper");
      if (parentSwiper?.swiper?.update) {
        parentSwiper.swiper.update();
      }
    });
  }

  function hydrateInfoCards(item, activities) {
    const location = [firstValue(item.city), firstValue(item.state, item.uf)].filter(Boolean).join(" - ");
    const firstActivity = activities[0] || {};
    const infoByLabel = {
      "Localização": location,
      "Horário": activitySummary(firstActivity),
      "Gratuito/Pago": "Gratuito",
      "Atividades": activities.length ? `${activities.length} ${activities.length === 1 ? "atividade cadastrada" : "atividades cadastradas"}` : "",
      "Estacionamento": firstValue(item.endereco),
      "Acessibilidade": firstValue(item.responsavel, item.realizador),
    };

    Object.entries(infoByLabel).forEach(([label, value]) => {
      if (!value) {
        return;
      }

      const titleNode = findByText(".elementor-icon-box-title span, .elementor-icon-box-title", label);
      const box = titleNode?.closest(".elementor-icon-box-content");
      const description = box?.querySelector(".elementor-icon-box-description");
      setText(description, value);
    });
  }

  function hydrateRoute(item) {
    const address = firstValue(item.endereco);
    const mapUrl = getMapUrl(item);
    const routeTitle = findByText(".elementor-heading-title", "Como chegar");
    const section = routeTitle?.closest(".e-con");

    if (section && address) {
      const text = section.querySelector(".elementor-widget-text-editor p");
      setText(text, address);
    }

    if (section && mapUrl) {
      const button = Array.from(section.querySelectorAll("a.elementor-button, a"))
        .find((link) => /abrir no google maps/i.test(textOf(link)));
      if (button) {
        button.href = mapUrl;
        button.target = "_blank";
        button.rel = "noopener";
      }

      const iframe = section.querySelector("iframe");
      if (iframe) {
        const query = Number.isFinite(Number(item.lat)) && Number.isFinite(Number(item.lng))
          ? `${item.lat},${item.lng}`
          : address;
        iframe.src = `https://maps.google.com/maps?q=${encodeURIComponent(query)}&t=m&z=10&output=embed&iwloc=near`;
        iframe.title = query;
        iframe.setAttribute("aria-label", query);
      }
    }
  }

  function hydrateHero(item, activities) {
    const subtitle = findByText(".elementor-widget-text-editor p", "Uma experiência de conexão com a natureza em uma das áreas protegidas mais incríveis do país.");
    const firstActivity = activities[0] || {};
    const description = firstValue(
      item.description,
      item.excerpt,
      firstActivity.description,
      firstActivity.descricao
    );

    setText(subtitle, description);
  }

  async function hydrate() {
    try {
      const response = await fetch(config.apiUrl, { cache: "no-store" });

      if (!response.ok) {
        throw new Error(`Falha ao carregar UC (${response.status})`);
      }

      const item = await response.json();

      if (!item || !item.id) {
        throw new Error("UC nao encontrada na API.");
      }

      const activities = getActivities(item);
      const applyHydration = () => {
        hydrateHero(item, activities);
        hydrateActivityCards(activities);
        hydrateInfoCards(item, activities);
        hydrateRoute(item);
        document.documentElement.classList.add("uc-single-data-ready");
      };

      applyHydration();
      window.setTimeout(applyHydration, 800);
      window.setTimeout(applyHydration, 1800);
      window.setTimeout(applyHydration, 3500);
    } catch (error) {
      console.error(error);
    }
  }

  hydrate();
})();
