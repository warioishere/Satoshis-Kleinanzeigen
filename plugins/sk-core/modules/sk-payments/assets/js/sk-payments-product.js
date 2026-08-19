/**
 * SK Payments — Product Page JS
 *
 * Handles: Sofortkauf button, payment method selection, Lightning + Onchain flows.
 */
(function ($) {
    'use strict';

    var SKP = window.skPayments || {};
    var pendingData = null;

    /* ─── Sofortkauf Button ─── */

    $(document).on('click', '.skp-buy-btn', function (e) {
        e.preventDefault();
        var $btn = $(this);

        pendingData = {
            vendor_id: $btn.data('vendor-id'),
            product_id: $btn.data('product-id'),
            product_title: $btn.data('product-title'),
            price_sats: $btn.data('price-sats'),
            has_ln: $btn.data('has-ln') === 1 || $btn.data('has-ln') === '1',
            has_onchain: $btn.data('has-onchain') === 1 || $btn.data('has-onchain') === '1'
        };

        // Only one method available → go directly.
        if (pendingData.has_ln && !pendingData.has_onchain) {
            startLightning();
            return;
        }
        if (pendingData.has_onchain && !pendingData.has_ln) {
            startOnchain();
            return;
        }

        // Both available → show modal.
        $('#skp-method-modal').css('display', 'flex');
    });

    /* ─── Method Selection Modal ─── */

    $(document).on('click', '.skp-method-choice', function () {
        $('#skp-method-modal').hide();
        var method = $(this).data('method');
        if (method === 'lightning') {
            startLightning();
        } else {
            startOnchain();
        }
    });

    $(document).on('click', '#skp-method-cancel', function () {
        $('#skp-method-modal').hide();
        pendingData = null;
    });

    /* ─── Lightning Flow ─── */

    function startLightning() {
        if (!pendingData) return;
        var $btn = $('.skp-buy-btn');
        $btn.prop('disabled', true).text('Wird gesendet...');

        // Detect currency.
        var lang = (navigator.language || '').toLowerCase();
        var currency = /^(de|fr|it|rm)-ch$/.test(lang) ? 'CHF' : 'EUR';

        $.post(SKP.ajaxurl, {
            action: 'sk_create_purchase_request',
            nonce: SKP.nonce,
            vendor_id: pendingData.vendor_id,
            product_id: pendingData.product_id,
            product_title: pendingData.product_title,
            price_fiat: 0,
            currency: currency,
            price_sats: pendingData.price_sats
        }, function (res) {
            $btn.prop('disabled', false).text('Sofortkauf');
            if (res.success && res.data.chat_url) {
                window.location.href = res.data.chat_url;
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Fehler beim Senden der Kaufanfrage.');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Sofortkauf');
            alert('Netzwerkfehler. Bitte erneut versuchen.');
        });
    }

    /* ─── Onchain Flow ─── */

    function startOnchain() {
        if (!pendingData) return;
        var $btn = $('.skp-buy-btn');
        $btn.prop('disabled', true).text('Wird erstellt...');

        $.post(SKP.ajaxurl, {
            action: 'skp_create_onchain_payment',
            nonce: SKP.nonce,
            // Vendor, title and price are resolved server-side from the product.
            product_id: pendingData.product_id
        }, function (res) {
            $btn.prop('disabled', false).text('Sofortkauf');
            if (res.success) {
                showOnchainModal(res.data);
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Fehler.');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Sofortkauf');
            alert('Netzwerkfehler.');
        });
    }

    function showOnchainModal(data) {
        var sats = Number(data.amount_sats).toLocaleString('de-DE');
        var html = '';

        html += '<div style="text-align:center;margin-bottom:16px;">';
        html += '<div style="font-size:24px;font-weight:700;color:#f7931a;">' + sats + ' Sats</div>';
        html += '<div style="font-size:13px;color:#5a6a7e;">(' + data.btc_amount + ' BTC)</div>';
        html += '<div style="font-size:14px;color:#e8ecf0;margin-top:4px;">' + escHtml(data.product_title) + '</div>';
        html += '</div>';

        html += '<div style="margin-bottom:12px;">';
        html += '<div style="font-size:12px;color:#5a6a7e;margin-bottom:4px;">Zahle an diese Adresse:</div>';
        html += '<div style="background:#0f1923;border:1px solid rgba(255,255,255,0.08);border-radius:6px;padding:10px;word-break:break-all;font-family:monospace;font-size:13px;color:#e8ecf0;">';
        html += escHtml(data.address);
        html += '</div>';
        html += '</div>';

        // Copy + Wallet open buttons.
        html += '<div style="display:flex;gap:8px;margin-bottom:12px;">';
        html += '<button type="button" class="skp-copy-addr" data-copy="' + escAttr(data.address) + '" style="flex:1;padding:8px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:6px;color:#e8ecf0;cursor:pointer;font-size:13px;"><i class="fas fa-copy"></i> Adresse kopieren</button>';
        html += '<a href="' + escAttr(data.bip21) + '" style="flex:1;padding:8px;background:#f7931a;border:none;border-radius:6px;color:#fff;text-align:center;font-size:13px;text-decoration:none;display:flex;align-items:center;justify-content:center;"><i class="fab fa-bitcoin"></i>&nbsp;In Wallet öffnen</a>';
        html += '</div>';

        // QR Code — rendered server-side by QrImage, never by a third party.
        if (/^data:image\/png;base64,[A-Za-z0-9+/=]+$/.test(String(data.qr || ''))) {
            html += '<div style="text-align:center;margin-bottom:12px;">';
            html += '<img src="' + escAttr(data.qr) + '" alt="QR" style="max-width:180px;width:100%;border-radius:8px;background:#fff;padding:6px;" />';
            html += '</div>';
        }

        // Polling status.
        html += '<div id="skp-onchain-status" style="text-align:center;padding:10px;font-size:13px;color:#5a6a7e;">';
        html += '<i class="fas fa-spinner fa-spin"></i> Warte auf Blockchain-Bestätigung...';
        html += '</div>';

        if (data.chat_url) {
            html += '<div style="text-align:center;margin-top:4px;"><a href="' + escAttr(data.chat_url) + '" style="color:#f7931a;font-size:12px;">Chat öffnen</a></div>';
        }

        $('#skp-onchain-content').html(html);
        $('#skp-onchain-modal').css('display', 'flex');

        // Start polling blockchain.
        startOnchainPolling(data.payment_hash);
    }

    /* ─── Onchain Modal Close ─── */

    $(document).on('click', '#skp-onchain-close', function () {
        $('#skp-onchain-modal').hide();
        stopOnchainPolling();
    });

    /* ─── Onchain Blockchain Polling ─── */

    var onchainPollTimer = null;

    function startOnchainPolling(paymentHash) {
        stopOnchainPolling();
        var attempts = 0;
        var maxAttempts = 120; // 30 min at 15s intervals

        onchainPollTimer = setInterval(function () {
            attempts++;
            if (attempts > maxAttempts) {
                stopOnchainPolling();
                $('#skp-onchain-status').html('<span style="color:#5a6a7e;">Timeout — Anbieter kann manuell bestätigen.</span>');
                return;
            }

            $.ajax({
                url: SKP.resturl + 'check-onchain',
                method: 'GET',
                data: { payment_hash: paymentHash },
                headers: { 'X-WP-Nonce': SKP.restNonce },
                success: function (res) {
                    if (res.confirmed) {
                        stopOnchainPolling();
                        var msg = '<span style="color:#5cb85c;font-weight:600;">Zahlung bestätigt!';
                        if (res.txid) {
                            msg += ' <a href="https://mempool.space/tx/' + res.txid + '" target="_blank" rel="noopener" style="color:#f7931a;font-size:11px;">TX ansehen</a>';
                        }
                        msg += '</span>';
                        $('#skp-onchain-status').html(msg);
                    } else if (res.in_mempool) {
                        var memMsg = '<span style="color:#f7931a;">TX im Mempool erkannt';
                        if (res.txid) {
                            memMsg += ' — <a href="https://mempool.space/tx/' + res.txid + '" target="_blank" rel="noopener" style="color:#f7931a;">ansehen</a>';
                        }
                        memMsg += '<br><i class="fas fa-spinner fa-spin"></i> Warte auf Bestätigung...</span>';
                        $('#skp-onchain-status').html(memMsg);
                    }
                }
            });
        }, 15000); // Poll every 15 seconds.
    }

    function stopOnchainPolling() {
        if (onchainPollTimer) {
            clearInterval(onchainPollTimer);
            onchainPollTimer = null;
        }
    }

    /* ─── Copy Address ─── */

    $(document).on('click', '.skp-copy-addr', function () {
        var text = $(this).data('copy');
        if (navigator.clipboard && text) {
            navigator.clipboard.writeText(text);
            $(this).html('<i class="fas fa-check"></i> Kopiert!');
            var $b = $(this);
            setTimeout(function () { $b.html('<i class="fas fa-copy"></i> Adresse kopieren'); }, 2000);
        }
    });

    /* ─── Helpers ─── */

    function escHtml(str) {
        var div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    function escAttr(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

})(jQuery);
