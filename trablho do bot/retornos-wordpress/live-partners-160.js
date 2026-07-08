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
