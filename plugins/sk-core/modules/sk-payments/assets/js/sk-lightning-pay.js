/**
 * SK Lightning — Frontend JS
 *
 * Handles: Lightning pay button, chat message rendering,
 * invoice creation, payment confirmation.
 *
 * Currency detection uses the same logic as woo-fiatprices/geo-preis.js:
 * Swiss browser locales (de-ch, fr-ch, it-ch, rm-ch) → CHF, otherwise → EUR.
 */
(function ($) {
    'use strict';

    var SKL = window.skLightning || {};

    /* ─── Currency Detection (matches geo-preis.js logic) ─── */

    var browserLang = (navigator.language || (navigator.languages && navigator.languages[0]) || '').toLowerCase();
    var userCurrency = (browserLang === 'de-ch' || browserLang === 'fr-ch' || browserLang === 'it-ch' || browserLang === 'rm-ch') ? 'CHF' : 'EUR';
    var currencySymbol = userCurrency === 'CHF' ? 'CHF' : '€';

    // Cache for BTC rate (fetched once per page load).
    var btcRate = null;
    var btcRatePromise = null;

    /**
     * Fetch BTC rate for detected currency (cached).
     */
    function fetchBtcRate() {
        if (btcRatePromise) return btcRatePromise;

        btcRatePromise = $.ajax({
            url: 'https://blockchain.info/ticker',
            dataType: 'json',
            timeout: 5000
        }).then(function (prices) {
            var rate = prices && prices[userCurrency] && prices[userCurrency].last;
            if (rate && rate > 0) {
                btcRate = rate;
            }
            return btcRate;
        }).catch(function () {
            return null;
        });

        return btcRatePromise;
    }

    /**
     * Convert sats to fiat using cached rate.
     */
    function satsToFiat(sats) {
        if (!btcRate || !sats) return null;
        return (sats * btcRate / 100000000).toFixed(2);
    }

    /**
     * Format fiat amount with currency symbol.
     */
    function formatFiat(amount) {
        if (amount === null || amount === undefined) return '';
        var num = Number(amount).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return num + ' ' + currencySymbol;
    }

    /**
     * Format sats with locale.
     */
    function formatSats(sats) {
        return Number(sats).toLocaleString('de-DE');
    }

    // Pre-fetch rate on page load.
    fetchBtcRate();

    /* ─── Lightning Pay Button (Product Page) ─── */

    $(document).on('click', '.sk-lightning-pay-btn', function (e) {
        e.preventDefault();
        var $btn = $(this);

        // Vendor, title and price are resolved server-side from the product.
        var data = {
            action: 'sk_create_purchase_request',
            nonce: SKL.nonce,
            product_id: $btn.data('product-id')
        };

        $btn.prop('disabled', true).text('⚡ Wird gesendet...');

        $.post(SKL.ajaxurl, data, function (res) {
            if (res.success && res.data.chat_url) {
                window.location.href = res.data.chat_url;
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Fehler beim Senden der Kaufanfrage.');
                $btn.prop('disabled', false).html('⚡ Mit Lightning bezahlen');
            }
        }).fail(function () {
            alert('Netzwerkfehler. Bitte erneut versuchen.');
            $btn.prop('disabled', false).html('⚡ Mit Lightning bezahlen');
        });
    });

    /* ─── Chat Message Rendering ─── */

    /**
     * Render payment cards.
     *
     * The card data comes exclusively from the server via data-sk-card, where
     * PaymentCard has rebuilt it from the payment row. Message text is never
     * parsed — otherwise anyone could type a marker and fake an invoice.
     */
    function renderLightningMessages() {
        $('.dvc-message').each(function () {
            var $msg = $(this);
            if ($msg.data('sk-lightning-rendered')) return;
            $msg.data('sk-lightning-rendered', true);

            var card = $msg.data('sk-card');
            if (!card || typeof card !== 'object' || !card.type) return;

            switch (card.type) {
                case 'purchase_request':
                    renderPurchaseRequest($msg, card);
                    break;
                case 'lightning_invoice':
                    renderInvoice($msg, card);
                    break;
                case 'payment_confirmed':
                    renderConfirmation($msg, card);
                    break;
                case 'onchain_payment':
                    renderOnchainPayment($msg, card);
                    break;
                case 'onchain_confirmed':
                    renderOnchainConfirmed($msg, card);
                    break;
            }
        });
    }

    function renderPurchaseRequest($msg, data) {
        var priceSats = intVal(data.price_sats);
        var satsFormatted = formatSats(priceSats);
        var isVendor = !$msg.hasClass('own');

        // Fiat is always derived from the live rate.
        var fiatDisplay = '';
        var calcFiat = satsToFiat(priceSats);
        if (calcFiat) {
            fiatDisplay = formatFiat(calcFiat) + ' · ';
        }

        var html = '<div class="skl-purchase-request">' +
            '<div class="skl-pr-header">⚡ Kaufanfrage</div>' +
            '<div class="skl-pr-product">' + escHtml(data.product_title) + '</div>' +
            '<div class="skl-pr-price">' + escHtml(fiatDisplay + satsFormatted) + ' Sats</div>';

        if (isVendor) {
            html += '<button class="skl-create-invoice-btn" ' +
                'data-product-id="' + intVal(data.product_id) + '" ' +
                'data-amount-sats="' + priceSats + '" ' +
                'data-chat-id="' + escAttr(getChatId()) + '">' +
                '⚡ Invoice erstellen</button>';
        }

        html += '</div>';

        $msg.find('.dvc-message-text').html(html);
    }

    function renderInvoice($msg, data) {
        var paymentHash = hexId(data.payment_hash);
        if (!paymentHash) return;

        var amountSats = intVal(data.amount_sats);
        var satsFormatted = formatSats(amountSats);
        var isVendor = $msg.hasClass('own'); // Vendor sent the invoice

        var fiatInfo = '';
        var calcFiat = satsToFiat(amountSats);
        if (calcFiat) {
            fiatInfo = ' ≈ ' + formatFiat(calcFiat);
        }

        var html = '<div class="skl-invoice">' +
            '<div class="skl-inv-header">⚡ Lightning Invoice — ' + escHtml(satsFormatted) + ' Sats' + escHtml(fiatInfo) + '</div>';

        // QR Code (rendered server-side)
        html += '<div class="skl-qr-container" style="text-align:center;padding:16px;">' +
            qrImageTag(data.qr, 220) + '</div>';

        // bolt11 copyable
        html += '<div class="skl-bolt11-wrap">' +
            '<input type="text" class="skl-bolt11-input" value="' + escAttr(data.payment_request) + '" readonly />' +
            '<button class="skl-copy-btn" data-copy="' + escAttr(data.payment_request) + '">Kopieren</button>' +
            '</div>';

        // Deeplink (for buyer)
        if (!isVendor) {
            html += '<a href="' + escAttr(safeUri(data.deeplink)) + '" class="skl-deeplink-btn">⚡ In Wallet öffnen</a>';
        }

        // Payment status — settled state comes from the server.
        html += '<div class="skl-payment-status" data-payment-hash="' + escAttr(paymentHash) + '" ' +
            'style="text-align:center;padding:8px;font-size:13px;color:#5a6a7e;">' +
            (data.settled
                ? '<span style="color:#5cb85c;">✅ Zahlung bestätigt!</span>'
                : '<i class="fas fa-spinner fa-spin"></i> Warte auf Zahlung…') +
            '</div>';

        // Vendor: manual confirm button (fallback if no auto-verify)
        if (isVendor && !data.settled) {
            html += '<button class="skl-vendor-confirm-btn" ' +
                'data-payment-hash="' + escAttr(paymentHash) + '" ' +
                'data-chat-id="' + escAttr(getChatId()) + '" ' +
                'style="width:100%;margin-top:8px;background:rgba(40,167,69,0.12);color:#5cb85c;border:1px solid rgba(40,167,69,0.25);padding:8px 16px;border-radius:6px;cursor:pointer;font-size:13px;">' +
                '✓ Zahlung in Wallet erhalten — bestätigen</button>';
        }

        html += '</div>';

        $msg.find('.dvc-message-text').html(html);

        if (!data.settled) {
            startPaymentPolling(paymentHash);
        }
    }

    function renderConfirmation($msg, data) {
        var amountSats = intVal(data.amount_sats);
        var satsFormatted = formatSats(amountSats);

        var fiatInfo = '';
        var calcFiat = satsToFiat(amountSats);
        if (calcFiat) {
            fiatInfo = ' ≈ ' + formatFiat(calcFiat);
        }

        var html = '<div class="skl-confirmed">' +
            '<div class="skl-conf-icon">✅</div>' +
            '<div class="skl-conf-text">Zahlung bestätigt — ' + escHtml(satsFormatted) + ' Sats' + escHtml(fiatInfo) + '</div>';

        html += '</div>';

        $msg.find('.dvc-message-text').html(html);
    }

    /* ─── Onchain Payment in Chat ─── */

    function renderOnchainPayment($msg, data) {
        var paymentHash = hexId(data.payment_hash);
        if (!paymentHash) return;

        var amountSats = intVal(data.amount_sats);
        var satsFormatted = formatSats(amountSats);
        var isVendor = !$msg.hasClass('own');

        var html = '<div class="skl-purchase-request">' +
            '<div class="skl-pr-header"><i class="fab fa-bitcoin"></i> Onchain-Kaufanfrage</div>' +
            '<div class="skl-pr-product">' + escHtml(data.product_title) + '</div>' +
            '<div class="skl-pr-price">' + escHtml(satsFormatted) + ' Sats (' + escHtml(data.btc_amount) + ' BTC)</div>';

        html += '<div style="margin:10px 0;background:#0f1923;border:1px solid rgba(255,255,255,0.08);border-radius:6px;padding:10px;word-break:break-all;font-family:monospace;font-size:12px;color:#e8ecf0;">' +
            escHtml(data.address) + '</div>';

        html += '<div style="display:flex;gap:6px;margin-bottom:8px;">' +
            '<button class="skl-copy-btn" data-copy="' + escAttr(data.address) + '" style="font-size:11px;">Adresse kopieren</button>' +
            '<a href="https://mempool.space/address/' + encodeURIComponent(data.address) + '" target="_blank" rel="noopener" style="font-size:11px;color:#f7931a;text-decoration:none;padding:6px 10px;"><i class="fas fa-external-link-alt"></i> mempool.space</a>' +
            '</div>';

        // QR Code (rendered server-side)
        html += '<div style="text-align:center;margin-bottom:8px;">' +
            qrImageTag(data.qr, 180) + '</div>';

        // Payment status — settled state comes from the server.
        html += '<div class="skl-payment-status" data-payment-hash="' + escAttr(paymentHash) + '" ' +
            'style="text-align:center;padding:8px;font-size:13px;color:#5a6a7e;">' +
            (data.settled
                ? '<span style="color:#5cb85c;">✅ Onchain-Zahlung bestätigt!</span>'
                : '<i class="fas fa-spinner fa-spin"></i> Warte auf Blockchain-Bestätigung...') +
            '</div>';

        // Vendor: manual confirm button.
        if (isVendor && !data.settled) {
            html += '<button class="skl-vendor-confirm-btn" ' +
                'data-payment-hash="' + escAttr(paymentHash) + '" ' +
                'data-chat-id="' + escAttr(getChatId()) + '" ' +
                'style="width:100%;margin-top:8px;background:rgba(40,167,69,0.12);color:#5cb85c;border:1px solid rgba(40,167,69,0.25);padding:8px 16px;border-radius:6px;cursor:pointer;font-size:13px;">' +
                'Zahlung in Wallet erhalten — bestätigen</button>';
        }

        html += '</div>';

        $msg.find('.dvc-message-text').html(html);

        if (!data.settled) {
            startOnchainChatPolling(paymentHash);
        }
    }

    function renderOnchainConfirmed($msg, data) {
        var satsFormatted = formatSats(intVal(data.amount_sats));

        var html = '<div class="skl-confirmed">' +
            '<div class="skl-conf-icon">✅</div>' +
            '<div class="skl-conf-text">Onchain-Zahlung bestätigt — ' + escHtml(satsFormatted) + ' Sats</div>';

        var txid = hexId(data.txid);
        if (txid) {
            html += '<div style="margin-top:6px;font-size:11px;"><a href="https://mempool.space/tx/' + escAttr(txid) + '" target="_blank" rel="noopener" style="color:#f7931a;">TX auf mempool.space ansehen</a></div>';
        }

        html += '</div>';

        $msg.find('.dvc-message-text').html(html);
    }

    /* ─── Onchain Chat Polling ─── */

    var onchainChatPolls = {};

    function startOnchainChatPolling(paymentHash) {
        if (onchainChatPolls[paymentHash]) return;

        var attempts = 0;
        onchainChatPolls[paymentHash] = setInterval(function () {
            attempts++;
            if (attempts > 120) { // 30 min
                clearInterval(onchainChatPolls[paymentHash]);
                delete onchainChatPolls[paymentHash];
                updatePaymentStatus(paymentHash, 'Timeout — Anbieter kann manuell bestätigen.', false);
                return;
            }

            $.ajax({
                url: SKL.resturl + 'check-onchain',
                method: 'GET',
                data: { payment_hash: paymentHash },
                headers: { 'X-WP-Nonce': SKL.restNonce },
                success: function (res) {
                    var txid = hexId(res.txid);
                    if (res.confirmed) {
                        clearInterval(onchainChatPolls[paymentHash]);
                        delete onchainChatPolls[paymentHash];
                        var msg = '✅ Onchain-Zahlung bestätigt!';
                        if (txid) {
                            msg += ' <a href="https://mempool.space/tx/' + escAttr(txid) + '" target="_blank" rel="noopener" style="color:#f7931a;font-size:11px;">TX ansehen</a>';
                        }
                        updatePaymentStatus(paymentHash, msg, true);
                    } else if (res.in_mempool) {
                        var memMsg = '<span style="color:#f7931a;">TX im Mempool erkannt';
                        if (txid) {
                            memMsg += ' — <a href="https://mempool.space/tx/' + escAttr(txid) + '" target="_blank" rel="noopener" style="color:#f7931a;">ansehen</a>';
                        }
                        memMsg += '</span>';
                        updatePaymentStatus(paymentHash, memMsg, false);
                    }
                }
            });
        }, 15000);
    }

    /* ─── Create Invoice (Vendor clicks in chat) ─── */

    $(document).on('click', '.skl-create-invoice-btn', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var data = {
            action: 'sk_create_lightning_invoice',
            nonce: SKL.nonce,
            chat_id: $btn.data('chat-id'),
            amount_sats: $btn.data('amount-sats'),
            product_id: $btn.data('product-id')
        };

        $btn.prop('disabled', true).text('⚡ Wird erstellt...');

        $.post(SKL.ajaxurl, data, function (res) {
            if (res.success) {
                if (typeof window.dvcLoadMessages === 'function') {
                    window.dvcLoadMessages();
                } else {
                    location.reload();
                }
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Invoice-Erstellung fehlgeschlagen.');
                $btn.prop('disabled', false).html('⚡ Invoice erstellen');
            }
        }).fail(function () {
            alert('Netzwerkfehler.');
            $btn.prop('disabled', false).html('⚡ Invoice erstellen');
        });
    });

    /* ─── Auto-Polling: check payment status via LUD-21 verify ─── */

    var activePolls = {};

    function startPaymentPolling(paymentHash) {
        if (activePolls[paymentHash]) return;

        var attempts = 0;
        var maxAttempts = 60; // 5 minutes at 5s intervals

        activePolls[paymentHash] = setInterval(function () {
            attempts++;
            if (attempts > maxAttempts) {
                stopPaymentPolling(paymentHash);
                updatePaymentStatus(paymentHash, 'Zeitüberschreitung — Anbieter kann manuell bestätigen.', false);
                return;
            }

            $.ajax({
                url: SKL.resturl + 'check-payment',
                method: 'GET',
                data: { payment_hash: paymentHash },
                headers: { 'X-WP-Nonce': SKL.restNonce },
                success: function (res) {
                    if (res.settled || res.status === 'confirmed') {
                        stopPaymentPolling(paymentHash);
                        updatePaymentStatus(paymentHash, '✅ Zahlung bestätigt!', true);
                    }
                }
            });
        }, 5000);
    }

    function stopPaymentPolling(paymentHash) {
        if (activePolls[paymentHash]) {
            clearInterval(activePolls[paymentHash]);
            delete activePolls[paymentHash];
        }
    }

    function updatePaymentStatus(paymentHash, text, success) {
        var $el = $('.skl-payment-status[data-payment-hash="' + paymentHash + '"]');
        if ($el.length) {
            var color = success ? '#5cb85c' : '#5a6a7e';
            $el.html('<span style="color:' + color + ';">' + text + '</span>');
        }
        if (success) {
            // Hide vendor confirm button.
            $('.skl-vendor-confirm-btn[data-payment-hash="' + paymentHash + '"]').hide();
        }
    }

    /* ─── Vendor: manual payment confirmation (fallback) ─── */

    $(document).on('click', '.skl-vendor-confirm-btn', function (e) {
        e.preventDefault();
        var $btn = $(this);

        if (!confirm('Hast du die Zahlung in deiner Wallet erhalten?')) return;

        var data = {
            action: 'sk_confirm_payment',
            nonce: SKL.nonce,
            payment_hash: $btn.data('payment-hash'),
            chat_id: $btn.data('chat-id')
        };

        $btn.prop('disabled', true).text('Wird bestätigt...');

        $.post(SKL.ajaxurl, data, function (res) {
            if (res.success) {
                stopPaymentPolling($btn.data('payment-hash'));
                if (typeof window.dvcLoadMessages === 'function') {
                    window.dvcLoadMessages();
                } else {
                    location.reload();
                }
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Bestätigung fehlgeschlagen.');
                $btn.prop('disabled', false).html('✓ Zahlung in Wallet erhalten — bestätigen');
            }
        }).fail(function () {
            alert('Netzwerkfehler.');
            $btn.prop('disabled', false).html('✓ Zahlung in Wallet erhalten — bestätigen');
        });
    });

    /* ─── Copy bolt11 ─── */

    $(document).on('click', '.skl-copy-btn', function () {
        var text = $(this).data('copy');
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text);
            $(this).text('Kopiert!');
            var $btn = $(this);
            setTimeout(function () { $btn.text('Kopieren'); }, 2000);
        }
    });

    /* ─── QR Code ─── */

    /**
     * QR images are rendered server-side by QrImage and travel inside the card
     * as a PNG data URI. Nothing about a payment leaves the site, and no third
     * party can swap the image the payer scans.
     */
    function qrImageTag(dataUri, size) {
        if (!/^data:image\/png;base64,[A-Za-z0-9+/=]+$/.test(String(dataUri || ''))) {
            return '';
        }

        return '<img src="' + escAttr(dataUri) + '" alt="QR Code" ' +
            'style="max-width:' + intVal(size) + 'px;width:100%;border-radius:8px;background:#fff;" />';
    }

    /* ─── Helpers ─── */

    function getChatId() {
        var params = new URLSearchParams(window.location.search);
        return params.get('chat_id') || $('[data-chat-id]').first().data('chat-id') || 0;
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.textContent = String(str === null || str === undefined ? '' : str);
        return div.innerHTML;
    }

    function escAttr(str) {
        return String(str === null || str === undefined ? '' : str)
            .replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    /** Non-negative integer, or 0. */
    function intVal(value) {
        var n = parseInt(value, 10);
        return isNaN(n) || n < 0 ? 0 : n;
    }

    /** 64-char hex (payment hash / txid), or '' — never interpolate anything else. */
    function hexId(value) {
        var str = String(value === null || value === undefined ? '' : value);
        return /^[0-9a-f]{64}$/i.test(str) ? str.toLowerCase() : '';
    }

    /** Only wallet/web schemes reach an href — no javascript:/data: URIs. */
    function safeUri(value) {
        var url = String(value === null || value === undefined ? '' : value).trim();
        return /^(?:lightning:|bitcoin:|https:\/\/)[^\s"'<>]+$/i.test(url) ? url : '#';
    }

    /* ─── Observer: re-render when chat messages change ─── */

    var observer = new MutationObserver(function () {
        renderLightningMessages();
    });

    $(document).ready(function () {
        var chatArea = document.getElementById('dvc-messages-area');
        if (chatArea) {
            observer.observe(chatArea, { childList: true, subtree: true });
        }
        renderLightningMessages();
    });

})(jQuery);
