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
            // If no Lightning Address but has Nostr pubkey, fetch lud16 from Nostr profile.
            if (!data.lnAddress && data.nostrPubkey) {
                setStatus('<i class="fas fa-spinner fa-spin"></i> Lightning Address wird von Nostr geladen...', null);
                data.lnAddress = await fetchLud16FromNostr(data.nostrPubkey);
                if (!data.lnAddress) {
                    setStatus('Kein Lightning-Zahlungsweg im Nostr-Profil gefunden.', false);
                    $btn.prop('disabled', false).text('&#9889; Zap senden');
                    return;
                }
            }

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

            // Poll Nostr relays for Kind 9735 Zap Receipt to verify payment.
            if (data.nostrPubkey && lnurlData.allowsNostr) {
                watchForZapReceipt(data, amountSats);
            }

        } catch (err) {
            console.error('[SK Zaps] Error:', err);
            setStatus('Fehler: ' + err.message, false);
            $btn.prop('disabled', false).text('&#9889; Zap senden');
        }
    }

    /**
     * Resolve a Lightning Address to LNURL-pay metadata.
     */
    /**
     * Fetch lud16 (Lightning Address) from a Nostr profile via relay.
     */
    async function fetchLud16FromNostr(pubkeyHex) {
        var relays = (window.skZaps && skZaps.relays) || ['wss://purplepag.es', 'wss://relay.nostr.band'];

        for (var i = 0; i < relays.length; i++) {
            try {
                var lud16 = await new Promise(function (resolve, reject) {
                    var ws = new WebSocket(relays[i]);
                    var timeout = setTimeout(function () { ws.close(); reject('timeout'); }, 5000);

                    ws.onopen = function () {
                        ws.send(JSON.stringify(['REQ', 'lud16', { kinds: [0], authors: [pubkeyHex], limit: 1 }]));
                    };
                    ws.onmessage = function (msg) {
                        try {
                            var data = JSON.parse(msg.data);
                            if (data[0] === 'EVENT' && data[2] && data[2].content) {
                                var profile = JSON.parse(data[2].content);
                                clearTimeout(timeout);
                                ws.close();
                                resolve(profile.lud16 || '');
                            } else if (data[0] === 'EOSE') {
                                clearTimeout(timeout);
                                ws.close();
                                resolve('');
                            }
                        } catch (e) { /* ignore parse errors */ }
                    };
                    ws.onerror = function () { clearTimeout(timeout); reject('ws error'); };
                });

                if (lud16) return lud16;
            } catch (e) { /* try next relay */ }
        }

        return '';
    }

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
        var html = '<div style="text-align:center;margin-top:12px;display:flex;flex-direction:column;align-items:center;">';
        html += '<p style="color:#e8ecf0;font-size:14px;margin-bottom:8px;">' + amountSats + ' Sats</p>';
        html += '<img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' + encodeURIComponent(invoice.toUpperCase()) + '" style="border-radius:8px;background:#fff;padding:6px;display:block;" />';
        html += '<div style="margin-top:10px;display:flex;gap:6px;justify-content:center;">';
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
    /**
     * Watch Nostr relays for a Kind 9735 Zap Receipt confirming payment.
     */
    function watchForZapReceipt(data, amountSats) {
        var relays = (window.skZaps && skZaps.relays) || ['wss://purplepag.es', 'wss://relay.nostr.band'];
        var since = Math.floor(Date.now() / 1000) - 5; // small buffer
        var confirmed = false;
        var sockets = [];
        var timeout;

        function cleanup() {
            clearTimeout(timeout);
            sockets.forEach(function (ws) { try { ws.close(); } catch (e) {} });
            sockets = [];
        }

        function onReceipt(receiptEvent) {
            if (confirmed) return;
            confirmed = true;

            // Extract amount from bolt11 in zap receipt description tag
            var amountFromReceipt = amountSats; // fallback
            try {
                var descTag = (receiptEvent.tags || []).find(function (t) { return t[0] === 'description'; });
                if (descTag && descTag[1]) {
                    var zapReq = JSON.parse(descTag[1]);
                    var amountTag = (zapReq.tags || []).find(function (t) { return t[0] === 'amount'; });
                    if (amountTag) amountFromReceipt = Math.floor(parseInt(amountTag[1]) / 1000);
                }
            } catch (e) {}

            trackZap(data, amountFromReceipt);

            // Update UI
            setStatus('&#9889; Zap bestätigt! ' + amountFromReceipt + ' Sats', true);
            setTimeout(function () { $('#sk-zap-modal').remove(); }, 2500);

            cleanup();
        }

        // Subscribe to each relay
        relays.forEach(function (relayUrl) {
            try {
                var ws = new WebSocket(relayUrl);
                sockets.push(ws);

                ws.onopen = function () {
                    // Subscribe for Kind 9735 (Zap Receipt) tagging vendor pubkey
                    ws.send(JSON.stringify([
                        'REQ', 'zap-receipt',
                        { kinds: [9735], '#p': [data.nostrPubkey], since: since, limit: 5 }
                    ]));
                };

                ws.onmessage = function (msg) {
                    try {
                        var ev = JSON.parse(msg.data);
                        if (ev[0] === 'EVENT' && ev[2] && ev[2].kind === 9735) {
                            onReceipt(ev[2]);
                        }
                    } catch (e) {}
                };

                ws.onerror = function () { /* ignore, other relays may work */ };
            } catch (e) {}
        });

        // Give up after 90 seconds
        timeout = setTimeout(function () {
            if (!confirmed) {
                console.log('[SK Zaps] No zap receipt received within 90s');
            }
            cleanup();
        }, 90000);
    }

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
