/**
 * Reports clicks on a listing's contact details.
 *
 * The links themselves stay unchanged — no preventDefault, no redirect.
 * This is intentional: some contact targets are tel: and mailto:, which
 * can't be redirected cleanly, and a broken contact attempt would be
 * worse than a missing number.
 */
(function () {
    'use strict';

    if (!window.skContactClicks) return;

    /** Derive the channel from the icon class, otherwise from the target. */
    function channelOf(link) {
        var m = (link.className || '').match(/dkp-contact-icon--([a-z0-9]+)/);
        if (m) return m[1];

        var href = (link.getAttribute('href') || '').toLowerCase();
        if (href.indexOf('mailto:') === 0) return 'mail';
        if (href.indexOf('tel:') === 0) return 'tel';
        if (href.indexOf('t.me/') !== -1 || href.indexOf('telegram.me/') !== -1) return 'tg';
        if (href.indexOf('x.com/') !== -1 || href.indexOf('twitter.com/') !== -1) return 'x';
        if (href.indexOf('nostrich.org/') !== -1 || href.indexOf('primal.net/') !== -1 || href.indexOf('njump.me/') !== -1 || href.indexOf('nostr:') === 0) return 'nostr';
        return '';
    }

    /**
     * Product ID from the surrounding context. On the single page it's in
     * the body class, in listings it's on the tile (WooCommerce assigns post-<id>).
     */
    function productOf(link) {
        var item = link.closest('[class*="post-"]');
        if (item) {
            var m = (item.className || '').match(/(?:^|\s)post-(\d+)(?:\s|$)/);
            if (m) return m[1];
        }
        var b = (document.body.className || '').match(/(?:^|\s)postid-(\d+)(?:\s|$)/);
        return b ? b[1] : '0';
    }

    function contextOf(link) {
        if (link.closest('.dkp-contact-icons--single')) return 'single';
        if (link.closest('.dkp-contact-icons--loop')) return 'loop';
        if (link.closest('.kontakt-info-liste')) return 'tab';
        if (link.closest('.sk-store-custom-fields')) return 'store';
        return '';
    }

    document.addEventListener('click', function (e) {
        if (!e.target || !e.target.closest) return;

        var link = e.target.closest(
            'a.dkp-contact-icon, .kontakt-info-liste a, .sk-store-custom-fields a'
        );
        if (!link) return;

        var channel = channelOf(link);
        if (!channel) return;

        /*
         * Chat isn't counted here. Clicking its icon only opens the
         * window — or, for logged-out visitors, the login prompt. It's
         * counted server-side once a conversation actually comes about.
         */
        if (channel === 'chat') return;

        var body = new URLSearchParams();
        body.append('action', window.skContactClicks.action);
        body.append('nonce', window.skContactClicks.nonce);
        body.append('product_id', productOf(link));
        body.append('channel', channel);
        body.append('context', contextOf(link));

        // sendBeacon survives the page navigation; where it's unavailable, a
        // keepalive fetch is used instead. Errors are intentionally swallowed.
        try {
            if (navigator.sendBeacon) {
                navigator.sendBeacon(window.skContactClicks.ajaxurl, body);
            } else {
                fetch(window.skContactClicks.ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    keepalive: true,
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString()
                }).catch(function () {});
            }
        } catch (err) { /* Tracking must never interfere with the click */ }
    }, true);
}());
