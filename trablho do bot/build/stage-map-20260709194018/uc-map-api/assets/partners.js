(function () {
  function initSlider(root) {
    const track = root.querySelector("[data-uc-partner-track]");
    const slides = Array.from(root.querySelectorAll("[data-uc-partner-slide]"));

    if (!track || !slides.length || root.dataset.ucPartnerReady === "true") {
      return;
    }

    root.dataset.ucPartnerReady = "true";

    slides.forEach((slide) => {
      const clone = slide.cloneNode(true);
      clone.setAttribute("aria-hidden", "true");
      track.appendChild(clone);
    });

    const duration = Math.max(22, slides.length * 5);
    root.style.setProperty("--uc-partner-duration", `${duration}s`);

    function getVisibleCount() {
      const width = root.clientWidth || window.innerWidth;

      if (width < 640) {
        return 1;
      }

      if (width < 1024) {
        return 2;
      }

      return 4;
    }

    function updateSlideSize() {
      const visible = getVisibleCount();
      const width = root.clientWidth || window.innerWidth;
      root.style.setProperty("--uc-partner-slide-width", `${width / visible}px`);
    }

    updateSlideSize();
    window.addEventListener("resize", updateSlideSize, { passive: true });
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
