<?php

/**
 * Module related WP_Query filters for WC Products
 *
 */
class SK_Geolocation_Product_Query {

    /**
     * Latitude query value
     *
     *
     * @var float
     */
    private $latitude = 0;

    /**
     * Longitude query value
     *
     *
     * @var float
     */
    private $longitude = 0;

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
    /** Value of the extra entry in the shop's sort dropdown. */
    const WITH_LOCATION = 'sk_has_location';

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        add_action( 'woocommerce_product_query', array( $this, 'add_query_filters' ) );
        add_filter( 'woocommerce_catalog_orderby', array( $this, 'add_location_option' ) );
        add_filter( 'woocommerce_default_catalog_orderby_options', array( $this, 'add_location_option' ) );
        add_filter( 'woocommerce_get_catalog_ordering_args', array( $this, 'location_ordering_args' ), 10, 2 );
        add_action( 'woocommerce_product_query', array( $this, 'filter_products_with_location' ) );
    }

    /**
     * Offer "only listings with a location" alongside the sort options.
     */
    public function add_location_option( $options ) {
        $options[ self::WITH_LOCATION ] = __( 'Nur mit Ortsangabe', 'sk-core' );

        return $options;
    }

    /**
     * WooCommerce does not know this value, so it would fall through to its
     * default handling. Sorting stays by date, the selection only narrows the
     * result set.
     */
    public function location_ordering_args( $args, $orderby = '' ) {
        if ( self::WITH_LOCATION !== $orderby ) {
            return $args;
        }

        $args['orderby']  = 'date';
        $args['order']    = 'DESC';
        $args['meta_key'] = '';

        return $args;
    }

    /**
     * Narrow the loop to listings that actually carry a place.
     *
     * Listings without one have no sk_geo_address row at all, so EXISTS plus a
     * non-empty check is enough.
     */
    public function filter_products_with_location( $query ) {
        if ( ! isset( $_GET['orderby'] ) || self::WITH_LOCATION !== sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) ) {
            return;
        }

        $meta_query   = (array) $query->get( 'meta_query' );
        $meta_query[] = [
            'key'     => 'sk_geo_address',
            'value'   => '',
            'compare' => '!=',
        ];

        $query->set( 'meta_query', $meta_query );
    }

    /**
     * Add WooCommerce product query filters
     *
     *
     * @param \WP_Query $query
     */
    public function add_query_filters( $query ) {
        if ( ! $this->is_geolocation_show_on_shop_page() ) {
            return;
        }

        // Cast to numbers, the values go straight into the query as SQL literals.
        $this->latitude  = isset( $_GET['latitude'] ) ? sk_geo_float_val( wp_unslash( $_GET['latitude'] ) ) : null;
        $this->longitude = isset( $_GET['longitude'] ) ? sk_geo_float_val( wp_unslash( $_GET['longitude'] ) ) : null;
        $this->distance  = isset( $_GET['distance'] ) ? absint( wp_unslash( $_GET['distance'] ) ) : 0;

        if ( empty( $this->latitude ) || empty( $this->longitude ) ) {
            return;
        }

        add_filter( 'posts_fields_request', array( $this, 'posts_fields_request' ) );
        add_filter( 'posts_join_request', array( $this, 'posts_join_request' ) );
        add_filter( 'posts_groupby_request', array( $this, 'posts_groupby_request' ) );
    }

    /**
     * Add extra select fields
     *
     *
     * @param string $fields
     *
     * @return string
     */
    public function posts_fields_request( $fields ) {
        $fields .= ', metalat.meta_value as sk_geo_latitude, metalong.meta_value as sk_geo_longitude, metaaddr.meta_value as sk_geo_address';

        if ( $this->latitude && $this->longitude ) {
            // unit in kilometers or miles
            $distance_unit = sk_get_option( 'distance_unit', 'sk_geolocation', 'km' );

            $distance_earth_center_to_surface = ( 'km' === $distance_unit ) ? 6371 : 3959;

            $fields .= ", (
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

        remove_filter( 'posts_fields_request', array( $this, 'posts_fields_request' ) );

        return $fields;
    }

    /**
     * Add extra join SQL statements
     *
     *
     * @param string $join
     *
     * @return string
     */
    public function posts_join_request( $join ) {
        global $wpdb;

        $join .= " inner join {$wpdb->postmeta} as metalat on {$wpdb->posts}.ID = metalat.post_id and metalat.meta_key = 'sk_geo_latitude'";
        $join .= " inner join {$wpdb->postmeta} as metalong on {$wpdb->posts}.ID = metalong.post_id and metalong.meta_key = 'sk_geo_longitude'";
        $join .= " inner join {$wpdb->postmeta} as metaaddr on {$wpdb->posts}.ID = metaaddr.post_id and metaaddr.meta_key = 'sk_geo_address'";

        remove_filter( 'posts_join_request', array( $this, 'posts_join_request' ) );

        return $join;
    }

    /**
     * Add HAVING clause after GROUP BY clause
     *
     *
     * @param string $groupby
     *
     * @return string
     */
    public function posts_groupby_request( $groupby ) {
        if ( $this->latitude && $this->longitude ) {
            $distance = absint( $this->distance );
            $groupby .= " having geo_distance < {$distance}";
        }

        remove_filter( 'posts_groupby_request', array( $this, 'posts_groupby_request' ) );

        return $groupby;
    }

    /**
     * Is geolocation show on shop page
     *
     *
     * @return bool
     */
    public function is_geolocation_show_on_shop_page() {
        $show_map_pages = sk_get_option( 'show_location_map_pages', 'sk_geolocation', 'shop' );

        if ( ( is_shop() || is_product_taxonomy() ) && ( 'shop' === $show_map_pages || 'all' === $show_map_pages ) ) {
            return true;
        }

        return false;
    }
}
