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

    /**
     * Bulk bar: appears once something is picked, and the percent field
     * only for the price action. Sending with nothing picked would apply
     * the action to nothing, so the button stays out of reach until then.
     */
    function initBulkBar() {
        var bar = document.querySelector('.sk-bulk-bar');
        if (!bar) return;

        var form   = bar.closest('form');
        var action = bar.querySelector('.sk-bulk-bar__action');
        var pct    = bar.querySelector('.sk-bulk-bar__amount');
        var count  = bar.querySelector('[data-role=count]');
        var all    = form.querySelector('.sk-bulk-all');

        function picked() {
            return form.querySelectorAll('.sk-bulk-pick:checked').length;
        }

        function refresh() {
            var n = picked();
            bar.hidden = n === 0;
            count.textContent = n;
            pct.hidden = action.value !== 'sk_price';
        }

        form.addEventListener('change', function (e) {
            if (e.target === all) {
                form.querySelectorAll('.sk-bulk-pick').forEach(function (box) { box.checked = all.checked; });
            }
            refresh();
        });

        form.addEventListener('submit', function (e) {
            if (action.value === '-1' || !picked()) {
                e.preventDefault();
                return;
            }
            if (action.value === 'sk_price' && !pct.querySelector('input').value) {
                e.preventDefault();
            }
        });

        refresh();
    }

    function init() {
        initDesktopCollapse();
        initToggleRow();
        initBulkBar();
    }

    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('sk_product_inline_edit_done', init);
})();
