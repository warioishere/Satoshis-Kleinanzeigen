<?php

/**
 * Module related WP_User_Query filters
 *
 */
class SK_Geolocation_Vendor_Query {

    /**
     * Holds the WP_User_Query object
     *
     *
     * @var null|WP_User_Query
     */
    private $user_query = null;

    /**
     * Latitude query value
     *
     *
     * @var float
     */
    private $latitude = 0.00;

    /**
     * Longitude query value
     *
     *
     * @var float
     */
    private $longitude = 0.00;

    /**
     * Distance/Radius query value
     *
     *
     * @var int
     */
    private $distance = 0;

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        add_filter( 'sk_seller_listing_args', array( $this, 'filter_pre_user_query' ) );
    }

    /**
     * Add action to filter vendor sql query
     *
     *
     * @param array $seller_args
     *
     * @return array
     */
    public function filter_pre_user_query( $seller_args ) {
        add_action( 'pre_user_query', array( $this, 'add_user_query' ) );

        return $seller_args;
    }

    /**
     * WP_User_Query hook
     *
     *
     * @param WP_User_Query $user_query
     */
    public function add_user_query( $user_query ) {
        if ( version_compare( SK_CORE_VERSION, '2.9.6', '>' ) ) {
            if ( empty( $user_query->query_vars['role__in'] ) || $user_query->query_vars['role__in'] !== ['seller', 'administrator'] ) {
                return;
            }
        } else {
            if ( empty( $user_query->query_vars['role'] ) || 'seller' !== $user_query->query_vars['role'] ) {
                return;
            }
        }

        // Temporarily disable the nonce check if it is  a REST request.
        if ( ! $this->is_rest_request()  && ( ! isset( $_GET['_store_filter_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_store_filter_nonce'] ) ), 'sk_store_lists_filter_nonce' ) ) ) {
            return;
        }

        $this->user_query = $user_query;

        $this->latitude  = isset( $_GET['latitude'] ) ? sanitize_text_field( wp_unslash( $_GET['latitude'] ) ) : null;
        $this->longitude = isset( $_GET['longitude'] ) ? sanitize_text_field( wp_unslash( $_GET['longitude'] ) ) : null;
        $this->distance  = isset( $_GET['distance'] ) ? sanitize_text_field( wp_unslash( $_GET['distance'] ) ) : 0;

        if ( empty( $this->latitude ) || empty( $this->longitude ) ) {
            return;
        }

        $this->filter_query_fields();
        $this->filter_query_from();
        $this->filter_query_orderby();
    }

    /**
     * Add extra select statement to WP_User_Query
     *
     *
     * @return void
     */
    private function filter_query_fields() {
        $this->user_query->query_fields .= ', metalat.meta_value as sk_geo_latitude, metalong.meta_value as sk_geo_longitude, metaaddr.meta_value as sk_geo_address';

        if ( $this->latitude && $this->longitude ) {
            // unit in kilometers or miles
            $distance_unit = sk_get_option( 'distance_unit', 'sk_geolocation', 'km' );

            $distance_earth_center_to_surface = ( 'km' === $distance_unit ) ? 6371 : 3959;

            $this->user_query->query_fields .= ", (
                {$distance_earth_center_to_surface} * acos(
                    cos( radians( {$this->latitude} ) ) *
                    cos( radians( metalat.meta_value ) ) *
                    cos(
                        radians( metalong.meta_value ) - radians( {$this->longitude} )
                    ) +
                    sin( radians( {$this->latitude} ) ) *
                    sin( radians( metalat.meta_value ) )
                )
            ) as geo_distance";
        }
    }

    /**
     * Add inner join clauses to WP_User_Query
     *
     *
     * @return void
     */
    private function filter_query_from() {
        global $wpdb;

        $this->user_query->query_from .= " inner join {$wpdb->usermeta} as metalat on {$wpdb->users}.ID = metalat.user_id and metalat.meta_key = 'sk_geo_latitude'";
        $this->user_query->query_from .= " inner join {$wpdb->usermeta} as metalong on {$wpdb->users}.ID = metalong.user_id and metalong.meta_key = 'sk_geo_longitude'";
        $this->user_query->query_from .= " inner join {$wpdb->usermeta} as metaaddr on {$wpdb->users}.ID = metaaddr.user_id and metaaddr.meta_key = 'sk_geo_address'";
    }

    /**
     * Add having clause to WP_User_Query
     *
     *
     * @return void
     */
    private function filter_query_orderby() {
        if ( $this->latitude && $this->longitude ) {
            $distance = absint( $this->distance );
            $this->user_query->query_orderby = "having ( geo_distance < {$distance} or geo_distance is null ) " . $this->user_query->query_orderby;
        }
    }

    /**
     * Determine if the current request is a REST request.
     *
     *
     * @return bool
     */
    private function is_rest_request(): bool {
        return defined('REST_REQUEST') || ( strpos( $_SERVER['REQUEST_URI'], 'wp-json') === 1 );
    }
}
