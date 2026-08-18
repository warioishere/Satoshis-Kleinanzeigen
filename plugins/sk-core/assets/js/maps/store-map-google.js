/**
 * Read-only Google map of the store location widget.
 *
 * Each container carries its own coordinates as data attributes, so several
 * widget instances on one page each get their own map.
 */
jQuery( document ).ready( function () {
    'use strict';

    document.querySelectorAll( '.sk-store-map-google[data-longitude]' ).forEach( function ( el ) {
        try {
            var curpoint = new google.maps.LatLng( parseFloat( el.dataset.latitude ), parseFloat( el.dataset.longitude ) );

            var gmap = new google.maps.Map( el, {
                center: curpoint,
                zoom: parseFloat( el.dataset.zoom ),
                mapTypeId: window.google.maps.MapTypeId.ROADMAP,
            } );

            new window.google.maps.Marker( {
                position: curpoint,
                map: gmap
            } );
        } catch ( error ) {
            console.log( error );
        }
    } );
} );
