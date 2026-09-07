/**
 * Add and remove rows for variants.
 */
(function () {
    'use strict';

    var list = document.getElementById('sk-variants-rows');
    if (!list) return;

    function row() {
        var li = list.querySelector('.sk-variants-row');
        if (!li) return null;
        var copy = li.cloneNode(true);
        copy.querySelectorAll('input').forEach(function (i) {
            i.value = '';
            // Otherwise the new row would inherit the first row's amounts and
            // fill itself in again on the next unit switch.
            i.removeAttribute('data-sats');
            i.removeAttribute('data-fiat');
        });
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

        // Clear the last row instead of removing it — otherwise no new row
        // could be added without reloading.
        if (list.querySelectorAll('.sk-variants-row').length > 1) {
            button.closest('.sk-variants-row').remove();
        } else {
            button.closest('.sk-variants-row').querySelectorAll('input').forEach(function (i) { i.value = ''; });
        }
    });
}());

/**
 * Switch the listing price's unit.
 *
 * The entered amount switches along with it: if 49.90 stayed put when
 * switching from CHF to Sats, a listing for 80,000 Sats would silently
 * become one for 50. Anything typed in once is remembered, so switching
 * back and forth loses nothing.
 */
(function () {
    'use strict';

    var unit = document.getElementById('sk_price_unit');
    var price = document.getElementById('_regular_price');
    if (!unit || !price) return;

    var list = document.getElementById('sk-variants-rows');
    var remembered = {
        SATS: unit.getAttribute('data-sats') || '',
        FIAT: unit.getAttribute('data-fiat') || ''
    };
    var previousUnit = unit.value;

    function isSats(value) { return value === 'SATS'; }

    unit.addEventListener('change', function () {
        remembered[isSats(previousUnit) ? 'SATS' : 'FIAT'] = price.value;
        price.value = remembered[isSats(unit.value) ? 'SATS' : 'FIAT'];

        if (list) {
            var label = isSats(unit.value)
                ? (list.getAttribute('data-sats-label') || 'Sats')
                : unit.value;

            list.querySelectorAll('.sk-variants-row__price').forEach(function (field) {
                field.placeholder = label;
                field.setAttribute(isSats(previousUnit) ? 'data-sats' : 'data-fiat', field.value);
                field.value = field.getAttribute(isSats(unit.value) ? 'data-sats' : 'data-fiat') || '';
            });
        }

        previousUnit = unit.value;
    });
}());
