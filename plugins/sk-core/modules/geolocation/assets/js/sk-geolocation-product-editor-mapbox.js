/**
 * SK Geolocation — Vendor Dashboard Product Location Editor (Mapbox)
 * Sets product location via draggable marker + geocoder search.
 */
(function ($) {
    'use strict';

    var $container = $('#sk-geolocation-product-location');
    if (!$container.length) return;

    var map, marker, geocoder;
    var $useStore = $('#_sk_geolocation_use_store_settings');
    var $lat = $('[name="_sk_geolocation_product_sk_geo_latitude"]');
    var $lng = $('[name="_sk_geolocation_product_sk_geo_longitude"]');
    var $addr = $('#_sk_geolocation_product_location');
    var state = { latitude: $lat.val(), longitude: $lng.val(), address: $addr.val(), zoom: 12 };

    function syncState(data) {
        Object.assign(state, data);
        $lat.val(state.latitude);
        $lng.val(state.longitude);
        $addr.val(state.address);
    }

    function initMap() {
        var token = $('[name="_sk_geolocation_mapbox_access_token"]').val();
        if (!token || map) return;

        mapboxgl.accessToken = token;

        map = new mapboxgl.Map({
            container: 'sk-geolocation-product-location-map',
            style: 'mapbox://styles/mapbox/streets-v12',
            center: [state.longitude, state.latitude],
            zoom: state.zoom
        });

        map.addControl(new mapboxgl.NavigationControl());

        // Search button control
        var searchBtn = document.createElement('button');
        searchBtn.type = 'button';
        searchBtn.innerHTML = '<i class="fa fa-search"></i> Search Map';
        searchBtn.addEventListener('click', function (e) {
            e.preventDefault();
            var ctrl = document.getElementById('sk-geolocation-product-location-map')
                .getElementsByClassName('mapboxgl-ctrl-top-left')[0];
            if (ctrl) ctrl.classList.add('show-geocoder');
        });

        var searchControl = { onAdd: function () {
            var div = document.createElement('div');
            div.className = 'mapboxgl-ctrl mapboxgl-ctrl-group sk-mapboxgl-ctrl';
            div.appendChild(searchBtn);
            return div;
        }, onRemove: function () {} };

        map.addControl(searchControl, 'top-left');

        map.on('load', function () {
            geocoder = new MapboxGeocoder({
                accessToken: mapboxgl.accessToken,
                mapboxgl: mapboxgl,
                zoom: map.getZoom(),
                placeholder: 'Search Address',
                marker: false,
                reverseGeocode: true
            });

            map.addControl(geocoder, 'top-left');
            geocoder.setInput(state.address);

            geocoder.on('result', function (e) {
                var center = e.result.center;
                marker.setLngLat(center);
                map.setCenter(center);
                syncState({ address: e.result.place_name, latitude: center[1], longitude: center[0] });
            });
        });

        marker = new mapboxgl.Marker({ draggable: true })
            .setLngLat([state.longitude, state.latitude])
            .addTo(map)
            .on('dragend', onMarkerDrag);
    }

    function onMarkerDrag() {
        var lngLat = marker.getLngLat().wrap();
        map.setCenter([lngLat.lng, lngLat.lat]);
        syncState({ latitude: lngLat.lat, longitude: lngLat.lng });

        // Reverse geocode to get address
        var url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/' +
            lngLat.lng + '%2C' + lngLat.lat +
            '.json?access_token=' + mapboxgl.accessToken +
            '&cachebuster=' + Date.now() + '&autocomplete=true';

        geocoder._inputEl.disabled = true;
        geocoder._loadingEl.style.display = 'block';

        fetch(url, { credentials: 'omit' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.features && geocoder._typeahead) {
                    geocoder._typeahead.update(data.features);
                    $(map._controlContainer).find('.mapboxgl-ctrl-top-left').addClass('show-geocoder');
                }
            })
            .finally(function () {
                geocoder._inputEl.disabled = false;
                geocoder._loadingEl.style.display = '';
            });
    }

    // Use store settings toggle
    $useStore.on('change', function () {
        $('#sk-geolocation-product-location-no-store-settings').toggleClass('sk-hide');
        $container.toggleClass('sk-hide');
        if (!$(this).is(':checked') && !map) initMap();
    });

    // Locate me button
    var $locate = $container.find('.locate-icon');
    if (navigator.geolocation) {
        $locate.on('click', function () {
            navigator.geolocation.getCurrentPosition(function (pos) {
                var lat = pos.coords.latitude, lng = pos.coords.longitude;
                marker.setLngLat([lng, lat]);
                map.setCenter([lng, lat]);
                syncState({ latitude: lat, longitude: lng });
            });
        });
    } else {
        $locate.addClass('sk-hide');
    }

    // Address input sync
    $('#sk-map-add').on('input', function () {
        syncState({ address: this.value });
    });

    // Init if not using store settings
    if (!$useStore.is(':checked')) initMap();

})(jQuery);
