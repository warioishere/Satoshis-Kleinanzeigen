<?php
wp_enqueue_script( 'sk-store-map-google' );
?>
<div class="location-container">
    <div id="sk-store-location"
        class="sk-store-map-google"
        data-latitude="<?php echo esc_attr( $location['latitude'] ); ?>"
        data-longitude="<?php echo esc_attr( $location['longitude'] ); ?>"
        data-zoom="<?php echo esc_attr( $location['zoom'] ); ?>"></div>
</div>
