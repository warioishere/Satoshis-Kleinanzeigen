(function(){
    'use strict';

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    ready(function () {
        var container = document.getElementById('weo-treuhand');
        if (!container) {
            return;
        }

        if (typeof window.Map !== 'function') {
            return;
        }

        var tabLinks = container.querySelectorAll('.weo-treuhand-tabs a[data-tab-target]');
        if (!tabLinks.length) {
            return;
        }

        var panels = container.querySelectorAll('.weo-treuhand-panel[data-tab-panel]');
        if (!panels.length) {
            return;
        }

        var tabMap = new Map();
        var navMap = new Map();
        tabLinks.forEach(function (link) {
            var target = link.getAttribute('data-tab-target');
            if (!target) {
                return;
            }

            tabMap.set(target, link);
            var li = link.closest('.weo-treuhand-tab');
            if (li) {
                navMap.set(target, li);
            }
        });

        var panelMap = new Map();
        panels.forEach(function (panel) {
            var key = panel.getAttribute('data-tab-panel');
            if (key) {
                panelMap.set(key, panel);
            }
        });

        if (!tabMap.size || !panelMap.size) {
            return;
        }

        var supportsHistory = typeof window.history !== 'undefined' && typeof window.history.pushState === 'function';
        var supportsURL = typeof window.URL === 'function';

        function writeUrl(target, replace) {
            if (!supportsHistory || !supportsURL) {
                return;
            }

            var url;
            try {
                url = new URL(window.location.href);
            } catch (err) {
                return;
            }

            url.searchParams.set('weo_tab', target);
            var state = { weoTab: target };
            if (replace) {
                window.history.replaceState(state, '', url.toString());
            } else {
                window.history.pushState(state, '', url.toString());
            }
        }

        function setActive(target, push) {
            if (!tabMap.has(target) || !panelMap.has(target)) {
                return;
            }

            var current = container.getAttribute('data-active-tab');
            var isSame = current === target;

            tabMap.forEach(function (link, key) {
                var isTarget = key === target;
                link.classList.toggle('nav-tab-active', isTarget);
                link.classList.toggle('active', isTarget);
                link.setAttribute('aria-selected', isTarget ? 'true' : 'false');
                var parent = navMap.get(key);
                if (parent) {
                    parent.classList.toggle('active', isTarget);
                }
            });

            panelMap.forEach(function (panel, key) {
                var isTarget = key === target;
                panel.setAttribute('aria-hidden', isTarget ? 'false' : 'true');
                if (isTarget) {
                    panel.removeAttribute('hidden');
                } else {
                    panel.setAttribute('hidden', 'hidden');
                }
            });

            container.setAttribute('data-active-tab', target);

            if (push && !isSame) {
                writeUrl(target, false);
            }
        }

        tabLinks.forEach(function (link) {
            link.addEventListener('click', function (event) {
                if (event.defaultPrevented) {
                    return;
                }

                if (event.button !== 0 || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
                    return;
                }

                var target = link.getAttribute('data-tab-target');
                if (!target) {
                    return;
                }

                event.preventDefault();
                setActive(target, true);
            });
        });

        if (supportsHistory) {
            window.addEventListener('popstate', function (event) {
                var stateTab = event.state && event.state.weoTab;
                var target = stateTab;

                if (!target && supportsURL) {
                    try {
                        var url = new URL(window.location.href);
                        target = url.searchParams.get('weo_tab');
                    } catch (err) {
                        target = null;
                    }
                }

                if (target && tabMap.has(target)) {
                    setActive(target, false);
                }
            });
        }

        var initial = container.getAttribute('data-active-tab');
        if (supportsURL) {
            try {
                var url = new URL(window.location.href);
                var paramTab = url.searchParams.get('weo_tab');
                if (paramTab && tabMap.has(paramTab)) {
                    initial = paramTab;
                }
            } catch (err) {
                // ignore
            }
        }

        if (!initial || !tabMap.has(initial)) {
            var first = tabLinks[0].getAttribute('data-tab-target');
            if (first) {
                initial = first;
            }
        }

        if (initial && tabMap.has(initial)) {
            setActive(initial, false);
            writeUrl(initial, true);
        }
    });
})();
