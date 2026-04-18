/**
 * Verification Info Banner
 *
 * Injects a "Verification is optional" info-box above the verification
 * settings page header. SPA dashboard mounts verification async, so we use a
 * MutationObserver as fallback when the header isn't present yet.
 */
(function () {
    'use strict';

    var BANNER_ID = 'verify-infobox';
    var observer = null;

    function buildBanner() {
        var el = document.createElement('div');
        el.id = BANNER_ID;
        el.innerHTML = '<div class="row"><div class="icon" aria-hidden="true">🔒</div><div>' +
            '<strong>Die Verifizierung ist optional.</strong><br>' +
            'Du kannst Dich mit einer der unten stehenden Methoden verifizieren. ' +
            'Sobald wir die Angaben geprüft haben, erhältst Du ein <em>Verifiziert</em>-Badge in Deinem Shop Profil. ' +
            'Das schafft zusätzlich Vertrauen gegenüber anderen die Deine Waren kaufen wollen. Deine Daten werden sicher auf unserem Server gespeichert und mit absolut niemanden geteilt.' +
            ' Hast Du kein Bock Dokumente einzureichen, dann lass Dich via Video Call verifizieren.' +
            '</div></div>';
        return el;
    }

    function findHeaderRow() {
        return document.querySelector('#sk-vendor-dashboard-root .sk-header-title-section')
            || document.querySelector('.sk-header-title-section');
    }

    function ensureBanner() {
        var row = findHeaderRow();
        if (!row) return false;
        var box = document.getElementById(BANNER_ID);
        if (box && box.previousElementSibling === row) return true;
        if (!box) box = buildBanner(); else box.remove();
        row.insertAdjacentElement('afterend', box);
        return true;
    }

    function isVerificationPage() {
        return location.hash.indexOf('settings/verification') !== -1;
    }

    function disconnectObserver() {
        if (observer) { observer.disconnect(); observer = null; }
    }

    function watchForHeader() {
        var root = document.querySelector('#sk-vendor-dashboard-root');
        var container = root ? (root.querySelector('.sk-dashboard-wrap') || root) : null;
        if (!container) return;
        disconnectObserver();
        observer = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var nodes = mutations[i].addedNodes || [];
                for (var j = 0; j < nodes.length; j++) {
                    var node = nodes[j];
                    if (node.nodeType !== 1) continue;
                    var isHeader = (node.matches && node.matches('.sk-header-title-section'))
                        || (node.querySelector && node.querySelector('.sk-header-title-section'));
                    if (isHeader && ensureBanner()) {
                        disconnectObserver();
                        return;
                    }
                }
            }
        });
        observer.observe(container, { childList: true, subtree: true });
        // Auto-stop after 6s so a missing header doesn't leak observer forever.
        var stopAt = performance.now() + 6000;
        (function loop() {
            if (!observer) return;
            if (performance.now() > stopAt) { disconnectObserver(); return; }
            requestAnimationFrame(loop);
        })();
    }

    function mountBanner() {
        if (!isVerificationPage()) {
            disconnectObserver();
            var e = document.getElementById(BANNER_ID);
            if (e) e.remove();
            return;
        }
        if (ensureBanner()) { disconnectObserver(); return; }
        requestAnimationFrame(watchForHeader);
    }

    document.addEventListener('DOMContentLoaded', function () {
        mountBanner();
        var root = document.getElementById('sk-vendor-dashboard-root');
        if (root) {
            root.addEventListener('sk:page-loaded', function () {
                requestAnimationFrame(mountBanner);
            });
        }
    });
    window.addEventListener('hashchange', function () {
        requestAnimationFrame(mountBanner);
    });
})();
