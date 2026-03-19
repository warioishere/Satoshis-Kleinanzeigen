/**
 * Minimal Suggestions polyfill.
 *
 * Provides the same API as the "suggestions" npm package (v1.x) that was
 * previously bundled inside mapbox-gl-geocoder v4.  The sk-pro geolocation
 * filter scripts rely on the global `Suggestions` constructor.
 */
(function () {
    'use strict';

    function Suggestions(input, data, options) {
        this.input    = input;
        this.data     = data || [];
        this.options  = options || {};
        this.selected = null;
        this.getItemValue = function (item) { return item; };

        // Build the dropdown container
        var list = document.createElement('div');
        list.className = 'suggestions';
        list.style.cssText =
            'position:absolute;z-index:10000;background:#1a2332;' +
            'border:1px solid rgba(255,255,255,0.12);border-radius:6px;' +
            'max-height:200px;overflow-y:auto;display:none;' +
            'width:' + input.offsetWidth + 'px;';
        input.parentNode.style.position = 'relative';
        input.parentNode.appendChild(list);
        this._list = list;

        var self = this;

        // Hide on outside click
        document.addEventListener('click', function (e) {
            if (!input.contains(e.target) && !list.contains(e.target)) {
                list.style.display = 'none';
            }
        });

        // Show when typing (if items exist)
        input.addEventListener('focus', function () {
            if (self._list.childNodes.length) {
                self._list.style.display = 'block';
            }
        });
    }

    Suggestions.prototype.update = function (items) {
        var self = this;
        var list = this._list;
        list.innerHTML = '';

        if (!items || !items.length) {
            list.style.display = 'none';
            return;
        }

        var limit = (this.options.limit || 5);
        items.slice(0, limit).forEach(function (item) {
            var el = document.createElement('div');
            el.style.cssText =
                'padding:8px 12px;cursor:pointer;color:#e8ecf0;font-size:14px;';
            el.textContent = self.getItemValue(item);

            el.addEventListener('mousedown', function (e) {
                e.preventDefault();
                self.selected = item;
                self.input.value = self.getItemValue(item);
                list.style.display = 'none';

                // Fire change event so listeners pick it up
                var evt = document.createEvent('HTMLEvents');
                evt.initEvent('change', true, false);
                self.input.dispatchEvent(evt);
            });

            el.addEventListener('mouseenter', function () {
                this.style.background = 'rgba(255,255,255,0.08)';
            });
            el.addEventListener('mouseleave', function () {
                this.style.background = '';
            });

            list.appendChild(el);
        });

        list.style.display = 'block';
    };

    Suggestions.prototype.cancel = function () {
        // no-op – compatibility stub
    };

    window.Suggestions = Suggestions;
})();
