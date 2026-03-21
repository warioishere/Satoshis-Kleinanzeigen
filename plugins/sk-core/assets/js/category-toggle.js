/* Mobile: Klick auf Hauptkategorie öffnet/schließt Unterkategorien.
   Desktop bleibt bei deinem :hover-CSS. */
(function () {
  const isMobile = () => window.matchMedia('(max-width: 768px)').matches;

  // Erstes Untermenü im Top-Item (robust gegen Klassenänderungen)
  function getSubmenu(topItem) {
    return topItem.querySelector(':scope > .wc-block-product-categories-list--depth-1')
        || topItem.querySelector(':scope > ul');
  }

  function closeAllOpen(root, except) {
    root.querySelectorAll('.wc-block-product-categories-list-item.open').forEach(li => {
      if (li !== except) li.classList.remove('open');
    });
  }

  // Klick-Delegation
  document.addEventListener('click', function (e) {
    // Außerhalb: alle schließen (nur Mobile)
    const list = e.target.closest('.wc-block-product-categories-list');
    if (!list) {
      if (isMobile()) {
        document.querySelectorAll('.wc-block-product-categories-list').forEach(ul => closeAllOpen(ul));
      }
      return;
    }

    if (!isMobile()) return; // Desktop: nix tun (Hover regelt)

    // Direktes Top-Item?
    const topItem = e.target.closest('.wc-block-product-categories-list > .wc-block-product-categories-list-item');
    if (!topItem) return;

    const submenu = getSubmenu(topItem);
    if (!submenu) return; // keine Unterkategorien -> normaler Link

    // Klicks im Untermenü normal durchlassen
    if (submenu.contains(e.target)) return;

    // Auf Hauptlink geklickt? Dann Navigation unterbinden und toggeln
    const clickedA = e.target.closest('a');
    const topLink = topItem.querySelector(':scope > a');
    if (clickedA && topLink && (clickedA === topLink || topLink.contains(clickedA))) {
      e.preventDefault();
    }

    // Andere offene schließen
    closeAllOpen(list, topItem);

    // Toggle
    topItem.classList.toggle('open');
  }, { passive: true }); // passive: true -> Scroll bleibt smooth
})();
