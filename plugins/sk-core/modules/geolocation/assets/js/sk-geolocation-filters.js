/**
 * SK Geolocation Filters — Address search, distance slider, scope switch, geocoding
 */
(function ($) {
    'use strict';

    function debounce(fn, delay) {
        var timer;
        return function () {
            var args = arguments, ctx = this;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
        };
    }

    function mapboxGetPlaces(query, callback) {
        var token = $('.sk-mapbox-access-token').val();
        if (!token || !query) return;

        if (query.lng && query.lat) query = query.lng + '%2C' + query.lat;

        fetch('https://api.mapbox.com/geocoding/v5/mapbox.places/' + encodeURIComponent(query) +
            '.json?access_token=' + token + '&cachebuster=' + Date.now() + '&autocomplete=true',
            { credentials: 'omit' })
            .then(function (r) { return r.json(); })
            .then(function (data) { if (data.features && callback) callback(data.features); })
            .catch(function () {});
    }

    /* ── Main Filter Class (shop/store pages) ── */
    function GeoFilter(form) {
        this.$form = $(form);
        this.queries = {};
        this.queryParams = [];
        this.scope = this.$form.data('scope') || null;
        this.switchableScope = this.scope || 'product';
        this.latitude = this.$form.find('[name="latitude"]').val() || 0;
        this.longitude = this.$form.find('[name="longitude"]').val() || 0;
        this.init();
    }

    GeoFilter.prototype.init = function () {
        var self = this;

        // Parse existing URL params
        self.parseUrlParams();

        // Show form, hide loading
        self.$form.find('.sk-geolocation-filters-loading').remove();
        self.$form.find('.sk-row').removeClass('sk-hide');

        // Prevent form submit (we redirect manually)
        self.$form.on('submit', function (e) { e.preventDefault(); });

        // Layout columns based on scope
        self.setupLayout();

        // Range slider
        var $slider = self.$form.find('.sk-range-slider');
        var $sliderVal = $slider.prev('.sk-range-slider-value').find('span');
        $slider.on('input', function () { $sliderVal.html(this.value); });
        $slider.on('change', function () { self.setParam('distance', this.value); });

        // Search inputs
        self.$form.find('[name="s"], [name="sk_seller_search"]').on('blur keypress', function (e) {
            if (e.type === 'keypress' && e.which !== 13) return;
            self.setParam('s', $(this).val());
            self.setParam('sk_seller_search', $(this).val());
        });

        // Store filter nonce
        var nonce = self.$form.find('[name="_store_filter_nonce"]').first().val();
        if (nonce) self.setParam('_store_filter_nonce', nonce);

        // Product category picker (React component integration)
        $(document.body).on('sk_product_category_picker_ready', function () {
            var $cat = self.$form.find('[name="product_cat"]');
            if ($cat.length && $cat.val()) self.queries.product_cat = $cat.val();
            $cat.on('change', function () { self.setParam('product_cat', $(this).val()); });
            $(document.body).on('sk_product_category_picker_update', function (e) {
                self.setParam('product_cat', e.detail);
                $cat.val(e.detail);
            });
        });

        // Store categories
        self.$form.find('[name="store_categories"]').on('change', function () {
            self.setParam('store_categories', $(this).val());
        });

        // Search buttons
        self.$form.find('.sk-geo-filters-search-btn').on('click', function (e) {
            e.preventDefault();
            self.redirect(self.switchableScope);
        });
        self.$form.find('.sk-geo-product-search-btn').on('click', function () {
            self.redirect(self.scope);
        });

        // Scope switch
        self.setupScopeSwitch();

        // Address input (Mapbox geocoding)
        self.bindAddressInput();
    };

    GeoFilter.prototype.setupLayout = function () {
        var scope = this.scope;
        var display = this.$form.data('display');
        var hasStoreCategory = this.$form.find('#store-category-dropdown').length > 0;

        if (display !== 'inline') {
            this.$form.find('.sk-geo-filters-column').addClass('sk-w12');
        } else if (scope === 'product') {
            this.$form.find('.sk-geo-filters-column:not(.sk-geo-product-categories)').addClass('sk-w4');
            this.$form.find('.sk-geo-product-categories').addClass('sk-hide');
        } else if (scope === 'vendor' && hasStoreCategory) {
            this.$form.find('.sk-geo-filters-column').addClass('sk-w4');
        } else if (scope === 'vendor') {
            this.$form.find('.sk-geo-filters-column').addClass('sk-w6');
        } else {
            this.$form.find('.sk-geo-filters-column').addClass('sk-w3');
        }

        if (!scope) {
            this.switchableScope = 'product';
            this.$form.find('[name="store_categories"]').parent().addClass('sk-hide');
        }
    };

    GeoFilter.prototype.setupScopeSwitch = function () {
        var self = this;
        var $links = self.$form.find('.sk-geo-filter-scope-switch a');
        var $label = self.$form.find('.sk-geo-filter-scope');
        var hasStoreCategory = self.$form.find('#store-category-dropdown').length > 0;

        $links.on('click', function (e) {
            e.preventDefault();
            var newScope = $(this).data('switch-scope');
            if (newScope === 'product') {
                self.$form.find('[name="s"]').removeClass('sk-hide');
                self.$form.find('[name="sk_seller_search"]').addClass('sk-hide');
                self.$form.find('.sk-geo-product-categories').removeClass('sk-hide');
                self.$form.find('[name="store_categories"]').parent().addClass('sk-hide');
            } else {
                self.$form.find('[name="s"]').addClass('sk-hide');
                self.$form.find('[name="sk_seller_search"]').removeClass('sk-hide');
                self.$form.find('.sk-geo-product-categories').addClass('sk-hide');
                self.$form.find('[name="store_categories"]').parent().removeClass('sk-hide');
            }
            $label.html($(this).html());
            self.switchableScope = newScope;
        });
    };

    GeoFilter.prototype.bindAddressInput = function () {
        var self = this;
        var $input = self.$form.find('.location-address input');
        if (!$input.length) return;

        // Mapbox Suggestions
        if (window.Suggestions && $('.sk-mapbox-access-token').val()) {
            var el = $input.get(0);
            var suggestions = new Suggestions(el, [], { minLength: 3, limit: 3, hideOnBlur: false });
            suggestions.getItemValue = function (item) { return item.place_name; };

            $input.on('change blur', function () {
                if (suggestions.selected && $.trim($(this).val()).length > 0) {
                    var s = suggestions.selected;
                    self.latitude = s.geometry.coordinates[1];
                    self.longitude = s.geometry.coordinates[0];
                    self.setAddress(s.place_name);
                } else {
                    self.setAddress('');
                    self.setParam('distance', '');
                    self.setParam('latitude', '');
                    self.setParam('longitude', '');
                }
            });

            var debouncedSearch = debounce(function (query) {
                mapboxGetPlaces(query, function (features) { suggestions.update(features); });
            }, 250);

            $input.on('input', function () { debouncedSearch($(this).val()); });
        }

        // Locate me button
        var $locate = self.$form.find('.locate-icon');
        var $loader = $locate.next();
        if (navigator.geolocation) {
            $locate.removeClass('sk-hide').on('click', function () {
                $locate.addClass('sk-hide');
                $loader.removeClass('sk-hide');
                navigator.geolocation.getCurrentPosition(function (pos) {
                    $locate.removeClass('sk-hide');
                    $loader.addClass('sk-hide');
                    self.latitude = pos.coords.latitude;
                    self.longitude = pos.coords.longitude;
                    mapboxGetPlaces({ lng: self.longitude, lat: self.latitude }, function (features) {
                        if (features && features.length) {
                            self.setAddress(features[0].place_name);
                            $input.val(features[0].place_name);
                        }
                    });
                }, function () {
                    $locate.removeClass('sk-hide');
                    $loader.addClass('sk-hide');
                });
            });
        }
    };

    GeoFilter.prototype.parseUrlParams = function () {
        var self = this;
        var search = window.location.search.replace('?', '');
        search.split('&').forEach(function (pair) {
            if (!pair) return;
            var parts = pair.split('=');
            var key = parts[0].toLowerCase(), val = parts[1] || '';
            if (key === 'distance') self.distance = parseInt(val, 10);
            if (key === 'latitude') self.latitude = parseFloat(val);
            if (key === 'longitude') self.longitude = parseFloat(val);
            if (key === 'address') self.address = val;
            if (self.queryParams.indexOf(key) < 0) self.queryParams.push(key);
            self.queries[key] = val;
        });
    };

    GeoFilter.prototype.setParam = function (key, val) {
        if (this.queryParams.indexOf(key) < 0) this.queryParams.push(key);
        this[key] = val;
        if (val) this.queries[key] = val;
        else delete this.queries[key];
    };

    GeoFilter.prototype.setAddress = function (addr) {
        this.setParam('address', addr);
        if (!this.distance) {
            var $slider = this.$form.find('.sk-range-slider');
            var val = $slider.val();
            if (!val) {
                var min = parseInt($slider.attr('min'), 10) || 0;
                var max = parseInt($slider.attr('max'), 10) || 100;
                val = Math.ceil((min + max) / 2);
            }
            this.setParam('distance', val);
        }
        this.setParam('latitude', this.latitude);
        this.setParam('longitude', this.longitude);
    };

    GeoFilter.prototype.redirect = function (scope) {
        var params = [];
        for (var key in this.queries) {
            if (['post_type', 'sk_seller_search', 's'].indexOf(key) >= 0) continue;
            if (key === 'distance' && (!this.latitude || !this.longitude)) continue;
            params.push(key + '=' + this.queries[key]);
        }
        var s = this.$form.find('[name="s"]').val() || '';
        var seller = this.$form.find('[name="sk_seller_search"]').val() || '';
        var url;

        if (scope === 'product') {
            if (s) params.push('s=' + s);
            params.push('post_type=product');
            url = this.$form.find('[name="wc_shop_page"]').val();
        } else {
            if (seller) params.push('sk_seller_search=' + seller);
            url = this.$form.find('[name="sk_store_listing_page"]').val();
        }

        window.location.href = url + '?' + params.join('&');
    };

    // Init filter forms on page (exclude store listing — handled separately below)
    $('.sk-geolocation-location-filters').not('.store-lists-other-filter-wrap .sk-geolocation-location-filters').each(function () {
        new GeoFilter(this);
    });

    /* ── Store Lists Filter (simpler version for vendor listing) ── */
    var $storeFilter = $('.store-lists-other-filter-wrap .sk-geolocation-location-filters');
    if ($storeFilter.length) {
        var query = (window.sk && sk.storeLists) ? sk.storeLists.query : {};
        var $slider = $storeFilter.find('.sk-range-slider');
        var $sliderVal = $slider.prev('.sk-range-slider-value').find('span');
        var $addrInput = $storeFilter.find('.location-address input');

        $slider.on('input', function () { $sliderVal.html(this.value); });
        $slider.on('change', function () { query.distance = this.value; });

        $addrInput.on('change', function () {
            var val = this.value;
            query.address = val;
            if (!val) {
                delete query.distance;
                delete query.longitude;
                delete query.latitude;
            }
        });

        // Mapbox address autocomplete for store lists
        if (window.Suggestions && $('.sk-mapbox-access-token').val()) {
            var el = $addrInput.get(0);
            var sugg = new Suggestions(el, [], { minLength: 3, limit: 3, hideOnBlur: false });
            sugg.getItemValue = function (item) { return item.place_name; };

            $addrInput.on('change', function () {
                if (sugg.selected) {
                    query.latitude = sugg.selected.geometry.coordinates[1];
                    query.longitude = sugg.selected.geometry.coordinates[0];
                    query.address = sugg.selected.place_name;
                    if (!query.distance) {
                        var min = parseInt($slider.attr('min'), 10) || 0;
                        var max = parseInt($slider.attr('max'), 10) || 100;
                        query.distance = Math.ceil((min + max) / 2);
                    }
                }
            });

            var debouncedSearch = debounce(function (q) {
                mapboxGetPlaces(q, function (f) { sugg.update(f); });
            }, 250);

            $addrInput.on('input', function () { debouncedSearch($(this).val()); });
        }

        // Locate me
        var $locate = $storeFilter.find('.locate-icon');
        var $loader = $locate.next();
        if (navigator.geolocation) {
            $locate.removeClass('sk-hide').on('click', function () {
                $locate.addClass('sk-hide');
                $loader.removeClass('sk-hide');
                navigator.geolocation.getCurrentPosition(function (pos) {
                    $locate.removeClass('sk-hide');
                    $loader.addClass('sk-hide');
                    query.latitude = pos.coords.latitude;
                    query.longitude = pos.coords.longitude;
                    mapboxGetPlaces({ lng: pos.coords.longitude, lat: pos.coords.latitude }, function (f) {
                        if (f && f.length) {
                            query.address = f[0].place_name;
                            $addrInput.val(f[0].place_name);
                        }
                    });
                }, function () {
                    $locate.removeClass('sk-hide');
                    $loader.addClass('sk-hide');
                });
            });
        }

        $('#sk-store-listing-filter-wrap .sk-geolocation-filters-loading').remove();
    }

    /* ── Simple Dropdown Plugin (for scope switch) ── */
    $(document).on('click', '[data-toggle="sk-geo-dropdown"]', function (e) {
        e.preventDefault();
        var $parent = $(this).parent();
        var isOpen = $parent.hasClass('open');
        $('[data-toggle="sk-geo-dropdown"]').parent().removeClass('open');
        if (!isOpen) $parent.addClass('open');
    });
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.sk-geo-dropdown').length) {
            $('[data-toggle="sk-geo-dropdown"]').parent().removeClass('open');
        }
    });

})(jQuery);
