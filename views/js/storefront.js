/**
 * Mega Builder — Lightweight Zero-Framework Storefront Interactivity
 * Sliders and MegaMenus. No dependencies; safe to run more than once (elements are marked ready).
 */
(function () {
  'use strict';

  function ready(el) {
    if (el.dataset.mbReady === '1') return false;
    el.dataset.mbReady = '1';
    return true;
  }

  /* ------------------------------------------------------------------ Sliders */
  function initSliders() {
    document.querySelectorAll('[data-mb-component="slider"]').forEach(function (slider) {
      if (!ready(slider)) return;
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

  /* ---------------------------------------------------------------- MegaMenus */
  var FOCUSABLE = 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])';

  function initMegaMenu(nav) {
    if (!ready(nav)) return;
    nav.classList.add('is-js');

    var collapse = parseInt(nav.getAttribute('data-mb-collapse'), 10) || 1024;
    var mq = window.matchMedia('(max-width: ' + collapse + 'px)');
    var burger = nav.querySelector('.mb-mm-burger');
    var drawer = nav.querySelector('.mb-mm-drawer');
    var closeBtn = nav.querySelector('.mb-mm-close');
    var overlay = nav.querySelector('.mb-mm-overlay');
    var items = Array.prototype.slice.call(nav.querySelectorAll('.mb-mm-item'));
    var topLinks = items.map(function (li) { return li.querySelector('.mb-mm-link'); });
    var hoverTimer = null;

    function isMobile() { return nav.classList.contains('is-mobile'); }

    function setOpen(li, open) {
      var toggle = li.querySelector('.mb-mm-toggle');
      if (!toggle) return;
      li.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function closeAll(except) {
      items.forEach(function (li) { if (li !== except) setOpen(li, false); });
    }

    function openPanel(li, focusFirst) {
      if (!isMobile()) closeAll(li);
      setOpen(li, true);
      if (focusFirst) {
        var first = li.querySelector('.mb-mm-panel ' + FOCUSABLE);
        if (first) first.focus();
      }
    }

    function openDrawer() {
      nav.classList.add('is-drawer-open');
      burger.setAttribute('aria-expanded', 'true');
      if (overlay) overlay.hidden = false;
      document.documentElement.classList.add('mb-mm-lock');
      if (closeBtn) closeBtn.focus();
    }

    function closeDrawer(returnFocus) {
      if (!nav.classList.contains('is-drawer-open')) return;
      nav.classList.remove('is-drawer-open');
      burger.setAttribute('aria-expanded', 'false');
      if (overlay) overlay.hidden = true;
      document.documentElement.classList.remove('mb-mm-lock');
      if (returnFocus) burger.focus();
    }

    function applyMode() {
      nav.classList.toggle('is-mobile', mq.matches);
      closeAll();
      closeDrawer(false);
    }
    applyMode();
    if (mq.addEventListener) mq.addEventListener('change', applyMode);
    else if (mq.addListener) mq.addListener(applyMode);

    items.forEach(function (li, idx) {
      var toggle = li.querySelector('.mb-mm-toggle');

      if (toggle) {
        toggle.addEventListener('click', function (e) {
          e.stopPropagation();
          if (li.classList.contains('is-open')) setOpen(li, false);
          else openPanel(li, false);
        });
      }

      // Desktop hover with a little intent delay; touch and mobile use the toggle button.
      li.addEventListener('pointerenter', function (e) {
        if (isMobile() || e.pointerType !== 'mouse' || !toggle) return;
        clearTimeout(hoverTimer);
        hoverTimer = setTimeout(function () { openPanel(li, false); }, 80);
      });
      li.addEventListener('pointerleave', function (e) {
        if (isMobile() || e.pointerType !== 'mouse' || !toggle) return;
        clearTimeout(hoverTimer);
        hoverTimer = setTimeout(function () { setOpen(li, false); }, 200);
      });

      // Arrow keys move between top-level items; ArrowDown enters the panel.
      li.addEventListener('keydown', function (e) {
        var inBar = e.target === topLinks[idx] || e.target === toggle;
        if (!inBar) return;
        if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
          if (isMobile()) return;
          e.preventDefault();
          var step = e.key === 'ArrowRight' ? 1 : -1;
          var target = topLinks[(idx + step + topLinks.length) % topLinks.length];
          if (target) target.focus();
        } else if (e.key === 'ArrowDown' && toggle) {
          e.preventDefault();
          openPanel(li, true);
        }
      });
    });

    nav.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        var open = items.filter(function (li) { return li.classList.contains('is-open'); })[0];
        if (open) {
          setOpen(open, false);
          var t = open.querySelector('.mb-mm-toggle');
          if (t) t.focus();
        } else if (isMobile()) {
          closeDrawer(true);
        }
        return;
      }
      // Keep Tab inside the open drawer.
      if (e.key === 'Tab' && isMobile() && nav.classList.contains('is-drawer-open')) {
        var f = Array.prototype.filter.call(drawer.querySelectorAll(FOCUSABLE), function (el) {
          return el.offsetParent !== null;
        });
        if (!f.length) return;
        if (e.shiftKey && document.activeElement === f[0]) {
          e.preventDefault();
          f[f.length - 1].focus();
        } else if (!e.shiftKey && document.activeElement === f[f.length - 1]) {
          e.preventDefault();
          f[0].focus();
        }
      }
    });

    // Focus leaving the menu closes desktop panels.
    nav.addEventListener('focusout', function (e) {
      if (isMobile()) return;
      if (!e.relatedTarget || !nav.contains(e.relatedTarget)) closeAll();
    });

    document.addEventListener('click', function (e) {
      if (!nav.contains(e.target) && !isMobile()) closeAll();
    });

    if (burger) burger.addEventListener('click', function () {
      if (nav.classList.contains('is-drawer-open')) closeDrawer(true);
      else openDrawer();
    });
    if (closeBtn) closeBtn.addEventListener('click', function () { closeDrawer(true); });
    if (overlay) overlay.addEventListener('click', function () { closeDrawer(true); });
  }

  function initMegaMenus() {
    document.querySelectorAll('[data-mb-component="megamenu"]').forEach(initMegaMenu);
  }

  function init() {
    initSliders();
    initMegaMenus();
  }

  window.MegaBuilderStorefront = { init: init };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
