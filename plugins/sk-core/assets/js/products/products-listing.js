/**
 * Mobile behaviour of the seller product list: expand row actions and
 * the Status / Preis / Datum columns.
 */
(function () {
    'use strict';

    /* ── Mobile only: add "mehr ▾" pill that expands row details ── */
    function initDesktopCollapse() {
        if (window.innerWidth > 768) return;

        document.querySelectorAll('#sk-product-list-table .row-actions').forEach(function (wrap) {
            if (wrap.dataset.skDesktop) return;
            wrap.dataset.skDesktop = '1';

            // Toggle link
            var toggle = document.createElement('span');
            toggle.className = 'sk-row-toggle';
            toggle.innerHTML = '<a href="#" class="sk-row-toggle-link">mehr ▾</a>';
            toggle.querySelector('a').addEventListener('click', function (e) {
                e.preventDefault();
                var tr = wrap.closest('tr');
                if (tr) {
                    tr.classList.toggle('is-expanded');
                    this.textContent = tr.classList.contains('is-expanded') ? 'weniger ▴' : 'mehr ▾';
                }
            });
            wrap.appendChild(toggle);
        });
    }

    /* ── Mobile: toggle-row button expands Status / Preis / Datum ── */
    function initToggleRow() {
        document.querySelectorAll('#sk-product-list-table .toggle-row').forEach(function (btn) {
            if (btn.dataset.skInit) return;
            btn.dataset.skInit = '1';
            btn.addEventListener('click', function () {
                var tr = btn.closest('tr');
                if (tr) tr.classList.toggle('is-expanded');
            });
        });
    }

    function init() {
        initDesktopCollapse();
        initToggleRow();
    }

    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('sk_product_inline_edit_done', init);
})();
