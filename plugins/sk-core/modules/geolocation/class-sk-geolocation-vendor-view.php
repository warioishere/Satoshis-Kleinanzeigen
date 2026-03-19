<?php

/**
 * Shows location maps for Vendors
 *
 */
class SK_Geolocation_Vendor_View {

    /**
     * Map location
     *
     * Possible values: top, left, right
     *
     *
     * @var string
     */
    private static $map_location = 'top';

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        self::$map_location = sk_get_option( 'show_locations_map', 'sk_geolocation', 'top' );

        add_action( 'sk_before_seller_listing_loop', array( self::class, 'before_seller_listing_loop' ) );
        add_action( 'sk_after_seller_listing_loop', array( self::class, 'after_seller_listing_loop' ) );
        add_action( 'sk_seller_listing_footer_content', array( self::class, 'seller_listing_footer_content' ), 11, 1 );
        add_action( 'sk_store_lists_filter_form', array( self::class, 'load_store_lists_filter' ) );

        add_filter( 'sk_show_seller_search', '__return_false' );
    }

    /**
     * Include locations map template in store listing page
     *
     *
     * @return void
     */
    public static function before_seller_listing_loop() {
        if ( ! self::is_geolocation_show_on_store_listing_page() ) {
            return;
        }

        sk_geo_enqueue_locations_map();

        $show_filters = sk_get_option( 'show_filters_before_locations_map', 'sk_geolocation', 'on' );

        switch ( self::$map_location ) {
            case 'right':
                echo '<div class="sk-geolocation-row sk-geolocation-map-right"><div class="sk-geolocation-col-7">';

                if ( 'on' === $show_filters ) {
                    sk_geo_filter_form( 'vendor' );
                }
                break;

            case 'left':
                echo '<div class="sk-geolocation-row sk-geolocation-map-left"><div class="sk-geolocation-col-5">';
                sk_geo_get_template( 'map', [ 'layout' => 'left' ] );
                echo '</div><div class="sk-geolocation-col-7">';

                if ( 'on' === $show_filters ) {
                    sk_geo_filter_form( 'vendor' );
                }
                break;

            case 'top':
            default:
                if ( 'on' === $show_filters ) {
                    sk_geo_filter_form( 'vendor' );
                }

                sk_geo_get_template( 'map', [ 'layout' => 'top' ] );
                break;
        }

    }


    /**
     * Include locations map template in store listing page
     *
     *
     * @return void
     */
    public static function before_store_lists_filter_left() {
        if ( ! self::is_geolocation_show_on_store_listing_page() ) {
            return;
        }

        $show_filters = sk_get_option( 'show_filters_before_locations_map', 'sk_geolocation', 'on' );

        sk_geo_enqueue_locations_map();
        sk_geo_get_template( 'loading', [ 'show_filters' => $show_filters ] );
        sk_geo_get_template( 'map', [ 'layout' => 'top' ] );
    }

    /**
     * Include location filter form in store listing page
     *
     *
     * @return void
     */
    public static function before_store_lists_filter_category() {
        if ( ! self::is_geolocation_show_on_store_listing_page() ) {
            return;
        }

        sk_geo_store_lists_filter_form();
    }

    /**
     * Include locations map template in store listing page
     *
     *
     * @return void
     */
    public static function after_seller_listing_loop() {
        if ( ! self::is_geolocation_show_on_store_listing_page() ) {
            return;
        }

        switch ( self::$map_location ) {
            case 'right':
                echo '</div><div class="sk-geolocation-col-5">';
                sk_geo_get_template( 'map', [ 'layout' => 'right' ] );
                echo '</div></div>';
                break;

            case 'left':
                echo '</div></div>';
                break;

            default:
                break;
        }
    }

    /**
     * Include geolocation data for every vendor
     *
     *
     * @param WP_User $seller
     *
     * @return void
     */
    public static function seller_listing_footer_content( $seller ) {
        $seller_id = $seller->ID ?? $seller->id ?? 0;
        $lat = get_user_meta( $seller_id, 'sk_geo_latitude', true );
        $lng = get_user_meta( $seller_id, 'sk_geo_longitude', true );
        if ( ! $seller_id ) {
            return;
        }

        if ( empty( $lat ) || empty( $lng ) ) {
            return;
        }

        $vendor = new SK\Core\Vendor\Vendor( $seller );

        $info_window_data = array(
            'title'   => $vendor->get_shop_name(),
            'link'    => sk_get_store_url( $vendor->get_id() ),
            'image'   => $vendor->get_avatar(),
            'address' => get_user_meta( $vendor->get_id(), 'sk_geo_address', true ),
        );

        /**
         * Filter to modify vendor data for map marker info window
         *
         *
         * @param array        $info_window_data
         * @param SK\Core\Vendor\Vendor $vendor
         */
        $info = apply_filters( 'sk_geolocation_info_vendor', $info_window_data, $vendor );

        $args = array(
            'id'                  => $seller->ID,
            'sk_geo_latitude'  => get_user_meta( $vendor->get_id(), 'sk_geo_latitude', true ),
            'sk_geo_longitude' => get_user_meta( $vendor->get_id(), 'sk_geo_longitude', true ),
            'sk_geo_address'   => get_user_meta( $vendor->get_id(), 'sk_geo_address', true ),
            'info'                => wp_json_encode( $info ),
        );

        sk_geo_get_template( 'item-geolocation-data', $args );
    }

    /**
     * Load store lists filter
     *
     *
     * @return void
     */
    public static function load_store_lists_filter() {
        $show_filters = sk_get_option( 'show_filters_before_locations_map', 'sk_geolocation', 'on' );

        if ( 'on' === $show_filters ) {
            /**
             * Since here we removing top bar search filter which one comes from sk lite
             * because when geolocation use left or right then here we adding new search
             * filter and removing top search area
             */
            add_filter( 'sk_load_store_lists_filter_search_bar', '__return_false', 99 );
        }

        if ( 'top' !== self::$map_location ) {
            return;
        }

        remove_action( 'sk_before_seller_listing_loop', array( self::class, 'before_seller_listing_loop' ) );
        add_action( 'sk_before_store_lists_filter_left', array( self::class, 'before_store_lists_filter_left' ) );

        if ( 'on' === $show_filters ) {
            add_action( 'sk_before_store_lists_filter_category', array( self::class, 'before_store_lists_filter_category' ) );
        }
    }

    /**
     * Is geolocation show on store listing page
     *
     *
     * @return bool
     */
    public static function is_geolocation_show_on_store_listing_page() {
        $show_map_pages = sk_get_option( 'show_location_map_pages', 'sk_geolocation', 'store_listing' );

        if ( 'store_listing' === $show_map_pages || 'all' === $show_map_pages ) {
            return true;
        }

        return false;
    }
}
