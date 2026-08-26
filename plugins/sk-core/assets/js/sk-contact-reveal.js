/**
 * Kontaktweg erst auf Klick holen.
 *
 * Adresse, Nummer und Handle stehen nicht mehr im Quelltext — ein Abruf der
 * Shopseite brachte vorher alle Telegram-Namen auf einmal. Der Wert kommt nun
 * einzeln aus einer Abfrage, die mengenbegrenzt ist.
 *
 * Nach der Antwort ist die Nutzergeste abgelaufen, ein neues Fenster wird
 * deshalb oft blockiert. Darum wird der Wert zusaetzlich sichtbar gemacht:
 * klappt das Oeffnen nicht, steht der Kontakt trotzdem da und ist anklickbar.
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
         * In der Kontaktliste ist der Wert die Antwort — dort stand vorher
         * "anzeigen", das wird ersetzt. Das kompakte Symbol auf einer
         * Inseratskarte bleibt dagegen ein Symbol: waechst es auf die Breite
         * eines Handles, schiebt es bei zwei Kontakten die Karte auseinander.
         */
        if (icon.classList.contains('dkp-contact-reveal-link')) {
            wertZeigen(icon, daten.kurz);
        }

        // mailto: und tel: brauchen kein neues Fenster und werden nicht geblockt.
        if (daten.ziel.indexOf('mailto:') === 0 || daten.ziel.indexOf('tel:') === 0) {
            window.location.href = daten.ziel;
            return;
        }

        /*
         * Ohne 'noopener' in den Fensteroptionen: mit der Angabe liefert
         * window.open immer null, auch wenn das Fenster aufgeht — die Blockade
         * liesse sich dann nicht von einem Erfolg unterscheiden. Die Trennung
         * zum Aufrufer wird stattdessen danach gesetzt.
         */
        var fenster = window.open(daten.ziel, '_blank');

        if (fenster) {
            fenster.opener = null;
            return;
        }

        // Blockiert: jetzt muss der Wert sichtbar werden, sonst weiss niemand,
        // worauf der zweite Klick fuehrt.
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
    }
}());
