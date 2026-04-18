/**
 * Abo Picture — inject each subscription pack's product thumbnail below its
 * title in the pack listing (both dashboard and public subscription page).
 * Pack data is provided by PHP via window.DST_ALL_PACKS.
 */
(function () {
    'use strict';

    var packs = Array.isArray(window.DST_ALL_PACKS) ? window.DST_ALL_PACKS : [];
    if (!packs.length) return;

    var SELECTOR = '.pack_content_wrapper .product_pack_item .pack_content h2';
    var THUMB_CLASS = 'dst-sub-thumb';

    function norm(s) {
        return String(s || '').trim().toLowerCase().replace(/\s+/g, ' ');
    }

    function findByTitle(text) {
        var nt = norm(text);
        for (var i = 0; i < packs.length; i++) {
            var pt = norm(packs[i].title);
            if (pt === nt || pt.indexOf(nt) === 0 || nt.indexOf(pt) === 0) return packs[i];
        }
        return null;
    }

    function alreadyInjected(heading) {
        return !!(heading.parentElement && heading.parentElement.querySelector('.' + THUMB_CLASS));
    }

    function inject(heading, pack) {
        if (!pack || !pack.thumb || alreadyInjected(heading)) return;
        var wrap = document.createElement('div');
        wrap.className = THUMB_CLASS;
        var img = document.createElement('img');
        img.src = pack.thumb;
        img.alt = pack.alt || pack.title || 'Abo';
        img.loading = 'lazy';
        wrap.appendChild(img);
        heading.parentElement.insertBefore(wrap, heading.nextSibling);
    }

    function scan() {
        document.querySelectorAll(SELECTOR).forEach(function (h) {
            var pack = findByTitle(h.textContent || '');
            if (pack) inject(h, pack);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scan);
    } else {
        scan();
    }
})();
