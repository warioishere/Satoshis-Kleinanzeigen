document.addEventListener('click', function (e) {
  const box = e.target.closest('.sk-vendor-info-wrap');
  if (!box) return;

  // don't hijack other controls
  if (e.target.closest('a, button, input, textarea, select, [role="button"]')) return;

  const link = box.querySelector('.sk-vendor-name a');
  if (link && link.href) {
    window.location.assign(link.href);
  }
});

// Keyboard accessibility (Enter/Space anywhere on the box)
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.sk-vendor-info-wrap').forEach(box => {
    box.setAttribute('tabindex', '0');
    box.addEventListener('keydown', (ev) => {
      if (ev.key === 'Enter' || ev.key === ' ') {
        const link = box.querySelector('.sk-vendor-name a');
        if (link && link.href) {
          ev.preventDefault();
          window.location.assign(link.href);
        }
      }
    });
  });
});

