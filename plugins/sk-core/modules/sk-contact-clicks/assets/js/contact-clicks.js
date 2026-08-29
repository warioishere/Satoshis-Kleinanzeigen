/**
 * Meldet Klicks auf Kontaktangaben eines Inserats.
 *
 * Die Links selbst bleiben unveraendert — kein preventDefault, keine
 * Weiterleitung. Das ist Absicht: Kontaktziele sind teils tel: und mailto:,
 * die sich nicht sauber umleiten lassen, und eine kaputte Kontaktaufnahme
 * waere schlimmer als eine fehlende Zahl.
 */
(function () {
    'use strict';

    if (!window.skContactClicks) return;

    /** Kanal aus der Symbolklasse, sonst aus dem Ziel ableiten. */
    function channelOf(link) {
        var m = (link.className || '').match(/dkp-contact-icon--([a-z0-9]+)/);
        if (m) return m[1];

        var href = (link.getAttribute('href') || '').toLowerCase();
        if (href.indexOf('mailto:') === 0) return 'mail';
        if (href.indexOf('tel:') === 0) return 'tel';
        if (href.indexOf('t.me/') !== -1 || href.indexOf('telegram.me/') !== -1) return 'tg';
        if (href.indexOf('x.com/') !== -1 || href.indexOf('twitter.com/') !== -1) return 'x';
        if (href.indexOf('primal.net/') !== -1 || href.indexOf('njump.me/') !== -1 || href.indexOf('nostr:') === 0) return 'nostr';
        return '';
    }

    /**
     * Produkt-ID aus dem Umfeld. Auf der Einzelseite steht sie in der
     * body-Klasse, in Listen an der Kachel (WooCommerce vergibt post-<id>).
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
         * Der Chat zaehlt nicht hier. Ein Klick auf sein Symbol oeffnet nur
         * das Fenster — und bei Ausgeloggten den Anmeldehinweis. Gezaehlt
         * wird serverseitig, wenn eine Unterhaltung wirklich zustande kommt.
         */
        if (channel === 'chat') return;

        var body = new URLSearchParams();
        body.append('action', window.skContactClicks.action);
        body.append('nonce', window.skContactClicks.nonce);
        body.append('product_id', productOf(link));
        body.append('channel', channel);
        body.append('context', contextOf(link));

        // sendBeacon ueberlebt den Seitenwechsel; wo es fehlt, ein
        // keepalive-fetch. Fehler werden bewusst verschluckt.
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
        } catch (err) { /* Messung darf den Klick nie stoeren */ }
    }, true);
}());
