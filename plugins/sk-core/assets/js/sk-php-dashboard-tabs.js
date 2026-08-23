/**
 * Haelt den aktiven Reiter sichtbar.
 *
 * Die Reiterleiste ist eine einzelne, seitlich scrollbare Zeile. Liegt der
 * aktive Reiter ausserhalb des sichtbaren Bereichs, waere sonst nicht
 * erkennbar, wo man sich befindet.
 */
(function () {
    'use strict';

    function scrollActiveIntoView() {
        var nav = document.querySelector('.wrap.sk-php-dashboard nav.nav-tab-wrapper');
        if (!nav) return;

        var active = nav.querySelector('.nav-tab-active');
        if (!active) return;

        if (nav.scrollWidth <= nav.clientWidth) return;

        var target = active.offsetLeft - (nav.clientWidth - active.offsetWidth) / 2;
        nav.scrollLeft = Math.max(0, target);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scrollActiveIntoView);
    } else {
        scrollActiveIntoView();
    }
}());
