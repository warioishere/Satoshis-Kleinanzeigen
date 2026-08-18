<?php
wp_enqueue_script( 'sk-store-map-mapbox' );
?>
<div class="location-container">
    <div id="sk-store-location"
        class="sk-store-map-mapbox"
        data-access-token="<?php echo esc_attr( $access_token ); ?>"
        data-latitude="<?php echo esc_attr( $location['latitude'] ); ?>"
        data-longitude="<?php echo esc_attr( $location['longitude'] ); ?>"
        data-zoom="<?php echo esc_attr( $location['zoom'] ); ?>"></div>
</div>
