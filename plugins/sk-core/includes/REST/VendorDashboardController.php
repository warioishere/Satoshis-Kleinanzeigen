<?php

namespace SK\Core\REST;

use SK\Core\Utilities\VendorUtil;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class VendorDashboardController extends \WP_REST_Controller {

    /**
     * Endpoint namespace
     *
     * @var string
     */
    protected $namespace = 'sk/v1';

    /**
     * Route name
     *
     * @var string
     */
    protected $base = 'vendor-dashboard';

    /**
     * Vendor dashboard controller constructor.
     *
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace, '/' . $this->base, [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_dashboard_statistics' ],
                    'args'                => [],
                    'permission_callback' => 'is_user_logged_in',
                ],
            ]
        );
        register_rest_route(
            $this->namespace, '/' . $this->base . '/profile', [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_profile_information' ],
                    'args'                => [],
                    'permission_callback' => 'is_user_logged_in',
                ],
            ]
        );
        register_rest_route(
            $this->namespace, '/' . $this->base . '/products', [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_products_summary' ],
                    'args'                => [],
                    'permission_callback' => 'is_user_logged_in',
                ],
            ]
        );
        register_rest_route(
            $this->namespace, '/' . $this->base . '/orders', [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_orders_summary' ],
                    'args'                => $this->get_collection_params(),
                    'permission_callback' => 'is_user_logged_in',
                ],
                'schema' => array( $this, 'get_public_item_schema' ),
            ]
        );
        register_rest_route(
            $this->namespace, '/' . $this->base . '/preferences', [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_preferences' ],
                    'args'                => [],
                    'schema'              => [ $this, 'get_preferences_schema' ],
                    'permission_callback' => '__return_true',
                ],
            ]
        );
    }

    /**
     * Get dashboard statistics.
     *
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function get_dashboard_statistics() {
        $user_id = sk_get_current_user_id();

        return rest_ensure_response(
            [
                'balance'  => sk_get_seller_balance( $user_id ),
                'orders'   => sk_count_orders( $user_id ),
                'products' => sk_count_posts( 'product', $user_id ),
                'sales'    => wc_price( sk_author_total_sales( $user_id ) ),
                'earnings' => sk_get_seller_earnings( $user_id ),
                'views'    => sk_author_pageviews( $user_id ),
            ]
        );
    }

    /**
     * Get Vendor profile Information.
     *
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function get_profile_information() {
        $profile_info   = sk_get_store_info( sk_get_current_user_id() );
        $banner         = ! empty( $profile_info['banner'] ) ? absint( $profile_info['banner'] ) : 0;
        $banner_url     = $banner ? wp_get_attachment_url( $banner ) : VendorUtil::get_vendor_default_banner_url();
        $profile_info['banner_url'] = $banner_url;

        $gravatar_id  = ! empty( $profile_info['gravatar'] ) ? $profile_info['gravatar'] : 0;
        $gravatar_url = $gravatar_id ? wp_get_attachment_url( $gravatar_id ) : VendorUtil::get_vendor_default_avatar_url();
        $profile_info['gravatar_url'] = $gravatar_url;

        $profile_info = apply_filters( 'sk_vendor_profile_response', $profile_info );
        return rest_ensure_response( $profile_info );
    }

    /**
     * Get Vendor products reports summary.
     *
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function get_products_summary() {
        return rest_ensure_response( sk_count_posts( 'product', sk_get_current_user_id() ) );
    }

    /**
     * Get Vendor Order reports summary.
     *
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function get_orders_summary( $request ) {
        $start_date  = ! empty( $request['after'] ) ? sanitize_text_field( $request['after'] ) : '';
        $end_date    = ! empty( $request['before'] ) ? sanitize_text_field( $request['before'] ) : '';
        $customer_id = ! empty( $request['customer_id'] ) ? absint( $request['customer_id'] ) : 0;

        $args = [
            'return'    => 'count',
            'seller_id' => sk_get_current_user_id(),
            'date'      => [
                'from' => $start_date,
                'to'   => $end_date,
            ],
            'customer_id' => $customer_id,
            'status'      => 'all',
        ];

        $result = [];
        $result['total'] = wc_get_orders( $args );

        $order_statuses = wc_get_order_statuses();
        foreach ( $order_statuses as $key => $status ) {
            $args['status'] = $key;
            $result[ $key ] = wc_get_orders( $args );
        }

        return rest_ensure_response( $result );
    }

    /**
     * Get Preferences.
     *
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     * @throws Exception
     */
    public function get_preferences() {
        $currency_options = [];
        foreach ( get_woocommerce_currencies() as $key => $currency ) {
            $currency_options[ $key ] = get_woocommerce_currency_symbol( $key );
        }
        $tz_string = get_option( 'timezone_string' );
        $offset    = get_option( 'gmt_offset' );

        if ( $tz_string ) {
            // Timezone like "Asia/Dhaka"
            $dt = new DateTime( 'now', new DateTimeZone( $tz_string ) );
            $timezone_utc = $dt->format( 'P' ); // e.g. +06:00
        } else {
            // Fallback offset
            $hours   = (int) $offset;
            $minutes = abs( $offset - $hours ) * 60;
            $timezone_utc = sprintf( '%+03d:%02d', $hours, $minutes );
        }
        $icon_id  = get_option( 'site_icon' );
        $favicon  = $icon_id ? wp_get_attachment_image_url( $icon_id, 'full' ) : '';

        return rest_ensure_response(
            [
                'site_title'            => get_bloginfo( 'name' ),
                'tagline'               => get_bloginfo( 'description' ),
                'site_icon'             => $favicon,
                'currency'              => get_woocommerce_currency(),
                'currency_options'      => $currency_options,
                'currency_position'     => get_option( 'woocommerce_currency_pos' ),
                'currency_symbol'       => get_woocommerce_currency_symbol(),
                'decimal_separator'     => wc_get_price_decimal_separator(),
                'thousand_separator'    => wc_get_price_thousand_separator(),
                'decimal_point'         => wc_get_price_decimals(),
                'tax_calculation'       => get_option( 'woocommerce_calc_taxes' ),
                'tax_display_cart'      => get_option( 'woocommerce_tax_display_cart' ),
                'tax_round_at_subtotal' => get_option( 'woocommerce_tax_round_at_subtotal' ),
                'coupon_enabled'        => get_option( 'woocommerce_enable_coupons' ),
                'coupon_compound'       => get_option( 'woocommerce_calc_discounts_sequentially' ),
                'weight_unit'           => get_option( 'woocommerce_weight_unit' ),
                'dimension_unit'        => get_option( 'woocommerce_dimension_unit' ),
                'product_reviews'       => get_option( 'woocommerce_enable_reviews' ),
                'product_ratings'       => get_option( 'woocommerce_enable_review_rating' ),
                'stock_management'      => get_option( 'woocommerce_manage_stock' ),
                'timezone'              => wp_timezone_string(),
                'date_format'           => get_option( 'date_format' ),
                'time_format'           => get_option( 'time_format' ),
                'language'              => get_locale(),
                'week_start_on'         => get_option( 'start_of_week' ),
                'store_color'           => sk_get_option( 'store_color_pallete', 'sk_colors', [] ),
                'timezone_utc'          => $timezone_utc,
            ]
        );
    }

    /**
     * Get our sample schema for preferences.
     */
    public function get_preferences_schema() {
        return [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            // The title property marks the identity of the resource.
            'title'      => 'preferences',
            'type'       => 'object',
            // In JSON Schema you can specify object properties in the properties attribute.
            'properties' => [
                'site_title'            => [
                    'description' => esc_html__( 'Site title.', 'sk-core' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'tagline'               => [
                    'description' => esc_html__( 'Tagline.', 'sk-core' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'site_icon'             => [
                    'description' => esc_html__( 'Favicon.', 'sk-core' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'currency'              => [
                    'description' => esc_html__( 'Payment currency.', 'sk-core' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'currency_options'      => [
                    'description' => esc_html__( 'Currency Options.', 'sk-core' ),
                    'type'        => 'object',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'currency_position'     => [
                    'description' => esc_html__( 'Payment currency position.', 'sk-core' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'currency_symbol'       => [
                    'description' => esc_html__( 'Currency symbol.', 'sk-core' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'decimal_separator'     => [
                    'description' => esc_html__( 'Decimal separator.', 'sk-core' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'thousand_separator'    => [
                    'description' => esc_html__( 'Thousand separator.', 'sk-core' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'decimal_point'         => [
                    'description' => esc_html__( 'Decimal point.', 'sk-core' ),
                    'type'        => 'integer',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'tax_calculation'       => [
                    'description' => esc_html__( 'Tax Calculation enabled or not.', 'sk-core' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'tax_display_cart'      => [
                    'description' => esc_html__( 'Tax display in cart price.', 'sk-core' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'tax_round_at_subtotal' => [
                    'description' => esc_html__( 'Tax Tax price round up in subtotal.', 'sk-core' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'coupon_enabled'        => [
                    'description' => esc_html__( 'Coupon enabled in store.', 'sk-core' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'coupon_compound'       => [
                    'description' => esc_html__( 'Compound coupon calculation.', 'sk-core' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'weight_unit'           => [
                    'description' => esc_html__( 'Measurement unit for weight.', 'sk-core' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'dimension_unit'        => [
                    'description' => esc_html__( 'Measurement unit for dimension.', 'sk-core' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'product_reviews'       => [
                    'description' => esc_html__( 'Enabled product reviews.', 'sk-core' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'product_ratings'       => [
                    'description' => esc_html__( 'Enabled product rating.', 'sk-core' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'stock_management'      => [
                    'description' => esc_html__( 'Enabled product stock management.', 'sk-core' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'timezone'              => [
                    'description' => esc_html__( 'Store timezone.', 'sk-core' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'date_format'           => [
                    'description' => esc_html__( 'Store date format.', 'sk-core' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'time_format'           => [
                    'description' => esc_html__( 'Store time format.', 'sk-core' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'timezone_utc'           => [
                    'description' => esc_html__( 'Store UTC time.', 'sk-core' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'language'              => [
                    'description' => esc_html__( 'Store language.', 'sk-core' ),
                    'type'        => 'string',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'week_start_on' => [
                    'description' => esc_html__( 'Store start of week.', 'sk-core' ),
                    'type'        => 'object',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'store_color' => [
                    'description' => esc_html__( 'Store color.', 'sk-core' ),
                    'type'        => 'object',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
            ],
        ];
    }

    /**
     * Get our sample schema for order-summary.
     */
    public function get_order_summary_schema() {
        return [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'order-summary',
            'type'       => 'object',
            'properties' => [
                'customer_id'          => array(
                    'description' => __( 'User ID who owns the order. 0 for guests.', 'sk-core' ),
                    'type'        => 'integer',
                    'default'     => 0,
                    'context'     => array( 'view' ),
                ),
                'after' => array(
                    'description' => __( 'Start date to show orders', 'sk-core' ),
                    'type'        => 'date-time',
                    'default'     => null,
                    'context'     => array( 'view' ),
                    'readonly'    => true,
                ),
                'before' => array(
                    'description' => __( 'End date to show orders', 'sk-core' ),
                    'type'        => 'date-time',
                    'default'     => null,
                    'context'     => array( 'view' ),
                    'readonly'    => true,
                ),
            ],
        ];
    }

    /**
     * Retrieves the query params for the posts collection.
     *
     *
     * @return array Collection parameters.
     */
    public function get_collection_params() {
        $query_params = parent::get_collection_params();

        $query_params['context']['default'] = 'view';

        $schema            = $this->get_order_summary_schema();
        $schema_properties = $schema['properties'];

        $query_params['customer_id'] = array(
            'required'    => false,
            'default'     => $schema_properties['customer_id']['default'],
            'description' => $schema_properties['customer_id']['description'],
            'type'        => $schema_properties['customer_id']['type'],
        );

        $query_params['after'] = array(
            'required'    => false,
            'default'     => $schema_properties['after']['default'],
            'description' => $schema_properties['after']['description'],
            'type'        => $schema_properties['after']['type'],
        );

        $query_params['before'] = array(
            'required'    => false,
            'default'     => $schema_properties['before']['default'],
            'description' => $schema_properties['before']['description'],
            'type'        => $schema_properties['before']['type'],
        );

        return $query_params;
    }
}
