(function ($) {
    'use strict';


    var btcpayUrl    = (skBuynow && skBuynow.btcpayUrl) ? skBuynow.btcpayUrl : '';
    var modalOpen    = false;
    var buynowActive = false;
    var advPending   = false;

    // ── Open BTCPay modal with a given invoiceId ──────────────────────────────
    function openModal(invoiceId, orderCompleteLink) {
        if (!window.btcpay) {
            alert('BTCPay modal konnte nicht geladen werden.');
            return;
        }
        modalOpen = true;
        window.btcpay.setApiUrlPrefix(btcpayUrl);
        window.btcpay.showInvoice(invoiceId);
        window.btcpay.onModalReceiveMessage(function (event) {
            if (!event || typeof event.data !== 'object') return;
            var status = (event.data.status || '').toLowerCase();
            if (['complete', 'paid', 'processing', 'settled'].indexOf(status) !== -1) {
                modalOpen = false;
                window.location.href = orderCompleteLink;
            }
            if (status === 'expired' || status === 'invalid') {
                modalOpen = false;
            }
        });
    }

    // ── AJAX: create order + get BTCPay invoiceId ─────────────────────────────
    function callBuynow(type, productId, $btn) {
        if (buynowActive) return;
        buynowActive = true;

        if ($btn) {
            $btn.prop('disabled', true).addClass('sk-buynow-loading');
        }

        $.post(
            skBuynow.ajaxurl,
            {
                action:     'sk_buynow',
                nonce:      skBuynow.nonce,
                type:       type,
                product_id: productId || 0,
            },
            function (response) {
                buynowActive = false;
                if ($btn) {
                    $btn.prop('disabled', false).removeClass('sk-buynow-loading');
                }
                if (response.success) {
                    openModal(response.data.invoiceId, response.data.orderCompleteLink);
                } else {
                    var msg = (response.data && response.data.message)
                        ? response.data.message
                        : 'Fehler beim Zahlungsvorgang.';
                    alert(msg);
                }
            }
        ).fail(function () {
            buynowActive = false;
            if ($btn) {
                $btn.prop('disabled', false).removeClass('sk-buynow-loading');
            }
            alert('Verbindungsfehler. Bitte versuche es erneut.');
        });
    }

    // ── Intercept sk_sweetalert for advertisement paid flow ───────────────────
    // When ajaxSuccess detects a paid adv cart-add, we install a one-shot wrapper
    // around sk_sweetalert. The wrapper shows the normal "added to cart" dialog,
    // but after the user clicks OK it opens BTCPay instead of letting
    // purchase_advertisement.js call window.location.replace(checkout_url).
    function installSweetAlertIntercept() {
        if (typeof window.sk_sweetalert !== 'function') {
            console.warn('[SK BuyNow] sk_sweetalert not found');
            return;
        }
        var origSwal = window.sk_sweetalert;
        window.sk_sweetalert = function (message, options) {
            // Restore immediately — one-shot intercept
            window.sk_sweetalert = origSwal;

            var result = origSwal.call(this, message, options);

            // Only intercept the "success + confirm" dialog — that's the
            // "Product added to cart" confirmation in purchase_advertisement.js
            if (options && options.action === 'confirm' && options.icon === 'success') {
                return result.then(function (res) {
                    if (res && res.isConfirmed) {
                        setTimeout(function () { callBuynow('adv', 0, null); }, 50);
                        // Return a never-resolving promise so the .then(()=>location.replace(...))
                        // in purchase_advertisement.js never fires and cannot kill the modal.
                        return new Promise(function () {});
                    }
                    return res;
                });
            }
            return result;
        };
    }

    // ── Watch for paid advertisement cart-add ────────────────────────────────
    $(document).ajaxSuccess(function (event, xhr, settings) {
        var dataStr = settings.data;
        if (typeof dataStr === 'object' && dataStr !== null) {
            dataStr = $.param(dataStr);
        }
        if (!dataStr || dataStr.indexOf('sk_add_advertise_product_to_cart') === -1) {
            return;
        }
        try {
            var resp = xhr.responseJSON || JSON.parse(xhr.responseText);
            if (!resp || !resp.success || !resp.data) return;
            if (resp.data.free_purchase === true) return;
            installSweetAlertIntercept();
        } catch (e) {
            console.error('[SK BuyNow] ajaxSuccess error:', e);
        }
    });

    // ── Subscriptions: intercept .buy_product_pack link clicks ───────────────
    $(document).on('click', 'a.buy_product_pack', function (e) {
        if ($(this).hasClass('trial_pack')) return;

        e.preventDefault();

        var href  = $(this).attr('href') || '';
        var match = href.match(/[?&]add-to-cart=(\d+)/);
        if (!match) {
            window.location.href = href;
            return;
        }

        callBuynow('subscription', match[1], $(this));
    });

})(jQuery);
