/**
 * SK Nostr Market — signing with the vendor's own Nostr extension.
 *
 * Vendors who log in through an extension give us only their public key.
 * Nobody but them can sign their listing, and nobody but them can withdraw
 * it: both happen here, right after the page loads, one item at a time.
 *
 * Two queues arrive from the server: listings waiting to be published
 * (pendingSign, kind 30402) and withdrawals waiting to be sent
 * (pendingDelete, kind 5). A visible modal explains what the extension is
 * about to ask, and the vendor can always get out of it.
 */
(function ($) {
    'use strict';

    var SKN = window.skNostrMarket || {};

    var items = [];

    (SKN.pendingSign || []).forEach(function (p) {
        items.push({ type: 'sign', data: p });
    });

    (SKN.pendingDelete || []).forEach(function (d) {
        items.push({ type: 'delete', data: d });
    });

    if (!items.length) {
        return;
    }

    var modal = document.getElementById('sk-nostr-sign-modal');

    if (!modal) {
        return;
    }

    var icon      = modal.querySelector('.sk-nostr-sign-icon i');
    var heading   = modal.querySelector('.sk-nostr-sign-heading');
    var titleEl   = modal.querySelector('.sk-nostr-sign-title');
    var textEl    = modal.querySelector('.sk-nostr-sign-text');
    var retryBtn  = modal.querySelector('.sk-nostr-sign-retry');
    var cancelBtn = modal.querySelector('.sk-nostr-sign-cancel');

    var index   = 0;
    var current = null;

    function setState(state, opts) {
        modal.setAttribute('data-state', state);
        icon.className = opts.icon;
        heading.textContent = opts.heading;
        textEl.textContent = opts.text;
        retryBtn.style.display = opts.retry ? '' : 'none';
        cancelBtn.textContent = opts.cancelLabel;
    }

    function close() {
        modal.style.display = 'none';
    }

    /** Take the next waiting item, or close. */
    function next() {
        if (index >= items.length) {
            close();
            return;
        }

        current = items[index];
        index += 1;

        titleEl.textContent = current.data.title || '';
        modal.style.display = 'flex';

        attempt();
    }

    function isDelete() {
        return current && current.type === 'delete';
    }

    /**
     * Extension there? Then ask for the signature, otherwise explain.
     *
     * Nothing is published under our key as a stand-in: a listing carries
     * its vendor's name, or it does not go to Nostr.
     */
    function attempt() {
        if (!window.nostr) {
            setState('missing', {
                icon: 'fas fa-triangle-exclamation',
                heading: 'Keine Nostr-Erweiterung gefunden',
                text: isDelete()
                    ? 'Dein Inserat ist hier entfernt, auf Nostr steht es noch. Zum Zurückziehen brauchen wir deine Unterschrift. Entsperre deine Erweiterung und versuch es erneut.'
                    : 'Dein Inserat ist gespeichert und auf der Plattform sichtbar. Für Nostr brauchen wir deine Unterschrift — dein Inserat soll deinen Namen tragen, nicht unseren. Entsperre deine Erweiterung und versuch es erneut.',
                retry: true,
                cancelLabel: isDelete() ? 'Auf Nostr lassen' : 'Nicht auf Nostr'
            });
            retryBtn.textContent = 'Erneut versuchen';
            return;
        }

        sign();
    }

    function sign() {
        setState('waiting', {
            icon: 'fas fa-circle-notch',
            heading: isDelete() ? 'Warten auf deine Signatur zum Zurückziehen' : 'Warten auf deine Signatur',
            text: isDelete()
                ? 'Deine Nostr-Erweiterung fragt gleich nach deiner Unterschrift. Danach wird dein Inserat aus dem Nostr-Netz zurückgezogen.'
                : 'Deine Nostr-Erweiterung fragt gleich nach deiner Unterschrift. Danach geht dein Inserat unter deinem eigenen Schlüssel ins Nostr-Netz.',
            retry: false,
            cancelLabel: 'Abbrechen'
        });

        var event = isDelete()
            ? {
                kind: 5,
                created_at: Math.floor(Date.now() / 1000),
                content: '',
                tags: current.data.tags
            }
            : {
                kind: 30402,
                created_at: Math.floor(Date.now() / 1000),
                content: current.data.content,
                tags: current.data.tags
            };

        window.nostr.signEvent(event).then(function (signed) {
            if (!signed || !signed.id) {
                throw new Error('Die Erweiterung hat nichts zurückgegeben.');
            }

            var post = {
                action: isDelete() ? 'sk_nostr_market_publish_signed_delete' : 'sk_nostr_market_publish_signed',
                nonce: SKN.nonce,
                signed_event: JSON.stringify(signed)
            };

            if (!isDelete()) {
                post.post_id = current.data.post_id;
            }

            return $.post(SKN.ajaxurl, post);
        }).then(function (res) {
            if (res && res.success) {
                setState('done', {
                    icon: 'fas fa-circle-check',
                    heading: isDelete() ? 'Zurückgezogen' : 'Signiert und veröffentlicht',
                    text: isDelete()
                        ? 'Dein Inserat ist aus dem Nostr-Netz zurückgezogen.'
                        : 'Dein Inserat ist unter deinem eigenen Schlüssel im Nostr-Netz.',
                    retry: false,
                    cancelLabel: 'Schliessen'
                });
                window.setTimeout(next, 1500);
                return;
            }

            fail((res && res.data && res.data.message) || 'Unbekannter Fehler.');
        }).catch(function (err) {
            fail(err && err.message ? err.message : 'Die Signierung wurde abgebrochen.');
        });
    }

    function fail(reason) {
        setState('error', {
            icon: 'fas fa-triangle-exclamation',
            heading: 'Signieren fehlgeschlagen',
            text: reason + (isDelete()
                ? ' Auf Nostr steht das Inserat noch.'
                : ' Dein Inserat ist gespeichert und auf der Plattform sichtbar — nur auf Nostr ist es noch nicht.'),
            retry: true,
            cancelLabel: 'Abbrechen'
        });
        retryBtn.textContent = 'Erneut versuchen';
    }

    /** Drop the queue entry so the next page load does not ask again. */
    function forget(then) {
        var post = { nonce: SKN.nonce };

        if (isDelete()) {
            post.action = 'sk_nostr_market_cancel_delete';
            post.event_id = current.data.event_id;
        } else {
            post.action = 'sk_nostr_market_cancel_sign';
            post.post_id = current.data.post_id;
        }

        $.post(SKN.ajaxurl, post).always(then);
    }

    retryBtn.addEventListener('click', attempt);

    cancelBtn.addEventListener('click', function () {
        // Always reachable, also while waiting: if the extension hangs, this
        // is the only way out.
        forget(next);
    });

    next();

})(jQuery);
