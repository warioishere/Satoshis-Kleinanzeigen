/**
 * Zeilen fuer Ausfuehrungen hinzufuegen und entfernen.
 */
(function () {
    'use strict';

    var list = document.getElementById('sk-variants-rows');
    if (!list) return;

    function row() {
        var li = list.querySelector('.sk-variants-row');
        if (!li) return null;
        var copy = li.cloneNode(true);
        copy.querySelectorAll('input').forEach(function (i) { i.value = ''; });
        return copy;
    }

    var add = document.getElementById('sk-variants-add');
    if (add) {
        add.addEventListener('click', function () {
            var next = row();
            if (next) {
                list.appendChild(next);
                var first = next.querySelector('input');
                if (first) first.focus();
            }
        });
    }

    list.addEventListener('click', function (e) {
        var button = e.target.closest('.sk-variants-row__remove');
        if (!button) return;

        // Die letzte Zeile nicht entfernen, sondern leeren — sonst laesst sich
        // ohne Neuladen keine neue mehr anlegen.
        if (list.querySelectorAll('.sk-variants-row').length > 1) {
            button.closest('.sk-variants-row').remove();
        } else {
            button.closest('.sk-variants-row').querySelectorAll('input').forEach(function (i) { i.value = ''; });
        }
    });
}());
