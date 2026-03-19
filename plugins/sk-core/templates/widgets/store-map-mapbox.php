<script type="text/javascript">
    jQuery( document ).ready( function ( $ ) {
        var accessToken = '<?php echo esc_js( $access_token ); ?>';
        var location = <?php echo wp_json_encode( $location ); ?>

        mapboxgl.accessToken = accessToken;

        var skMapbox = new mapboxgl.Map( {
            container: 'sk-store-location',
            style: 'mapbox://styles/mapbox/streets-v12',
            center: [ location.longitude, location.latitude ],
            zoom: location.zoom,
        } );

        skMapbox.addControl( new mapboxgl.NavigationControl() );

        new mapboxgl.Marker()
            .setLngLat( [ location.longitude, location.latitude ] )
            .addTo( skMapbox );
    } );
</script>
