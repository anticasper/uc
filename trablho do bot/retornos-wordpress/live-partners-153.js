(function () {
  function initSlider(root) {
    const track = root.querySelector("[data-uc-partner-track]");
    const slides = Array.from(root.querySelectorAll("[data-uc-partner-slide]"));
    const dots = root.querySelector("[data-uc-partner-dots]");

    if (!track || !dots || !slides.length) {
      return;
    }

    let currentPage = 0;

    function getVisibleCount() {
      const width = root.clientWidth || window.innerWidth;

      if (width < 520) {
        return 2;
      }

      if (width < 820) {
        return 3;
      }

      if (width < 1120) {
        return 4;
      }

      return 5;
    }

    function render() {
      const visible = Math.min(getVisibleCount(), slides.length);
      const pages = Math.max(1, Math.ceil(slides.length / visible));

      currentPage = Math.min(currentPage, pages - 1);
      root.style.setProperty("--uc-partner-visible", String(visible));
      track.style.transform = `translateX(-${currentPage * 100}%)`;

      dots.innerHTML = "";
      dots.hidden = pages <= 1;

      for (let index = 0; index < pages; index += 1) {
        const dot = document.createElement("button");
        dot.type = "button";
        dot.className = "uc-partner-dot";
        dot.setAttribute("aria-label", `Ir para grupo ${index + 1}`);
        dot.setAttribute("aria-current", index === currentPage ? "true" : "false");
        dot.addEventListener("click", () => {
          currentPage = index;
          render();
        });
        dots.appendChild(dot);
      }
    }

    render();
    window.addEventListener("resize", render, { passive: true });
  }

  function initAll() {
    document.querySelectorAll("[data-uc-partner-slider]").forEach(initSlider);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initAll);
  } else {
    initAll();
  }
})();
