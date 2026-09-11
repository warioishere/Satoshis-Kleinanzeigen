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
 * Relay access, verification and the untrusted-input helpers come from
 * sk-nostr.js (window.skNostr): nothing a relay sends counts without a
 * valid signature, keys and ids are 64 hex or nothing.
 */
(function () {
    'use strict';

    var cfg = window.skTrustGraph;
    var nostr = window.skNostr;
    if (!cfg || !nostr) {
        return;
    }

    var relays = (cfg.relays && cfg.relays.length) ? cfg.relays : (nostr.config.relays || []);
    if (!relays.length) {
        return;
    }

    var TTL = 24 * 60 * 60 * 1000;
    var PREFIX = 'skTrust:v3:';
    var AUTHORS_PER_FILTER = 200;
    var FILTERS_PER_REQ = 10;
    var MAX_CONTACTS = 4000;
    var NAMES_PER_VENDOR = 8;

    var isHex64 = nostr.isHex64;
    var hexKey = nostr.hexKey;
    var dict = nostr.dict;
    var own = nostr.own;
    var esc = nostr.esc;

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

    // ── Relays ───────────────────────────────────────────────────────────

    /** Verified events from the first `count` relays, merged. */
    async function query(filters, count) {
        return (await nostr.query(filters, { relays: relays, count: count })).events;
    }

    // ── The viewer ───────────────────────────────────────────────────────

    async function viewerPubkey() {
        if (isHex64(cfg.viewer)) { return cfg.viewer; }

        // A declined prompt is remembered for a day; the key itself only per tab.
        if (load('viewer') === 'none') { return ''; }
        var cached = loadSession('viewer');
        if (isHex64(cached)) { return cached; }

        var ext = await nostr.waitForNostr(3000);
        if (!ext) { return ''; }

        try {
            var pk = hexKey(await ext.getPublicKey());
            if (pk) { saveSession('viewer', pk); return pk; }
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
        var events = await query([{ kinds: [3], authors: [viewer], limit: 1 }], relays.length);
        var latest = nostr.latestPerAuthor(events)[viewer];
        var contacts = latest ? nostr.tagValues(latest, 'p') : [];

        // Unique, and never the viewer themself.
        var seen = dict();
        contacts = contacts.filter(function (p) { if (seen[p] || p === viewer) { return false; } seen[p] = 1; return true; });

        save(viewer + ':contacts', contacts);
        return contacts;
    }

    // ── Degree two ───────────────────────────────────────────────────────

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

    /**
     * For each vendor: which of the viewer's contacts follow them (f), and
     * which have reported them (r, kind 1984 with a type that matters here).
     * One round of REQs covers both; returns { vendor: { f: [...], r: [...] } }.
     */
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

        var latest = nostr.latestPerAuthor(events.filter(function (e) { return e.kind === 3; }));
        Object.keys(latest).forEach(function (author) {
            if (!contactSet[author]) { return; }
            var follows = nostr.tagValues(latest[author], 'p');
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
            e.tags.forEach(function (t) {
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
            var latest = nostr.latestPerAuthor(await query([{ kinds: [0], authors: missing }], 2));
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

    /**
     * What a chip says, per place.
     *
     * The store banner has room for whole sentences, one per line. The
     * vendor box on a product page and the product card have none: there
     * only the badge shows and the sentence moves into its tooltip. The feed
     * card keeps its short one-liner.
     *
     * @return {{lines: string[], title: string}}
     */
    function compose(ctx, isContact, count) {
        var full = ctx === 'store' || ctx === 'page';
        var follow = full ? cfg.i18n.follow : cfg.i18n.followS;
        var contacts = (count === 1
            ? (full ? cfg.i18n.contact1 : cfg.i18n.contact1S)
            : (full ? cfg.i18n.contactN : cfg.i18n.contactNS)).replace('%d', count);

        var lines = [];
        if (isContact) { lines.push(follow); }
        if (count) { lines.push(contacts); }

        if (ctx === 'product' || ctx === 'card') {
            // Badge only; the full sentence is still there on hover.
            var said = [];
            if (isContact) { said.push(cfg.i18n.follow); }
            if (count) { said.push((count === 1 ? cfg.i18n.contact1 : cfg.i18n.contactN).replace('%d', count)); }
            return { lines: [], title: said.join(' · ') };
        }
        if (ctx === 'store') {
            return { lines: lines, title: cfg.i18n.why };
        }
        return { lines: [lines.join(' · ')], title: cfg.i18n.why };
    }
    window.skTrustGraphCompose = compose;

    function fill(el, lines, icon, why, title) {
        var text = lines.length ? ' <span class="sk-trust-graph-text">' + lines.map(esc).join('<br>') + '</span>' : '';
        el.innerHTML = '<i class="fas ' + icon + '" aria-hidden="true"></i>' + text;
        el.title = title || cfg.i18n.why;
        if (why) {
            var pop = document.createElement('div');
            pop.className = 'sk-trust-pop';
            pop.innerHTML = why;
            el.appendChild(pop);
            el.classList.add('sk-trust-graph--why');
            el.addEventListener('click', function (ev) {
                ev.stopPropagation();
                toggle(pop, el);
            });
        }
        el.hidden = false;
    }

    /**
     * Open or close a list under its chip.
     *
     * The list is placed relative to the viewport, not inside the chip's
     * box: the store banner clips everything that leaves it, and the list
     * of followers always does. Aligned to the chip's left edge, or to its
     * right edge where the chip sits at the right of a vendor box.
     */
    function place(pop, el) {
        var r = el.getBoundingClientRect();
        var alignRight = !!el.closest('.sk-vendor-info-wrap');
        pop.style.top = Math.round(r.bottom + 6) + 'px';
        // Never past the bottom of the window: what does not fit scrolls inside.
        pop.style.maxHeight = Math.max(120, Math.round(window.innerHeight - r.bottom - 18)) + 'px';
        if (alignRight) {
            pop.style.left = 'auto';
            pop.style.right = Math.max(8, Math.round(window.innerWidth - r.right)) + 'px';
        } else {
            pop.style.right = 'auto';
            pop.style.left = Math.max(8, Math.round(r.left)) + 'px';
        }
    }
    window.skTrustGraphPlace = place;

    function toggle(pop, el) {
        var open = !pop.classList.contains('is-open');
        closeAll();
        if (open) {
            place(pop, el);
            pop.classList.add('is-open');
        }
    }

    function closeAll() {
        document.querySelectorAll('.sk-trust-pop.is-open').forEach(function (p) { p.classList.remove('is-open'); });
    }

    document.addEventListener('click', closeAll);
    // A fixed list would drift away from its chip while the page scrolls.
    window.addEventListener('scroll', closeAll, { passive: true });
    window.addEventListener('resize', closeAll);

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
            html += '<li><a href="https://nostrich.org/e/' + esc(r.id) + '" target="_blank" rel="noopener">' + esc(names[p] || shortKey(p)) + '</a><span class="sk-trust-pop-type">' + esc(label) + '</span></li>';
        });
        html += '</ul>';
        var pop = document.createElement('div');
        pop.className = 'sk-trust-pop';
        pop.innerHTML = html;
        el.appendChild(pop);
        el.addEventListener('click', function (ev) { ev.stopPropagation(); toggle(pop, el); });

        chip.parentNode.insertBefore(el, chip.nextSibling);
    }

    function whyList(followers, names) {
        var shown = followers.slice(0, NAMES_PER_VENDOR);
        var html = '<div class="sk-trust-pop-note">' + esc(cfg.i18n.why) + '</div><ul>';
        shown.forEach(function (p) {
            html += '<li><a href="https://nostrich.org/p/' + esc(p) + '" target="_blank" rel="noopener">' + esc(names[p] || shortKey(p)) + '</a></li>';
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
            nostr.loadVerifier();

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
                var ctx = (c.className.match(/sk-trust-graph--(\w+)/) || [])[1] || '';
                var said = compose(ctx, !!contactSet[v], list.length);

                if (contactSet[v]) {
                    fill(c, said.lines, 'fa-user-check', list.length ? whyList(list, names) : '', said.title);
                } else if (list.length) {
                    fill(c, said.lines, 'fa-users', whyList(list, names), said.title);
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
