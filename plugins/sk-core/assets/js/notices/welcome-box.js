/**
 * Welcome-Box Toggle
 *
 * Hides #welcome-box when the dashboard is showing a subpage (subscription,
 * announcement, verification, review), so it only appears on the main
 * dashboard landing. Reacts to hash changes + SPA-style page loads.
 */
(function () {
    'use strict';

    var BOX_ID = 'welcome-box';
    var HIDE_ROUTES = ['subscription', 'announcement', 'verification', 'review'];
    var HIDE_SELECTORS = [
        '.sk-subscription-content',
        '.sk-subscription-pack-content',
        '[data-vue-root="sk-verification"]',
        '.sk-verification',
        '.verification-content',
        '.sk-reviews-area',
    ];

    function routeHas(key) {
        return (window.location.hash || '').toLowerCase().indexOf(key) !== -1;
    }

    function shouldHideBox() {
        for (var i = 0; i < HIDE_ROUTES.length; i++) {
            if (routeHas(HIDE_ROUTES[i])) return true;
        }
        for (var j = 0; j < HIDE_SELECTORS.length; j++) {
            if (document.querySelector(HIDE_SELECTORS[j])) return true;
        }
        return false;
    }

    function toggleBox(attempt) {
        var box = document.getElementById(BOX_ID);
        if (!box) return;
        box.style.display = shouldHideBox() ? 'none' : '';
        // Retry a few frames — dashboard content sometimes mounts async.
        if (attempt < 5) requestAnimationFrame(function () { toggleBox(attempt + 1); });
    }

    function onRouteChange() {
        var box = document.getElementById(BOX_ID);
        if (box) box.style.display = 'none';
        requestAnimationFrame(function () { toggleBox(0); });
    }

    toggleBox(0);
    window.addEventListener('hashchange', onRouteChange);
    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('sk_turbo_load', function () { toggleBox(0); });
    }
    var root = document.querySelector('#sk-vendor-dashboard-root');
    if (root) root.addEventListener('sk:page-loaded', onRouteChange);
})();
