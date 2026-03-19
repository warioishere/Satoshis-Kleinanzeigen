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
        $('._enable_recurring_payment_field').hide();
        $('.sk_subscription_pricing').hide();
        $('._sale_price_field').show();
        $('.sk_subscription_trial_period').hide();

        if (type === 'product_pack') {
            $('._no_of_product_field').show();
            $('._pack_validity_field').show();
            $('._enable_recurring_payment_field').show();
            $('._sale_price_field').hide();

            if ($('#sk_subscription_enable_trial').is(':checked')) {
                $('.sk_subscription_trial_period').show();
            }
            if ($('#_enable_recurring_payment').is(':checked')) {
                $('.sk_subscription_pricing').show();
                $('._pack_validity_field').hide();
                $('._exclusive_for_admin_only_field').prop('checked', false);
            }
        }
    });

    // Trial period toggle
    $('.woocommerce_options_panel').on('change', '#sk_subscription_enable_trial', function () {
        $('.sk_subscription_trial_period').hide();
        if ($(this).is(':checked')) {
            $('.sk_subscription_trial_period').fadeIn();
        }
    });

    // Recurring payment toggle
    $('.woocommerce_options_panel').on('change', '#_enable_recurring_payment', function () {
        $('.sk_subscription_pricing').hide();
        $('._pack_validity_field').show();
        if ($(this).is(':checked')) {
            $('.sk_subscription_pricing').fadeIn();
            $('._pack_validity_field').hide();
            $('#_exclusive_for_admin_only').prop('checked', false);
        }
    });

    // Exclusive for admin only — not allowed with recurring
    $('.woocommerce_options_panel').on('change', '#_exclusive_for_admin_only', function () {
        if ($(this).is(':checked') && $('#_enable_recurring_payment').is(':checked')) {
            $(this).prop('checked', false);
            alert(skSubscription.exclusiveForAdminOnly);
        }
    });

    // Update subscription length options when period/interval changes
    $('#woocommerce-product-data').on('change', '[name^="_sk_subscription_period"], [name^="_sk_subscription_period_interval"]', function () {
        $('[name^="_sk_subscription_length"]').each(function () {
            var $select = $(this);
            var currentVal = $select.val();
            var found = false;
            var billingInterval = parseInt($('#_sk_subscription_period_interval').val());
            var period = $('#_sk_subscription_period').val();

            $select.empty();

            $.each(skSubscription.subscriptionLengths[period], function (key, label) {
                if (parseInt(key) === 0 || parseInt(key) % billingInterval === 0) {
                    $select.append($('<option></option>').attr('value', key).text(label));
                }
            });

            $select.children('option').each(function () {
                if (this.value == currentVal) { found = true; return false; }
            });

            $select.val(found ? currentVal : 0);
        });
    });

})(jQuery);
