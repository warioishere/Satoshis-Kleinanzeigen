<?php

/**
 * Include Geolocation template
 *
 *
 * @param string $name
 * @param array  $args
 *
 * @return void
 */
function sk_geo_get_template( $name, $args = [] ) {
    sk_get_template( "$name.php", $args, 'sk/modules/geolocation', trailingslashit( SK_GEOLOCATION_VIEWS ) );
}

/**
 * Default geolocation latitude and longitude
 *
 *
 * @return array
 */
function sk_geo_get_default_location() {
    $location = sk_get_option( 'location', 'sk_geolocation' );

    // Fallback when no default is configured under SK > Geolocation. The
    // upstream code shipped the vendor's own office in Dhaka here, which is
    // how listings without coordinates ended up on the map in Bangladesh.
    if ( empty( $location['latitude'] ) || empty( $location['longitude'] ) ) {
        $location              = [];
        $location['latitude']  = 52.520008;
        $location['longitude'] = 13.404954;
        $location['address']   = 'Berlin, Deutschland';
    }

    /**
     * Filter default latitude and longitude use by Geolocation module
     *
     *
     * @param array $location
     */
    return apply_filters( 'sk_geolocation_default_location', $location );
}

/**
 * Enqueue locations map style and scripts
 *
 *
 * @return void
 */
function sk_geo_enqueue_locations_map() {
    ob_start();
    sk_geo_get_template( 'map-marker-info-window' );
    $info_window_template = ob_get_clean();

    sk()->scripts->load_map_scripts();

    wp_enqueue_style( 'sk-geo-locations-map' );
    wp_enqueue_script( 'sk-geo-locations-map' );

    /**
     * Filter to modify the map marker image
     *
     *
     * @param string $marker_image_path
     */
    $image = apply_filters( 'sk_geolocation_marker_image_path', SK_GEOLOCATION_ASSETS . '/images/marker-32x32.png' );

    /**
     * Filter to modify the map marker clusterer images
     *
     *
     * @param string
     */
    $clusterer = apply_filters( 'sk_geolocation_marker_clusterer_image_path', SK_GEOLOCATION_ASSETS . '/images/clusterer-40x40.png' );

    $sk_geo = array(
        'marker' => array(
            'image'     => $image,
            'clusterer' => $clusterer,
        ),
        'info_window_template' => $info_window_template,
        'default_geolocation'  => sk_geo_get_default_location(),
        'map_zoom'             => sk_get_option( 'map_zoom', 'sk_geolocation', 11 ),
        'is_auto_zoom'         => is_singular( 'product' ) ? 0 : 1, // Autozoom only work when pass 1
    );

    $sk_geo['mapbox_access_token'] = sk_get_option( 'mapbox_access_token', 'sk_appearance', null );

    wp_localize_script( 'sk-geo-locations-map', 'SkGeo', $sk_geo );
}

/**
 * Geolocation Filter Form
 *
 *
 * @param string $scope   null|product|vendor
 * @param string $display inline|block
 *
 * @return void
 */
function sk_geo_filter_form( $scope = '', $display = 'inline' ) {
    global $wp;

    sk()->scripts->load_map_scripts();

    wp_enqueue_style( 'sk-geo-filters' );
    wp_enqueue_script( 'sk-geo-filters' );

    $get_data = wp_unslash( $_GET ); // phpcs:ignore

    $s             = get_query_var( 's', '' );
    $seller_s      = isset( $get_data['sk_seller_search'] ) ? sanitize_text_field( $get_data['sk_seller_search'] ) : '';
    $search_query  = $seller_s;
    $latitude      = isset( $get_data['latitude'] ) ? sanitize_text_field( $get_data['latitude'] ) : null;
    $longitude     = isset( $get_data['longitude'] ) ? sanitize_text_field( $get_data['longitude'] ) : null;
    $address       = isset( $get_data['address'] ) ? sanitize_text_field( $get_data['address'] ) : '';
    $distance_min  = sk_get_option( 'distance_min', 'sk_geolocation', 0 );
    $distance_max  = sk_get_option( 'distance_max', 'sk_geolocation', 10 );
    $distance_unit = sk_get_option( 'distance_unit', 'sk_geolocation', 'km' );
    $distance      = isset( $get_data['distance'] ) ? sanitize_text_field( $get_data['distance'] ) : $distance_max;

    /**
     * Add wc_product_dropdown_categories argument filter
     *
     *
     * @param array $args
     */
    $wc_categories_args = apply_filters(
        'sk_geolocation_product_dropdown_categories_args', array(
            'pad_counts' => 0,
            'show_count' => 0,
        )
    );

    $get_current_page_url = home_url();

    $shop_page_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';

    if ( ! $shop_page_url ) {
        $shop_page_url = $get_current_page_url;
    }

    $store_listing_page_url = $get_current_page_url;

    if ( $scope === 'vendor' || $scope === '' ) {
        $store_listing_page_url = get_permalink( sk_get_option( 'store_listing', 'sk_pages', 0 ) );
    }

    global $post;
    if ( isset( $post->post_content ) && has_shortcode( $post->post_content, 'sk-stores' ) ) {
        $store_listing_page_url = home_url( $wp->request );
    }

    $args = array(
        'scope'      => $scope,
        'display'    => $display,
        's'          => $s,
        'seller_s'   => $seller_s,
        'latitude'   => $latitude,
        'longitude'  => $longitude,
        'address'    => $address,
        'distance'   => absint( $distance ),
        'placeholders' => array(
            'search_all'      => sk_get_option( 'placeholder_search_all', 'sk_geolocation', __( 'Search Vendors or Products', 'sk' ) ),
            'search_vendors'  => sk_get_option( 'placeholder_search_vendors', 'sk_geolocation', __( 'Search Vendors', 'sk' ) ),
            'search_products' => sk_get_option( 'placeholder_search_product', 'sk_geolocation', __( 'Search Products', 'sk' ) ),
            'location'        => sk_get_option( 'placeholder_location', 'sk_geolocation', __( 'Location', 'sk' ) ),
        ),
        'slider' => array(
            'min'      => $distance_min,
            'max'      => $distance_max,
            'unit'     => ( 'km' === $distance_unit ) ? 'km' : 'miles',
        ),
        'wc_categories_args' => $wc_categories_args,
        'wc_shop_page'       => $shop_page_url,
        'store_listing_page' => remove_query_arg( 'lang', $store_listing_page_url ),
    );

    if ( sk_is_store_categories_feature_on() ) {
        $args['categories'] = get_terms(
            array(
                'taxonomy'   => 'store_category',
                'hide_empty' => false,
            )
        );

        $args['store_category'] = ! empty( $get_data['store_categories'] ) ? sanitize_text_field( $get_data['store_categories'] ) : null;
    }

    $mapbox_access_token = sk_get_option( 'mapbox_access_token', 'sk_appearance', null );

    if ( $mapbox_access_token ) {
        $args['mapbox_access_token'] = $mapbox_access_token;
    }

    sk_geo_get_template( 'filters', $args );
}

/**
 * Prints product location map with address
 *
 *
 * @return void
 */
function sk_geo_product_location() {
    global $product;

    if ( ! $product instanceof WC_Product ) {
        $product = wc_get_product( get_the_ID() );
    }

    sk_geo_enqueue_locations_map();

    $args = array(
        'address' => $product->get_meta( 'sk_geo_address', true ),
    );

    sk_geo_get_template( 'product-location', $args );
}

/**
 * A helper function to remove Geolocation hook in seller listing footer content
 *
 *
 * @return void
 */
function sk_geo_remove_seller_listing_footer_content_hook() {
    add_action(
        'sk_seller_listing_footer_content', function () {
            remove_action( 'sk_seller_listing_footer_content', array( SK_Geolocation_Vendor_View::class, 'seller_listing_footer_content' ), 11, 1 );
        }, 9
    );
}

/**
 * A helper function to escape float values
 *
 *
 * @return float
 */
function sk_geo_float_val( $val ) {
    return floatval( preg_replace( '/[^-0-9\.]/', '', $val ) );
}

/**
 * Geolocation Store Lists Filter Form
 *
 *
 * @return void
 */
function sk_geo_store_lists_filter_form() {
    sk()->scripts->load_map_scripts();

    wp_enqueue_style( 'sk-geo-filters' );
    wp_enqueue_script( 'sk-geo-filters-store-lists' );

    $get_data = wp_unslash( $_GET ); // phpcs:ignore

    $latitude      = isset( $get_data['latitude'] ) ? sanitize_text_field( $get_data['latitude'] ) : null;
    $longitude     = isset( $get_data['longitude'] ) ? sanitize_text_field( $get_data['longitude'] ) : null;
    $address       = isset( $get_data['address'] ) ? sanitize_text_field( $get_data['address'] ) : '';
    $distance_min  = sk_get_option( 'distance_min', 'sk_geolocation', 0 );
    $distance_max  = sk_get_option( 'distance_max', 'sk_geolocation', 10 );
    $distance_unit = sk_get_option( 'distance_unit', 'sk_geolocation', 'km' );
    $distance      = isset( $get_data['distance'] ) ? sanitize_text_field( $get_data['distance'] ) : $distance_max;

    $args = [
        'latitude'  => $latitude,
        'longitude' => $longitude,
        'address'   => $address,
        'distance'  => absint( $distance ),
        'placeholders' => [
            'location'       => sk_get_option( 'placeholder_location', 'sk_geolocation', __( 'Location', 'sk' ) ),
            'search_vendors' => sk_get_option( 'placeholder_search_vendors', 'sk_geolocation', __( 'Search Vendors', 'sk' ) ),
        ],
        'slider' => [
            'min'  => $distance_min,
            'max'  => $distance_max,
            'unit' => ( 'km' === $distance_unit ) ? 'km' : 'miles',
        ],
    ];

    $mapbox_access_token = sk_get_option( 'mapbox_access_token', 'sk_appearance', null );

    if ( $mapbox_access_token ) {
        $args['mapbox_access_token'] = $mapbox_access_token;
    }

    sk_geo_get_template( 'store-lists-filters', $args );
}

/**
 * Retrieves product related data.
 *
 *
 * @param int $product_id
 *
 * @return array
 */
function sk_geo_get_product_data( $product_id ) {
    $store_id           = sk_get_current_user_id();
    $store_geo_latitude = get_user_meta( $store_id, 'sk_geo_latitude', true );
    $use_store_settings = 'no' !== get_post_meta( $product_id, '_sk_geolocation_use_store_settings', true );

    if ( $use_store_settings ) {
        $sk_geo_latitude  = get_user_meta( $store_id, 'sk_geo_latitude', true );
        $sk_geo_longitude = get_user_meta( $store_id, 'sk_geo_longitude', true );
        $sk_geo_public    = get_user_meta( $store_id, 'sk_geo_public', true );
        $sk_geo_address   = get_user_meta( $store_id, 'sk_geo_address', true );
    } else {
        $sk_geo_latitude  = get_post_meta( $product_id, 'sk_geo_latitude', true );
        $sk_geo_longitude = get_post_meta( $product_id, 'sk_geo_longitude', true );
        $sk_geo_public    = get_post_meta( $product_id, 'sk_geo_public', true );
        $sk_geo_address   = get_post_meta( $product_id, 'sk_geo_address', true );
    }

    // The default is only where the map opens, never a value that gets filled
    // into the form. Otherwise a vendor who never picked a place would save it
    // as their location on the next save.
    $default_location = sk_geo_get_default_location();

    return [
        'use_store_settings'  => $use_store_settings ? 'yes' : 'no',
        'sk_geo_latitude'  => $sk_geo_latitude,
        'sk_geo_longitude' => $sk_geo_longitude,
        'sk_geo_public'    => $sk_geo_public,
        'sk_geo_address'   => $sk_geo_address,
        'map_center_lat'   => $default_location['latitude'],
        'map_center_lng'   => $default_location['longitude'],
        'store_has_settings'  => ! empty( $store_geo_latitude ),
        'store_settings_url'  => sk_get_navigation_url( 'settings/store' ),
    ];
}
