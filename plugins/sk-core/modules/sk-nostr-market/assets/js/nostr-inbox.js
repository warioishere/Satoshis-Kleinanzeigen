/**
 * SK Nostr Market — Nachrichten oeffnen, die nur der Anbieter oeffnen kann.
 *
 * Wer sich ueber eine Erweiterung anmeldet, gibt uns nur seinen oeffentlichen
 * Schluessel. Nachrichten an sein Postfach holen wir vom Relay, oeffnen
 * koennen wir sie nicht. Das passiert hier, in seinem Browser, mit seiner
 * Erweiterung — der private Schluessel bleibt, wo er ist.
 *
 * Ein Gift Wrap hat zwei Schichten: aussen ein Umschlag mit einem
 * Wegwerfschluessel, darin ein Siegel mit dem echten Absender, darin die
 * Nachricht. Beide Schichten werden einzeln entschluesselt.
 */
(function ($) {
    'use strict';

    var CFG = window.skNostrInbox || {};

    if (!window.nostr || !window.nostr.nip44 || !CFG.ajaxurl) {
        // Ohne Erweiterung oder ohne NIP-44 bleibt alles vorgemerkt liegen und
        // wird beim naechsten Besuch erneut versucht.
        return;
    }

    $.post(CFG.ajaxurl, { action: 'sk_nostr_pending_wraps', nonce: CFG.nonce })
        .done(function (res) {
            if (!res || !res.success || !res.data || !res.data.wraps) {
                return;
            }

            res.data.wraps.reduce(function (kette, wrap) {
                return kette.then(function () { return open(wrap); });
            }, Promise.resolve());
        });

    function open(wrap) {
        if (Number(wrap.kind) !== 1059) {
            return drop(wrap.id);
        }

        // Schicht 1: gegen den Wegwerfschluessel des Umschlags.
        return window.nostr.nip44.decrypt(wrap.pubkey, wrap.content)
            .then(function (roh) {
                var siegel = JSON.parse(roh);

                if (!siegel || Number(siegel.kind) !== 13 || !siegel.pubkey) {
                    throw new Error('Kein gueltiges Siegel.');
                }

                // Schicht 2: gegen den echten Absender.
                return window.nostr.nip44.decrypt(siegel.pubkey, siegel.content)
                    .then(function (rohInnen) {
                        var nachricht = JSON.parse(rohInnen);

                        /*
                         * Das Siegel beweist den Absender, die innerste Schicht
                         * ist nicht signiert. Weichen sie ab, hat jemand eine
                         * fremde Nachricht untergeschoben.
                         */
                        if (nachricht.pubkey && nachricht.pubkey.toLowerCase() !== siegel.pubkey.toLowerCase()) {
                            throw new Error('Absender im Siegel und in der Nachricht weichen ab.');
                        }

                        return $.post(CFG.ajaxurl, {
                            action: 'sk_nostr_deliver_decrypted',
                            nonce: CFG.nonce,
                            event_id: wrap.id,
                            sender: siegel.pubkey,
                            text: nachricht.content || ''
                        });
                    });
            })
            .catch(function (err) {
                // Nicht fuer uns oder kaputt: einmal verwerfen, sonst haengt es
                // bei jedem Seitenaufruf erneut an der Erweiterung.
                console.warn('[SK Nostr] Nachricht liess sich nicht oeffnen:', err && err.message);
                return drop(wrap.id);
            });
    }

    function drop(id) {
        return $.post(CFG.ajaxurl, {
            action: 'sk_nostr_drop_wrap',
            nonce: CFG.nonce,
            event_id: id
        });
    }

})(jQuery);
