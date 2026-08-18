<?php
wp_enqueue_style( 'sk-map-picker' );
wp_enqueue_script( 'sk-mapbox-with-search' );
?>
<input id="sk-map-lat" type="hidden" name="location" value="<?php echo esc_attr( $map_location ); ?>" size="30" />

<div class="sk-map-wrap"
    data-map-id="<?php echo esc_attr( $map_id ); ?>"
    data-access-token="<?php echo esc_attr( $access_token ); ?>"
    data-address="<?php echo esc_attr( $location['address'] ); ?>"
    data-latitude="<?php echo esc_attr( $location['latitude'] ); ?>"
    data-longitude="<?php echo esc_attr( $location['longitude'] ); ?>"
    data-zoom="<?php echo esc_attr( $location['zoom'] ); ?>"
    data-geocode-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
    data-geocode-nonce="<?php echo esc_attr( wp_create_nonce( 'sk_geo_geocode' ) ); ?>"
    data-placeholder="<?php esc_attr_e( 'Search Address', 'sk-core' ); ?>">
    <div class="sk-map-search-bar">
        <input id="sk-map-add" type="hidden" class="sk-map-search" value="<?php echo esc_attr( $map_address ); ?>" name="find_address" placeholder="<?php esc_attr_e( 'Address', 'sk-core' ); ?>" size="30" />
        <a href="#" class="sk-map-find-btn" id="sk-location-find-btn" type="button"><?php esc_html_e( 'Find Address', 'sk-core' ); ?></a>
    </div>

    <div class="sk-maps-container">
        <div id="sk-geocoder" class="sk-geocoder"></div>
        <div id="<?php echo esc_attr( $map_id ); ?>" class="sk-map-canvas"></div>
    </div>
</div>

