/**
 * sk-nostr: what every Nostr script in the plugin needs, once.
 *
 * Keys and ids are accepted only as 64 lowercase hex; maps keyed by them
 * have no prototype; relay answers count only with a valid signature, checked
 * by sk-nostr-verify.js, which is fetched on first use. A relay can withhold
 * events, not invent them.
 *
 * Exposes window.skNostr. Configuration comes from window.skNostrConfig
 * ({ relays: [...], verify: <url of the verifier bundle> }), localized by
 * the server.
 */
(function () {
    'use strict';

    var cfg = window.skNostrConfig || {};
    var HEX64 = /^[0-9a-f]{64}$/;
    var DEFAULT_TIMEOUT = 7000;

    // ── Untrusted input ──────────────────────────────────────────────────

    function isHex64(value) {
        return typeof value === 'string' && HEX64.test(value);
    }

    /** Lowercase hex key, or '' when the value is not one. */
    function hexKey(value) {
        var s = String(value || '').toLowerCase();
        return HEX64.test(s) ? s : '';
    }

    /** A map without a prototype: "constructor" and "__proto__" hit nothing. */
    function dict() {
        return Object.create(null);
    }

    function own(obj, key) {
        return Object.prototype.hasOwnProperty.call(obj, key);
    }

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // ── Events ───────────────────────────────────────────────────────────

    /** First value of the first tag with this name, or ''. */
    function tagValue(event, name) {
        var tags = event && Array.isArray(event.tags) ? event.tags : [];
        for (var i = 0; i < tags.length; i++) {
            if (Array.isArray(tags[i]) && tags[i][0] === name) {
                return typeof tags[i][1] === 'string' ? tags[i][1] : '';
            }
        }
        return '';
    }

    /**
     * Every first value of the tags with this name. By default only values
     * that are keys, lowercased (p and e tags); pass { hex: false } for raw.
     */
    function tagValues(event, name, opts) {
        var hexOnly = !opts || opts.hex !== false;
        var out = [];
        var tags = event && Array.isArray(event.tags) ? event.tags : [];
        tags.forEach(function (t) {
            if (!Array.isArray(t) || t[0] !== name || typeof t[1] !== 'string') { return; }
            if (hexOnly) {
                var v = hexKey(t[1]);
                if (v) { out.push(v); }
            } else {
                out.push(t[1]);
            }
        });
        return out;
    }

    /** Latest event per author (hex keys only). */
    function latestPerAuthor(events) {
        var by = dict();
        (events || []).forEach(function (e) {
            var a = hexKey(e && e.pubkey);
            if (!a) { return; }
            if (!by[a] || by[a].created_at < e.created_at) { by[a] = e; }
        });
        return by;
    }

    /** Structurally an event as a relay should send it (before verification). */
    function wellFormed(e) {
        return !!e && typeof e === 'object'
            && isHex64(hexKey(e.id)) && isHex64(hexKey(e.pubkey))
            && typeof e.created_at === 'number' && typeof e.kind === 'number'
            && typeof e.content === 'string' && Array.isArray(e.tags);
    }

    // ── The extension ────────────────────────────────────────────────────

    /**
     * window.nostr, waiting for an extension that injects late. Resolves
     * with the object or null after `maxMs` (default 3 s).
     */
    function waitForNostr(maxMs) {
        var limit = typeof maxMs === 'number' ? maxMs : 3000;
        return new Promise(function (resolve) {
            if (window.nostr) { resolve(window.nostr); return; }
            var waited = 0;
            var timer = setInterval(function () {
                waited += 250;
                if (window.nostr) { clearInterval(timer); resolve(window.nostr); }
                else if (waited >= limit) { clearInterval(timer); resolve(null); }
            }, 250);
        });
    }

    // ── Signatures ───────────────────────────────────────────────────────

    var verifierPromise = null;

    /**
     * The Schnorr verifier, fetched once. Resolves with the function, or
     * with null when it cannot be had — then no event is trusted at all.
     */
    function loadVerifier() {
        if (typeof window.skNostrVerify === 'function') { return Promise.resolve(window.skNostrVerify); }
        if (!cfg.verify) { return Promise.resolve(null); }
        if (!verifierPromise) {
            verifierPromise = new Promise(function (resolve) {
                var s = document.createElement('script');
                s.src = cfg.verify;
                s.async = true;
                s.onload = function () { resolve(typeof window.skNostrVerify === 'function' ? window.skNostrVerify : null); };
                s.onerror = function () { resolve(null); };
                document.head.appendChild(s);
            });
        }
        return verifierPromise;
    }

    /**
     * Only the events whose id and signature hold. About a millisecond
     * each; yields now and then so a long list does not freeze the page.
     */
    async function verifyAll(events) {
        var verify = await loadVerifier();
        if (!verify) { return []; }
        var out = [];
        for (var i = 0; i < events.length; i++) {
            if (wellFormed(events[i]) && verify(events[i])) { out.push(events[i]); }
            if (i % 50 === 49) { await new Promise(function (r) { setTimeout(r, 0); }); }
        }
        return out;
    }

    /** One event, verified. Resolves true/false. */
    async function verifyOne(event) {
        var verify = await loadVerifier();
        return !!verify && wellFormed(event) && verify(event);
    }

    // ── Relays ───────────────────────────────────────────────────────────

    /**
     * One REQ against one relay: the events until EOSE (raw, unverified)
     * and whether EOSE was seen. Never rejects; an unreachable relay gives
     * { events: [], eose: false }.
     */
    function queryRelay(url, filters, opts) {
        var timeoutMs = (opts && opts.timeout) || DEFAULT_TIMEOUT;
        return new Promise(function (resolve) {
            var events = [];
            var done = false;
            var ws;
            var timer = setTimeout(function () { finish(false); }, timeoutMs);

            function finish(eose) {
                if (done) { return; }
                done = true;
                clearTimeout(timer);
                try { ws.close(); } catch (e) { /* ignore */ }
                resolve({ events: events, eose: !!eose });
            }

            try {
                ws = new WebSocket(url);
            } catch (e) {
                clearTimeout(timer);
                resolve({ events: [], eose: false });
                return;
            }

            var sub = 'sk' + Math.random().toString(36).slice(2, 10);
            ws.onopen = function () { ws.send(JSON.stringify(['REQ', sub].concat(filters))); };
            ws.onmessage = function (msg) {
                var data;
                try { data = JSON.parse(msg.data); } catch (e) { return; }
                if (!Array.isArray(data) || data[1] !== sub) { return; }
                if (data[0] === 'EVENT' && wellFormed(data[2])) { events.push(data[2]); }
                else if (data[0] === 'EOSE' || data[0] === 'CLOSED') { finish(true); }
            };
            ws.onerror = function () { finish(false); };
            ws.onclose = function () { finish(false); };
        });
    }

    /**
     * The same REQ against several relays in parallel, merged, deduplicated
     * by id and verified. Options: relays (default: the site's), count
     * (first N of them), timeout, verify (default true).
     * Resolves with { events: [...], answered: <relays that reached EOSE> }.
     */
    async function query(filters, opts) {
        opts = opts || {};
        var relays = (opts.relays || cfg.relays || []).slice(0, opts.count || undefined);
        var results = await Promise.all(relays.map(function (url) { return queryRelay(url, filters, opts); }));
        var seen = dict();
        var merged = [];
        var answered = 0;
        results.forEach(function (r) {
            if (r.eose) { answered++; }
            r.events.forEach(function (e) {
                var id = hexKey(e.id);
                if (!seen[id]) { seen[id] = 1; merged.push(e); }
            });
        });
        var events = opts.verify === false ? merged : await verifyAll(merged);
        return { events: events, answered: answered };
    }

    /**
     * A live subscription on several relays: onEvent(event) for every
     * verified, new event until close() is called or `timeout` ms pass.
     * Returns { close }.
     */
    function subscribe(filters, onEvent, opts) {
        opts = opts || {};
        var relays = opts.relays || cfg.relays || [];
        var sockets = [];
        var seen = dict();
        var closed = false;
        var sub = 'sk' + Math.random().toString(36).slice(2, 10);

        function close() {
            if (closed) { return; }
            closed = true;
            clearTimeout(timer);
            sockets.forEach(function (ws) { try { ws.send(JSON.stringify(['CLOSE', sub])); } catch (e) { /* ignore */ } try { ws.close(); } catch (e) { /* ignore */ } });
            sockets = [];
        }

        relays.forEach(function (url) {
            var ws;
            try { ws = new WebSocket(url); } catch (e) { return; }
            sockets.push(ws);
            ws.onopen = function () { ws.send(JSON.stringify(['REQ', sub].concat(filters))); };
            ws.onmessage = function (msg) {
                var data;
                try { data = JSON.parse(msg.data); } catch (e) { return; }
                if (!Array.isArray(data) || data[0] !== 'EVENT' || data[1] !== sub || !wellFormed(data[2])) { return; }
                var id = hexKey(data[2].id);
                if (seen[id] || closed) { return; }
                seen[id] = 1;
                verifyOne(data[2]).then(function (ok) { if (ok && !closed) { onEvent(data[2]); } });
            };
        });

        var timer = setTimeout(close, opts.timeout || 90000);

        return { close: close };
    }

    window.skNostr = {
        config: cfg,
        isHex64: isHex64,
        hexKey: hexKey,
        dict: dict,
        own: own,
        esc: esc,
        tagValue: tagValue,
        tagValues: tagValues,
        latestPerAuthor: latestPerAuthor,
        waitForNostr: waitForNostr,
        loadVerifier: loadVerifier,
        verifyAll: verifyAll,
        verifyOne: verifyOne,
        queryRelay: queryRelay,
        query: query,
        subscribe: subscribe
    };
})();
