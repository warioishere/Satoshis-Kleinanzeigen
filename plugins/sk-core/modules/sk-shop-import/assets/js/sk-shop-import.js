/**
 * Auswahl der zu importierenden Inserate.
 *
 * Haelt Zaehler und Knopfbeschriftung an der tatsaechlichen Auswahl — sonst
 * verspricht der Knopf "28 Inserate importieren", obwohl das Paket nur sechs
 * zulaesst und der naechste Schritt eine Absage waere.
 */
(function () {
    'use strict';

    var form = document.querySelector('.sk-import-pick');
    if (!form) return;

    var boxes = Array.prototype.slice.call(form.querySelectorAll('input[name="sk_pick[]"]'));
    var counter = document.getElementById('sk-import-count');
    var button = document.getElementById('sk-import-submit');
    var limit = parseInt(form.getAttribute('data-limit') || '0', 10);
    var labelOne = form.getAttribute('data-label-one') || '%d Inserat importieren';
    var labelMany = form.getAttribute('data-label-many') || '%d Inserate importieren';
    var overLabel = form.getAttribute('data-label-over') || '';

    function selected() {
        return boxes.filter(function (b) { return b.checked; }).length;
    }

    function update() {
        var n = selected();

        if (counter) counter.textContent = String(n);

        if (button) {
            var over = limit > 0 && n > limit;
            button.textContent = over && overLabel
                ? overLabel
                : (n === 1 ? labelOne : labelMany).replace('%d', String(n));
            button.disabled = n === 0;
            button.classList.toggle('sk-btn-default', over);
            button.classList.toggle('sk-btn-theme', !over);
        }

        form.classList.toggle('is-over-limit', limit > 0 && n > limit);
    }

    boxes.forEach(function (b) { b.addEventListener('change', update); });

    var all = document.getElementById('sk-import-all');
    if (all) {
        all.addEventListener('change', function () {
            boxes.forEach(function (b) { b.checked = all.checked; });
            update();
        });
    }

    var upTo = document.getElementById('sk-import-uptolimit');
    if (upTo) {
        upTo.addEventListener('click', function (e) {
            e.preventDefault();
            boxes.forEach(function (b, i) { b.checked = limit > 0 && i < limit; });
            update();
        });
    }

    update();

    /*
     * Waehrend des Imports passiert minutenlang sichtbar nichts: Das Formular
     * wird abgeschickt, der Server legt Inserate an und laedt Bilder nach.
     * Ohne Rueckmeldung klickt der Verkaeufer ein zweites Mal.
     */
    form.addEventListener('submit', function () {
        var box = document.getElementById('sk-import-progress');
        var text = document.getElementById('sk-import-progress-text');
        var n = selected();

        if (text) {
            text.textContent = n === 1
                ? '1 Inserat wird angelegt.'
                : n + ' Inserate werden angelegt.';
        }

        if (box) box.classList.add('is-visible');
        if (button) button.disabled = true;
    });
}());

/**
 * Modal auf der Abo-Seite: was der Katalogimport kann.
 *
 * Eigene Funktion, weil der obige Block auf der Abo-Seite gar nicht erst
 * laeuft — dort gibt es kein Auswahlformular. Klicks laufen ueber document,
 * damit die Reihenfolge von Skript und Markup egal ist.
 */
(function () {
    'use strict';

    var opener = null;

    function box() {
        return document.getElementById('sk-pack-info');
    }

    function close() {
        var el = box();
        if (!el || !el.classList.contains('is-visible')) return;
        el.classList.remove('is-visible');
        if (opener) {
            opener.focus();
            opener = null;
        }
    }

    document.addEventListener('click', function (e) {
        if (!e.target || !e.target.closest) return;

        var trigger = e.target.closest('[data-sk-pack-info]');
        if (trigger) {
            var el = box();
            if (!el) return;
            e.preventDefault();
            opener = trigger;
            el.classList.add('is-visible');
            var button = el.querySelector('.sk-pack-info__close');
            if (button) button.focus();
            return;
        }

        if (e.target.closest('.sk-pack-info__close') || e.target.closest('.sk-pack-info__backdrop')) {
            e.preventDefault();
            close();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });
}());
