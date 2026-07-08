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

  function normalizeText(value) {
    return String(value || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/\s+/g, " ")
      .trim()
      .toLowerCase();
  }

  function matchesText(node, value) {
    return normalizeText(textOf(node)) === normalizeText(value);
  }

  function setText(node, value) {
    if (node && value) {
      node.textContent = value;
    }
  }

  function parseCoordinate(value) {
    const normalized = String(value ?? "").trim().replace(",", ".");
    const parsed = Number(normalized);
    return Number.isFinite(parsed) ? parsed : null;
  }

  function getCoordinates(item) {
    const lat = parseCoordinate(item.lat);
    const lng = parseCoordinate(item.lng);

    if (lat === null || lng === null) {
      return null;
    }

    return { lat, lng };
  }

  function extractFirstUrl(value) {
    return firstValue(value).match(/https?:\/\/[^\s<>"']+/i)?.[0] || "";
  }

  function cleanAddress(value) {
    return firstValue(value)
      .replace(/https?:\/\/[^\s<>"']+/gi, "")
      .replace(/(?:localiza[cç][aã]o no google maps|google maps|endere[cç]o)\s*:?/gi, "")
      .replace(/\s+/g, " ")
      .trim();
  }

  function getActivities(item) {
    return Array.isArray(item?.atividades) ? item.atividades : [];
  }

  function getMapUrl(item) {
    const coordinates = getCoordinates(item);
    const directUrl = firstValue(item.link_do_endereco, extractFirstUrl(item.endereco));
    const address = cleanAddress(item.endereco);

    if (directUrl) {
      return directUrl;
    }

    if (coordinates) {
      return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${coordinates.lat},${coordinates.lng}`)}`;
    }

    if (address) {
      return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`;
    }

    return "";
  }

  function getMapEmbedQuery(item) {
    const coordinates = getCoordinates(item);

    if (coordinates) {
      return `${coordinates.lat},${coordinates.lng}`;
    }

    return cleanAddress(item.endereco) || firstValue(item.city, item.state, item.uf, item.name);
  }

  function allElements(selector) {
    return Array.from(document.querySelectorAll(selector));
  }

  function findByText(selector, value) {
    return allElements(selector).find((node) => matchesText(node, value));
  }

  function findAllByText(selector, value) {
    return allElements(selector).filter((node) => matchesText(node, value));
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

  function setValueAfterLabel(root, labels, value) {
    if (!value) {
      return;
    }

    const labelList = Array.isArray(labels) ? labels : [labels];
    const labelNode = Array.from(root.querySelectorAll(".elementor-heading-title, h1, h2, h3, span"))
      .find((node) => labelList.some((label) => matchesText(node, label)));

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
        setValueAfterLabel(slide, ["Data e Horario", "Data e Hor\u00e1rio", "Data e HorÃ¡rio"], activitySummary(activity));
        setValueAfterLabel(slide, ["Descricao curta", "Descri\u00e7\u00e3o curta", "DescriÃ§Ã£o curta", "Descricao", "Descri\u00e7\u00e3o"], description);
        setValueAfterLabel(slide, "Dificuldade", difficulty);
        setValueAfterLabel(slide, ["Publico", "P\u00fablico", "PÃºblico"], audience);

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
    const address = cleanAddress(item.endereco);
    const mapUrl = getMapUrl(item);
    const routeTitle = findByText(".elementor-heading-title", "Como chegar");
    let section = routeTitle?.closest(".e-con");

    while (section && !section.querySelector("iframe")) {
      section = section.parentElement?.closest(".e-con");
    }

    if (!section) {
      section = routeTitle?.closest(".e-con");
    }

    if (section && address) {
      const leftColumn = routeTitle?.closest(".e-con") || section;
      const text = leftColumn.querySelector(".elementor-widget-text-editor p, .elementor-widget-text-editor");
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
        const query = getMapEmbedQuery(item);
        iframe.src = `https://maps.google.com/maps?q=${encodeURIComponent(query)}&t=m&z=10&output=embed&iwloc=near`;
        iframe.title = query;
        iframe.setAttribute("aria-label", query);
      }
    }
  }

  function findHeroSubtitle(item) {
    const title = firstValue(item.name, item.title);
    const titleNode = allElements(".elementor-heading-title, h1, h2")
      .find((node) => title && matchesText(node, title));

    if (!titleNode) {
      return findByText(".elementor-widget-text-editor p", "Uma experiência de conexão com a natureza em uma das áreas protegidas mais incríveis do país.")
        || findByText(".elementor-widget-text-editor p", "Uma experiÃªncia de conexÃ£o com a natureza em uma das Ã¡reas protegidas mais incrÃ­veis do paÃ­s.");
    }

    let section = titleNode.closest(".e-con");

    while (section && !section.querySelector(".elementor-widget-text-editor p, .elementor-widget-text-editor")) {
      section = section.parentElement?.closest(".e-con");
    }

    if (!section) {
      return null;
    }

    const titleWidget = titleNode.closest(".elementor-element");
    let cursor = titleWidget;

    while (cursor && cursor.parentElement === titleWidget?.parentElement) {
      cursor = cursor.nextElementSibling;

      const text = cursor?.querySelector(".elementor-widget-text-editor p, .elementor-widget-text-editor");
      if (text) {
        return text;
      }
    }

    return section.querySelector(".elementor-widget-text-editor p, .elementor-widget-text-editor");
  }

  function hydrateHero(item) {
    const subtitle = findHeroSubtitle(item);
    const description = firstValue(
      item.description,
      item.breve_descricao,
      item.meta?._uc_breve_descricao,
      item.excerpt,
      item.content
    );

    if (subtitle) {
      subtitle.textContent = description;
      subtitle.hidden = !description;
    }
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
        hydrateHero(item);
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
