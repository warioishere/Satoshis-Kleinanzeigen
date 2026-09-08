/**
 * Silent key binding in the vendor's dashboard.
 *
 * Loaded only while the vendor's claimed Nostr key has no proof. Asks the
 * extension for its public key; if it is the claimed one, asks for a single
 * signature over the binding event and hands it to the server. Nothing is
 * typed, nothing is shown. A different key, no extension, or a declined
 * prompt leaves everything as it is.
 *
 * Depends on sk-nostr.js (window.skNostr).
 */
(function () {
    'use strict';

    var cfg = window.skTrustBind;
    var nostr = window.skNostr;
    if (!cfg || !cfg.candidate || !cfg.template || !nostr) {
        return;
    }

    // A declined prompt is not asked again in this browser session; an
    // extension holding a different key than the typed npub is not asked
    // again for a day — that npub may simply belong to someone else.
    var DECLINED = 'skTrustBindDeclined';
    var MISMATCH = 'skTrustBindMismatch';
    var DAY = 24 * 60 * 60 * 1000;
    try {
        if (window.sessionStorage && sessionStorage.getItem(DECLINED) === cfg.candidate) {
            return;
        }
        var m = JSON.parse(localStorage.getItem(MISMATCH) || 'null');
        if (m && m.c === cfg.candidate && Date.now() - m.ts < DAY) {
            return;
        }
    } catch (e) { /* storage unavailable */ }

    function declined() {
        try { sessionStorage.setItem(DECLINED, cfg.candidate); } catch (e) { /* ignore */ }
    }

    function mismatch() {
        try { localStorage.setItem(MISMATCH, JSON.stringify({ c: cfg.candidate, ts: Date.now() })); } catch (e) { /* ignore */ }
    }

    async function bind(ext) {
        var pubkey;
        try {
            pubkey = nostr.hexKey(await ext.getPublicKey());
        } catch (e) {
            declined();
            return;
        }

        if (!pubkey || pubkey !== cfg.candidate) {
            mismatch();
            return;
        }

        var unsigned = {
            kind: cfg.template.kind,
            created_at: Math.floor(Date.now() / 1000),
            tags: cfg.template.tags,
            content: cfg.template.content,
            pubkey: pubkey
        };

        var signed;
        try {
            signed = await ext.signEvent(unsigned);
        } catch (e) {
            declined();
            return;
        }

        var body = new URLSearchParams();
        body.append('action', cfg.action);
        body.append('_ajax_nonce', cfg.nonce);
        body.append('event', JSON.stringify(signed));

        try {
            await fetch(cfg.ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            });
        } catch (e) { /* next visit tries again */ }
    }

    // The extension may inject window.nostr well after the page has loaded.
    nostr.waitForNostr(10000).then(function (ext) {
        if (ext) { bind(ext); }
    });
})();
