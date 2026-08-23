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
}());
