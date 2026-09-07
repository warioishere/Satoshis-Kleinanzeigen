/**
 * SK Nostr Market — open messages and seal replies that only the vendor can.
 *
 * A vendor who logs in through an extension gives us only the public key.
 * We fetch messages to that mailbox from the relays but cannot open them.
 * That happens here, in the vendor's browser, with the extension; the
 * private key stays where it is.
 *
 * Inbound: a gift wrap has two layers: the outer wrap with a throwaway key,
 * inside it the seal with the real sender, inside that the message. Both
 * layers are decrypted separately. The seal goes to the server, which
 * verifies its signature, so the server knows who wrote without trusting
 * the browser.
 *
 * Outbound: the vendor's chat replies are encrypted here for the recipient
 * and signed as a seal. The server adds the wrap with a throwaway key, which
 * needs no vendor key.
 */
(function ($) {
    'use strict';

    var CFG = window.skNostrInbox || {};

    if (!window.nostr || !window.nostr.nip44 || !CFG.ajaxurl) {
        // Without an extension or without NIP-44 everything stays queued and
        // is retried on the next visit.
        return;
    }

    var TWO_DAYS = 2 * 24 * 60 * 60;

    /** Wraps opened per page load; each one prompts the extension twice. */
    var MAX_PER_VISIT = 10;

    /** An error we raised ourselves: the wrap is broken, not the extension. */
    function broken(message) {
        var err = new Error(message);
        err.broken = true;
        return err;
    }

    function post(data) {
        data.nonce = CFG.nonce;
        return $.post(CFG.ajaxurl, data);
    }

    /** One after another, never in parallel: every step prompts the extension. */
    function sequence(list, fn) {
        return list.reduce(function (chain, entry) {
            return chain.then(function () { return fn(entry); });
        }, Promise.resolve());
    }

    // ── Inbound ────────────────────────────────────────────────────────────

    post({ action: 'sk_nostr_pending_wraps' })
        .done(function (res) {
            if (!res || !res.success || !res.data || !res.data.wraps) {
                return;
            }

            sequence(res.data.wraps.slice(0, MAX_PER_VISIT), open).then(sealReplies);
        })
        .fail(sealReplies);

    function parse(raw, what) {
        try {
            return JSON.parse(raw);
        } catch (e) {
            throw broken(what + ' is not JSON.');
        }
    }

    function open(wrap) {
        if (Number(wrap.kind) !== 1059) {
            return drop(wrap.id);
        }

        // Layer 1: against the throwaway key of the wrap.
        return window.nostr.nip44.decrypt(wrap.pubkey, wrap.content)
            .then(function (raw) {
                var seal = parse(raw, 'Seal');

                if (!seal || Number(seal.kind) !== 13 || !seal.pubkey) {
                    throw broken('Not a valid seal.');
                }

                // Layer 2: against the real sender.
                return window.nostr.nip44.decrypt(seal.pubkey, seal.content)
                    .then(function (rawInner) {
                        var message = parse(rawInner, 'Message');

                        // Only NIP-17 text messages become chat text.
                        if (!message || Number(message.kind) !== 14) {
                            throw broken('Not a NIP-17 message.');
                        }

                        /*
                         * The seal proves the sender; the innermost layer is
                         * unsigned. If they differ, somebody planted a foreign
                         * message.
                         */
                        if (message.pubkey && message.pubkey.toLowerCase() !== seal.pubkey.toLowerCase()) {
                            throw broken('Sender in seal and message differ.');
                        }

                        return post({
                            action: 'sk_nostr_deliver_decrypted',
                            event_id: wrap.id,
                            seal: JSON.stringify(seal),
                            text: message.content || ''
                        });
                    });
            })
            .catch(function (err) {
                console.warn('[SK Nostr] Could not open message:', err && err.message);

                /*
                 * A broken wrap is dropped once, otherwise it hangs on the
                 * extension on every page load. Anything else — the vendor
                 * declined the prompt, the extension is locked or failed —
                 * stays queued and is tried again on the next visit; dropping
                 * it there lost the message for good.
                 */
                if (err && err.broken) {
                    return drop(wrap.id);
                }
            });
    }

    function drop(id) {
        return post({ action: 'sk_nostr_drop_wrap', event_id: id });
    }

    // ── Outbound ───────────────────────────────────────────────────────────

    function sealReplies() {
        return post({ action: 'sk_nostr_pending_replies' })
            .then(function (res) {
                if (!res || !res.success || !res.data || !res.data.replies || !res.data.replies.length) {
                    return;
                }

                return window.nostr.getPublicKey().then(function (pubkey) {
                    return sequence(res.data.replies, function (reply) {
                        return seal(reply, pubkey);
                    });
                });
            });
    }

    /**
     * NIP-17: the message (kind 14) stays unsigned, only its id is computed.
     * Encrypted for the recipient it becomes the content of the seal (kind
     * 13), which the extension signs.
     */
    function seal(reply, pubkey) {
        var now = Math.floor(Date.now() / 1000);

        var rumor = {
            pubkey: pubkey,
            created_at: now,
            kind: 14,
            tags: [['p', reply.to]],
            content: reply.text || ''
        };

        return eventId(rumor)
            .then(function (id) {
                rumor.id = id;
                return window.nostr.nip44.encrypt(reply.to, JSON.stringify(rumor));
            })
            .then(function (encrypted) {
                return window.nostr.signEvent({
                    kind: 13,
                    // Randomised timestamp, as NIP-59 asks for.
                    created_at: now - Math.floor(Math.random() * TWO_DAYS),
                    tags: [],
                    content: encrypted
                });
            })
            .then(function (signed) {
                if (!signed || !signed.sig) {
                    throw new Error('The extension returned nothing.');
                }

                return post({
                    action: 'sk_nostr_deliver_sealed',
                    reply_id: reply.id,
                    seal: JSON.stringify(signed)
                });
            })
            .catch(function (err) {
                // Stays queued and is retried on the next visit. The reply is
                // in the chat anyway; only Nostr is still missing it.
                console.warn('[SK Nostr] Could not seal reply:', err && err.message);
            });
    }

    /** Event id per NIP-01: SHA-256 over the canonical form. */
    function eventId(ev) {
        var bytes = new TextEncoder().encode(JSON.stringify([0, ev.pubkey, ev.created_at, ev.kind, ev.tags, ev.content]));

        return crypto.subtle.digest('SHA-256', bytes).then(function (hash) {
            return Array.prototype.map.call(new Uint8Array(hash), function (b) {
                return ('0' + b.toString(16)).slice(-2);
            }).join('');
        });
    }

})(jQuery);
