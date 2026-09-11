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
 * With a stored NWC connection (skZaps.hasNwc) but no WebLN, the invoice is
 * paid server-side through that connection; without an extension the whole
 * zap runs server-side as an anonymous zap (sk_zap_pay_nwc).
 *
 * Fallback (no extension, no NWC):
 *   1. Resolve vendor's Lightning Address via LNURL
 *   2. Request invoice for amount
 *   3. Show QR code + deeplink
 */
(function ($) {
    'use strict';

    var defaults = window.skZaps || {};
    var presetAmounts = [21, 100, 500, 1000, 5000];

    /*
     * The buttons are rendered hidden: a zap needs an extension on this side
     * to sign the request, and only the browser can tell whether there is
     * one. Shown once window.nostr is there — checked again after load and
     * after every AJAX round, for extensions that inject late and for feed
     * cards that arrive later.
     */
    var nostr = window.skNostr;

    function revealButtons() {
        if (!window.nostr && !defaults.hasNwc) {
            return;
        }
        $('.sk-zap-btn[hidden]').prop('hidden', false);
    }

    // Wait for a late-injecting extension once; afterwards every AJAX round
    // (feed cards arriving later) reveals whatever buttons came with it.
    if (nostr) {
        nostr.waitForNostr(3000).then(revealButtons);
    } else {
        revealButtons();
        setTimeout(revealButtons, 1500);
    }
    $(window).on('load', revealButtons);
    $(document).ajaxComplete(revealButtons);

    function relayList() {
        var fromServer = (window.skZaps && skZaps.relays) || (nostr && nostr.config.relays) || [];
        return fromServer.length ? fromServer : ['wss://relay.damus.io', 'wss://nos.lol'];
    }

    function ajaxUrl() {
        return defaults.ajaxurl || (window.skFeed && skFeed.ajaxurl) || '/wp-admin/admin-ajax.php';
    }

    function formatSats(n) {
        return String(parseInt(n, 10) || 0).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    /**
     * The zap is paid. Every counter on the page moves right now — the
     * vendor's total in the store banner or the product box, and the post's
     * own count — and the server is told, so the numbers hold on reload.
     *
     * @param paymentHash Our own invoice: the server checks the wallet.
     * @param receipt     Outside address: the receipt seen on the relays.
     */
    function zapConfirmed(data, amountSats, paymentHash, receipt) {
        bumpTotals(data, amountSats);

        if (paymentHash) {
            // Counts the payment once on the vendor, whichever path found it.
            $.post(ajaxUrl(), { action: 'sk_zap_check_payment', vendor_id: data.vendorId, payment_hash: paymentHash });
            trackZap(data, amountSats, paymentHash);
        } else if (receipt) {
            $.post(ajaxUrl(), {
                action: 'sk_zap_receipt',
                vendor_id: data.vendorId,
                post_id: data.postId || 0,
                receipt: JSON.stringify(receipt)
            }, function (res) {
                if (res && res.success && res.data && res.data.post_total !== null && data.$btn) {
                    data.$btn.find('.sk-zap-total').text(formatSats(res.data.post_total));
                }
            });
        }
    }

    function bumpTotals(data, amountSats) {
        $('.sk-store-zaps, .sk-vendor-zaps').each(function () {
            var $el = $(this);
            var current = parseInt(($el.text().match(/[\d.]+/) || ['0'])[0].replace(/\./g, ''), 10) || 0;
            $el.html('<i class="fas fa-bolt"></i> ' + formatSats(current + amountSats) + ' Sats');
        });

        if (data.$btn && data.$btn.hasClass('sk-zap-btn--feed')) {
            var $span = data.$btn.find('.sk-zap-total');
            var now = parseInt(($span.text() || '0').replace(/\./g, ''), 10) || 0;
            $span.text(formatSats(now + amountSats));
        }
    }

    /** Same toast as the rest of the site (.dm-toast lives in the theme stylesheet). */
    function toast(message, type) {
        var $t = $('<div class="dm-toast ' + (type || 'error') + '"><i class="fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i><span></span></div>');
        $t.find('span').text(message);
        $('body').append($t);
        setTimeout(function () { $t.fadeOut(300, function () { $t.remove(); }); }, 3000);
    }

    /** The viewer's own button: same account, or same key in the extension's profile. */
    function isSelf(data) {
        var me = parseInt(defaults.currentUserId, 10) || 0;
        if (me && me === (parseInt(data.vendorId, 10) || 0)) {
            return true;
        }
        return !!defaults.currentPubkey && !!data.nostrPubkey && String(data.nostrPubkey).toLowerCase() === defaults.currentPubkey;
    }

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

        if (isSelf(data)) {
            toast(defaults.i18nSelfZap || 'Du kannst dich nicht selbst zappen.');
            return;
        }

        showZapModal(data);
    });

    function showZapModal(data) {
        // Remove existing modal.
        $('#sk-zap-modal').remove();

        var hasNostr = !!data.nostrPubkey && !!window.nostr;
        var subtitle = hasNostr ? 'NIP-57 Zap' : (defaults.hasNwc ? 'Zap über deine verbundene Wallet' : 'Lightning Tip');

        var html = '<div id="sk-zap-modal" class="sk-zap-modal">';
        html += '<div class="sk-zap-modal-inner">';
        html += '<h3 style="margin:0 0 4px;color:#e8ecf0;font-size:18px;"><i class="fas fa-bolt" style="color:#f7931a;"></i> ' + escHtml(data.storeName) + '</h3>';
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
        html += '<i class="fas fa-bolt"></i> Zap senden</button>';

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
            // No extension to sign with, but a wallet connected on the server:
            // the whole zap runs there (anonymous zap request, paid via NWC).
            if (!window.nostr && defaults.hasNwc) {
                await payViaNwc(data, amountSats, '');
                return;
            }

            // If no Lightning Address but has Nostr pubkey, fetch lud16 from Nostr profile.
            if (!data.lnAddress && data.nostrPubkey) {
                setStatus('<i class="fas fa-spinner fa-spin"></i> Lightning Address wird von Nostr geladen...', null);
                data.lnAddress = await fetchLud16FromNostr(data.nostrPubkey);
                if (!data.lnAddress) {
                    setStatus('Kein Lightning-Zahlungsweg im Nostr-Profil gefunden.', false);
                    $btn.prop('disabled', false).html('<i class="fas fa-bolt"></i> Zap senden');
                    return;
                }
            }

            // Step 1: Resolve Lightning Address → LNURL-pay metadata.
            var lnurlData = await resolveLnAddress(data.lnAddress);
            if (!lnurlData || !lnurlData.callback) {
                setStatus('Lightning Address konnte nicht aufgelöst werden.', false);
                $btn.prop('disabled', false).html('<i class="fas fa-bolt"></i> Zap senden');
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
                $btn.prop('disabled', false).html('<i class="fas fa-bolt"></i> Zap senden');
                return;
            }

            var invoice = null;
            var invoiceResp = null;

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
                            ['relays'].concat(relayList()),
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
                        invoiceResp = await fetch(zapUrl).then(function (r) { return r.json(); });

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
                invoiceResp = await fetch(lnurlData.callback + sep + 'amount=' + amountMsats).then(function (r) { return r.json(); });
                if (invoiceResp.pr) {
                    invoice = invoiceResp.pr;
                }
            }

            if (!invoice) {
                setStatus('Keine Invoice erhalten.', false);
                $btn.prop('disabled', false).html('<i class="fas fa-bolt"></i> Zap senden');
                return;
            }

            // The invoice comes from the vendor's own LNURL server. It has to
            // ask for exactly what the dialog showed, otherwise the wallet
            // would be handed a different amount than the user agreed to.
            var invoiceMsats = bolt11AmountMsats(invoice);

            if (invoiceMsats === null) {
                setStatus('Invoice-Betrag konnte nicht geprüft werden.', false);
                $btn.prop('disabled', false).html('<i class="fas fa-bolt"></i> Zap senden');
                return;
            }

            if (invoiceMsats !== amountMsats) {
                setStatus('Invoice lautet über ' + Math.round(invoiceMsats / 1000) +
                          ' Sats statt ' + amountSats + '. Abgebrochen.', false);
                $btn.prop('disabled', false).html('<i class="fas fa-bolt"></i> Zap senden');
                return;
            }

            // Step 4: Pay the invoice.
            // Signed by the extension, paid by the connected wallet.
            if (!window.webln && defaults.hasNwc) {
                await payViaNwc(data, amountSats, invoice);
                return;
            }

            // Try WebLN first (Alby Hub exposes window.webln).
            if (window.webln) {
                try {
                    setStatus('<i class="fas fa-spinner fa-spin"></i> Zahlung wird gesendet...', null);
                    await window.webln.enable();
                    await window.webln.sendPayment(invoice);
                    setStatus('<i class="fas fa-bolt"></i> Zap gesendet!', true);
                    zapConfirmed(data, amountSats, invoiceResp && invoiceResp.payment_hash, null);
                    setTimeout(function () { $('#sk-zap-modal').remove(); }, 2000);
                    return;
                } catch (weblnErr) {
                    console.warn('[SK Zaps] WebLN payment failed:', weblnErr.message);
                }
            }

            // Fallback: show invoice as QR + deeplink.
            showInvoiceFallback(invoice, amountSats);

            // Poll for payment confirmation.
            if (invoiceResp && invoiceResp.payment_hash) {
                // Our LNURL-Pay endpoint — poll via lookup_invoice on vendor's wallet.
                pollPaymentStatus(data, amountSats, invoiceResp.payment_hash);
            } else if (data.nostrPubkey && lnurlData.allowsNostr) {
                // External LN address — watch for Nostr Zap Receipt.
                watchForZapReceipt(data, amountSats);
            }

        } catch (err) {
            console.error('[SK Zaps] Error:', err);
            setStatus('Fehler: ' + escHtml(err && err.message ? err.message : ''), false);
            $btn.prop('disabled', false).html('<i class="fas fa-bolt"></i> Zap senden');
        }
    }

    /**
     * Pay through the viewer's NWC connection stored on the server. With an
     * invoice only the payment happens there; without one the server builds
     * the zap itself. Errors surface in sendZap's catch.
     */
    function payViaNwc(data, amountSats, invoice) {
        setStatus('<i class="fas fa-spinner fa-spin"></i> Zahlung über deine verbundene Wallet...', null);

        return new Promise(function (resolve, reject) {
            $.post(ajaxUrl(), {
                action: 'sk_zap_pay_nwc',
                nonce: defaults.nwcNonce,
                vendor_id: data.vendorId,
                amount_sats: amountSats,
                invoice: invoice || ''
            }).done(function (res) {
                if (!res || !res.success) {
                    reject(new Error(res && res.data && res.data.message ? res.data.message : 'Zahlung fehlgeschlagen.'));
                    return;
                }
                setStatus('<i class="fas fa-bolt"></i> Zap gesendet!', true);
                zapConfirmed(data, amountSats, res.data.own ? res.data.payment_hash : null, null);
                setTimeout(function () { $('#sk-zap-modal').remove(); }, 2000);
                resolve();
            }).fail(function () {
                reject(new Error('Verbindungsfehler.'));
            });
        });
    }

    /**
     * Fetch lud16 (Lightning Address) from a Nostr profile via relay.
     */
    async function fetchLud16FromNostr(pubkeyHex) {
        var pubkey = nostr ? nostr.hexKey(pubkeyHex) : '';
        if (!pubkey) { return ''; }

        // The newest signed profile of the vendor from the site's relays: a
        // forged kind 0 must not redirect the payment to someone else's address.
        try {
            var result = await nostr.query([{ kinds: [0], authors: [pubkey], limit: 1 }], { relays: relayList(), timeout: 5000 });
            var latest = nostr.latestPerAuthor(result.events)[pubkey];
            if (!latest) { return ''; }
            var profile = JSON.parse(latest.content);
            return typeof profile.lud16 === 'string' ? profile.lud16 : '';
        } catch (e) {
            return '';
        }
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
        html += '<p style="color:#e8ecf0;font-size:14px;margin-bottom:8px;">' + parseInt(amountSats, 10) + ' Sats</p>';
        // Placeholder — filled by loadZapQr() with a server-rendered image, so
        // the invoice never reaches a third-party QR service.
        html += '<div id="sk-zap-qr" style="min-height:180px;display:flex;align-items:center;justify-content:center;color:#5a6a7e;font-size:12px;">QR wird erzeugt…</div>';
        html += '<div style="margin-top:10px;display:flex;gap:6px;justify-content:center;">';
        // The value goes into a data attribute, not into an inline onclick: there
        // the browser decodes HTML entities before JavaScript parses the string,
        // so an escaped quote would still break out into JS context.
        html += '<button type="button" class="sk-zap-close sk-zap-copy" data-copy="' + escAttr(invoice) + '">Invoice kopieren</button>';
        html += '<a href="lightning:' + encodeURIComponent(invoice) + '" class="sk-zap-send" style="text-align:center;text-decoration:none;display:block;">In Wallet öffnen</a>';
        html += '</div></div>';

        $('#sk-zap-send').hide();
        $('.sk-zap-amounts, .sk-zap-custom').hide();
        $('#sk-zap-status').html(html);

        loadZapQr(invoice);
    }

    // Copy handler for the invoice fallback.
    $(document).on('click', '.sk-zap-copy', function () {
        var text = $(this).data('copy');
        if (!text || !navigator.clipboard) return;

        var $btn = $(this);
        navigator.clipboard.writeText(String(text)).then(function () {
            $btn.text('Kopiert!');
            setTimeout(function () { $btn.text('Invoice kopieren'); }, 2000);
        });
    });

    /**
     * Fetch the QR image for an invoice from our own REST endpoint.
     */
    function loadZapQr(invoice) {
        var $target = $('#sk-zap-qr');
        if (!$target.length || !defaults.qrUrl) {
            $target.text('QR nicht verfügbar — bitte Invoice kopieren.');
            return;
        }

        $.getJSON(defaults.qrUrl, { data: invoice })
            .done(function (res) {
                if (res && /^data:image\/png;base64,[A-Za-z0-9+/=]+$/.test(String(res.qr || ''))) {
                    $target.html('<img src="' + escAttr(res.qr) + '" alt="QR Code" ' +
                        'style="max-width:180px;width:100%;border-radius:8px;background:#fff;padding:6px;display:block;" />');
                } else {
                    $target.text('QR nicht verfügbar — bitte Invoice kopieren.');
                }
            })
            .fail(function () {
                $target.text('QR nicht verfügbar — bitte Invoice kopieren.');
            });
    }

    function setStatus(html, success) {
        var color = success === true ? '#5cb85c' : (success === false ? '#e06c75' : '#5a6a7e');
        $('#sk-zap-status').html('<span style="color:' + color + ';">' + html + '</span>');
    }

    /**
     * Amount in millisats from a bolt11 invoice, read out of the human readable
     * part (lnbc<amount><multiplier>1...). Returns null when the invoice has no
     * amount or cannot be read.
     */
    function bolt11AmountMsats(invoice) {
        var m = /^lnbc(\d+)([munp]?)1/i.exec(String(invoice || '').trim());

        if (!m) {
            return null;
        }

        var value = parseInt(m[1], 10);

        if (!isFinite(value)) {
            return null;
        }

        switch (m[2].toLowerCase()) {
            case 'm': return value * 100000000;   // milli-BTC
            case 'u': return value * 100000;      // micro-BTC
            case 'n': return value * 100;         // nano-BTC
            case 'p': return value / 10;          // pico-BTC
            default:  return value * 100000000000; // whole BTC
        }
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
     * Poll server to check if invoice was paid (for vendors with LNDHub/NWC).
     */
    function pollPaymentStatus(data, amountSats, paymentHash) {
        var attempts = 0;
        var maxAttempts = 30; // 30 × 3s = 90s
        var confirmed = false;

        var interval = setInterval(function () {
            if (confirmed || attempts >= maxAttempts || !$('#sk-zap-modal').length) {
                clearInterval(interval);
                return;
            }
            attempts++;

            $.post(ajaxUrl(), {
                action: 'sk_zap_check_payment',
                vendor_id: data.vendorId,
                payment_hash: paymentHash
            }, function (res) {
                if (res.success && res.data && res.data.settled) {
                    confirmed = true;
                    clearInterval(interval);
                    zapConfirmed(data, amountSats, paymentHash, null);
                    setStatus('<i class="fas fa-bolt"></i> Zap bestätigt! ' + amountSats + ' Sats', true);
                    setTimeout(function () { $('#sk-zap-modal').remove(); }, 2500);
                }
            });
        }, 3000);
    }

    /**
     * Watch Nostr relays for a Kind 9735 Zap Receipt confirming payment.
     */
    function watchForZapReceipt(data, amountSats) {
        if (!nostr) { return; }

        var vendor = nostr.hexKey(data.nostrPubkey);
        if (!vendor) { return; }

        var since = Math.floor(Date.now() / 1000) - 5; // small buffer
        var confirmed = false;
        var subscription = null;

        function cleanup() {
            if (subscription) { subscription.close(); subscription = null; }
        }

        function onReceipt(receiptEvent) {
            if (confirmed) return;
            // Only a receipt that names this vendor; the signature was checked by sk-nostr.
            if (receiptEvent.kind !== 9735 || nostr.tagValues(receiptEvent, 'p').indexOf(vendor) === -1) return;
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

            // No payment hash on this path (external LN address): the receipt
            // itself is the proof, and the server checks it before counting.
            zapConfirmed(data, amountFromReceipt, null, receiptEvent);

            // Update UI
            setStatus('<i class="fas fa-bolt"></i> Zap bestätigt! ' + amountFromReceipt + ' Sats', true);
            setTimeout(function () { $('#sk-zap-modal').remove(); }, 2500);

            cleanup();
        }

        // Kind 9735 receipts naming the vendor, live from every relay, verified;
        // the subscription closes itself after 90 seconds.
        subscription = nostr.subscribe(
            [{ kinds: [9735], '#p': [vendor], since: since, limit: 5 }],
            onReceipt,
            { relays: relayList(), timeout: 90000 }
        );
    }

    // Only zaps we can prove server-side are counted. Without a payment hash
    // the backend has nothing to look up, so we do not even ask.
    function trackZap(data, amountSats, paymentHash) {
        if (!data.postId || !paymentHash || typeof skFeed === 'undefined') return;

        $.post(skFeed.ajaxurl, {
            action: 'sk_feed_track_zap',
            _nonce: skFeed.nonce,
            post_id: data.postId,
            payment_hash: paymentHash
        }, function (res) {
            if (res.success && data.$btn) {
                // Same shape as the server renders: sats with a thousands dot.
                var formatted = String(parseInt(res.data.total, 10) || 0).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                var $span = data.$btn.find('.sk-zap-total');
                if ($span.length) {
                    $span.text(formatted);
                } else {
                    data.$btn.html('<i class="fas fa-bolt"></i> <span class="sk-zap-total">' + formatted + '</span>');
                }
            }
        });
    }

})(jQuery);
