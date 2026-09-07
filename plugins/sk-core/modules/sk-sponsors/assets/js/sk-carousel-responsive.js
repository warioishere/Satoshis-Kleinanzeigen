/*
 * Adopted from the plugin wp-post-image-carousel 2.0.3.
 */

(function() {
  'use strict';

  const GAP_PX = 15;

  function initWppisCarousel() {
    document.querySelectorAll('.wppis-slider').forEach(slider => {
      // Set the gap CSS variable defensively (in case styles loaded earlier)
      slider.style.setProperty('--wppis-gap', GAP_PX + 'px');

      const track = slider.querySelector('.wppis-track');
      if (!track) return;

      // For horizontal sliders: single row, scrollable, fixed gap
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

      // For vertical sliders: column layout, no horizontal scrolling
      if (slider.classList.contains('vertical')) {
        track.style.display = 'flex';
        track.style.flexDirection = 'column';
        track.style.gap = GAP_PX + 'px';
        track.style.overflow = 'visible';
      }

      // Set slides to "flex: 0 0 auto" so no wraps occur
      track.querySelectorAll('.wppis-slide').forEach(slide => {
        slide.style.boxSizing = 'border-box';
        slide.style.flex = '0 0 auto';
        slide.style.margin = '0'; // spacing comes from gap
        slide.style.scrollSnapAlign = 'start';
      });

      // Keep images responsive
      track.querySelectorAll('.wppis-figure, .wppis-figure img').forEach(el => {
        el.style.display = 'block';
        el.style.width = '100%';
        el.style.height = 'auto';
        el.style.margin = '0';
      });
    });
  }

  // Init on DOMContentLoaded + after any Ajax loads (in case the plugin lazy-loads)
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initWppisCarousel);
  } else {
    initWppisCarousel();
  }

  // Optional: if the plugin loads slides dynamically, you can listen for its own events here
  // document.addEventListener('wppis:updated', initWppisCarousel);
})();