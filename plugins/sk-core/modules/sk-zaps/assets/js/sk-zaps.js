/**
 * SK Zaps — Lightning Zap Button
 *
 * NIP-57 flow (with Nostr extension like Alby Hub):
 *   1. Build Kind 9734 zap request event
 *   2. Sign with window.nostr.signEvent()
 *   3. Send to vendor's LNURL callback with nostr= parameter
 *   4. Get invoice back
 *   5. Pay with window.webln.sendPayment() or show QR
 *
 * Fallback (no extension):
 *   1. Resolve vendor's Lightning Address via LNURL
 *   2. Request invoice for amount
 *   3. Show QR code + deeplink
 */
(function ($) {
    'use strict';

    var defaults = window.skZaps || {};
    var presetAmounts = [21, 100, 500, 1000, 5000];

    // Zap button click.
    $(document).on('click', '.sk-zap-btn', function (e) {
        e.preventDefault();
        var $btn = $(this);

        var data = {
            vendorId: $btn.data('vendor-id'),
            lnAddress: $btn.data('lightning-address'),
            nostrPubkey: $btn.data('nostr-pubkey'),
            storeName: $btn.data('store-name'),
            defaultAmount: $btn.data('default-amount') || defaults.defaultAmount || 21,
            postId: $btn.data('post-id') || 0,
            $btn: $btn
        };

        showZapModal(data);
    });

    function showZapModal(data) {
        // Remove existing modal.
        $('#sk-zap-modal').remove();

        var hasNostr = !!data.nostrPubkey && !!window.nostr;
        var subtitle = hasNostr ? 'NIP-57 Zap' : 'Lightning Tip';

        var html = '<div id="sk-zap-modal" class="sk-zap-modal">';
        html += '<div class="sk-zap-modal-inner">';
        html += '<h3 style="margin:0 0 4px;color:#e8ecf0;font-size:18px;">&#9889; ' + escHtml(data.storeName) + '</h3>';
        html += '<p style="margin:0 0 12px;font-size:12px;color:#5a6a7e;">' + subtitle + '</p>';

        // Preset amounts.
        html += '<div class="sk-zap-amounts">';
        presetAmounts.forEach(function (amt) {
            var active = amt === data.defaultAmount ? ' active' : '';
            html += '<button type="button" class="sk-zap-amount-btn' + active + '" data-amount="' + amt + '">' + amt + '</button>';
        });
        html += '</div>';

        // Custom amount.
        html += '<div class="sk-zap-custom">';
        html += '<input type="number" id="sk-zap-amount" value="' + data.defaultAmount + '" min="1" placeholder="Sats" />';
        html += '</div>';

        // Send button.
        html += '<button type="button" class="sk-zap-send" id="sk-zap-send"';
        html += ' data-ln-address="' + escAttr(data.lnAddress) + '"';
        html += ' data-nostr-pubkey="' + escAttr(data.nostrPubkey) + '">';
        html += '&#9889; Zap senden</button>';

        // Status.
        html += '<div class="sk-zap-status" id="sk-zap-status"></div>';

        // Close.
        html += '<button type="button" class="sk-zap-close" id="sk-zap-close">Abbrechen</button>';
        html += '</div></div>';

        $('body').append(html);

        // Preset amount clicks.
        $('.sk-zap-amount-btn').on('click', function () {
            $('.sk-zap-amount-btn').removeClass('active');
            $(this).addClass('active');
            $('#sk-zap-amount').val($(this).data('amount'));
        });

        // Close.
        $('#sk-zap-close').on('click', function () { $('#sk-zap-modal').remove(); });
        $('#sk-zap-modal').on('click', function (e) {
            if (e.target === this) $('#sk-zap-modal').remove();
        });

        // Send.
        $('#sk-zap-send').on('click', function () {
            var amount = parseInt($('#sk-zap-amount').val(), 10);
            if (!amount || amount < 1) { setStatus('Bitte Betrag eingeben.', false); return; }
            sendZap(data, amount);
        });
    }

    async function sendZap(data, amountSats) {
        var $btn = $('#sk-zap-send');
        $btn.prop('disabled', true).text('Wird gesendet...');
        setStatus('<i class="fas fa-spinner fa-spin"></i> Lightning Address wird aufgelöst...', null);

        try {
            // Step 1: Resolve Lightning Address → LNURL-pay metadata.
            var lnurlData = await resolveLnAddress(data.lnAddress);
            if (!lnurlData || !lnurlData.callback) {
                setStatus('Lightning Address konnte nicht aufgelöst werden.', false);
                $btn.prop('disabled', false).text('&#9889; Zap senden');
                return;
            }

            var amountMsats = amountSats * 1000;

            // Check amount range.
            var min = lnurlData.minSendable || 1000;
            var max = lnurlData.maxSendable || 100000000000;
            if (amountMsats < min || amountMsats > max) {
                var minS = Math.ceil(min / 1000);
                var maxS = Math.floor(max / 1000);
                setStatus('Betrag muss zwischen ' + minS + ' und ' + maxS + ' Sats liegen.', false);
                $btn.prop('disabled', false).text('&#9889; Zap senden');
                return;
            }

            var invoice = null;

            // Step 2: Try NIP-57 Zap (if Nostr extension available + vendor has pubkey + LNURL supports nostr).
            if (window.nostr && data.nostrPubkey && lnurlData.nostrPubkey) {
                setStatus('<i class="fas fa-spinner fa-spin"></i> Zap Request wird signiert...', null);

                try {
                    // Build unsigned zap request (Kind 9734).
                    var zapRequest = {
                        kind: 9734,
                        created_at: Math.floor(Date.now() / 1000),
                        content: '',
                        tags: [
                            ['p', data.nostrPubkey],
                            ['amount', String(amountMsats)],
                            ['relays', 'wss://relay.nostr.band', 'wss://nos.lol', 'wss://relay.damus.io'],
                            ['lnurl', data.lnAddress]
                        ]
                    };

                    // Sign with Nostr extension (Alby Hub).
                    var signedZap = await window.nostr.signEvent(zapRequest);

                    if (signedZap && signedZap.id) {
                        // Request invoice with nostr zap request.
                        var sep = lnurlData.callback.indexOf('?') !== -1 ? '&' : '?';
                        var zapUrl = lnurlData.callback + sep + 'amount=' + amountMsats + '&nostr=' + encodeURIComponent(JSON.stringify(signedZap));

                        setStatus('<i class="fas fa-spinner fa-spin"></i> Invoice wird angefordert...', null);
                        var invoiceResp = await fetch(zapUrl).then(function (r) { return r.json(); });

                        if (invoiceResp.pr) {
                            invoice = invoiceResp.pr;
                        }
                    }
                } catch (nostrErr) {
                    console.warn('[SK Zaps] NIP-57 failed, falling back to LNURL:', nostrErr.message);
                }
            }

            // Step 3: Fallback — plain LNURL-pay invoice (no zap receipt on Nostr).
            if (!invoice) {
                setStatus('<i class="fas fa-spinner fa-spin"></i> Invoice wird angefordert...', null);
                var sep = lnurlData.callback.indexOf('?') !== -1 ? '&' : '?';
                var invoiceResp = await fetch(lnurlData.callback + sep + 'amount=' + amountMsats).then(function (r) { return r.json(); });
                if (invoiceResp.pr) {
                    invoice = invoiceResp.pr;
                }
            }

            if (!invoice) {
                setStatus('Keine Invoice erhalten.', false);
                $btn.prop('disabled', false).text('&#9889; Zap senden');
                return;
            }

            // Step 4: Pay the invoice.
            // Try WebLN first (Alby Hub exposes window.webln).
            if (window.webln) {
                try {
                    setStatus('<i class="fas fa-spinner fa-spin"></i> Zahlung wird gesendet...', null);
                    await window.webln.enable();
                    await window.webln.sendPayment(invoice);
                    setStatus('&#9889; Zap gesendet!', true);
                    trackZap(data, amountSats);
                    setTimeout(function () { $('#sk-zap-modal').remove(); }, 2000);
                    return;
                } catch (weblnErr) {
                    console.warn('[SK Zaps] WebLN payment failed:', weblnErr.message);
                }
            }

            // Fallback: show invoice as QR + deeplink.
            showInvoiceFallback(invoice, amountSats);

        } catch (err) {
            console.error('[SK Zaps] Error:', err);
            setStatus('Fehler: ' + err.message, false);
            $btn.prop('disabled', false).text('&#9889; Zap senden');
        }
    }

    /**
     * Resolve a Lightning Address to LNURL-pay metadata.
     */
    async function resolveLnAddress(address) {
        // Lightning Address: user@domain → https://domain/.well-known/lnurlp/user
        if (address.indexOf('@') !== -1) {
            var parts = address.split('@');
            var url = 'https://' + parts[1] + '/.well-known/lnurlp/' + parts[0];
            var resp = await fetch(url);
            return resp.json();
        }
        return null;
    }

    /**
     * Show invoice as QR code + deeplink when WebLN not available.
     */
    function showInvoiceFallback(invoice, amountSats) {
        var html = '<div style="text-align:center;margin-top:12px;">';
        html += '<p style="color:#e8ecf0;font-size:14px;margin-bottom:8px;">' + amountSats + ' Sats</p>';
        html += '<img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' + encodeURIComponent(invoice.toUpperCase()) + '" style="border-radius:8px;background:#fff;padding:6px;" />';
        html += '<div style="margin-top:10px;display:flex;gap:6px;">';
        html += '<button class="sk-zap-close" onclick="navigator.clipboard.writeText(\'' + escAttr(invoice) + '\');this.textContent=\'Kopiert!\'">Invoice kopieren</button>';
        html += '<a href="lightning:' + escAttr(invoice) + '" class="sk-zap-send" style="text-align:center;text-decoration:none;display:block;">In Wallet öffnen</a>';
        html += '</div></div>';

        $('#sk-zap-send').hide();
        $('.sk-zap-amounts, .sk-zap-custom').hide();
        $('#sk-zap-status').html(html);
    }

    function setStatus(html, success) {
        var color = success === true ? '#5cb85c' : (success === false ? '#e06c75' : '#5a6a7e');
        $('#sk-zap-status').html('<span style="color:' + color + ';">' + html + '</span>');
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    function escAttr(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    /**
     * Track zap amount on feed posts after successful payment.
     */
    function trackZap(data, amountSats) {
        if (!data.postId || typeof skFeed === 'undefined') return;

        $.post(skFeed.ajaxurl, {
            action: 'sk_feed_track_zap',
            _nonce: skFeed.nonce,
            post_id: data.postId,
            amount: amountSats
        }, function (res) {
            if (res.success && data.$btn) {
                var total = res.data.total;
                var formatted = total >= 1000 ? (total / 1000).toFixed(total % 1000 === 0 ? 0 : 1) + 'k' : total;
                var $span = data.$btn.find('.sk-zap-total');
                if ($span.length) {
                    $span.text(formatted);
                } else {
                    data.$btn.html('&#9889; <span class="sk-zap-total">' + formatted + '</span>');
                }
            }
        });
    }

})(jQuery);
