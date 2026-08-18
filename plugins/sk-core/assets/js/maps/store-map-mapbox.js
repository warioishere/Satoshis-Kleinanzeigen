/**
 * Read-only Mapbox map of the store location widget.
 *
 * Each container carries its own coordinates as data attributes, so several
 * widget instances on one page each get their own map.
 */
jQuery( document ).ready( function () {
    'use strict';

    if ( typeof mapboxgl === 'undefined' ) {
        return;
    }

    document.querySelectorAll( '.sk-store-map-mapbox[data-longitude]' ).forEach( function ( el ) {
        var longitude = parseFloat( el.dataset.longitude );
        var latitude  = parseFloat( el.dataset.latitude );

        mapboxgl.accessToken = el.dataset.accessToken;

        var skMapbox = new mapboxgl.Map( {
            container: el,
            style: 'mapbox://styles/mapbox/streets-v12',
            center: [ longitude, latitude ],
            zoom: parseFloat( el.dataset.zoom ),
        } );

        skMapbox.addControl( new mapboxgl.NavigationControl() );

        new mapboxgl.Marker()
            .setLngLat( [ longitude, latitude ] )
            .addTo( skMapbox );
    } );
} );
