/* Mobile: clicking the main category opens/closes subcategories.
   Desktop keeps using the existing :hover CSS. */
(function () {
  const isMobile = () => window.matchMedia('(max-width: 768px)').matches;

  // First submenu inside the top item (robust against class changes)
  function getSubmenu(topItem) {
    return topItem.querySelector(':scope > .wc-block-product-categories-list--depth-1')
        || topItem.querySelector(':scope > ul');
  }

  function closeAllOpen(root, except) {
    root.querySelectorAll('.wc-block-product-categories-list-item.open').forEach(li => {
      if (li !== except) li.classList.remove('open');
    });
  }

  // Click delegation
  document.addEventListener('click', function (e) {
    // Outside click: close all (mobile only)
    const list = e.target.closest('.wc-block-product-categories-list');
    if (!list) {
      if (isMobile()) {
        document.querySelectorAll('.wc-block-product-categories-list').forEach(ul => closeAllOpen(ul));
      }
      return;
    }

    if (!isMobile()) return; // Desktop: do nothing (hover handles it)

    // Direct top item?
    const topItem = e.target.closest('.wc-block-product-categories-list > .wc-block-product-categories-list-item');
    if (!topItem) return;

    const submenu = getSubmenu(topItem);
    if (!submenu) return; // no subcategories -> normal link

    // Let clicks inside the submenu pass through normally
    if (submenu.contains(e.target)) return;

    // Clicked on the main link? Then prevent navigation and toggle
    const clickedA = e.target.closest('a');
    const topLink = topItem.querySelector(':scope > a');
    if (clickedA && topLink && (clickedA === topLink || topLink.contains(clickedA))) {
      e.preventDefault();
    }

    // Close other open ones
    closeAllOpen(list, topItem);

    // Toggle
    topItem.classList.toggle('open');
  }, { passive: true }); // passive: true -> scrolling stays smooth
})();
