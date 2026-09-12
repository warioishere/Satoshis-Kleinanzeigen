/**
 * Tapping a moving slide.
 *
 * The product slider animates for three of every three and a half seconds.
 * A finger that lands while a slide travels lifts over a different element
 * than it touched, so the browser synthesises no click at all and the
 * listing simply does not open. Waiting for a standstill is the only way in.
 *
 * So the tap is carried out here: the link is remembered on touchstart and
 * followed on touchend, but only when the slide really moved in between —
 * a still slider keeps the browser's own click, and a swipe or a long press
 * is left alone.
 */
(function () {
    'use strict';

    var MOVED_FINGER = 10;  // px of finger travel that makes it a swipe
    var MOVED_SLIDE  = 2;   // px of slide travel that kills the browser click
    var MAX_TAP      = 700; // ms; longer is a press, not a tap

    var tap = null;

    function left(el) {
        return el.getBoundingClientRect().left;
    }

    document.addEventListener('touchstart', function (e) {
        tap = null;

        if (e.touches.length !== 1) {
            return;
        }

        var link = e.target.closest && e.target.closest('.splide a[href]');

        if (!link) {
            return;
        }

        tap = {
            link: link,
            x: e.touches[0].clientX,
            y: e.touches[0].clientY,
            left: left(link),
            at: Date.now()
        };
    }, { passive: true, capture: true });

    document.addEventListener('touchmove', function (e) {
        if (!tap || !e.touches.length) {
            return;
        }

        if (Math.abs(e.touches[0].clientX - tap.x) > MOVED_FINGER ||
            Math.abs(e.touches[0].clientY - tap.y) > MOVED_FINGER) {
            tap = null;
        }
    }, { passive: true, capture: true });

    document.addEventListener('touchend', function () {
        var t = tap;
        tap = null;

        if (!t || Date.now() - t.at > MAX_TAP) {
            return;
        }

        // The slide stood still: the browser fires its own click.
        if (Math.abs(left(t.link) - t.left) <= MOVED_SLIDE) {
            return;
        }

        window.location.assign(t.link.href);
    }, { passive: true, capture: true });

    document.addEventListener('touchcancel', function () {
        tap = null;
    }, { passive: true, capture: true });
})();
