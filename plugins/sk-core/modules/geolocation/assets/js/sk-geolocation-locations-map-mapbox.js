/**
 * SK Geolocation — Mapbox Locations Map with Marker Clusters
 * Renders products/vendors on a Mapbox map with clustering + popups.
 */
(function ($) {
    'use strict';

    if (!$('#sk-geolocation-locations-map').length || !window.SkGeo || !SkGeo.mapbox_access_token) return;

    var LocationsMap = {
        map: null,
        data: { type: 'FeatureCollection', features: [] },
        markerImages: { image: null, clusterer: null },

        init: function () {
            var self = this;
            var avgLng = 0, avgLat = 0, count = 0;

            mapboxgl.accessToken = SkGeo.mapbox_access_token;

            // Collect GeoJSON features from hidden inputs
            $('[name="sk_geolocation[]"]').each(function (i) {
                var $el = $(this);
                var lat = $el.data('latitude'), lng = $el.data('longitude');
                if (!lat || !lng) return;

                self.data.features.push({
                    type: 'Feature',
                    properties: { id: 'sk-geo-item-' + i, info: $el.data('info') },
                    geometry: { type: 'Point', coordinates: [lng, lat, 0] }
                });
                avgLng += lng;
                avgLat += lat;
                count++;
            });

            // Determine center
            var center = [SkGeo.default_geolocation.longitude, SkGeo.default_geolocation.latitude];
            if (count > 0) {
                center = [avgLng / count, avgLat / count];
            } else {
                var params = new URLSearchParams(window.location.search);
                var pLng = parseFloat(params.get('longitude'));
                var pLat = parseFloat(params.get('latitude'));
                if (!isNaN(pLng) && !isNaN(pLat)) center = [pLng, pLat];
            }

            self.map = new mapboxgl.Map({
                container: 'sk-geolocation-locations-map',
                style: 'mapbox://styles/mapbox/streets-v12',
                center: center,
                zoom: SkGeo.map_zoom || 5
            });

            self.map.addControl(new mapboxgl.NavigationControl());

            self.map.on('load', function () {
                self.loadImage('image', SkGeo.marker.image);
                self.loadImage('clusterer', SkGeo.marker.clusterer);
            });
        },

        loadImage: function (key, url) {
            var self = this;
            self.map.loadImage(url, function (err, img) {
                if (err) return;
                self.markerImages[key] = img;
                self.map.addImage('sk-marker-' + key, img);
                self.addLayers();
            });
        },

        addLayers: function () {
            var self = this;
            if (!self.markerImages.image || !self.markerImages.clusterer) return;

            self.map.addSource('sk_geo_data', {
                type: 'geojson',
                data: self.data,
                cluster: true,
                clusterMaxZoom: 14,
                clusterRadius: 50
            });

            // Cluster icons
            self.map.addLayer({
                id: 'clusters', type: 'symbol', source: 'sk_geo_data',
                filter: ['has', 'point_count'],
                layout: { 'icon-image': 'sk-marker-clusterer', 'icon-allow-overlap': true, 'text-allow-overlap': true }
            });

            // Cluster count text
            self.map.addLayer({
                id: 'cluster-count', type: 'symbol', source: 'sk_geo_data',
                filter: ['has', 'point_count'],
                layout: { 'text-field': '{point_count_abbreviated}', 'text-font': ['DIN Offc Pro Medium', 'Arial Unicode MS Bold'], 'text-size': 12 },
                paint: { 'text-color': 'rgb(253, 218, 206)' }
            });

            // Single point markers
            self.map.addLayer({
                id: 'unclustered-point', type: 'symbol', source: 'sk_geo_data',
                filter: ['!', ['has', 'point_count']],
                layout: { 'icon-image': 'sk-marker-image', 'icon-allow-overlap': true, 'text-allow-overlap': true, 'icon-size': 1, 'icon-anchor': 'bottom' }
            });

            // Cluster click → zoom or show modal
            self.map.on('click', 'clusters', function (e) {
                var clusterId = self.map.queryRenderedFeatures(e.point, { layers: ['clusters'] })[0].properties.cluster_id;
                var currentZoom = self.map.getZoom();

                self.map.getSource('sk_geo_data').getClusterLeaves(clusterId, 255, 0, function (err, leaves) {
                    if (currentZoom < 14) {
                        self.map.getSource('sk_geo_data').getClusterExpansionZoom(clusterId, function (err, zoom) {
                            if (!err) self.map.easeTo({ center: leaves[0].geometry.coordinates, zoom: Math.min(zoom, 14) });
                        });
                    } else if (leaves.length > 1) {
                        var html = '<div class="sk-geo-map-info-windows-in-popup">';
                        leaves.forEach(function (l) { html += self.renderInfo(l.properties.info); });
                        html += '</div>';
                        self.showModal(html);
                    } else {
                        self.map.easeTo({ center: leaves[0].geometry.coordinates, zoom: currentZoom });
                    }
                });
            });

            self.map.on('mouseenter', 'clusters', function () { self.map.getCanvas().style.cursor = 'pointer'; });
            self.map.on('mouseleave', 'clusters', function () { self.map.getCanvas().style.cursor = ''; });

            // Single marker click → popup
            self.map.on('click', 'unclustered-point', function (e) {
                var info = self.map.queryRenderedFeatures(e.point, { layers: ['unclustered-point'] })[0].properties.info;
                if (!info) return;
                self.map.easeTo({ center: e.lngLat });
                new mapboxgl.Popup({ closeOnClick: true })
                    .setLngLat(e.lngLat)
                    .setHTML(self.renderInfo(info))
                    .setMaxWidth('654px')
                    .addTo(self.map);
            });
        },

        renderInfo: function (info) {
            if (typeof info === 'string') info = JSON.parse(info);
            var html = SkGeo.info_window_template;
            for (var key in info) html = html.replace('{' + key + '}', info[key]);
            return html;
        },

        showModal: function (content) {
            var $modal = $('.sk-geo-location-modals');
            if ($.fn.iziModal && $modal.length) {
                $modal.iziModal('setContent', content);
                $modal.iziModal('open');
            } else {
                // Fallback: simple overlay
                var overlay = document.createElement('div');
                overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:99999;display:flex;align-items:center;justify-content:center;';
                var box = document.createElement('div');
                box.style.cssText = 'background:#1f2933;color:#e2e8f0;border-radius:12px;padding:24px;max-width:654px;width:90%;max-height:80vh;overflow-y:auto;';
                box.innerHTML = '<button onclick="this.closest(\'div[style]\').remove()" style="float:right;background:none;border:none;color:#8b949e;font-size:24px;cursor:pointer;">&times;</button>' + content;
                overlay.appendChild(box);
                overlay.addEventListener('click', function (e) { if (e.target === overlay) overlay.remove(); });
                document.body.appendChild(overlay);
            }
        }
    };

    // Init modal for iziModal if available
    if ($.fn.iziModal) {
        $('.sk-geo-location-modals').iziModal({
            closeButton: true, appendTo: 'body', title: '',
            headerColor: (window.sk && sk.modal_header_color) || '#f7931a'
        });
    }

    LocationsMap.init();

    // Expose for country filter and other extensions
    SkGeo.LocationsMaps = LocationsMap;

})(jQuery);
