/**
 * Subscription Info Banner
 *
 * Injects a "Support SK via subscription" info-box above the pack listing on
 * the subscription-packs route of the SPA dashboard. Auto-mounts/un-mounts
 * based on the hash route.
 */
(function () {
    'use strict';

    var BANNER_ID = 'subscription-infobox';
    var MAX_RETRIES = 20;
    var routeTickTimer = null;
    var retryCount = 0;

    function buildBanner() {
        var el = document.createElement('div');
        el.id = BANNER_ID;
        el.innerHTML = '<div class="row"><div class="icon" aria-hidden="true">📢</div><div>' +
            '<strong>Satoshis Kleinanzeigen lebt von der Community.</strong><br>' +
            'Mit einem kleinen Abo unterstützt du nicht nur den Betrieb, sondern erhältst auch mehr Reichweite und Sichtbarkeit für deine Inserate.<br><br>' +
            'Schon ab 10 000 Sats bist du dabei – unkompliziert, anonym, ohne Zwang.<br><br>' +
            'Du kannst auch einmalig ein Abo kaufen, mehrere Produkte einstellen, und das Abo auslaufen lassen. Alle zusätzlichen Inserate über dem Grundkontigent, werden nicht gelöscht!' +
            '</div></div>';
        return el;
    }

    function findSubscriptionContainer() {
        var root = document.querySelector('#sk-vendor-dashboard-root') || document;
        var activePanel = root.querySelector('.sk-tab-panel:not([hidden])');
        if (activePanel) {
            var matchCard = Array.from(activePanel.querySelectorAll('.sk-card')).find(function (card) {
                var h = card.querySelector('.sk-card-title, h1, h2, h3, h4, h5, h6');
                if (!h || !h.textContent) return false;
                var t = h.textContent.trim().toLowerCase();
                return ['current subscription', 'aktuelles abo', 'aktuelles abonnement', 'abonnementen pakete', 'current plan']
                    .some(function (l) { return t.indexOf(l) !== -1; });
            });
            if (matchCard) return matchCard.closest('.sk-layout') || matchCard;
            var layout = activePanel.querySelector('.sk-layout.mb-5');
            if (layout) return layout;
            var fallbackCard = activePanel.querySelector('.sk-card.mb-5');
            if (fallbackCard) return fallbackCard.closest('.sk-layout') || fallbackCard;
        }
        return root.querySelector('.sk-subscription-pack-content, .sk-subscription-content');
    }

    function ensureBanner(container) {
        if (!container || !container.parentNode) return false;
        var box = document.getElementById(BANNER_ID);
        if (box && box.nextElementSibling === container) return true;
        if (!box) box = buildBanner(); else box.remove();
        container.parentNode.insertBefore(box, container);
        return true;
    }

    function isSubscriptionRoute() {
        var path = (location.pathname || '').replace(/\/+$/, '');
        if (!path.endsWith('/dashboard/new')) return false;
        return (location.hash || '').toLowerCase().indexOf('#/subscription') !== -1;
    }

    function isSubscriptionPacksPage() {
        if (!isSubscriptionRoute()) return false;
        var match = (location.hash || '').toLowerCase().match(/tab=([^&]+)/);
        var tab = match ? match[1] : null;
        return tab === null || tab === 'packs';
    }

    function removeBanner() {
        var b = document.getElementById(BANNER_ID);
        if (b) b.remove();
    }

    function stopWatcher() {
        if (routeTickTimer) { clearInterval(routeTickTimer); routeTickTimer = null; }
        retryCount = 0;
    }

    function handleRouteTick() {
        if (!isSubscriptionRoute() || !isSubscriptionPacksPage()) {
            removeBanner();
            stopWatcher();
            return;
        }
        var c = findSubscriptionContainer();
        if (c) {
            ensureBanner(c);
            stopWatcher();
        }
    }

    function startRouteWatcher() {
        if (routeTickTimer) return;
        retryCount = 0;
        routeTickTimer = setInterval(function () {
            if (++retryCount > MAX_RETRIES) { stopWatcher(); return; }
            requestAnimationFrame(handleRouteTick);
        }, 500);
    }

    function forceRouteCheck() {
        stopWatcher();
        requestAnimationFrame(handleRouteTick);
        if (isSubscriptionRoute()) startRouteWatcher();
    }

    function init() {
        var root = document.querySelector('#sk-vendor-dashboard-root');
        if (root) {
            root.addEventListener('sk:page-loaded', function () { forceRouteCheck(); }, { passive: true });
        }
        forceRouteCheck();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
    window.addEventListener('hashchange', function () { forceRouteCheck(); });
})();
