/**
 * SK Nostr Market — Unterschreiben mit der eigenen Nostr-Erweiterung.
 *
 * Anbieter, die sich ueber eine Erweiterung anmelden, geben uns nur ihren
 * oeffentlichen Schluessel. Ihr Inserat kann deshalb niemand ausser ihnen
 * selbst signieren — das passiert hier, direkt nach dem Speichern.
 *
 * Frueher lief das lautlos im Hintergrund und meldete sich nur auf der
 * Entwicklerkonsole. Wer nicht wusste, dass er auf ein Fenster seiner
 * Erweiterung warten sollte, verstand nicht, was gerade geschieht. Deshalb
 * jetzt ein sichtbares Wartefenster, aus dem man jederzeit wieder herauskommt.
 */
(function ($) {
    'use strict';

    var SKN = window.skNostrMarket || {};

    if (!SKN.pendingSign || !SKN.pendingSign.length) {
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

    /** Das naechste wartende Inserat vornehmen, sonst schliessen. */
    function next() {
        if (index >= SKN.pendingSign.length) {
            close();
            return;
        }

        current = SKN.pendingSign[index];
        index += 1;

        titleEl.textContent = current.title || '';
        modal.style.display = 'flex';

        attempt();
    }

    /**
     * Erweiterung da? Dann unterschreiben lassen, sonst erklaeren.
     *
     * Unter unserem Schluessel wird nicht mehr ersatzweise veroeffentlicht:
     * ein Inserat traegt den Namen seines Anbieters, oder es geht nicht auf
     * Nostr.
     */
    function attempt() {
        if (!window.nostr) {
            setState('missing', {
                icon: 'fas fa-triangle-exclamation',
                heading: 'Keine Nostr-Erweiterung gefunden',
                text: 'Dein Inserat ist gespeichert und auf der Plattform sichtbar. Für Nostr brauchen wir deine Unterschrift — dein Inserat soll deinen Namen tragen, nicht unseren. Entsperre deine Erweiterung und versuch es erneut.',
                retry: true,
                cancelLabel: 'Nicht auf Nostr'
            });
            retryBtn.textContent = 'Erneut versuchen';
            return;
        }

        sign();
    }

    function sign() {
        setState('waiting', {
            icon: 'fas fa-circle-notch',
            heading: 'Warten auf deine Signatur',
            text: 'Deine Nostr-Erweiterung fragt gleich nach deiner Unterschrift. Danach geht dein Inserat unter deinem eigenen Schlüssel ins Nostr-Netz.',
            retry: false,
            cancelLabel: 'Abbrechen'
        });

        var event = {
            kind: 30402,
            created_at: Math.floor(Date.now() / 1000),
            content: current.content,
            tags: current.tags
        };

        window.nostr.signEvent(event).then(function (signed) {
            if (!signed || !signed.id) {
                throw new Error('Die Erweiterung hat nichts zurückgegeben.');
            }

            return $.post(SKN.ajaxurl, {
                action: 'sk_nostr_market_publish_signed',
                nonce: SKN.nonce,
                post_id: current.post_id,
                signed_event: JSON.stringify(signed)
            });
        }).then(function (res) {
            if (res && res.success) {
                setState('done', {
                    icon: 'fas fa-circle-check',
                    heading: 'Signiert und veröffentlicht',
                    text: 'Dein Inserat ist unter deinem eigenen Schlüssel im Nostr-Netz.',
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

    function fail(grund) {
        setState('error', {
            icon: 'fas fa-triangle-exclamation',
            heading: 'Signieren fehlgeschlagen',
            text: grund + ' Dein Inserat ist gespeichert und auf der Plattform sichtbar — nur auf Nostr ist es noch nicht.',
            retry: true,
            cancelLabel: 'Abbrechen'
        });
        retryBtn.textContent = 'Erneut versuchen';
    }

    /** Vormerkung loeschen, damit nicht bei jedem Seitenaufruf erneut gefragt wird. */
    function forget(action, danach) {
        $.post(SKN.ajaxurl, {
            action: action,
            nonce: SKN.nonce,
            post_id: current.post_id
        }).always(danach);
    }

    retryBtn.addEventListener('click', attempt);

    cancelBtn.addEventListener('click', function () {
        // Immer erreichbar, auch waehrend gewartet wird: wenn die Erweiterung
        // haengt, ist das der einzige Weg hier heraus.
        forget('sk_nostr_market_cancel_sign', next);
    });

    next();

})(jQuery);
