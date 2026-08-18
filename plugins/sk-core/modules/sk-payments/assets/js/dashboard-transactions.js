/**
 * Lightning transaction list in the seller dashboard: fiat equivalents plus the
 * confirm-delivery, confirm-payment and report-problem actions.
 */
(function() {
    /* Currency detection — same logic as geo-preis.js */
    var lang = (navigator.language || (navigator.languages && navigator.languages[0]) || '').toLowerCase();
    var currency = (lang === 'de-ch' || lang === 'fr-ch' || lang === 'it-ch' || lang === 'rm-ch') ? 'CHF' : 'EUR';

    /* Fetch rate and append fiat equivalent to sats amounts */
    fetch('https://blockchain.info/ticker')
        .then(function(r) { return r.json(); })
        .then(function(prices) {
            var rate = prices && prices[currency] && prices[currency].last;
            if (!rate) return;

            document.querySelectorAll('.skl-sats-amount').forEach(function(el) {
                var sats = parseInt(el.getAttribute('data-sats'), 10);
                if (!isNaN(sats) && sats > 0) {
                    var fiat = (sats * rate / 100000000).toFixed(2);
                    var formatted = Number(fiat).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    el.insertAdjacentHTML('afterend',
                        '<span style="margin-left:8px;font-size:13px;font-weight:400;color:#5a6a7e;">≈ ' + formatted + ' ' + currency + '</span>'
                    );
                }
            });
        })
        .catch(function() { /* ignore */ });

    /* Buyer: confirm product received */
    jQuery(document).on('click', '.skl-confirm-delivery-btn', function(e) {
        e.preventDefault();
        if (!confirm('Hast du das Produkt erhalten?')) return;

        var $btn = jQuery(this);
        $btn.addClass('disabled').css('pointer-events', 'none').html('<i class="fas fa-spinner fa-spin"></i> Wird bestätigt...');

        jQuery.post(skLightning.ajaxurl, {
            action: 'sk_confirm_delivery',
            nonce: skLightning.nonce,
            payment_hash: $btn.data('payment-hash')
        }, function(res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Fehler.');
                $btn.removeClass('disabled').css('pointer-events', '').html('<i class="fas fa-box-open"></i> Produkt erhalten');
            }
        });
    });

    /* Vendor: confirm payment from dashboard */
    jQuery(document).on('click', '.skl-vendor-confirm-dashboard', function(e) {
        e.preventDefault();
        if (!confirm('Hast du die Zahlung in deiner Wallet erhalten?')) return;

        var $btn = jQuery(this);
        $btn.addClass('disabled').css('pointer-events', 'none').html('<i class="fas fa-spinner fa-spin"></i> Bestätige...');

        jQuery.post(skLightning.ajaxurl, {
            action: 'sk_confirm_payment',
            nonce: skLightning.nonce,
            payment_hash: $btn.data('payment-hash'),
            chat_id: 0
        }, function(res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Fehler.');
                $btn.removeClass('disabled').css('pointer-events', '').html('<i class="fas fa-check"></i> Zahlung bestätigen');
            }
        });
    });

    /* Report problem handler */
    jQuery(document).on('click', '.skl-report-problem-btn', function(e) {
        e.preventDefault();
        var reason = prompt('Bitte beschreibe das Problem:');
        if (!reason) return;

        var $btn = jQuery(this);
        $btn.addClass('disabled').css('pointer-events', 'none');

        jQuery.post(skLightning.ajaxurl, {
            action: 'sk_report_problem',
            nonce: skLightning.nonce,
            payment_hash: $btn.data('payment-hash'),
            reason: reason
        }, function(res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Fehler.');
                $btn.removeClass('disabled').css('pointer-events', '');
            }
        });
    });
})();
