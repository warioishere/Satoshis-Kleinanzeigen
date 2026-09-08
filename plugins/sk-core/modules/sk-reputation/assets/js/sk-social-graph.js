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
 */
(function () {
    'use strict';

    var cfg = window.skTrustGraph;
    if (!cfg || !cfg.relays || !cfg.relays.length) {
        return;
    }

    var TTL = 24 * 60 * 60 * 1000;
    var PREFIX = 'skTrust:v1:';
    var AUTHORS_PER_FILTER = 200;
    var FILTERS_PER_REQ = 10;
    var MAX_CONTACTS = 4000;
    var NAMES_PER_VENDOR = 8;
    var REQ_TIMEOUT = 7000;

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

    function save(key, value) {
        try { localStorage.setItem(PREFIX + key, JSON.stringify({ ts: Date.now(), v: value })); } catch (e) { /* full or blocked */ }
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
     * The same REQ against the first `count` relays in parallel, merged
     * and deduplicated. No single relay has everything: a contact list
     * missing on one is often on the next.
     */
    async function query(filters, count) {
        var relays = cfg.relays.slice(0, count || cfg.relays.length);
        var results = await Promise.all(relays.map(function (url) {
            return queryRelay(url, filters).catch(function () { return []; });
        }));
        var seen = {};
        var merged = [];
        results.forEach(function (list) {
            list.forEach(function (e) {
                if (e && e.id && !seen[e.id]) { seen[e.id] = 1; merged.push(e); }
            });
        });
        return merged;
    }

    function tagValues(event, name) {
        var out = [];
        (event.tags || []).forEach(function (t) { if (t[0] === name && t[1]) { out.push(String(t[1]).toLowerCase()); } });
        return out;
    }

    /** Latest event per author. */
    function latestPerAuthor(events) {
        var by = {};
        events.forEach(function (e) {
            var a = String(e.pubkey || '').toLowerCase();
            if (!by[a] || by[a].created_at < e.created_at) { by[a] = e; }
        });
        return by;
    }

    // ── The viewer ───────────────────────────────────────────────────────

    async function viewerPubkey() {
        if (cfg.viewer && /^[0-9a-f]{64}$/.test(cfg.viewer)) { return cfg.viewer; }

        var cached = load('viewer');
        if (cached === 'none') { return ''; }
        if (cached) { return cached; }

        // The extension may inject window.nostr after load.
        for (var i = 0; i < 6 && !window.nostr; i++) {
            await new Promise(function (r) { setTimeout(r, 500); });
        }
        if (!window.nostr) { return ''; }

        try {
            var pk = String(await window.nostr.getPublicKey()).toLowerCase();
            if (/^[0-9a-f]{64}$/.test(pk)) { save('viewer', pk); return pk; }
        } catch (e) {
            // Declined: not asked again today.
            save('viewer', 'none');
        }
        return '';
    }

    async function contactsOf(viewer) {
        var cached = load(viewer + ':contacts');
        if (cached) { return cached; }

        // Every relay: the viewer's list lives wherever their client wrote it.
        var events = await query([{ kinds: [3], authors: [viewer], limit: 1 }], cfg.relays.length);
        var latest = latestPerAuthor(events)[viewer];
        var contacts = latest ? tagValues(latest, 'p') : [];

        // Unique, and never the viewer themself.
        var seen = {};
        contacts = contacts.filter(function (p) { if (seen[p] || p === viewer) { return false; } seen[p] = 1; return true; });

        save(viewer + ':contacts', contacts);
        return contacts;
    }

    // ── Degree two ───────────────────────────────────────────────────────

    /**
     * For each vendor, which of the viewer's contacts follow them.
     * Returns { vendor: [contact, ...] }.
     */
    async function followersAmongContacts(viewer, contacts, vendors) {
        var result = {};
        var pending = [];

        vendors.forEach(function (v) {
            var cached = load(viewer + ':f:' + v);
            if (cached) { result[v] = cached; } else { pending.push(v); result[v] = []; }
        });

        if (!pending.length || !contacts.length) { return result; }

        var authors = contacts.slice(0, MAX_CONTACTS);
        var filters = [];
        for (var i = 0; i < authors.length; i += AUTHORS_PER_FILTER) {
            filters.push({ kinds: [3], authors: authors.slice(i, i + AUTHORS_PER_FILTER), '#p': pending });
        }

        var events = [];
        for (var j = 0; j < filters.length; j += FILTERS_PER_REQ) {
            events = events.concat(await query(filters.slice(j, j + FILTERS_PER_REQ), 2));
        }

        var latest = latestPerAuthor(events);
        var contactSet = {};
        authors.forEach(function (c) { contactSet[c] = 1; });

        Object.keys(latest).forEach(function (author) {
            if (!contactSet[author]) { return; }
            var follows = tagValues(latest[author], 'p');
            pending.forEach(function (v) {
                if (follows.indexOf(v) !== -1) { result[v].push(author); }
            });
        });

        pending.forEach(function (v) { save(viewer + ':f:' + v, result[v]); });
        return result;
    }

    // ── Names, for the "why" ─────────────────────────────────────────────

    async function namesOf(pubkeys) {
        var names = {};
        var missing = [];
        pubkeys.forEach(function (p) {
            var cached = load('name:' + p);
            if (cached !== null) { names[p] = cached; } else { missing.push(p); }
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

    function whyList(followers, names) {
        var shown = followers.slice(0, NAMES_PER_VENDOR);
        var html = '<div class="sk-trust-pop-note">' + esc(cfg.i18n.why) + '</div><ul>';
        shown.forEach(function (p) {
            html += '<li><a href="https://njump.me/' + p + '" target="_blank" rel="noopener">' + esc(names[p] || shortKey(p)) + '</a></li>';
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

            contactsPromise = contactsPromise || contactsOf(viewer);
            var contacts = await contactsPromise;
            if (!contacts.length) { hint('nomatch'); return; }

            var contactSet = {};
            contacts.forEach(function (c) { contactSet[c] = 1; });

            var vendors = [];
            chips.forEach(function (c) {
                var v = c.getAttribute('data-pubkey').toLowerCase();
                if (v !== viewer && vendors.indexOf(v) === -1) { vendors.push(v); }
            });
            if (!vendors.length) { return; }

            var followers = await followersAmongContacts(viewer, contacts, vendors);

            var namePubkeys = [];
            vendors.forEach(function (v) {
                (followers[v] || []).slice(0, NAMES_PER_VENDOR).forEach(function (p) { if (namePubkeys.indexOf(p) === -1) { namePubkeys.push(p); } });
            });
            var names = namePubkeys.length ? await namesOf(namePubkeys) : {};

            chips.forEach(function (c) {
                var v = c.getAttribute('data-pubkey').toLowerCase();
                var list = followers[v] || [];
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
