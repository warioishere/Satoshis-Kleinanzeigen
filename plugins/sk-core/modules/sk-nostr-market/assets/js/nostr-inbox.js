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
 * the browser. A kind 4 (NIP-04) is one layer; the server verifies the
 * event it stored itself.
 *
 * Outbound: the vendor's chat replies are encrypted here for the recipient
 * and signed as a seal. The server adds the wrap with a throwaway key, which
 * needs no vendor key.
 *
 * Nothing is attempted while the extension is logged into a key other than
 * the one this account is linked to: opening would fail, and a seal signed
 * with the wrong key is refused by the server anyway.
 */
(function ($) {
    'use strict';

    var CFG = window.skNostrInbox || {};

    if (!window.nostr || !window.nostr.nip44 || !CFG.ajaxurl) {
        // Without an extension or without NIP-44 everything stays queued and
        // is retried on the next visit.
        return;
    }

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

    /** A one-line notice at the top of the page, shown once. */
    var noticeShown = false;

    function notice(text) {
        if (noticeShown || !text) {
            return;
        }

        noticeShown = true;

        var el = document.createElement('div');
        el.className = 'sk-nostr-inbox-notice';
        el.setAttribute('role', 'status');
        el.style.cssText = 'position:fixed;left:0;right:0;top:0;z-index:99999;padding:10px 16px;background:#7c2d12;color:#fff;font:14px/1.4 system-ui,sans-serif;text-align:center;';
        el.textContent = text;
        document.body.appendChild(el);
    }

    // ── Which key is the extension using? ──────────────────────────────────

    /** The extension's key, asked for once and kept; null when it is the wrong one. */
    var identity = null;

    function withIdentity(fn) {
        if (!identity) {
            identity = window.nostr.getPublicKey().then(function (pubkey) {
                if (CFG.pubkey && pubkey && String(pubkey).toLowerCase() !== CFG.pubkey) {
                    notice(CFG.i18nWrongKey);
                    return null;
                }

                return pubkey;
            });
        }

        return identity.then(function (pubkey) {
            return pubkey ? fn(pubkey) : undefined;
        }).catch(function (err) {
            console.warn('[SK Nostr] Extension gave no key:', err && err.message);
        });
    }

    /** One run at a time: every step prompts the extension. */
    var running = Promise.resolve();

    function run(fn) {
        running = running.then(function () { return withIdentity(fn); });
        return running;
    }

    /** The chat page: the one place where prompting on load is expected. */
    function onChatPage() {
        return document.getElementById('dvc-active-list') !== null || /vendor-chat/.test(window.location.pathname);
    }

    // On load, and only on the chat page: whatever waited from earlier —
    // messages to open, replies to seal. Elsewhere nothing prompts.
    if (CFG.hasPending && onChatPage()) {
        run(function (pubkey) {
            return openInbox().then(function () { return sealReplies(pubkey); });
        });
    }

    // A message just sent in the chat: its mirror is queued by now, seal it
    // here and now instead of on some later page load.
    $(document).on('sk:chat-sent', function () {
        run(function (pubkey) {
            return sealReplies(pubkey);
        });
    });

    // ── Inbound ────────────────────────────────────────────────────────────

    function openInbox() {
        return post({ action: 'sk_nostr_pending_wraps' })
            .then(function (res) {
                if (!res || !res.success || !res.data || !res.data.wraps) {
                    return;
                }

                return sequence(res.data.wraps.slice(0, MAX_PER_VISIT), open);
            }, function () {
                // The list could not be fetched; nothing to open.
            });
    }

    function parse(raw, what) {
        try {
            return JSON.parse(raw);
        } catch (e) {
            throw broken(what + ' is not JSON.');
        }
    }

    /**
     * Hand the plaintext to the server. It answers with a code when it
     * refuses: "invalid" means the message is not what it claims to be and
     * is dropped; anything else keeps it queued for another try.
     */
    function deliver(id, seal, text, tags) {
        return post({
            action: 'sk_nostr_deliver_decrypted',
            event_id: id,
            seal: JSON.stringify(seal),
            text: text || '',
            // A reply names the message it answers; the server routes it
            // into that chat.
            tags: JSON.stringify(Array.isArray(tags) ? tags : [])
        }).then(function (res) {
            if (res && !res.success && res.data && res.data.code === 'invalid') {
                return drop(id);
            }
        });
    }

    function open(wrap) {
        var kind = Number(wrap.kind);

        if (kind === 4) {
            // NIP-04: one layer, encrypted between sender and us. The server
            // verifies the event it stored; only the plaintext goes back.
            if (!window.nostr.nip04) {
                // This extension cannot open it, and no visit here ever will.
                return drop(wrap.id);
            }

            return window.nostr.nip04.decrypt(wrap.pubkey, wrap.content)
                .then(function (text) {
                    return deliver(wrap.id, {}, text, []);
                })
                .catch(function (err) {
                    console.warn('[SK Nostr] Could not open message:', err && err.message);
                });
        }

        if (kind !== 1059) {
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

                        return deliver(wrap.id, seal, message.content, message.tags);
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

    function sealReplies(pubkey) {
        return post({ action: 'sk_nostr_pending_replies' })
            .then(function (res) {
                if (!res || !res.success || !res.data || !res.data.replies || !res.data.replies.length) {
                    return;
                }

                var stop = false;

                return sequence(res.data.replies, function (reply) {
                    if (stop) {
                        return;
                    }

                    return seal(reply, pubkey).then(function (outcome) {
                        // The server refused the key: every further seal
                        // would be refused the same way. Stop prompting.
                        if (outcome === 'wrong_key') {
                            stop = true;
                            notice(CFG.i18nWrongKey);
                        }
                    });
                });
            });
    }

    /**
     * Encrypt the message for one key and have the extension sign the seal
     * (kind 13).
     *
     * The seal carries the real time. NIP-59 suggests backdating it, but the
     * seal is only ever seen by whoever can open the wrap, and they learn
     * the time from the message anyway; the relays see the wrap alone. A
     * backdated event, on the other hand, is exactly what a signer refuses
     * to approve without asking — Alby prompted for every seal despite a
     * standing "always allow".
     */
    function sealFor(recipient, rumorJson, now) {
        return window.nostr.nip44.encrypt(recipient, rumorJson)
            .then(function (encrypted) {
                return window.nostr.signEvent({
                    kind: 13,
                    created_at: now,
                    tags: [],
                    content: encrypted
                });
            })
            .then(function (signed) {
                if (!signed || !signed.sig) {
                    throw new Error('The extension returned nothing.');
                }

                return signed;
            });
    }

    /**
     * NIP-17: the message (kind 14) stays unsigned, only its id is computed.
     * Encrypted for the recipient it becomes the content of the seal (kind
     * 13), which the extension signs.
     *
     * A second seal goes to the sender's own key: that copy is what their
     * own client shows as the sent message — without it the conversation
     * in Amethyst has the replies and none of the questions.
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

        var rumorJson = '';
        var forRecipient = null;

        return eventId(rumor)
            .then(function (id) {
                rumor.id = id;
                rumorJson = JSON.stringify(rumor);
                return sealFor(reply.to, rumorJson, now);
            })
            .then(function (signed) {
                forRecipient = signed;

                // The copy for ourselves; if the extension declines it, the
                // message still goes to the recipient.
                return sealFor(pubkey, rumorJson, now).catch(function (err) {
                    console.warn('[SK Nostr] No copy for own inbox:', err && err.message);
                    return null;
                });
            })
            .then(function (forSelf) {
                return post({
                    action: 'sk_nostr_deliver_sealed',
                    reply_id: reply.id,
                    seal: JSON.stringify(forRecipient),
                    self_seal: forSelf ? JSON.stringify(forSelf) : '',
                    // So a reply naming this message finds its chat again.
                    rumor_id: rumor.id
                });
            })
            .then(function (res) {
                return res && !res.success && res.data ? res.data.code : '';
            })
            .catch(function (err) {
                // Stays queued and is retried on the next visit. The reply is
                // in the chat anyway; only Nostr is still missing it.
                console.warn('[SK Nostr] Could not seal reply:', err && err.message);
                return '';
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
