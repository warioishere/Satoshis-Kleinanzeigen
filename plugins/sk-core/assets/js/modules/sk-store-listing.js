/**
 * SK Store Listing — Sort, Filter, Product Search (Grid only, no list view)
 */
(function ($) {
    'use strict';

    var ajaxurl = (window.sk && sk.ajaxurl) ;

    $(function () {
        var $wrap = $('#sk-store-listing-filter-wrap');
        var $listWrap = $('#sk-seller-listing-wrap');
        var $filterForm = $('#sk-store-listing-filter-form-wrap');

        if (!$wrap.length) return;

        // Remove loading spinners
        $('.sk-geolocation-filters-loading').remove();

        // Force grid view always
        $listWrap.removeClass('list-view').addClass('grid-view');

        // Hide list/grid toggle
        $wrap.find('.toggle-view').hide();

        /* ── Sort By ── */
        $wrap.on('change', '.sort-by #stores_orderby', function () {
            var val = $(this).val();
            var url = new URL(window.location.href);
            url.searchParams.set('stores_orderby', val);
            window.location = url.toString();
        });

        /* ── Filter Form Toggle ── */
        $wrap.on('click', '.sk-store-list-filter-button, .sk-icons, #cancel-filter-btn', function (e) {
            e.preventDefault();
            $filterForm.slideToggle('fast');
        });

        /* ── Apply Filter ── */
        $filterForm.on('click', '#apply-filter-btn', function (e) {
            e.preventDefault();
            $filterForm.closest('form').submit();
        });

        /* ── Store Search ── */
        $filterForm.on('keyup', '.store-search-input', function (e) {
            if (e.keyCode === 13) {
                $filterForm.closest('form').submit();
            }
        });

        /* ── Product Search on Store Page ── */
        $(document).on('submit', '.sk-store-products-filter-search', function (e) {
            e.preventDefault();
            var $form = $(this);
            var search = $form.find('input[name="product_search_query"]').val();
            var storeId = $form.data('store_id');

            if (!search) return;

            $.post(ajaxurl, {
                action: 'sk_store_product_search_action',
                search_term: search,
                store_id: storeId
            }, function (res) {
                if (res.success && res.data) {
                    $form.closest('.sk-store-products-filter-area')
                        .siblings('.seller-items').html(res.data);
                }
            });
        });
    });

})(jQuery);
