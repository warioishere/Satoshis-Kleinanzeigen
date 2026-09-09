/**
 * SK Subscription — WooCommerce Product Editor: Product Pack type toggle
 */
(function ($) {
    'use strict';

    var $productData = $('#woocommerce-product-data');
    if (!$productData.length) return;

    // Show/hide tabs for product_pack type
    $productData
        .find('.pricing').addClass('show_if_product_pack')
        .end().find('.inventory_tab').addClass('hide_if_product_pack')
        .end().find('.shipping_tab').addClass('hide_if_product_pack')
        .end().find('.linked_product_tab').addClass('hide_if_product_pack')
        .end().find('.attributes_tab').addClass('hide_if_product_pack')
        .end().find('._no_of_product_field').hide()
        .end().find('._pack_validity_field').hide()
        .end().find('#_tax_status').parent().parent().addClass('show_if_product_pack');

    // Hide inventory tab if already product_pack
    if ($('#product-type').val() === 'product_pack') {
        setTimeout(function () { $('.inventory_tab').hide(); }, 500);
    }

    // Product type change handler
    $('body').on('woocommerce-product-type-change', function (e, type) {
        $('._no_of_product_field').hide();
        $('._pack_validity_field').hide();
        $('._sale_price_field').show();

        if (type === 'product_pack') {
            $('._no_of_product_field').show();
            $('._pack_validity_field').show();
            $('._sale_price_field').hide();
        }
    });

})(jQuery);
