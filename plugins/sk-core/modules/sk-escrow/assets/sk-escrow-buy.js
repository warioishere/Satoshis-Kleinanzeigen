/*
 * "Treuhand" step of the instant purchase on the product page.
 *
 * sk-payments-product.js hands over the pending purchase (product, variant,
 * delivery note); this modal collects the refund address and the buyer's
 * key (generated in the browser by sk-escrow-ui.js, or a pasted xpub) and
 * sends the request. No money moves here: the seller accepts first.
 */
(function ($) {
    'use strict';

    var pending = null;
    var cfg = window.weoSigner || {};
    var L = cfg.l10n || {};

    function t(key) { return L[key] || key; }

    function error(msg) {
        $('#weo-escrow-error').text(msg).show();
    }

    window.SKEscrowBuy = {
        start: function (data) {
            pending = data;
            $('#weo-escrow-error').hide();
            $('#weo-escrow-modal').css('display', 'flex');
        }
    };

    $(document).on('click', '#weo-escrow-cancel', function () {
        $('#weo-escrow-modal').hide();
        pending = null;
    });

    $(document).on('click', '#weo-escrow-submit', function () {
        if (!pending) { return; }
        var $btn = $(this);
        var xpub = $.trim($('#weo_buyer_xpub').val() || '');
        var refund = $.trim($('#weo_refund_address').val() || '');

        $('#weo-escrow-error').hide();
        if (!/^bc1[0-9a-z]{8,87}$/i.test(refund)) { error(t('addrBad')); return; }
        if (!xpub) { error(t('xpubMissing')); return; }

        $btn.prop('disabled', true).text(t('working'));

        $.post(cfg.ajaxurl, {
            action: 'weo_request',
            nonce: cfg.nonce,
            product_id: pending.product_id,
            variant: pending.variant,
            note: pending.note,
            xpub: xpub,
            refund_address: refund
        }, function (res) {
            $btn.prop('disabled', false).text($btn.data('label') || $btn.text());
            if (res && res.success) {
                var html = '<p style="color:#5cb85c;font-weight:600;">' + escHtml(res.data.message) + '</p>';
                if (res.data.url) {
                    html += '<p><a href="' + escAttr(res.data.url) + '" style="color:#f7931a;">Zu meinen Käufen</a></p>';
                }
                $('#weo-escrow-modal > div').html(html + '<button type="button" id="weo-escrow-cancel" style="display:block;width:100%;padding:10px;margin-top:8px;background:none;border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#5a6a7e;font-size:14px;cursor:pointer;">Schliessen</button>');
            } else {
                error(res && res.data && res.data.message ? res.data.message : t('netError'));
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            error(t('netError'));
        });
    });

    function escHtml(str) {
        var div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    function escAttr(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
})(jQuery);
