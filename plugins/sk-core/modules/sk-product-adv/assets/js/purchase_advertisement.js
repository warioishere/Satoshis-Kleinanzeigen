/**
 * SK Product Advertisement — Purchase UI
 */
(function ($, window) {
    'use strict';

    var started = false;

    function handleError(err) {
        if (err.responseJSON && err.responseJSON.message) return err.responseJSON.message;
        if (err.responseJSON && err.responseJSON.data && err.responseJSON.data.message) return err.responseJSON.data.message;
        return err.responseText || '';
    }

    function toggleSpin($el, on) {
        var icon = $el.find('i.adv_icon_2').first();
        if (icon.length) on ? icon.addClass('fa-spin') : icon.removeClass('fa-spin');
    }

    // Advertise from product list table
    $('#sk-product-list-table').on('click', 'span.sk-product-advertisement', async function () {
        var $el = $(this);
        var productId = $el.data('product-id');
        var alreadyAdvertised = $el.data('already-advertised');
        var productStatus = $el.data('product-status');
        var advText = '';

        if (started || alreadyAdvertised) return;
        started = true;
        toggleSpin($el, true);

        if (productStatus !== 'publish') {
            sk_sweetalert(sk_purchase_advertisement.product_not_published, { icon: 'error' });
            toggleSpin($el, false);
            started = false;
            return;
        }

        await Swal.fire({
            title: sk_purchase_advertisement.on_load_advertisement_status,
            icon: 'info',
            showCloseButton: false,
            showCancelButton: false,
            allowOutsideClick: false,
            didOpen: function () {
                Swal.showLoading();
                wp.ajax.post('sk_get_advertisement_status', {
                    product_id: productId,
                    advertise_product_nonce: sk_purchase_advertisement.advertise_product_nonce
                }).then(function (res) {
                    advText = res.advertisement_text;
                    Swal.close();
                }).fail(function (err) {
                    var msg = handleError(err);
                    if (msg) sk_sweetalert(msg, { action: 'error', title: sk_purchase_advertisement.on_error_message, icon: 'error' });
                    started = false;
                    toggleSpin($el, false);
                });
            }
        });

        if (!advText) return;

        var result = await sk_sweetalert('', { action: 'confirm', icon: 'warning', html: advText });
        if (!result.isConfirmed) {
            $el.prop('checked', false);
            toggleSpin($el, false);
            started = false;
            return;
        }

        try {
            await wp.ajax.post('sk_add_advertise_product_to_cart', {
                product_id: productId,
                advertise_product_nonce: sk_purchase_advertisement.advertise_product_nonce
            }).then(function (res) {
                $el.addClass('advertised');
                $el.find('i.adv_icon_1').first().css('color', sk_purchase_advertisement.advertise_active);
                if (res.free_purchase === true) {
                    sk_sweetalert(res.message, { action: 'confirm', title: sk_purchase_advertisement.on_success_message, icon: 'success' }).then(function () {
                        window.location.reload();
                    });
                } else if (typeof skBuynow !== 'undefined' && skBuynow.nonce) {
                    sk_sweetalert(res.message, { action: 'confirm', title: sk_purchase_advertisement.on_success_message, icon: 'success' }).then(function (r) {
                        if (r && r.isConfirmed) {
                            $.post(skBuynow.ajaxurl, { action: 'sk_buynow', nonce: skBuynow.nonce, type: 'adv', product_id: 0 }, function (resp) {
                                if (resp.success && window.btcpay) {
                                    window.btcpay.setApiUrlPrefix(skBuynow.btcpayUrl);
                                    window.btcpay.showInvoice(resp.data.invoiceId);
                                    window.btcpay.onModalReceiveMessage(function (ev) {
                                        if (!ev || typeof ev.data !== 'object') return;
                                        var st = (ev.data.status || '').toLowerCase();
                                        if (['complete','paid','processing','settled'].indexOf(st) !== -1) window.location.href = resp.data.orderCompleteLink;
                                    });
                                } else {
                                    alert(resp.data && resp.data.message || 'Fehler');
                                }
                            });
                        }
                    });
                } else {
                    window.location.replace(sk_purchase_advertisement.checkout_url);
                }
            }).fail(function (err) {
                var msg = handleError(err);
                if (msg) sk_sweetalert(msg, { action: 'error', title: sk_purchase_advertisement.on_error_message, icon: 'error' });
            }).always(function () {
                toggleSpin($el, false);
                started = false;
            });
        } catch (e) {}
    });

    // Advertise from single product page
    $('#sk_advertise_single_product').on('click', function () {
        var $el = $(this);
        if (!$el.is(':checked')) return;

        var productId = $el.data('product-id');
        if (!productId) return;

        sk_sweetalert(sk_purchase_advertisement.advertise_alert, { action: 'confirm', icon: 'warning' }).then(function (result) {
            if (!result.isConfirmed) { $el.prop('checked', false); return; }

            wp.ajax.post('sk_add_advertise_product_to_cart', {
                product_id: productId,
                advertise_product_nonce: sk_purchase_advertisement.advertise_product_nonce
            }).then(function (res) {
                sk_sweetalert(res.message, { action: 'success', title: sk_purchase_advertisement.on_success_message, icon: 'success' });
                $el.prop('disabled', true);
                $(document.body).trigger('wc_fragment_refresh');
            }).fail(function (err) {
                $el.prop('checked', false);
                var msg = handleError(err);
                if (msg) sk_sweetalert(msg, { action: 'error', title: sk_purchase_advertisement.on_error_message, icon: 'error' });
            });
        });
    });

})(jQuery, window);
