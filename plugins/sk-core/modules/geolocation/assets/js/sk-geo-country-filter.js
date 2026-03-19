/**
 * SK Geolocation Country Filter — hybrid approach:
 *   Map:      filtered client-side (instant)
 *   Products: fetched from server via fetch (full pagination)
 */
(function($) {
    'use strict';

    var Handler = {

        allFeatures: null,
        countryKeywords: null,
        excludedLocations: ['dhaka'],
        mapInstance: null,
        sourceId: null,
        abortCtrl: null,

        init: function() {
            var self = this;
            if (!document.getElementById('sk-country-filter')) return;

            if (typeof SkCountryFilter !== 'undefined' && SkCountryFilter.countries) {
                self.countryKeywords = {};
                SkCountryFilter.countries.forEach(function(c) {
                    self.countryKeywords[c.code] = c.keywords;
                });
            }

            self.attachEventHandlers();

            if (typeof SkGeo !== 'undefined') {
                setTimeout(function() { self.observeMapLoading(); }, 300);
            }
        },

        discoverMap: function() {
            if (this.mapInstance && this.sourceId) return true;

            if (typeof SkGeo === 'undefined' || !SkGeo.LocationsMaps || !SkGeo.LocationsMaps.map) {
                return false;
            }

            var map = SkGeo.LocationsMaps.map;

            try {
                var source = map.getSource('sk_geo_data');
                if (source) {
                    this.mapInstance = map;
                    this.sourceId = 'sk_geo_data';
                    return true;
                }
            } catch (e) {}

            return false;
        },

        observeMapLoading: function() {
            var self = this;
            if (!document.getElementById('sk-geolocation-locations-map')) return;

            var attempts = 0;
            var check = setInterval(function() {
                attempts++;
                if (self.discoverMap()) {
                    clearInterval(check);
                    self.onMapReady();
                } else if (attempts >= 80) {
                    clearInterval(check);
                }
            }, 100);
        },

        onMapReady: function() {
            this.filterExcludedLocations();
            this.cacheCurrentFeatures();

            // If page loaded with country filter, the map only has that country's features.
            // Fetch the unfiltered page in background to get ALL features for switching.
            var url = new URL(window.location);
            if (url.searchParams.get('sk_country')) {
                this.fetchAllFeatures();
            }

            this.checkUrlAndApply();
            this.scrollToProductsIfFiltered();
        },

        cacheCurrentFeatures: function() {
            var source = this.mapInstance.getSource(this.sourceId);
            if (source && source._data && source._data.features) {
                // Only set allFeatures if not already loaded from background fetch
                if (!this.allFeatures) {
                    this.allFeatures = source._data.features.slice();
                }
            }
        },

        /**
         * Fetch the unfiltered shop page to extract ALL geolocation features.
         * This runs in background when page was loaded with ?sk_country=X.
         */
        fetchAllFeatures: function() {
            var self = this;
            var baseUrl = new URL(window.location.pathname, window.location.origin);
            // Remove country filter to get all vendors
            baseUrl.searchParams.delete('sk_country');

            fetch(baseUrl.toString(), {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(res) { return res.text(); })
            .then(function(html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var inputs = doc.querySelectorAll('[name="sk_geolocation[]"]');

                if (!inputs.length) return;

                var features = [];
                inputs.forEach(function(el, i) {
                    var lat = parseFloat(el.getAttribute('data-latitude'));
                    var lng = parseFloat(el.getAttribute('data-longitude'));
                    var info = el.getAttribute('data-info');
                    if (!lat || !lng) return;

                    features.push({
                        type: 'Feature',
                        properties: { id: 'sk-geo-item-' + i, info: info },
                        geometry: { type: 'Point', coordinates: [lng, lat, 0] }
                    });
                });

                if (features.length > 0) {
                    // Filter excluded locations
                    features = features.filter(function(f) { return !self.isExcluded(f); });
                    self.allFeatures = features;
                }
            })
            .catch(function() { /* silent fail, keep using partial cache */ });
        },

        filterExcludedLocations: function() {
            var self = this;
            var source = self.mapInstance.getSource(self.sourceId);
            if (!source || !source._data || !source._data.features) return;

            var filtered = source._data.features.filter(function(f) {
                return !self.isExcluded(f);
            });

            source.setData({ type: 'FeatureCollection', features: filtered });
        },

        getAddress: function(feature) {
            if (!feature.properties) return '';
            var info = feature.properties.info;
            if (!info) return '';
            if (typeof info === 'string') {
                try { info = JSON.parse(info); } catch (e) { return ''; }
            }
            return (info.address || '').toLowerCase();
        },

        isExcluded: function(feature) {
            var addr = this.getAddress(feature);
            if (!addr) return false;
            for (var i = 0; i < this.excludedLocations.length; i++) {
                if (addr.indexOf(this.excludedLocations[i]) !== -1) return true;
            }
            return false;
        },

        // ── URL State ──────────────────────────────────────────────────────

        checkUrlAndApply: function() {
            var url = new URL(window.location);
            var country = url.searchParams.get('sk_country');
            if (country && ['de', 'at', 'ch'].indexOf(country) !== -1) {
                this.setActiveButton(country);
                this.filterMapByCountry(country);
            }
        },

        scrollToProductsIfFiltered: function() {
            var url = new URL(window.location);
            var country = url.searchParams.get('sk_country');
            if (!country || 'all' === country) return;

            var products = document.querySelector('ul.products.content-wrap');
            if (!products) return;

            setTimeout(function() {
                var top = products.getBoundingClientRect().top + window.scrollY - 125;
                window.scrollTo({ top: top, behavior: 'smooth' });
            }, 300);
        },

        // ── Event Handlers ─────────────────────────────────────────────────

        attachEventHandlers: function() {
            var self = this;
            var container = document.getElementById('sk-country-filter');
            if (!container) return;

            container.querySelectorAll('.sk-geo-country-filter-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.filterByCountry(this.getAttribute('data-country'));
                });
            });

            window.addEventListener('popstate', function() {
                var url = new URL(window.location);
                var country = url.searchParams.get('sk_country') || 'all';
                self.setActiveButton(country);
                self.filterMapByCountry(country);
                self.fetchProducts(country);
            });
        },

        setActiveButton: function(country) {
            var container = document.getElementById('sk-country-filter');
            if (!container) return;
            container.querySelectorAll('.sk-geo-country-filter-btn').forEach(function(btn) {
                btn.classList.remove('active');
            });
            var active = container.querySelector('[data-country="' + country + '"]');
            if (active) active.classList.add('active');
        },

        // ── Main Filter Logic ──────────────────────────────────────────────

        filterByCountry: function(country) {
            // Map update is never blocked — always instant
            this.setActiveButton(country);

            var url = new URL(window.location);
            if (country === 'all') {
                url.searchParams.delete('sk_country');
            } else {
                url.searchParams.set('sk_country', country);
            }
            window.history.pushState({}, '', url.toString());

            this.filterMapByCountry(country);

            // Product fetch: cancel previous if still pending
            if (this.abortCtrl) this.abortCtrl.abort();

            var baseUrl = new URL(window.location.pathname + window.location.search, window.location.origin);
            if (country === 'all') {
                baseUrl.searchParams.delete('sk_country');
            } else {
                baseUrl.searchParams.set('sk_country', country);
            }
            this.fetchProducts(country, baseUrl.toString());
        },

        // ── Client-Side Map Filtering ──────────────────────────────────────

        filterMapByCountry: function(country) {
            if (!this.allFeatures || !this.mapInstance) return;
            if (!this.mapInstance.getSource(this.sourceId)) return;

            var self = this;
            var features;

            if (country === 'all') {
                features = self.allFeatures;
            } else {
                var keywords = self.countryKeywords && self.countryKeywords[country];
                if (!keywords) return;

                features = self.allFeatures.filter(function(f) {
                    var addr = self.getAddress(f);
                    for (var i = 0; i < keywords.length; i++) {
                        if (addr.indexOf(keywords[i]) !== -1) return true;
                    }
                    return false;
                });
            }

            self.mapInstance.getSource(self.sourceId).setData({
                type: 'FeatureCollection',
                features: features
            });

            if (features.length > 0) {
                this.fitMapBounds(self.mapInstance, features);
            }
        },

        fitMapBounds: function(map, features) {
            if (!features.length) return;
            var bounds = [[Infinity, Infinity], [-Infinity, -Infinity]];
            features.forEach(function(f) {
                var c = f.geometry.coordinates;
                bounds[0][0] = Math.min(bounds[0][0], c[0]);
                bounds[0][1] = Math.min(bounds[0][1], c[1]);
                bounds[1][0] = Math.max(bounds[1][0], c[0]);
                bounds[1][1] = Math.max(bounds[1][1], c[1]);
            });
            try {
                map.fitBounds(bounds, { padding: 50, maxZoom: 14, duration: 750 });
            } catch (e) {}
        },

        // ── Server-Side Product Fetching ───────────────────────────────────

        fetchProducts: function(country, url) {
            var self = this;
            var fetchUrl = url || window.location.href;

            var productsWrap = document.querySelector('ul.products')
                            || document.querySelector('.products');
            if (!productsWrap) return;

            productsWrap.style.opacity = '0.4';
            productsWrap.style.pointerEvents = 'none';

            var statusDiv = document.querySelector('.sk-geo-country-filter-status');
            if (statusDiv) {
                statusDiv.textContent = 'Wird gefiltert...';
                statusDiv.classList.add('loading');
            }

            self.abortCtrl = new AbortController();

            fetch(fetchUrl, {
                credentials: 'same-origin',
                cache: 'no-store',
                signal: self.abortCtrl.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(res) { return res.text(); })
            .then(function(html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');

                var newProducts = doc.querySelector('ul.products')
                               || doc.querySelector('.products');

                if (newProducts) {
                    productsWrap.innerHTML = newProducts.innerHTML;
                } else {
                    productsWrap.innerHTML = '<li class="sk-geo-no-products" style="grid-column:1/-1;text-align:center;padding:2rem;color:#8a9bb0;">Keine Produkte in dieser Region gefunden.</li>';
                }

                var currentPag = document.querySelector('nav.woocommerce-pagination, .woocommerce-pagination');
                var newPag = doc.querySelector('nav.woocommerce-pagination, .woocommerce-pagination');
                if (currentPag && newPag) {
                    currentPag.innerHTML = newPag.innerHTML;
                } else if (currentPag && !newPag) {
                    currentPag.remove();
                }

                var currentCount = document.querySelector('.woocommerce-result-count');
                var newCount = doc.querySelector('.woocommerce-result-count');
                if (currentCount && newCount) {
                    currentCount.innerHTML = newCount.innerHTML;
                }

                productsWrap.style.opacity = '';
                productsWrap.style.pointerEvents = '';

                if (statusDiv) {
                    statusDiv.textContent = '';
                    statusDiv.classList.remove('loading');
                }
            })
            .catch(function(err) {
                if (err.name === 'AbortError') return; // cancelled by new click, ignore

                productsWrap.style.opacity = '';
                productsWrap.style.pointerEvents = '';
                if (statusDiv) {
                    statusDiv.textContent = '';
                    statusDiv.classList.remove('loading');
                }
                window.location.href = fetchUrl;
            });
        }
    };

    $(document).ready(function() {
        Handler.init();
    });

})(jQuery);
