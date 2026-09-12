/**
 * Tapping a slide on a touch device.
 *
 * The product slider glides for three seconds at a time. A finger that
 * lands during that glide never opens the listing: the slider treats the
 * touch as a drag, snaps to the next slide on release, and the browser —
 * whose target has moved away underneath — synthesises no click at all.
 * Waiting for a full standstill was the only way in.
 *
 * So the tap is carried out here: the link is remembered when the finger
 * lands and followed when it lifts. A swipe and a long press are left to
 * the slider; on a slide that stands still the browser's own click follows
 * to the same address, which costs nothing.
 */
(function () {
    'use strict';

    var MOVED   = 10;  // px of finger travel that makes it a swipe
    var MAX_TAP = 700; // ms; longer is a press, not a tap

    var tap = null;

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
            href: link.href,
            x: e.touches[0].clientX,
            y: e.touches[0].clientY,
            at: Date.now()
        };
    }, { passive: true, capture: true });

    document.addEventListener('touchmove', function (e) {
        if (!tap || !e.touches.length) {
            return;
        }

        if (Math.abs(e.touches[0].clientX - tap.x) > MOVED ||
            Math.abs(e.touches[0].clientY - tap.y) > MOVED) {
            tap = null;
        }
    }, { passive: true, capture: true });

    document.addEventListener('touchend', function () {
        var t = tap;
        tap = null;

        if (t && Date.now() - t.at <= MAX_TAP) {
            window.location.assign(t.href);
        }
    }, { passive: true, capture: true });

    document.addEventListener('touchcancel', function () {
        tap = null;
    }, { passive: true, capture: true });
})();
