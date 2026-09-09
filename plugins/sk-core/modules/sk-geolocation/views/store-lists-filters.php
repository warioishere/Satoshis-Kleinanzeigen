<div class="sk-geolocation-location-filters">
    <div class="sk-geo-filters-column">
        <input type="text" class="store-search-input sk-form-control" name="sk_seller_search" placeholder="<?php echo esc_attr( $placeholders['search_vendors'] ); ?>">
    </div>

    <div class="sk-geo-filters-column">
        <div class="location-address">
            <input type="text" name="address" placeholder="<?php echo esc_attr( $placeholders['location'] ); ?>" value="<?php echo esc_attr( $address ); ?>">

            <?php if ( is_ssl() || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ): ?>
                <i class="locate-icon sk-hide" style="background-image: url(<?php echo SK_GEOLOCATION_URL . '/assets/images/locate.svg'; ?>)"></i>
                <i class="locate-loader sk-hide" style="background-image: url(<?php echo SK_GEOLOCATION_URL . '/assets/images/spinner.svg'; ?>)"></i>
            <?php endif; ?>
        </div>
    </div>

    <div class="range-slider-container">
        <span class="sk-range-slider-value sk-left">
            <?php _e( 'Radius', 'sk-core' ); ?> <span><?php echo $distance; ?></span><?php echo $slider['unit']; ?>
        </span>

        <input
            class="sk-range-slider sk-left"
            type="range"
            name="distance"
            value="<?php echo esc_attr( $distance ); ?>"
            min="<?php echo esc_attr( $slider['min'] ); ?>"
            max="<?php echo esc_attr( $slider['max'] ); ?>"
        >
    </div>

    <input type="hidden" name="latitude" value="<?php echo esc_attr( $latitude ); ?>">
    <input type="hidden" name="longitude" value="<?php echo esc_attr( $longitude ); ?>">

</div>
