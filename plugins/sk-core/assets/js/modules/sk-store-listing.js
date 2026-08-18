/**
 * SK Store Listing — Filter panel, Sort by, Grid/List view toggle
 */
(function ($) {
    'use strict';

    var STORAGE_KEY = 'sk_store_listing_view';

    function readStoredView() {
        try {
            var view = window.localStorage.getItem(STORAGE_KEY);
            return ('list-view' === view || 'grid-view' === view) ? view : null;
        } catch (e) {
            return null;
        }
    }

    function storeView(view) {
        try {
            window.localStorage.setItem(STORAGE_KEY, view);
        } catch (e) {
            // Storage unavailable (private mode) — view still applies for this page.
        }
    }

    $(function () {
        var $wrap = $('#sk-store-listing-filter-wrap');

        if (!$wrap.length) {
            return;
        }

        var $listWrap = $('#sk-seller-listing-wrap');
        var $filterForm = $('#sk-store-listing-filter-form-wrap');
        var $toggleView = $wrap.find('.toggle-view');

        /* ── Grid / List view ── */
        function applyView(view) {
            $listWrap.removeClass('grid-view list-view').addClass(view);
            $toggleView.find('[data-view]').removeClass('active')
                .filter('[data-view="' + view + '"]').addClass('active');
        }

        applyView(readStoredView() || ($listWrap.hasClass('list-view') ? 'list-view' : 'grid-view'));

        $toggleView.on('click', '[data-view]', function () {
            var view = $(this).data('view');

            applyView(view);
            storeView(view);
        });

        /* ── Sort by ── */
        $wrap.on('change', '.sort-by #stores_orderby', function () {
            var url = new URL(window.location.href);

            url.searchParams.set('stores_orderby', $(this).val());
            url.searchParams.delete('paged');
            // Drop the pagination segment, the new sort order starts on page one.
            url.pathname = url.pathname.replace(/\/page\/\d+\/?$/, '/');

            window.location.href = url.toString();
        });

        /* ── Filter panel ── */
        $wrap.on('click', '.sk-store-list-filter-button, .sk-icons', function (e) {
            e.preventDefault();
            $filterForm.slideToggle(300, function () {
                if ($(this).is(':visible')) {
                    $(this).find('input[type="text"], input[type="search"]').first().focus();
                }
            });
        });

        $filterForm.on('click', '#cancel-filter-btn', function (e) {
            e.preventDefault();
            $filterForm.slideUp(300);
        });

        // Keep the chosen sort order when the filter form is submitted.
        $filterForm.on('submit', function () {
            var orderby = $wrap.find('#stores_orderby').val();

            if (!orderby || $filterForm.find('input[name="stores_orderby"]').length) {
                return;
            }

            $('<input>', { type: 'hidden', name: 'stores_orderby', value: orderby }).appendTo($filterForm);
        });
    });

})(jQuery);
