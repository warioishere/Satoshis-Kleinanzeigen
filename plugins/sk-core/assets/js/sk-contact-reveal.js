/**
 * Fetch the contact channel only on click.
 *
 * Address, number, and handle no longer live in the page source — fetching
 * the shop page used to expose all Telegram handles at once. The value is
 * now fetched individually through a rate-limited request.
 *
 * By the time the response arrives, the user gesture has expired, so a new
 * window is often blocked. That's why the value is also made visible: if
 * opening fails, the contact is still shown and clickable.
 */
(function () {
    'use strict';

    var cfg = window.skContactReveal || {};
    if (!cfg.ajaxurl || !cfg.nonce) return;

    var laufend = false;

    document.addEventListener('click', function (e) {
        if (!e.target || !e.target.closest) return;

        var icon = e.target.closest('[data-sk-contact]');
        if (!icon || icon.classList.contains('is-revealed')) return;

        e.preventDefault();
        if (laufend) return;
        laufend = true;
        icon.classList.add('is-loading');

        var body = new URLSearchParams();
        body.append('action', 'sk_reveal_contact');
        body.append('nonce', cfg.nonce);
        body.append('vendor', icon.getAttribute('data-vendor') || '');
        body.append('channel', icon.getAttribute('data-sk-contact') || '');

        fetch(cfg.ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                laufend = false;
                icon.classList.remove('is-loading');

                if (!res || !res.success) {
                    var text = (res && res.data && res.data.message) || cfg.fehler;
                    icon.setAttribute('title', text);
                    icon.classList.add('is-error');
                    return;
                }

                zeigen(icon, res.data);
            })
            .catch(function () {
                laufend = false;
                icon.classList.remove('is-loading');
                icon.setAttribute('title', cfg.fehler);
                icon.classList.add('is-error');
            });
    });

    function zeigen(icon, daten) {
        icon.classList.add('is-revealed');
        icon.setAttribute('href', daten.ziel);
        icon.setAttribute('title', daten.wert);
        icon.setAttribute('aria-label', daten.wert);
        icon.removeAttribute('data-sk-contact');

        /*
         * In the contact list, the value is the answer — it used to say
         * "show" there, which this replaces. The compact icon on a listing
         * card, however, stays an icon: if it grew to the width of a handle,
         * two contacts would push the card apart.
         */
        if (icon.classList.contains('dkp-contact-reveal-link')) {
            wertZeigen(icon, daten.kurz);
        }

        // mailto: and tel: don't need a new window and aren't blocked.
        if (daten.ziel.indexOf('mailto:') === 0 || daten.ziel.indexOf('tel:') === 0) {
            window.location.href = daten.ziel;
            return;
        }

        /*
         * Deliberately without 'noopener' in the window options: with it,
         * window.open always returns null, even when the window actually
         * opens — that would make a block indistinguishable from success.
         * The severance from the opener is set afterward instead.
         */
        var fenster = window.open(daten.ziel, '_blank');

        if (fenster) {
            fenster.opener = null;
            return;
        }

        // Blocked: the value now needs to become visible, otherwise no one
        // knows what the second click leads to.
        icon.classList.add('is-blocked');
        wertZeigen(icon, daten.kurz);
    }

    function wertZeigen(icon, kurz) {
        if (!kurz || icon.querySelector('.dkp-contact-icon__value')) return;

        Array.prototype.slice.call(icon.childNodes).forEach(function (n) {
            if (n.nodeType === 3) icon.removeChild(n);
        });

        var span = document.createElement('span');
        span.className = 'dkp-contact-icon__value';
        span.textContent = kurz;
        icon.appendChild(span);

        // The box may only widen once the value is visible.
        icon.classList.add('has-value');
    }
}());
