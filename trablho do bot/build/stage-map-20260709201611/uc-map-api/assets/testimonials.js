(function () {
  const sliders = document.querySelectorAll("[data-uc-testimonial-slider]");

  if (!sliders.length) {
    return;
  }

  sliders.forEach((root) => {
    const track = root.querySelector("[data-uc-testimonial-track]");
    const slides = Array.from(root.querySelectorAll("[data-uc-testimonial-slide]"));
    const prev = root.querySelector("[data-uc-testimonial-prev]");
    const next = root.querySelector("[data-uc-testimonial-next]");
    const dotsWrap = root.querySelector("[data-uc-testimonial-dots]");

    if (!track || !slides.length) {
      return;
    }

    let current = 0;
    let autoplay = null;
    let startX = 0;

    function goTo(index) {
      current = (index + slides.length) % slides.length;
      track.style.transform = `translateX(-${current * 100}%)`;

      if (dotsWrap) {
        Array.from(dotsWrap.children).forEach((dot, dotIndex) => {
          dot.classList.toggle("is-active", dotIndex === current);
          dot.setAttribute("aria-selected", dotIndex === current ? "true" : "false");
        });
      }
    }

    function startAutoplay() {
      if (slides.length < 2 || autoplay) {
        return;
      }

      autoplay = window.setInterval(() => goTo(current + 1), 6500);
    }

    function stopAutoplay() {
      if (!autoplay) {
        return;
      }

      window.clearInterval(autoplay);
      autoplay = null;
    }

    if (dotsWrap) {
      slides.forEach((_, index) => {
        const dot = document.createElement("button");
        dot.type = "button";
        dot.className = "uc-testimonial-slider__dot";
        dot.setAttribute("aria-label", `Ir para depoimento ${index + 1}`);
        dot.setAttribute("aria-selected", index === 0 ? "true" : "false");
        dot.addEventListener("click", () => {
          stopAutoplay();
          goTo(index);
          startAutoplay();
        });
        dotsWrap.appendChild(dot);
      });
    }

    prev?.addEventListener("click", () => {
      stopAutoplay();
      goTo(current - 1);
      startAutoplay();
    });

    next?.addEventListener("click", () => {
      stopAutoplay();
      goTo(current + 1);
      startAutoplay();
    });

    root.addEventListener("mouseenter", stopAutoplay);
    root.addEventListener("mouseleave", startAutoplay);

    root.addEventListener(
      "touchstart",
      (event) => {
        startX = event.touches[0]?.clientX || 0;
      },
      { passive: true }
    );

    root.addEventListener(
      "touchend",
      (event) => {
        const endX = event.changedTouches[0]?.clientX || 0;
        const diff = endX - startX;

        if (Math.abs(diff) < 40) {
          return;
        }

        stopAutoplay();
        goTo(current + (diff < 0 ? 1 : -1));
        startAutoplay();
      },
      { passive: true }
    );

    goTo(0);
    startAutoplay();
  });
})();
