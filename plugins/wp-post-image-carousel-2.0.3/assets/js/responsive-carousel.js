(function() {
  'use strict';

  const GAP_PX = 15;

  function initWppisCarousel() {
    document.querySelectorAll('.wppis-slider').forEach(slider => {
      // CSS-Variable für Lücke sicher setzen (falls Styles früher geladen wurden)
      slider.style.setProperty('--wppis-gap', GAP_PX + 'px');

      const track = slider.querySelector('.wppis-track');
      if (!track) return;

      // Für horizontale Slider: einzeilig, scrollbar, Lücke fixieren
      if (slider.classList.contains('horizontal')) {
        track.style.display = 'flex';
        track.style.flexDirection = 'row';
        track.style.gap = GAP_PX + 'px';
        track.style.overflowX = 'auto';
        track.style.overflowY = 'hidden';
        track.style.webkitOverflowScrolling = 'touch';
        track.style.scrollSnapType = 'x proximity';
        track.style.flexWrap = 'nowrap';
      }

      // Für vertikale Slider: Spalte, keine horizontale Scrollerei
      if (slider.classList.contains('vertical')) {
        track.style.display = 'flex';
        track.style.flexDirection = 'column';
        track.style.gap = GAP_PX + 'px';
        track.style.overflow = 'visible';
      }

      // Slides auf "flex: 0 0 auto" setzen, damit keine Wraps entstehen
      track.querySelectorAll('.wppis-slide').forEach(slide => {
        slide.style.boxSizing = 'border-box';
        slide.style.flex = '0 0 auto';
        slide.style.margin = '0'; // Lücke über gap
        slide.style.scrollSnapAlign = 'start';
      });

      // Bilder responsiv halten
      track.querySelectorAll('.wppis-figure, .wppis-figure img').forEach(el => {
        el.style.display = 'block';
        el.style.width = '100%';
        el.style.height = 'auto';
        el.style.margin = '0';
      });
    });
  }

  // Init auf DOMContentLoaded + nach evtl. Ajax-Loads (falls Plugin nachlädt)
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initWppisCarousel);
  } else {
    initWppisCarousel();
  }

  // Optional: Wenn das Plugin Slides dynamisch lädt, kannst du hier auf eigene Events hören
  // document.addEventListener('wppis:updated', initWppisCarousel);
})();
