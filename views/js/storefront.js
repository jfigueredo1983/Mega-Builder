/**
 * Mega Builder — Lightweight Zero-Framework Storefront Interactivity
 * Powers Hero Sliders, Hotspots, and Accessible Accordions in < 2 KB.
 */
(function () {
  function initSliders() {
    var sliders = document.querySelectorAll('[data-mb-component="slider"]');
    sliders.forEach(function (slider) {
      if (slider.dataset.mbReady === '1') return;
      slider.dataset.mbReady = '1';
      var slides = slider.querySelectorAll('.mb-slide-card');
      if (slides.length <= 1) return;
      var current = 0;
      function show(idx) {
        slides.forEach(function (s, i) {
          s.classList.toggle('is-active', i === idx);
        });
      }
      var prev = slider.querySelector('.mb-prev');
      var next = slider.querySelector('.mb-next');
      if (prev) {
        prev.addEventListener('click', function () {
          current = (current - 1 + slides.length) % slides.length;
          show(current);
        });
      }
      if (next) {
        next.addEventListener('click', function () {
          current = (current + 1) % slides.length;
          show(current);
        });
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSliders);
  } else {
    initSliders();
  }
})();
