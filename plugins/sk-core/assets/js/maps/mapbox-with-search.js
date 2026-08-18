/**
 * Mapbox picker with address search, used by the store settings form.
 *
 * Every map reads its own configuration from data attributes on the wrapper,
 * so more than one map can live on a page.
 */
jQuery(document).ready(function ($) {
    'use strict';

    if (typeof mapboxgl === 'undefined' || typeof MapboxGeocoder === 'undefined') {
        return;
    }

    function initMap(wrap) {
        var config = wrap.dataset;
        var mapboxId = config.mapId;
        var mapEl = document.getElementById(mapboxId);
        var geocoderEl = wrap.querySelector('.sk-geocoder');

        if (!mapEl || !geocoderEl) {
            return;
        }

        var mapLocation = {
            address: config.address || '',
            latitude: parseFloat(config.latitude),
            longitude: parseFloat(config.longitude),
            zoom: parseFloat(config.zoom)
        };

        // Reverse geocoding runs through our own server so the request does not
        // leave the browser with our token. The map service itself still needs
        // the token to draw the tiles.
        var skGeocodeUrl = config.geocodeUrl;
        var skGeocodeNonce = config.geocodeNonce;

        // Update the fields of the form this map belongs to, not every field on the page.
        var scope = wrap.closest('form') || document;

        mapboxgl.accessToken = config.accessToken;

        var skMapbox = new mapboxgl.Map({
            container: mapboxId,
            style: 'mapbox://styles/mapbox/streets-v12',
            center: [mapLocation.longitude, mapLocation.latitude],
            zoom: mapLocation.zoom
        });

        var skGeocoder = null;
        var skMarker = null;

        function onMarkerDragEnd() {
            var lng = skMarker.getLngLat().wrap().lng;
            var lat = skMarker.getLngLat().wrap().lat;

            skMapbox.setCenter([lng, lat]);

            setLocation({
                latitude: lat,
                longitude: lng
            });

            skGeocoder._inputEl.disabled = true;
            skGeocoder._loadingEl.style.display = 'block';

            jQuery.post(skGeocodeUrl, {
                action: 'sk_geo_geocode',
                nonce: skGeocodeNonce,
                lng: lng,
                lat: lat
            }).done(function (response) {
                var features = (response && response.success && response.data) ? response.data.features : null;

                if (features && features.length) {
                    skGeocoder._typeahead.update(features);
                }
            }).always(function () {
                skGeocoder._inputEl.disabled = false;
                skGeocoder._loadingEl.style.display = '';
            });
        }

        function setLocation(newLocation) {
            mapLocation = Object.assign(mapLocation, newLocation);

            $(scope).find('[name="location"]').val(mapLocation.latitude + ',' + mapLocation.longitude);
            $(scope).find('[name="find_address"]').val(mapLocation.address);
        }

        skMapbox.addControl(new mapboxgl.NavigationControl());

        skMapbox.on('load', function () {
            skGeocoder = new MapboxGeocoder({
                accessToken: mapboxgl.accessToken,
                mapboxgl: mapboxgl,
                zoom: skMapbox.getZoom(),
                placeholder: config.placeholder,
                marker: false,
                reverseGeocode: true
            });

            geocoderEl.appendChild(skGeocoder.onAdd(skMapbox));

            skGeocoder.setInput(mapLocation.address);

            skGeocoder.on('result', function (resultData) {
                var result = resultData.result;
                var lngLat = result.center;
                var address = result.place_name;

                skMarker.setLngLat(lngLat);
                skMapbox.setCenter([lngLat[0], lngLat[1]]);

                setLocation({
                    address: address,
                    latitude: lngLat[1],
                    longitude: lngLat[0]
                });
            });
        });

        skMarker = new mapboxgl.Marker({
            draggable: true
        })
            .setLngLat([mapLocation.longitude, mapLocation.latitude])
            .addTo(skMapbox)
            .on('dragend', onMarkerDragEnd);

        $(wrap).find('.sk-map-search').on('input', function (e) {
            setLocation({
                address: e.target.value
            });
        });
    }

    document.querySelectorAll('.sk-map-wrap[data-map-id]').forEach(initMap);
});
