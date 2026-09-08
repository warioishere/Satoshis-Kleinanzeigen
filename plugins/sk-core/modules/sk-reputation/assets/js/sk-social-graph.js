/**
 * Social graph trust signal, computed in the viewer's browser.
 *
 * Every vendor with a proven Nostr key carries a hidden chip with that key.
 * This script works out, from the viewer's own contact list (kind 3), who
 * among the vendors on the page the viewer follows, and how many of the
 * viewer's contacts follow each vendor. The second question is asked the
 * cheap way: the relay is given the contacts as authors and the vendors as
 * #p, and returns only the contact lists that mention a vendor.
 *
 * The viewer's key comes from the site (a logged-in, proven key) or from
 * the extension. No key, no contacts, or nothing found: the chips stay
 * hidden. Results are cached in localStorage for a day.
 *
 * Nothing a relay sends is taken at its word: every event is checked for
 * a valid NIP-01 id and BIP-340 signature (sk-nostr-verify.js, loaded on
 * demand) before it counts. A relay can withhold events, not invent them.
 */
(function () {
    'use strict';

    var cfg = window.skTrustGraph;
    if (!cfg || !cfg.relays || !cfg.relays.length) {
        return;
    }

    var TTL = 24 * 60 * 60 * 1000;
    var PREFIX = 'skTrust:v3:';
    var AUTHORS_PER_FILTER = 200;
    var FILTERS_PER_REQ = 10;
    var MAX_CONTACTS = 4000;
    var NAMES_PER_VENDOR = 8;
    var REQ_TIMEOUT = 7000;

    // ── Untrusted input ──────────────────────────────────────────────────
    //
    // Everything a relay sends is data from a third party. Keys and event
    // ids are accepted only as 64 lowercase hex characters, and every map
    // keyed by them has no prototype, so "constructor" or "__proto__" as a
    // key hits nothing.

    var HEX64 = /^[0-9a-f]{64}$/;

    function isHex64(value) {
        return typeof value === 'string' && HEX64.test(value);
    }

    /** Lowercase hex key, or '' when the value is not one. */
    function hexKey(value) {
        var s = String(value || '').toLowerCase();
        return HEX64.test(s) ? s : '';
    }

    function dict() {
        return Object.create(null);
    }

    function own(obj, key) {
        return Object.prototype.hasOwnProperty.call(obj, key);
    }

    // ── Storage ──────────────────────────────────────────────────────────

    function load(key) {
        try {
            var raw = localStorage.getItem(PREFIX + key);
            if (!raw) { return null; }
            var obj = JSON.parse(raw);
            if (!obj || !obj.ts || Date.now() - obj.ts > TTL) { return null; }
            return obj.v;
        } catch (e) { return null; }
    }

    /**
     * Per-tab storage for the extension's key: a browser shared between
     * accounts, or an account switched in the extension, must not show
     * the previous person's graph for a day. getPublicKey() is silent once
     * a site is approved, so asking again per tab costs nothing visible.
     */
    function loadSession(key) {
        try {
            var raw = sessionStorage.getItem(PREFIX + key);
            return raw ? JSON.parse(raw) : null;
        } catch (e) { return null; }
    }

    function saveSession(key, value) {
        try { sessionStorage.setItem(PREFIX + key, JSON.stringify(value)); } catch (e) { /* unavailable */ }
    }

    function save(key, value) {
        try { localStorage.setItem(PREFIX + key, JSON.stringify({ ts: Date.now(), v: value })); } catch (e) { /* full or blocked */ }
    }

    // ── Signatures ───────────────────────────────────────────────────────

    var verifierPromise = null;

    /**
     * The Schnorr verifier, fetched once and only when needed. Resolves
     * with the function, or with null when it cannot be had — in which
     * case no event is trusted at all.
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
     * each; the loop yields now and then so a long list does not freeze
     * the page.
     */
    async function verifiedOnly(events) {
        var verify = await loadVerifier();
        if (!verify) { return []; }
        var out = [];
        for (var i = 0; i < events.length; i++) {
            if (verify(events[i])) { out.push(events[i]); }
            if (i % 50 === 49) { await new Promise(function (r) { setTimeout(r, 0); }); }
        }
        return out;
    }

    // ── Relays ───────────────────────────────────────────────────────────

    /**
     * One REQ against one relay. Resolves with the events seen until EOSE;
     * rejects only when the relay could not be reached at all.
     */
    function queryRelay(url, filters) {
        return new Promise(function (resolve, reject) {
            var events = [];
            var done = false;
            var ws;
            var timer = setTimeout(function () { finish(events.length ? null : 'timeout'); }, REQ_TIMEOUT);

            function finish(err) {
                if (done) { return; }
                done = true;
                clearTimeout(timer);
                try { ws.close(); } catch (e) { /* ignore */ }
                if (err && !events.length) { reject(err); } else { resolve(events); }
            }

            try {
                ws = new WebSocket(url);
            } catch (e) {
                clearTimeout(timer);
                reject(e);
                return;
            }

            var sub = 'skt' + Math.random().toString(36).slice(2, 10);
            ws.onopen = function () { ws.send(JSON.stringify(['REQ', sub].concat(filters))); };
            ws.onmessage = function (msg) {
                var data;
                try { data = JSON.parse(msg.data); } catch (e) { return; }
                if (data[0] === 'EVENT' && data[1] === sub && data[2]) { events.push(data[2]); }
                else if ((data[0] === 'EOSE' || data[0] === 'CLOSED') && data[1] === sub) { finish(null); }
            };
            ws.onerror = function () { finish('error'); };
            ws.onclose = function () { finish(null); };
        });
    }

    /**
     * The same REQ against the first `count` relays in parallel, merged,
     * deduplicated and signature-checked. No single relay has everything:
     * a contact list missing on one is often on the next.
     */
    async function query(filters, count) {
        var relays = cfg.relays.slice(0, count || cfg.relays.length);
        var results = await Promise.all(relays.map(function (url) {
            return queryRelay(url, filters).catch(function () { return []; });
        }));
        var seen = dict();
        var merged = [];
        results.forEach(function (list) {
            list.forEach(function (e) {
                if (!e || typeof e !== 'object') { return; }
                var id = hexKey(e.id);
                if (!id || seen[id] || !hexKey(e.pubkey) || typeof e.created_at !== 'number') { return; }
                seen[id] = 1;
                merged.push(e);
            });
        });
        return verifiedOnly(merged);
    }

    /** The hex keys in the event's tags of one name; anything else is dropped. */
    function tagValues(event, name) {
        var out = [];
        var tags = Array.isArray(event.tags) ? event.tags : [];
        tags.forEach(function (t) {
            if (!Array.isArray(t) || t[0] !== name) { return; }
            var v = hexKey(t[1]);
            if (v) { out.push(v); }
        });
        return out;
    }

    /** Latest event per author. */
    function latestPerAuthor(events) {
        var by = dict();
        events.forEach(function (e) {
            var a = hexKey(e.pubkey);
            if (!a) { return; }
            if (!by[a] || by[a].created_at < e.created_at) { by[a] = e; }
        });
        return by;
    }

    // ── The viewer ───────────────────────────────────────────────────────

    async function viewerPubkey() {
        if (cfg.viewer && /^[0-9a-f]{64}$/.test(cfg.viewer)) { return cfg.viewer; }

        // A declined prompt is remembered for a day; the key itself only per tab.
        if (load('viewer') === 'none') { return ''; }
        var cached = loadSession('viewer');
        if (isHex64(cached)) { return cached; }

        // The extension may inject window.nostr after load.
        for (var i = 0; i < 6 && !window.nostr; i++) {
            await new Promise(function (r) { setTimeout(r, 500); });
        }
        if (!window.nostr) { return ''; }

        try {
            var pk = String(await window.nostr.getPublicKey()).toLowerCase();
            if (/^[0-9a-f]{64}$/.test(pk)) { saveSession('viewer', pk); return pk; }
        } catch (e) {
            // Declined: not asked again today.
            save('viewer', 'none');
        }
        return '';
    }

    async function contactsOf(viewer) {
        var cached = load(viewer + ':contacts');
        if (Array.isArray(cached)) { return cached.filter(isHex64); }

        // Every relay: the viewer's list lives wherever their client wrote it.
        var events = await query([{ kinds: [3], authors: [viewer], limit: 1 }], cfg.relays.length);
        var latest = latestPerAuthor(events)[viewer];
        var contacts = latest ? tagValues(latest, 'p') : [];

        // Unique, and never the viewer themself.
        var seen = dict();
        contacts = contacts.filter(function (p) { if (seen[p] || p === viewer) { return false; } seen[p] = 1; return true; });

        save(viewer + ':contacts', contacts);
        return contacts;
    }

    // ── Degree two ───────────────────────────────────────────────────────

    /**
     * For each vendor, which of the viewer's contacts follow them.
     * Returns { vendor: [contact, ...] }.
     */
    /**
     * For each vendor: which of the viewer's contacts follow them (f), and
     * which have reported them (r, kind 1984 with a type that matters here).
     * One round of REQs covers both; returns { vendor: { f: [...], r: [...] } }.
     */
    /** A cached per-vendor result, with anything that is not a key thrown out. */
    function cleanGraphEntry(entry) {
        if (!entry || typeof entry !== 'object') { return null; }
        var f = Array.isArray(entry.f) ? entry.f.filter(isHex64) : [];
        var r = Array.isArray(entry.r) ? entry.r.filter(function (x) {
            return x && isHex64(x.id) && isHex64(x.reporter) && own(cfg.i18n.types, String(x.type)) && typeof x.created_at === 'number';
        }).map(function (x) {
            return { id: x.id, reporter: x.reporter, type: String(x.type), created_at: x.created_at };
        }) : [];
        return { f: f, r: r };
    }

    async function graphOf(viewer, contacts, vendors) {
        var result = dict();
        var pending = [];

        vendors.forEach(function (v) {
            var cached = cleanGraphEntry(load(viewer + ':g:' + v));
            if (cached) { result[v] = cached; } else { pending.push(v); result[v] = { f: [], r: [] }; }
        });

        if (!pending.length || !contacts.length) { return result; }

        var authors = contacts.slice(0, MAX_CONTACTS);
        var filters = [];
        for (var i = 0; i < authors.length; i += AUTHORS_PER_FILTER) {
            var chunk = authors.slice(i, i + AUTHORS_PER_FILTER);
            filters.push({ kinds: [3], authors: chunk, '#p': pending });
            filters.push({ kinds: [1984], authors: chunk, '#p': pending });
        }

        var events = [];
        for (var j = 0; j < filters.length; j += FILTERS_PER_REQ) {
            events = events.concat(await query(filters.slice(j, j + FILTERS_PER_REQ), 2));
        }

        var contactSet = dict();
        authors.forEach(function (c) { contactSet[c] = 1; });

        var latest = latestPerAuthor(events.filter(function (e) { return e.kind === 3; }));
        Object.keys(latest).forEach(function (author) {
            if (!contactSet[author]) { return; }
            var follows = tagValues(latest[author], 'p');
            pending.forEach(function (v) {
                // A vendor listing themself is not one of the viewer's contacts following them.
                if (author !== v && follows.indexOf(v) !== -1) { result[v].f.push(author); }
            });
        });

        events.filter(function (e) { return e.kind === 1984; }).forEach(function (e) {
            var reporter = hexKey(e.pubkey);
            var id = hexKey(e.id);
            if (!reporter || !id || !contactSet[reporter]) { return; }
            // Automated classifiers report by score, not by judgement.
            if (/^\s*automated/i.test(String(e.content || ''))) { return; }
            var tags = Array.isArray(e.tags) ? e.tags : [];
            tags.forEach(function (t) {
                if (!Array.isArray(t) || t[0] !== 'p') { return; }
                var target = hexKey(t[1]);
                var type = String(t[2] || '').toLowerCase();
                if (!target || target === reporter || pending.indexOf(target) === -1 || !own(cfg.i18n.types, type)) { return; }
                result[target].r.push({ id: id, reporter: reporter, type: type, created_at: e.created_at });
            });
        });

        pending.forEach(function (v) { save(viewer + ':g:' + v, result[v]); });
        return result;
    }

    // ── Names, for the "why" ─────────────────────────────────────────────

    async function namesOf(pubkeys) {
        var names = dict();
        var missing = [];
        pubkeys.forEach(function (p) {
            if (!isHex64(p)) { return; }
            var cached = load('name:' + p);
            if (typeof cached === 'string') { names[p] = cached; } else { missing.push(p); }
        });

        if (missing.length) {
            var events = await query([{ kinds: [0], authors: missing }], 2);
            var latest = latestPerAuthor(events);
            missing.forEach(function (p) {
                var name = '';
                try {
                    var profile = latest[p] ? JSON.parse(latest[p].content) : null;
                    name = profile ? String(profile.display_name || profile.name || '') : '';
                } catch (e) { /* unreadable profile */ }
                names[p] = name;
                save('name:' + p, name);
            });
        }

        return names;
    }

    function shortKey(p) { return p.slice(0, 8) + '…' + p.slice(-4); }

    // ── Rendering ────────────────────────────────────────────────────────

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function fill(el, text, icon, why) {
        el.innerHTML = '<i class="fas ' + icon + '" aria-hidden="true"></i> <span class="sk-trust-graph-text">' + esc(text) + '</span>';
        el.title = cfg.i18n.why;
        if (why) {
            var pop = document.createElement('div');
            pop.className = 'sk-trust-pop';
            pop.innerHTML = why;
            el.appendChild(pop);
            el.classList.add('sk-trust-graph--why');
            el.addEventListener('click', function (ev) {
                ev.stopPropagation();
                pop.classList.toggle('is-open');
            });
        }
        el.hidden = false;
    }

    document.addEventListener('click', function () {
        document.querySelectorAll('.sk-trust-pop.is-open').forEach(function (p) { p.classList.remove('is-open'); });
    });

    /**
     * The trust page explains an empty result; everywhere else silence.
     * 'nokey': no viewer key; 'nomatch': a key, but nothing in common.
     */
    function hint(which) {
        var el = document.querySelector('[data-sk-trust-' + which + ']');
        if (el) { el.hidden = false; }
    }

    /**
     * Reports by the viewer's own contacts, as a chip of its own next to
     * the graph chip. Absent when there are none.
     */
    function renderReports(chip, reports, names) {
        if (!reports || !reports.length || chip.nextElementSibling && chip.nextElementSibling.classList.contains('sk-trust-report')) { return; }

        var byReporter = dict();
        reports.forEach(function (r) { if (!byReporter[r.reporter] || byReporter[r.reporter].created_at < r.created_at) { byReporter[r.reporter] = r; } });
        var distinct = Object.keys(byReporter);
        if (!distinct.length) { return; }

        var short = !chip.classList.contains('sk-trust-graph--store') && !chip.classList.contains('sk-trust-graph--page');
        var text = (distinct.length === 1
            ? (short ? cfg.i18n.report1S : cfg.i18n.report1)
            : (short ? cfg.i18n.reportNS : cfg.i18n.reportN)).replace('%d', distinct.length);

        var ctx = (chip.className.match(/sk-trust-graph--(\w+)/) || [])[1] || '';
        var el = document.createElement(chip.tagName);
        el.className = 'sk-trust-report sk-trust-report--' + ctx;
        el.title = cfg.i18n.reportWhy;
        el.innerHTML = '<i class="fas fa-triangle-exclamation" aria-hidden="true"></i> <span class="sk-trust-report-text">' + esc(text) + '</span>';

        var html = '<div class="sk-trust-pop-note">' + esc(cfg.i18n.reportWhy) + '</div><ul>';
        distinct.slice(0, NAMES_PER_VENDOR).forEach(function (p) {
            var r = byReporter[p];
            var label = own(cfg.i18n.types, r.type) ? cfg.i18n.types[r.type] : r.type;
            html += '<li><a href="https://njump.me/' + esc(r.id) + '" target="_blank" rel="noopener">' + esc(names[p] || shortKey(p)) + '</a><span class="sk-trust-pop-type">' + esc(label) + '</span></li>';
        });
        html += '</ul>';
        var pop = document.createElement('div');
        pop.className = 'sk-trust-pop';
        pop.innerHTML = html;
        el.appendChild(pop);
        el.addEventListener('click', function (ev) { ev.stopPropagation(); pop.classList.toggle('is-open'); });

        chip.parentNode.insertBefore(el, chip.nextSibling);
    }

    function whyList(followers, names) {
        var shown = followers.slice(0, NAMES_PER_VENDOR);
        var html = '<div class="sk-trust-pop-note">' + esc(cfg.i18n.why) + '</div><ul>';
        shown.forEach(function (p) {
            html += '<li><a href="https://njump.me/' + esc(p) + '" target="_blank" rel="noopener">' + esc(names[p] || shortKey(p)) + '</a></li>';
        });
        html += '</ul>';
        if (followers.length > shown.length) {
            html += '<div class="sk-trust-pop-more">' + esc(cfg.i18n.more.replace('%d', followers.length - shown.length)) + '</div>';
        }
        return html;
    }

    // ── Main ─────────────────────────────────────────────────────────────

    var viewerPromise = null;
    var contactsPromise = null;
    var running = false;
    var queued = false;

    async function run() {
        if (running) { queued = true; return; }
        running = true;

        try {
            var chips = Array.prototype.slice.call(document.querySelectorAll('.sk-trust-graph[data-pubkey]:not([data-done])'));
            chips.forEach(function (c) { c.setAttribute('data-done', '1'); });
            if (!chips.length) { return; }

            viewerPromise = viewerPromise || viewerPubkey();
            var viewer = await viewerPromise;
            if (!viewer) { hint('nokey'); return; }

            // Fetch the verifier while the relays are being asked.
            loadVerifier();

            contactsPromise = contactsPromise || contactsOf(viewer);
            var contacts = await contactsPromise;
            if (!contacts.length) { hint('nomatch'); return; }

            var contactSet = dict();
            contacts.forEach(function (c) { contactSet[c] = 1; });

            var vendors = [];
            chips.forEach(function (c) {
                var v = hexKey(c.getAttribute('data-pubkey'));
                if (v && v !== viewer && vendors.indexOf(v) === -1) { vendors.push(v); }
            });
            if (!vendors.length) { return; }

            var graph = await graphOf(viewer, contacts, vendors);

            var namePubkeys = [];
            vendors.forEach(function (v) {
                var g = graph[v] || { f: [], r: [] };
                g.f.slice(0, NAMES_PER_VENDOR).concat(g.r.map(function (r) { return r.reporter; })).forEach(function (p) {
                    if (namePubkeys.indexOf(p) === -1) { namePubkeys.push(p); }
                });
            });
            var names = namePubkeys.length ? await namesOf(namePubkeys) : {};

            chips.forEach(function (c) {
                var v = hexKey(c.getAttribute('data-pubkey'));
                if (!v) { return; }
                var g = graph[v] || { f: [], r: [] };
                var list = g.f;
                renderReports(c, g.r, names);
                // Only the store banner and the trust page have room for the full sentence.
                var short = !c.classList.contains('sk-trust-graph--store') && !c.classList.contains('sk-trust-graph--page');
                var follow = short ? cfg.i18n.followS : cfg.i18n.follow;
                var contacts = list.length === 1
                    ? (short ? cfg.i18n.contact1S : cfg.i18n.contact1)
                    : (short ? cfg.i18n.contactNS : cfg.i18n.contactN);
                contacts = contacts.replace('%d', list.length);

                if (contactSet[v]) {
                    fill(c, follow + (list.length ? ' · ' + contacts : ''), 'fa-user-check', list.length ? whyList(list, names) : '');
                } else if (list.length) {
                    fill(c, contacts, 'fa-users', whyList(list, names));
                } else if (c.classList.contains('sk-trust-graph--page')) {
                    hint('nomatch');
                }
            });
        } catch (e) {
            /* a relay problem leaves the chips hidden */
        } finally {
            running = false;
            if (queued) { queued = false; run(); }
        }
    }

    // Chips loaded later (feed pages, AJAX) are picked up as they appear.
    var debounce = null;
    new MutationObserver(function () {
        clearTimeout(debounce);
        debounce = setTimeout(run, 300);
    }).observe(document.body, { childList: true, subtree: true });

    run();
})();
