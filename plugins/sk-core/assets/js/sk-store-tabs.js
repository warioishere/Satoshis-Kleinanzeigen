/**
 * SK Store Tabs — AJAX tab switching without page reload.
 *
 * Intercepts clicks on .sk-store-tabs .sk-list-inline a,
 * fetches the target page, extracts the content after .sk-profile-frame-wrapper,
 * swaps it in, and loads any new scripts/styles from the target page.
 * Uses history.pushState for URL + back-button support.
 */
(function () {
    'use strict';

    var tabList = document.querySelector('.sk-store-tabs .sk-list-inline');
    if (!tabList) return;

    var contentEl = document.getElementById('sk-content');
    if (!contentEl) return;

    var headerWrapper = contentEl.querySelector('.sk-profile-frame-wrapper');
    if (!headerWrapper) return;

    var loading = false;
    var loadedScripts = {};

    tabList.addEventListener('click', function (e) {
        var link = e.target.closest('a');
        if (!link || loading) return;

        var url = link.href;
        if (!url || url === window.location.href) return;

        e.preventDefault();
        loadTab(url, link.closest('li'));
    });

    // Back/forward button support.
    window.addEventListener('popstate', function () {
        var activeLi = findActiveLi(window.location.pathname);
        loadTab(window.location.href, activeLi, true);
    });

    function loadTab(url, newActiveLi, skipPush) {
        loading = true;

        // Update active state immediately.
        var items = tabList.querySelectorAll('li');
        for (var i = 0; i < items.length; i++) items[i].classList.remove('active');
        if (newActiveLi) newActiveLi.classList.add('active');

        // Fade out current content.
        var oldContent = getContentAfterHeader();
        oldContent.forEach(function (el) {
            el.style.transition = 'opacity .15s';
            el.style.opacity = '0.4';
        });

        fetch(url, { credentials: 'same-origin' })
            .then(function (res) { return res.text(); })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var remoteContentEl = doc.getElementById('sk-content');

                if (!remoteContentEl) {
                    window.location.href = url;
                    return;
                }

                var remoteHeader = remoteContentEl.querySelector('.sk-profile-frame-wrapper');
                if (!remoteHeader) {
                    window.location.href = url;
                    return;
                }

                // Collect new content nodes (everything after .sk-profile-frame-wrapper).
                var newNodes = [];
                var sibling = remoteHeader.nextSibling;
                while (sibling) {
                    newNodes.push(sibling);
                    sibling = sibling.nextSibling;
                }

                // Remove old content after header.
                while (headerWrapper.nextSibling) {
                    headerWrapper.nextSibling.remove();
                }

                // Append new content.
                newNodes.forEach(function (node) {
                    contentEl.appendChild(node.cloneNode(true));
                });

                // Load new styles from target page that we don't have yet.
                loadNewStyles(doc);

                // Load new scripts from target page that we don't have yet,
                // then fire jQuery ready handlers for the new content.
                loadNewScripts(doc).then(function () {
                    // Re-trigger jQuery ready so plugins bind to new DOM.
                    if (window.jQuery) {
                        jQuery(contentEl).find('.sk-store-review-iziModal').trigger('init');
                        jQuery(document).trigger('sk-store-tab-loaded');
                    }
                    loading = false;
                });

                // Update URL.
                if (!skipPush) {
                    history.pushState(null, '', url);
                }

                // Update document title.
                var newTitle = doc.querySelector('title');
                if (newTitle) document.title = newTitle.textContent;

                // Scroll to tabs if out of view.
                var tabsRect = tabList.getBoundingClientRect();
                if (tabsRect.top < 0) {
                    tabList.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            })
            .catch(function () {
                window.location.href = url;
            });
    }

    /**
     * Find scripts on the fetched page that aren't loaded yet, inject them.
     * Returns a Promise that resolves when all new scripts have loaded.
     */
    function loadNewScripts(doc) {
        // Snapshot current scripts at call time (not init time) so footer scripts are included.
        document.querySelectorAll('script[src]').forEach(function (s) {
            loadedScripts[s.src.split('?')[0]] = true;
        });

        var remoteScripts = doc.querySelectorAll('script[src]');
        var promises = [];

        remoteScripts.forEach(function (rs) {
            var src = rs.src.split('?')[0];
            if (loadedScripts[src]) return;
            loadedScripts[src] = true;

            promises.push(new Promise(function (resolve) {
                var script = document.createElement('script');
                script.src = rs.src;
                script.onload = resolve;
                script.onerror = resolve;
                document.body.appendChild(script);
            }));
        });

        // Also execute inline scripts from the fetched content area.
        var remoteContentEl = doc.getElementById('sk-content');
        if (remoteContentEl) {
            var inlineScripts = remoteContentEl.querySelectorAll('script:not([src])');
            inlineScripts.forEach(function (rs) {
                if (rs.textContent.trim()) {
                    try { new Function(rs.textContent)(); } catch (e) { /* ignore */ }
                }
            });
        }

        return promises.length ? Promise.all(promises) : Promise.resolve();
    }

    /**
     * Load CSS stylesheets from the fetched page that aren't on the current page.
     */
    function loadNewStyles(doc) {
        var currentLinks = {};
        document.querySelectorAll('link[rel="stylesheet"]').forEach(function (l) {
            currentLinks[l.href.split('?')[0]] = true;
        });

        doc.querySelectorAll('link[rel="stylesheet"]').forEach(function (rl) {
            var href = rl.href.split('?')[0];
            if (currentLinks[href]) return;
            currentLinks[href] = true;

            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = rl.href;
            document.head.appendChild(link);
        });
    }

    function getContentAfterHeader() {
        var nodes = [];
        var sibling = headerWrapper.nextElementSibling;
        while (sibling) {
            nodes.push(sibling);
            sibling = sibling.nextElementSibling;
        }
        return nodes;
    }

    function findActiveLi(pathname) {
        var current = trailingSlash(pathname);
        var items = tabList.querySelectorAll('li');
        for (var i = 0; i < items.length; i++) {
            var a = items[i].querySelector('a');
            if (a && trailingSlash(new URL(a.href).pathname) === current) return items[i];
        }
        return null;
    }

    function trailingSlash(path) {
        return path.replace(/\/?$/, '/');
    }
})();
