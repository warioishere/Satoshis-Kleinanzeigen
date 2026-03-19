<?php

namespace SK\Core;

use WC_Countries;
use WC_Meta_Box_Product_Data;
use WC_Product_Variable;
use WC_Tax;
use SK\Core\Shipping\ShippingZone;

/**
 * SK Pro Ajax class
 *
 *
 */
class Ajax {

    /**
     * Loading automatically when class initiate
     *
     *
     * @uses  action hook
     * @uses  filter hook
     */
    public function __construct() {
        $settings = sk_get_container()->get( 'dashboard' )->templates->settings;
        add_action( 'wp_ajax_sk_settings', [ $settings, 'ajax_settings' ] );

        add_action( 'wp_ajax_sk_json_search_products_tags', [ $this, 'sk_json_search_products_tags' ] );

        // Shipping ajax hanlding
        add_action( 'wp_ajax_dps_select_state_by_country', [ $this, 'load_state_by_country' ] );
        add_action( 'wp_ajax_nopriv_dps_select_state_by_country', [ $this, 'load_state_by_country' ] );

        // shipping state ajax
        add_action( 'wp_ajax_nopriv_sk_shipping_country_select', [ $this, 'get_state_by_shipping_country' ] );
        add_action( 'wp_ajax_sk_shipping_country_select', [ $this, 'get_state_by_shipping_country' ] );

        // shipping calculation ajax
        add_action( 'wp_ajax_nopriv_sk_shipping_calculator', [ $this, 'get_calculated_shipping_cost' ] );
        add_action( 'wp_ajax_sk_shipping_calculator', [ $this, 'get_calculated_shipping_cost' ] );

        // Variation Handle for Vendor frontend
        add_action( 'wp_ajax_sk_add_variation', [ $this, 'add_variation' ] );
        add_action( 'wp_ajax_sk_link_all_variations', [ $this, 'link_all_variations' ] );
        add_action( 'wp_ajax_sk_pre_define_attribute', [ $this, 'sk_pre_define_attribute' ] );
        add_action( 'wp_ajax_sk_save_attributes', [ $this, 'save_attributes' ] );
        add_action( 'wp_ajax_sk_remove_variation', [ $this, 'remove_variations' ] );
        add_action( 'wp_ajax_sk_load_variations', [ $this, 'load_variations' ] );
        add_action( 'wp_ajax_sk_save_variations', [ $this, 'save_variations' ] );
        add_action( 'wp_ajax_sk_bulk_edit_variations', [ $this, 'bulk_edit_variations' ] );

        // Single product Design ajax
        add_action( 'wp_ajax_sk_get_pre_attribute', [ $this, 'add_attr_predefined_attribute' ] );
        add_action( 'wp_ajax_nopriv_sk_get_pre_attribute', [ $this, 'add_attr_predefined_attribute' ] );
        add_action( 'wp_ajax_sk_add_new_attribute', [ $this, 'add_new_attribute' ] );
        add_action( 'wp_ajax_nopriv_sk_add_new_attribute', [ $this, 'add_new_attribute' ] );
        add_action( 'wp_ajax_sk_load_order_items', [ $this, 'load_order_items' ] );
        add_action( 'wp_ajax_nopriv_sk_load_order_items', [ $this, 'load_order_items' ] );

        add_action( 'wp_ajax_sk_toggle_seller', [ $this, 'toggle_seller_status' ] );

        // Shipping Zone
        add_action( 'wp_ajax_sk-get-shipping-zone', [ $this, 'get_shipping_zone' ] );
        add_action( 'wp_ajax_sk-update-shipping-method-settings', [ $this, 'update_shipping_methods_settings' ] );
        add_action( 'wp_ajax_sk-toggle-shipping-method-enabled', [ $this, 'toggle_shipping_method' ] );
        add_action( 'wp_ajax_sk-save-zone-settings', [ $this, 'save_zone_settings' ] );
        add_action( 'wp_ajax_sk-add-shipping-method', [ $this, 'add_shipping_method' ] );
        add_action( 'wp_ajax_sk-delete-shipping-method', [ $this, 'delete_shipping_method' ] );
        add_action( 'wp_ajax_sk-save-shipping-settings', [ $this, 'save_shipping_settings' ] );
        add_action( 'wp_ajax_sk-get-shipping-settings', [ $this, 'get_shipping_settings' ] );

        // Profile Progressbar
    }

    /**
     * Get shipping zone
     *
     *
     * @return void
     */
    public function get_shipping_zone() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'sk_reviews' ) ) {
            wp_send_json_error( __( 'Invalid nonce', 'sk' ) );
        }

        if ( isset( $_POST['zoneID'] ) ) {
            $zones = ShippingZone::get_zone( absint( $_POST['zoneID'] ) );
        } else {
            $zones = ShippingZone::get_zones();
            // we are sorting by `zone_order` key of the zone.
            usort(
                $zones,
                function ( $zone1, $zone2 ) {
                    // handle the `Locations not covered by your other zones`
                    // here zone id = 0 is the zone "not covered by your other zones";.
                    if ( 0 === $zone1['id'] ) {
                        return 1;
                    } elseif ( 0 === $zone2['id'] ) {
                        return -1;
                    }

                    return $zone1['zone_order'] <=> $zone2['zone_order'];
                }
            );
        }

        wp_send_json_success( $zones );
    }

    /**
     * Get shipping methods
     *
     *
     * @return void
     */
    public function get_shipping_methods() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'sk_reviews' ) ) {
            wp_send_json_error( __( 'Invalid nonce', 'sk' ) );
        }

        if ( ! isset( $_POST['zoneID'] ) ) {
            wp_send_json_error( __( 'Zone not found', 'sk' ) );
        }

        $methods = ShippingZone::get_shipping_methods( absint( $_POST['zoneID'] ) );

        wp_send_json_success( $methods );
    }

    /**
     * Update shipping methods settings
     *
     *
     * @return void
     */
    public function update_shipping_methods_settings() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'sk_reviews' ) ) {
            wp_send_json_error( __( 'Invalid nonce', 'sk' ) );
        }

        $zone_id = isset( $_POST['zoneID'] ) ? absint( $_POST['zoneID'] ) : '';

        if ( '' === $zone_id ) {
            wp_send_json_error( __( 'Shipping zone not found', 'sk' ) );
        }

        $defaults = [
            'instance_id' => '',
            'method_id'   => '',
            'zone_id'     => $zone_id,
            'settings'    => [],
        ];

        $args = sk_parse_args( $_POST['data'], $defaults ); // phpcs:ignore

        if ( empty( $args['settings']['title'] ) ) {
            wp_send_json_error( __( 'Shipping title must be required', 'sk' ) );
        }

        $result = ShippingZone::update_shipping_method( $args );

        wp_send_json_success( $result );
    }

    /**
     * Toggle shipping method
     *
     *
     * @return void
     */
    public function toggle_shipping_method() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'sk_reviews' ) ) {
            wp_send_json_error( __( 'Invalid nonce', 'sk' ) );
        }

        $zone_id = isset( $_POST['zoneID'] ) ? absint( $_POST['zoneID'] ) : '';
        if ( '' === $zone_id ) {
            wp_send_json_error( __( 'Shipping zone not found', 'sk' ) );
        }

        $instance_id = ! empty( $_POST['instance_id'] ) ? absint( $_POST['instance_id'] ) : 0;
        if ( ! $instance_id ) {
            wp_send_json_error( __( 'Shipping method not found', 'sk' ) );
        }

        $data = [
            'instance_id' => $instance_id,
            'zone_id'     => $zone_id,
            'checked'     => isset( $_POST['checked'] ) && $_POST['checked'] === 'true' ? 1 : 0,
        ];

        $result = ShippingZone::toggle_shipping_method( $data );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        $message = $data['checked'] ? __( 'Shipping method enabled successfully', 'sk' ) : __( 'Shipping method disabled successfully', 'sk' );
        wp_send_json_success( $message );
    }

    /**
     * Add new shipping method for a zone
     *
     *
     * @return void
     */
    public function add_shipping_method() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'sk_reviews' ) ) {
            wp_send_json_error( __( 'Invalid nonce', 'sk' ) );
        }

        $zone_id = isset( $_POST['zoneID'] ) ? absint( $_POST['zoneID'] ) : '';
        if ( '' === $zone_id ) {
            wp_send_json_error( __( 'Shipping zone not found', 'sk' ) );
        }

        if ( empty( $_POST['method'] ) ) {
            wp_send_json_error( __( 'Please select a shipping method', 'sk' ) );
        }

        $data = [
            'zone_id'   => $zone_id,
            'method_id' => sanitize_text_field( $_POST['method'] ),
            'settings'  => $_POST['settings'], // phpcs:ignore
        ];

        $result = ShippingZone::add_shipping_methods( $data );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message(), 'sk' );
        }

        wp_send_json_success( __( 'Shipping method added successfully', 'sk' ) );
    }

    /**
     * Delete shipping method
     *
     *
     * @return void
     */
    public function delete_shipping_method() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'sk_reviews' ) ) {
            wp_send_json_error( __( 'Invalid nonce', 'sk' ) );
        }

        $zone_id = isset( $_POST['zoneID'] ) ? $_POST['zoneID'] : '';

        if ( $zone_id === '' ) {
            wp_send_json_error( __( 'Shipping zone not found', 'sk' ) );
        }

        if ( empty( $_POST['instance_id'] ) ) {
            wp_send_json_error( __( 'Shipping method not found', 'sk' ) );
        }

        $data = [
            'zone_id'     => $zone_id,
            'instance_id' => $_POST['instance_id'],
        ];

        $result = ShippingZone::delete_shipping_methods( $data );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message(), 'sk' );
        }

        wp_send_json_success( __( 'Shipping method deleted', 'sk' ) );
    }

    /**
     * Save shipping Settings
     *
     *
     * @return void
     */
    public function save_shipping_settings() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'sk_reviews' ) ) {
            wp_send_json_error( __( 'Invalid nonce', 'sk' ) );
        }

        $settings = $_POST['settings'];
        $user_id  = sk_get_current_user_id();

        if ( isset( $settings['processing_time'] ) ) {
            update_user_meta( $user_id, '_dps_pt', $settings['processing_time'] );
        }

        if ( isset( $settings['shipping_policy'] ) ) {
            update_user_meta( $user_id, '_dps_ship_policy', $settings['shipping_policy'] );
        }

        if ( isset( $settings['refund_policy'] ) ) {
            update_user_meta( $user_id, '_dps_refund_policy', $settings['refund_policy'] );
        }

        update_user_meta( $user_id, '_dps_zone_wise_settings', 'yes' );

        wp_send_json_success( __( 'Settings save successfully', 'sk' ) );
    }

    /**
     * Get shipping settings
     *
     *
     * @return void
     */
    public function get_shipping_settings() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'sk_reviews' ) ) {
            wp_send_json_error( __( 'Invalid nonce', 'sk' ) );
        }

        $user_id = sk_get_current_user_id();

        $dps_processing  = get_user_meta( $user_id, '_dps_pt', true );
        $shipping_policy = get_user_meta( $user_id, '_dps_ship_policy', true );
        $refund_policy   = get_user_meta( $user_id, '_dps_refund_policy', true );

        $shipping_data = [
            'processing_time' => $dps_processing,
            'shipping_policy' => $shipping_policy,
            'refund_policy'   => $refund_policy,
        ];

        wp_send_json_success( $shipping_data );
    }

    /**
     * Save zone settings
     *
     *
     * @return void
     */
    public function save_zone_settings() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'sk_reviews' ) ) {
            wp_send_json_error( __( 'Invalid nonce', 'sk' ) );
        }

        $zone_id = isset( $_POST['zoneID'] ) ? $_POST['zoneID'] : '';

        if ( $zone_id === '' ) {
            wp_send_json_error( __( 'Shipping zone not found', 'sk' ) );
        }

        $location = [];

        if ( ! empty( $_POST['continent'] ) && is_array( $_POST['continent'] ) ) {
            $continent_array = [];

            foreach ( $_POST['continent'] as $continent ) {
                $continent_array[] = [
                    'code' => $continent['code'],
                    'type' => 'continent',
                ];
            }

            $location = array_merge( $location, $continent_array );
        }

        if ( ! empty( $_POST['country'] ) && is_array( $_POST['country'] ) ) {
            $country_array = [];

            foreach ( $_POST['country'] as $country ) {
                $country_array[] = [
                    'code' => $country['code'],
                    'type' => 'country',
                ];
            }

            $location = array_merge( $location, $country_array );
        }

        if ( ! empty( $_POST['state'] ) && is_array( $_POST['state'] ) ) {
            $state_array = [];

            foreach ( $_POST['state'] as $state ) {
                $state_array[] = [
                    'code' => $state['code'],
                    'type' => 'state',
                ];
            }

            $location = array_merge( $location, $state_array );
        }

        if ( ! empty( $_POST['postcode'] ) ) {
            $postcodes      = explode( ',', $_POST['postcode'] );
            $postcode_array = [];

            foreach ( $postcodes as $postcode ) {
                if ( false !== strpos( $postcode, '...' ) ) {
                    $postcode = implode( '...', array_map( 'trim', explode( '...', $postcode ) ) );
                }

                $postcode_array[] = [
                    'code' => trim( $postcode ),
                    'type' => 'postcode',
                ];
            }

            $location = array_merge( $location, $postcode_array );
        }

        $result = ShippingZone::save_location( $location, $zone_id );

        wp_send_json_success( __( 'Zone settings save successfully', 'sk' ) );
    }

    /**
     * Load variations
     *
     * @return void
     */
    public function load_variations() {
        ob_start();

        check_ajax_referer( 'load-variations', 'security' );

        // Check permissions again and make sure we have what we need
        if ( ! current_user_can( 'skdar' ) || empty( $_POST['product_id'] ) || empty( $_POST['attributes'] ) ) {
            die( -1 );
        }

        global $post;

        $product_id = absint( $_POST['product_id'] );
        $post       = get_post( $product_id ); // Set $post global so its available like within the admin screens
        $per_page   = ! empty( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 10;
        $page       = ! empty( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;

        // Get attributes
        $attributes        = [];
        $posted_attributes = json_decode( wp_unslash( $_POST['attributes'] ) );

        foreach ( $posted_attributes as $key => $value ) {
            $attributes[ $key ] = array_map( 'wc_clean', (array) $value );
        }

        // Get tax classes
        $tax_classes           = WC_Tax::get_tax_classes();
        $tax_class_options     = [];
        $tax_class_options[''] = __( 'Standard', 'sk' );

        if ( ! empty( $tax_classes ) ) {
            foreach ( $tax_classes as $class ) {
                $tax_class_options[ sanitize_title( $class ) ] = esc_attr( $class );
            }
        }

        // Set backorder options
        $backorder_options = [
            'no'     => __( 'Do not allow', 'sk' ),
            'notify' => __( 'Allow, but notify customer', 'sk' ),
            'yes'    => __( 'Allow', 'sk' ),
        ];

        // set stock status options
        $stock_status_options = [
            'instock'     => __( 'In stock', 'sk' ),
            'outofstock'  => __( 'Out of stock', 'sk' ),
            'onbackorder' => __( 'On backorder', 'sk' ),
        ];

        $parent_data = [
            'id'                   => $product_id,
            'attributes'           => $attributes,
            'tax_class_options'    => $tax_class_options,
            'sku'                  => get_post_meta( $product_id, '_sku', true ),
            'weight'               => wc_format_localized_decimal( get_post_meta( $product_id, '_weight', true ) ),
            'length'               => wc_format_localized_decimal( get_post_meta( $product_id, '_length', true ) ),
            'width'                => wc_format_localized_decimal( get_post_meta( $product_id, '_width', true ) ),
            'height'               => wc_format_localized_decimal( get_post_meta( $product_id, '_height', true ) ),
            'tax_class'            => get_post_meta( $product_id, '_tax_class', true ),
            'backorder_options'    => $backorder_options,
            'stock_status_options' => $stock_status_options,
        ];

        if ( ! $parent_data['weight'] ) {
            $parent_data['weight'] = wc_format_localized_decimal( 0 );
        }

        if ( ! $parent_data['length'] ) {
            $parent_data['length'] = wc_format_localized_decimal( 0 );
        }

        if ( ! $parent_data['width'] ) {
            $parent_data['width'] = wc_format_localized_decimal( 0 );
        }

        if ( ! $parent_data['height'] ) {
            $parent_data['height'] = wc_format_localized_decimal( 0 );
        }

        // Get variations
        $args = apply_filters(
            'woocommerce_ajax_admin_get_variations_args', [
				'post_type'      => 'product_variation',
				'post_status'    => [ 'private', 'publish' ],
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'orderby'        => [
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
				],
				'post_parent'    => $product_id,
			], $product_id
        );

        $variations = get_posts( $args );
        $loop       = 0;

        if ( $variations ) {
            foreach ( $variations as $variation ) {
                $variation_id     = absint( $variation->ID );
                $variation_meta   = get_post_meta( $variation_id );
                $variation_data   = [];
                $shipping_classes = get_the_terms( $variation_id, 'product_shipping_class' );
                $variation_fields = [
                    '_sku'                   => '',
                    '_stock'                 => '',
                    '_regular_price'         => '',
                    '_sale_price'            => '',
                    '_weight'                => '',
                    '_length'                => '',
                    '_width'                 => '',
                    '_height'                => '',
                    '_download_limit'        => '',
                    '_download_expiry'       => '',
                    '_downloadable_files'    => '',
                    '_downloadable'          => '',
                    '_virtual'               => '',
                    '_thumbnail_id'          => '',
                    '_sale_price_dates_from' => '',
                    '_sale_price_dates_to'   => '',
                    '_manage_stock'          => '',
                    '_stock_status'          => '',
                    '_backorders'            => null,
                    '_tax_class'             => null,
                    '_variation_description' => '',
                    '_low_stock_amount'      => '',
                ];

                foreach ( $variation_fields as $field => $value ) {
                    $variation_data[ $field ] = isset( $variation_meta[ $field ][0] ) ? maybe_unserialize( $variation_meta[ $field ][0] ) : $value;
                }

                // Add the variation attributes
                $variation_data = array_merge( $variation_data, wc_get_product_variation_attributes( $variation_id ) );

                // Formatting
                $variation_data['_regular_price']    = wc_format_localized_price( $variation_data['_regular_price'] );
                $variation_data['_sale_price']       = wc_format_localized_price( $variation_data['_sale_price'] );
                $variation_data['_weight']           = wc_format_localized_decimal( $variation_data['_weight'] );
                $variation_data['_length']           = wc_format_localized_decimal( $variation_data['_length'] );
                $variation_data['_width']            = wc_format_localized_decimal( $variation_data['_width'] );
                $variation_data['_height']           = wc_format_localized_decimal( $variation_data['_height'] );
                $variation_data['_thumbnail_id']     = absint( $variation_data['_thumbnail_id'] );
                $variation_data['image']             = $variation_data['_thumbnail_id'] ? wp_get_attachment_thumb_url( $variation_data['_thumbnail_id'] ) : '';
                $variation_data['shipping_class']    = $shipping_classes && ! is_wp_error( $shipping_classes ) ? current( $shipping_classes )->term_id : '';
                $variation_data['menu_order']        = $variation->menu_order;
                $variation_data['_stock']            = '' === $variation_data['_stock'] ? '' : wc_stock_amount( $variation_data['_stock'] );
                $variation_data['_low_stock_amount'] = '' === $variation_data['_low_stock_amount'] ? '' : wc_format_decimal( $variation_data['_low_stock_amount'] );

                sk_get_template_part(
                    'products/edit/html-product-variation', '', [
                        'pro'            => true,
                        'loop'           => $loop,
                        'variation_id'   => $variation_id,
                        'parent_data'    => $parent_data,
                        'variation_data' => $variation_data,
                        'variation'      => $variation,
                    ]
                );

                ++$loop;
            }
        }

        die();
    }

    /**
     * Save variations via AJAX.
     */
    public static function save_variations() {
        // Checking nonce and security.
        check_ajax_referer( 'save-variations', 'security' );

        // Check permissions again and make sure we have what we need
        if ( ! current_user_can( 'skdar' ) || empty( $_POST ) || empty( $_POST['product_id'] ) ) {
            die( -1 );
        }

        ob_start();

        $product_id   = absint( $_POST['product_id'] );
        $product_type = empty( $_POST['product_type'] ) ? 'simple' : sanitize_title( stripslashes( $_POST['product_type'] ) );

        $product_type_terms = wp_get_object_terms( $product_id, 'product_type' );

        // If the product type hasn't been set or it has changed, update it before saving variations
        if ( empty( $product_type_terms ) || $product_type !== sanitize_title( current( $product_type_terms )->name ) ) {
            wp_set_object_terms( $product_id, $product_type, 'product_type' );
        }

        WC_Meta_Box_Product_Data::save_variations( $product_id, get_post( $product_id ) );

        do_action( 'sk_ajax_save_product_variations', $product_id );

        // Clear cache/transients
        wc_delete_product_transients( $product_id );
        die();
    }


    /**
     * Bulk action - Toggle Enabled.
     *
     *
     * @used-by bulk_edit_variations
     *
     * @param array $variations
     * @param array $data
     */
    private static function variation_bulk_action_toggle_enabled( $variations, $data ) {
        global $wpdb;

        foreach ( $variations as $variation_id ) {
            $post_status = get_post_status( $variation_id );
            $new_status  = 'private' === $post_status ? 'publish' : 'private';
            $wpdb->update( $wpdb->posts, [ 'post_status' => $new_status ], [ 'ID' => $variation_id ] );
        }
    }

    /**
     * Bulk action - Toggle Downloadable Checkbox.
     *
     *
     * @used-by bulk_edit_variations
     *
     * @param array $variations
     * @param array $data
     */
    private static function variation_bulk_action_toggle_downloadable( $variations, $data ) {
        foreach ( $variations as $variation_id ) {
            $_downloadable   = get_post_meta( $variation_id, '_downloadable', true );
            $is_downloadable = 'no' === $_downloadable ? 'yes' : 'no';
            update_post_meta( $variation_id, '_downloadable', $is_downloadable );
        }
    }

    /**
     * Bulk action - Toggle Virtual Checkbox.
     *
     *
     * @used-by bulk_edit_variations
     *
     * @param array $variations
     * @param array $data
     */
    private static function variation_bulk_action_toggle_virtual( $variations, $data ) {
        foreach ( $variations as $variation_id ) {
            $_virtual   = get_post_meta( $variation_id, '_virtual', true );
            $is_virtual = 'no' === $_virtual ? 'yes' : 'no';
            update_post_meta( $variation_id, '_virtual', $is_virtual );
        }
    }

    /**
     * Bulk action - Toggle Manage Stock Checkbox.
     *
     *
     * @used-by bulk_edit_variations
     *
     * @param array $variations
     * @param array $data
     */
    private static function variation_bulk_action_toggle_manage_stock( $variations, $data ) {
        foreach ( $variations as $variation_id ) {
            $_manage_stock   = get_post_meta( $variation_id, '_manage_stock', true );
            $is_manage_stock = 'no' === $_manage_stock || '' === $_manage_stock ? 'yes' : 'no';
            update_post_meta( $variation_id, '_manage_stock', $is_manage_stock );
        }
    }

    /**
     * Bulk action - Set Regular Prices.
     *
     *
     * @used-by bulk_edit_variations
     *
     * @param array $variations
     * @param array $data
     */
    private static function variation_bulk_action_variable_regular_price( $variations, $data ) {
        if ( ! isset( $data['value'] ) ) {
            return;
        }

        foreach ( $variations as $variation_id ) {
            // Price fields
            $regular_price = wc_clean( $data['value'] );
            $sale_price    = get_post_meta( $variation_id, '_sale_price', true );

            // Date fields
            $date_from = get_post_meta( $variation_id, '_sale_price_dates_from', true );
            $date_to   = get_post_meta( $variation_id, '_sale_price_dates_to', true );
            $date_from = ! empty( $date_from ) ? date( 'Y-m-d', $date_from ) : '';
            $date_to   = ! empty( $date_to ) ? date( 'Y-m-d', $date_to ) : '';

            sk_save_product_price( $variation_id, $regular_price, $sale_price, $date_from, $date_to );
        }
    }

    /**
     * Bulk action - Set Sale Prices.
     *
     *
     * @used-by bulk_edit_variations
     *
     * @param array $variations
     * @param array $data
     */
    private static function variation_bulk_action_variable_sale_price( $variations, $data ) {
        if ( ! isset( $data['value'] ) ) {
            return;
        }

        foreach ( $variations as $variation_id ) {
            // Price fields
            $regular_price = get_post_meta( $variation_id, '_regular_price', true );
            $sale_price    = wc_clean( $data['value'] );

            // Date fields
            $date_from = get_post_meta( $variation_id, '_sale_price_dates_from', true );
            $date_to   = get_post_meta( $variation_id, '_sale_price_dates_to', true );
            $date_from = ! empty( $date_from ) ? date( 'Y-m-d', $date_from ) : '';
            $date_to   = ! empty( $date_to ) ? date( 'Y-m-d', $date_to ) : '';

            sk_save_product_price( $variation_id, $regular_price, $sale_price, $date_from, $date_to );
        }
    }

    /**
     * Bulk action - Set Stock.
     *
     *
     * @used-by bulk_edit_variations
     *
     * @param array $variations
     * @param array $data
     */
    private static function variation_bulk_action_variable_stock( $variations, $data ) {
        if ( ! isset( $data['value'] ) ) {
            return;
        }

        $value = wc_clean( $data['value'] );

        foreach ( $variations as $variation_id ) {
            if ( 'yes' === get_post_meta( $variation_id, '_manage_stock', true ) ) {
                wc_update_product_stock( $variation_id, wc_stock_amount( $value ) );
            } else {
                delete_post_meta( $variation_id, '_stock' );
            }
        }
    }

    /**
     * Bulk action - Set Weight.
     *
     *
     * @used-by bulk_edit_variations
     *
     * @param array $variations
     * @param array $data
     */
    private static function variation_bulk_action_variable_weight( $variations, $data ) {
        self::variation_bulk_set_meta( $variations, '_weight', wc_clean( $data['value'] ) );
    }

    /**
     * Bulk action - Set Length.
     *
     *
     * @used-by bulk_edit_variations
     *
     * @param array $variations
     * @param array $data
     */
    private static function variation_bulk_action_variable_length( $variations, $data ) {
        self::variation_bulk_set_meta( $variations, '_length', wc_clean( $data['value'] ) );
    }

    /**
     * Bulk action - Set Width.
     *
     *
     * @used-by bulk_edit_variations
     *
     * @param array $variations
     * @param array $data
     */
    private static function variation_bulk_action_variable_width( $variations, $data ) {
        self::variation_bulk_set_meta( $variations, '_width', wc_clean( $data['value'] ) );
    }

    /**
     * Bulk action - Set Height.
     *
     *
     * @used-by bulk_edit_variations
     *
     * @param array $variations
     * @param array $data
     */
    private static function variation_bulk_action_variable_height( $variations, $data ) {
        self::variation_bulk_set_meta( $variations, '_height', wc_clean( $data['value'] ) );
    }

    /**
     * Bulk action - Set Download Limit.
     *
     *
     * @used-by bulk_edit_variations
     *
     * @param array $variations
     * @param array $data
     */
    private static function variation_bulk_action_variable_download_limit( $variations, $data ) {
        self::variation_bulk_set_meta( $variations, '_download_limit', wc_clean( $data['value'] ) );
    }

    /**
     * Bulk action - Set Download Expiry.
     *
     *
     * @used-by bulk_edit_variations
     *
     * @param array $variations
     * @param array $data
     */
    private static function variation_bulk_action_variable_download_expiry( $variations, $data ) {
        self::variation_bulk_set_meta( $variations, '_download_expiry', wc_clean( $data['value'] ) );
    }

    /**
     * Bulk action - Delete all.
     *
     *
     * @used-by bulk_edit_variations
     *
     * @param array $variations
     * @param array $data
     */
    private static function variation_bulk_action_delete_all( $variations, $data ) {
        if ( isset( $data['allowed'] ) && 'true' === $data['allowed'] ) {
            foreach ( $variations as $variation_id ) {
                wp_delete_post( $variation_id );
            }
        }
    }

    /**
     * Bulk action - Sale Schedule.
     *
     *
     * @used-by bulk_edit_variations
     *
     * @param array $variations
     * @param array $data
     */
    private static function variation_bulk_action_variable_sale_schedule( $variations, $data ) {
        if ( ! isset( $data['date_from'] ) && ! isset( $data['date_to'] ) ) {
            return;
        }

        foreach ( $variations as $variation_id ) {
            // Price fields
            $regular_price = get_post_meta( $variation_id, '_regular_price', true );
            $sale_price    = get_post_meta( $variation_id, '_sale_price', true );

            // Date fields
            $date_from = get_post_meta( $variation_id, '_sale_price_dates_from', true );
            $date_to   = get_post_meta( $variation_id, '_sale_price_dates_to', true );

            if ( 'false' === $data['date_from'] ) {
                $date_from = ! empty( $date_from ) ? date( 'Y-m-d', $date_from ) : '';
            } else {
                $date_from = $data['date_from'];
            }

            if ( 'false' === $data['date_to'] ) {
                $date_to = ! empty( $date_to ) ? date( 'Y-m-d', $date_to ) : '';
            } else {
                $date_to = $data['date_to'];
            }

            sk_save_product_price( $variation_id, $regular_price, $sale_price, $date_from, $date_to );
        }
    }

    /**
     * Bulk action - Increase Regular Prices.
     *
     *
     * @used-by bulk_edit_variations
     *
     * @param array $variations
     * @param array $data
     */
    private static function variation_bulk_action_variable_regular_price_increase( $variations, $data ) {
        self::variation_bulk_adjust_price( $variations, '_regular_price', '+', wc_clean( $data['value'] ) );
    }

    /**
     * Bulk action - Decrease Regular Prices.
     *
     *
     * @used-by bulk_edit_variations
     *
     * @param array $variations
     * @param array $data
     */
    private static function variation_bulk_action_variable_regular_price_decrease( $variations, $data ) {
        self::variation_bulk_adjust_price( $variations, '_regular_price', '-', wc_clean( $data['value'] ) );
    }

    /**
     * Bulk action - Increase Sale Prices.
     *
     *
     * @used-by bulk_edit_variations
     *
     * @param array $variations
     * @param array $data
     */
    private static function variation_bulk_action_variable_sale_price_increase( $variations, $data ) {
        self::variation_bulk_adjust_price( $variations, '_sale_price', '+', wc_clean( $data['value'] ) );
    }

    /**
     * Bulk action - Decrease Sale Prices.
     *
     *
     * @used-by bulk_edit_variations
     *
     * @param array $variations
     * @param array $data
     */
    private static function variation_bulk_action_variable_sale_price_decrease( $variations, $data ) {
        self::variation_bulk_adjust_price( $variations, '_sale_price', '-', wc_clean( $data['value'] ) );
    }

    /**
     * Bulk action - Set Price.
     *
     *
     * @used-by bulk_edit_variations
     *
     * @param array  $variations
     * @param string $operator + or -
     * @param string $field    price being adjusted
     * @param string $value    Price or Percent
     */
    private static function variation_bulk_adjust_price( $variations, $field, $operator, $value ) {
        foreach ( $variations as $variation_id ) {
            // Get existing data
            $_regular_price = get_post_meta( $variation_id, '_regular_price', true );
            $_sale_price    = get_post_meta( $variation_id, '_sale_price', true );
            $date_from      = get_post_meta( $variation_id, '_sale_price_dates_from', true );
            $date_to        = get_post_meta( $variation_id, '_sale_price_dates_to', true );
            $date_from      = ! empty( $date_from ) ? date( 'Y-m-d', $date_from ) : '';
            $date_to        = ! empty( $date_to ) ? date( 'Y-m-d', $date_to ) : '';

            if ( '%' === substr( $value, -1 ) ) {
                $percent = wc_format_decimal( substr( $value, 0, -1 ) );
                $$field  += ( ( $$field / 100 ) * $percent ) * "{$operator}1";
            } else {
                $$field += $value * "{$operator}1";
            }
            sk_save_product_price( $variation_id, $_regular_price, $_sale_price, $date_from, $date_to );
        }
    }

    /**
     * Bulk action - Set Meta.
     *
     *
     * @param array  $variations
     * @param string $field
     * @param string $value
     */
    private static function variation_bulk_set_meta( $variations, $field, $value ) {
        foreach ( $variations as $variation_id ) {
            update_post_meta( $variation_id, $field, $value );
        }
    }

    public static function bulk_edit_variations() {
        ob_start();

        check_ajax_referer( 'bulk-edit-variations', 'security' );

        // Check permissions again and make sure we have what we need
        if ( ! current_user_can( 'skdar' ) || empty( $_POST['product_id'] ) || empty( $_POST['bulk_action'] ) ) {
            die( -1 );
        }

        $product_id  = absint( $_POST['product_id'] );
        $bulk_action = wc_clean( $_POST['bulk_action'] );
        $data        = ! empty( $_POST['data'] ) ? array_map( 'wc_clean', $_POST['data'] ) : [];
        $variations  = [];

        if ( apply_filters( 'sk_bulk_edit_variations_need_children', true ) ) {
            $variations = get_posts(
                [
                    'post_parent'    => $product_id,
                    'posts_per_page' => -1,
                    'post_type'      => 'product_variation',
                    'fields'         => 'ids',
                    'post_status'    => [ 'publish', 'private' ],
                ]
            );
        }

        if ( method_exists( __CLASS__, "variation_bulk_action_$bulk_action" ) ) {
            call_user_func( [ __CLASS__, "variation_bulk_action_$bulk_action" ], $variations, $data );
        } else {
            do_action( 'sk_bulk_edit_variations_default', $bulk_action, $data, $product_id, $variations );
        }

        do_action( 'sk_bulk_edit_variations', $bulk_action, $data, $product_id, $variations );

        // Sync and update transients
        WC_Product_Variable::sync( $product_id );
        wc_delete_product_transients( $product_id );
        die();
    }

    /**
     * Delete variations via ajax function.
     */
    public static function remove_variations() {
        check_ajax_referer( 'delete-variations', 'security' );

        if ( ! current_user_can( 'skdar' ) ) {
            die( -1 );
        }

        $variation_ids = (array) $_POST['variation_ids'];

        foreach ( $variation_ids as $variation_id ) {
            $variation = get_post( $variation_id );

            if ( $variation && 'product_variation' === $variation->post_type ) {
                wp_delete_post( $variation_id );
            }
        }

        die();
    }

    /**
     * Enable/disable seller selling capability from admin seller listing page
     *
     * @return type
     */
    public function toggle_seller_status() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_POST['nonce'] ) && ! wp_verify_nonce( $_POST['nonce'], 'sk-admin-nonce' ) ) {
            return;
        }

        $user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
        $status  = sanitize_text_field( $_POST['type'] );

        if ( in_array( $status, [ 'yes', 'no' ] ) ) {
            if ( 'yes' === $status ) {
                $user = sk()->vendor->get( $user_id )->make_active();
            } else {
                $user = sk()->vendor->get( $user_id )->make_inactive();
            }
        }

        wp_send_json_success( $user );
        exit;
    }

    /**
     * Load State via ajax for refund
     *
     *
     * @return html Set of states
     */
    public function load_order_items() {
        check_ajax_referer( 'order-item', 'security' );

        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            die( -1 );
        }

        // Return HTML items
        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        $order    = wc_get_order( $order_id );
        $data     = $order->get_meta_data();

        sk_get_template_part(
            'orders/views/html-order-items',
            '',
            [
                'pro'   => true,
                'data'  => $data,
                'order' => $order,
            ]
        );

        die();
    }

    /**
     * Load State via ajax for shipping
     *
     *
     * @return html Set of states
     */
    public function load_state_by_country() {
        $country_id  = $_POST['country_id'];
        $country_obj = new WC_Countries();
        $states      = $country_obj->states;

        ob_start();
        if ( ! empty( $states[ $country_id ] ) ) {
            ?>
            <tr>
                <td>
                    <label for=""><?php _e( 'State', 'sk' ); ?></label>
                    <select name="dps_state_to[<?php echo $country_id; ?>][]" class="sk-form-control dps_state_selection" id="dps_state_selection">
                        <?php sk_state_dropdown( $states[ $country_id ], '', true ); ?>
                    </select>
                </td>
                <td>
                    <label for=""><?php _e( 'Cost', 'sk' ); ?></label>
                    <div class="sk-input-group">
                        <span class="sk-input-group-addon"><?php echo get_woocommerce_currency_symbol(); ?></span>
                        <input type="text" placeholder="0.00" class="sk-form-control" name="dps_state_to_price[<?php echo $country_id; ?>][]">
                    </div>
                </td>
                <td width="15%">
                    <label for=""></label>
                    <div>
                        <a class="dps-add" href="#"><i class="fas fa-plus"></i></a>
                        <a class="dps-remove" href="#"><i class="fas fa-minus"></i></a>
                    </div>
                </td>
            </tr>
            <?php
            // }
        } else {
            ?>
            <tr>
                <td>
                    <label for=""><?php _e( 'State', 'sk' ); ?></label>
                    <input type="text" name="dps_state_to[<?php echo $country_id; ?>][]" class="sk-form-control dps_state_selection" placeholder="State name">
                </td>
                <td>
                    <label for=""><?php _e( 'Cost', 'sk' ); ?></label>
                    <div class="sk-input-group">
                        <span class="sk-input-group-addon"><?php echo get_woocommerce_currency_symbol(); ?></span>
                        <input type="text" placeholder="0.00" class="sk-form-control" name="dps_state_to_price[<?php echo $country_id; ?>][]">
                    </div>
                </td>
                <td width="15%">
                    <label for=""></label>
                    <div>
                        <a class="dps-add" href="#"><i class="fas fa-plus"></i></a>
                        <a class="dps-remove" href="#"><i class="fas fa-minus"></i></a>
                    </div>
                </td>
            </tr>
            <?php
        }

        $data = ob_get_clean();

        wp_send_json_success( $data );
    }

    /**
     * get state by shipping country
     *
     *
     * @return json
     */
    public function get_state_by_shipping_country() {
        global $post;
        $dps_state_rates = get_user_meta( $_POST['author_id'], '_dps_state_rates', true );
        $country_obj     = new WC_Countries();
        $states          = $country_obj->states;

        $country = $_POST['country_id'];
        ob_start();
        ?>
        <?php
        if ( isset( $dps_state_rates[ $country ] ) && count( $dps_state_rates[ $country ] ) ) {
            ?>
            <label for="sk-shipping-state" class="sk-control-label"><?php _e( 'State', 'sk' ); ?></label>
            <select name="sk-shipping-state" class="sk-shipping-state sk-form-control" id="sk-shipping-state">
                <option value=""><?php _e( '--Select State--', 'sk' ); ?></option>
                <?php foreach ( $dps_state_rates[ $country ] as $state_code => $state_cost ) : ?>
                    <option value="<?php echo $state_code; ?>">
                        <?php
                        if ( $state_code === 'everywhere' ) {
                            _e( 'Other States', 'sk' );
                        } elseif ( isset( $states[ $country ][ $state_code ] ) ) {
                            echo $states[ $country ][ $state_code ];
                        } else {
                            echo $state_code;
                        }
                        ?>
                    </option>
                <?php endforeach ?>
            </select>
            <?php
        }
        $content = ob_get_clean();

        wp_send_json_success( $content );
    }

    /**
     * calculate shipping rate in single product page
     *
     *
     * @return json
     */
    public function get_calculated_shipping_cost() {
        global $post;

        if ( ! isset( $_POST['product_id'] ) || ! isset( $_POST['author_id'] ) ) {
            wp_send_json_error();
        }

        $product_id = absint( wp_unslash( $_POST['product_id'] ) );
        $author_id  = absint( wp_unslash( $_POST['author_id'] ) );

        $_overwrite_shipping = get_post_meta( $product_id, '_overwrite_shipping', true );

        $dps_country_rates = get_user_meta( $author_id, '_dps_country_rates', true );
        $dps_state_rates   = get_user_meta( $author_id, '_dps_state_rates', true );

        $store_shipping_type_price    = (float) get_user_meta( $author_id, '_dps_shipping_type_price', true );
        $additional_product_cost      = (float) get_post_meta( $product_id, '_additional_price', true );
        $base_shipping_type_price     = $store_shipping_type_price;
        $additional_qty_product_price = (float) wc_format_decimal( get_post_meta( $product_id, '_additional_qty', true ) );
        $dps_additional_qty           = (float) wc_format_decimal( get_user_meta( $author_id, '_dps_additional_qty', true ) );
        $additional_qty_price         = $dps_additional_qty;

        if ( $_overwrite_shipping === 'yes' ) {
            $base_shipping_type_price = $store_shipping_type_price + $additional_product_cost;
            $additional_qty_price     = $additional_qty_product_price ? $additional_qty_product_price : $dps_additional_qty;
        }

        if ( isset( $_POST['country_id'] ) || ! empty( $_POST['country_id'] ) ) {
            $country = $_POST['country_id'];
        } else {
            $country = '';
        }

        if ( isset( $_POST['quantity'] ) && $_POST['quantity'] > 0 ) {
            $quantity = $_POST['quantity'];
        } else {
            $quantity = 1;
        }

        $additional_quantity_cost = ( $quantity - 1 ) * $additional_qty_price;
        $flag                     = '';
        ob_start();
        if ( $country !== '' ) {
            if ( isset( $dps_state_rates[ $country ] ) && count( $dps_state_rates[ $country ] ) && empty( $_POST['state'] ) ) {
                esc_html_e( 'Please select a State from the dropdown', 'sk' );
            } elseif ( ! isset( $dps_state_rates[ $country ] ) && empty( $_POST['state'] ) ) {
                echo __( 'Shipping Cost : ', 'sk' ) . '<h4>' . wc_price( $dps_country_rates[ $country ] + $base_shipping_type_price + $additional_quantity_cost ) . '</h4>';
            } elseif ( isset( $_POST['state'] ) && ! empty( $_POST['state'] ) ) {
                $state = $_POST['state'];
                echo __( 'Shipping Cost : ', 'sk' ) . '<strong>' . wc_price( $dps_state_rates[ $country ][ $state ] + $base_shipping_type_price + $additional_quantity_cost ) . '</strong>';
            }
        } else {
            esc_html_e( 'Please select a country from the dropdown', 'sk' );
        }
        $content = ob_get_clean();

        wp_send_json_success( $content );
    }

    /**
     * Save attributes from edit product page
     *
     * @return void
     */
    public function save_attributes() {
        // Get post data
        parse_str( $_POST['data'], $data );
        $post_id = absint( $_POST['post_id'] );

        // Save Attributes
        $attributes = [];

        if ( isset( $data['attribute_names'] ) ) {
            $attribute_names  = array_map( 'stripslashes', $data['attribute_names'] );
            $attribute_values = isset( $data['attribute_values'] ) ? $data['attribute_values'] : [];

            if ( isset( $data['attribute_visibility'] ) ) {
                $attribute_visibility = $data['attribute_visibility'];
            }

            if ( isset( $data['attribute_variation'] ) ) {
                $attribute_variation = $data['attribute_variation'];
            }

            $attribute_is_taxonomy   = $data['attribute_is_taxonomy'];
            $attribute_position      = $data['attribute_position'];
            $attribute_names_max_key = max( array_keys( $attribute_names ) );

            for ( $i = 0; $i <= $attribute_names_max_key; $i++ ) {
                if ( empty( $attribute_names[ $i ] ) ) {
                    continue;
                }

                $is_visible   = isset( $attribute_visibility[ $i ] ) ? 1 : 0;
                $is_variation = isset( $attribute_variation[ $i ] ) ? 1 : 0;
                $is_taxonomy  = $attribute_is_taxonomy[ $i ] ? 1 : 0;

                if ( $is_taxonomy ) {
                    if ( isset( $attribute_values[ $i ] ) ) {

                        // Select based attributes - Format values (posted values are slugs)
                        if ( is_array( $attribute_values[ $i ] ) ) {
                            $values = $attribute_values[ $i ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                            // Text based attributes - Posted values are term names, wp_set_object_terms wants ids or slugs.
                        } else {
                            $values     = [];
                            $raw_values = explode( WC_DELIMITER, $attribute_values[ $i ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                            foreach ( $raw_values as $value ) {
                                $term = get_term_by( 'name', $value, $attribute_names[ $i ] );
                                if ( ! $term ) {
                                    $term = wp_insert_term( $value, $attribute_names[ $i ] );

                                    if ( $term && ! is_wp_error( $term ) ) {
                                        $values[] = $term['term_id'];
                                    }
                                } else {
                                    $values[] = $term->term_id;
                                }
                            }
                        }

                        // Remove empty items in the array
                        $values = array_filter( $values, 'strlen' );
                    } else {
                        $values = [];
                    }

                    $values = array_map( 'strval', $values );

                    // Update post terms
                    if ( taxonomy_exists( $attribute_names[ $i ] ) ) {
                        wp_set_object_terms( $post_id, $values, $attribute_names[ $i ] );
                    }

                    if ( ! empty( $values ) ) {
                        // Add attribute to array, but don't set values
                        $attributes[ sanitize_title( $attribute_names[ $i ] ) ] = [
                            'name'         => wc_clean( $attribute_names[ $i ] ),
                            'value'        => '',
                            'position'     => $attribute_position[ $i ],
                            'is_visible'   => $is_visible,
                            'is_variation' => $is_variation,
                            'is_taxonomy'  => $is_taxonomy,
                        ];
                    }
                } elseif ( isset( $attribute_values[ $i ] ) ) {

                    // Text based, possibly separated by pipes (WC_DELIMITER). Preserve line breaks in non-variation attributes.
                    $values = implode( ' ' . WC_DELIMITER . ' ', array_map( 'wc_clean', array_map( 'stripslashes', $attribute_values[ $i ] ) ) );

                    // Custom attribute - Add attribute to array and set the values
                    $attributes[ sanitize_title( $attribute_names[ $i ] ) ] = [
                        'name'         => wc_clean( $attribute_names[ $i ] ),
                        'value'        => $values,
                        'position'     => $attribute_position[ $i ],
                        'is_visible'   => $is_visible,
                        'is_variation' => $is_variation,
                        'is_taxonomy'  => $is_taxonomy,
                    ];
                }
            }
        }

        uasort( $attributes, 'wc_product_attribute_uasort_comparison' );
        update_post_meta( $post_id, '_product_attributes', $attributes );
        die();
    }

    /**
     * Delete variations via ajax function
     */
    public function remove_variation() {
        if ( ! current_user_can( 'skdar' ) ) {
            die( -1 );
        }

        $variation_ids = (array) $_POST['variation_ids'];

        foreach ( $variation_ids as $variation_id ) {
            $variation = get_post( $variation_id );

            if ( $variation && 'product_variation' === $variation->post_type ) {
                wp_delete_post( $variation_id );
            }
        }

        die();
    }

    public function add_attr_predefined_attribute() {
        check_ajax_referer( 'sk_reviews' );

        global $wc_product_attributes;

        $thepostid = 0;
        $taxonomy  = sanitize_text_field( $_POST['taxonomy'] );
        $i         = absint( $_POST['i'] );
        $attribute = [
            'name'         => $taxonomy,
            'value'        => '',
            'is_visible'   => apply_filters( 'sk_attribute_default_visibility', 1 ),
            'is_variation' => apply_filters( 'sk_attribute_default_is_variation', 0 ),
            'is_taxonomy'  => $taxonomy ? 1 : 0,
        ];

        if ( $taxonomy ) {
            $attribute_taxonomy = $wc_product_attributes[ $taxonomy ];
            $metabox_class[]    = 'taxonomy';
            $metabox_class[]    = $taxonomy;
            $attribute_label    = wc_attribute_label( $taxonomy );
        } else {
            $attribute_label    = '';
            $attribute_taxonomy = [];
            $metabox_class[]    = '';
        }
        ob_start();
        sk_get_template_part(
            'products/edit/html-product-attribute', '', [
                'pro'                => true,
                'i'                  => $i,
                'thepostid'          => $thepostid,
                'taxonomy'           => $taxonomy,
                'attribute_taxonomy' => $attribute_taxonomy,
                'attribute_label'    => $attribute_label,
                'attribute'          => $attribute,
                'metabox_class'      => $metabox_class,
                'position'           => 0,
            ]
        );
        $content = ob_get_clean();
        wp_send_json_success( $content );
    }

    /**
     * Add new attribute from predifined attribute
     *
     *
     * @return void
     */
    public function add_new_attribute() {
        check_ajax_referer( 'sk_reviews' );

        if ( ! current_user_can( 'skdar' ) ) {
            die( -1 );
        }

        $taxonomy = esc_attr( $_POST['taxonomy'] );
        $term     = wc_clean( $_POST['term'] );

        if ( taxonomy_exists( $taxonomy ) ) {
            $result = wp_insert_term( $term, $taxonomy );

            if ( is_wp_error( $result ) ) {
                wp_send_json(
                    [
                        'error' => $result->get_error_message(),
                    ]
                );
            } else {
                $term = get_term_by( 'id', $result['term_id'], $taxonomy );
                wp_send_json(
                    [
                        'term_id' => $term->term_id,
                        'name'    => $term->name,
                        'slug'    => $term->slug,
                    ]
                );
            }
        }
    }

    /**
     * Add Predefined Attribute
     *
     *
     * @return json success|$content (array)
     */
    public function add_predefined_attribute() {
        $attr_name               = $_POST['name'];
        $single                  = ( isset( $_POST['from'] ) && $_POST['from'] === 'popup' ) ? 'single-' : '';
        $remove_btn              = ( isset( $_POST['from'] ) && $_POST['from'] === 'popup' ) ? 'single_' : '';
        $attribute_taxonomy_name = wc_attribute_taxonomy_name( $attr_name );
        $tax                     = get_taxonomy( $attribute_taxonomy_name );
        $options                 = get_terms( $attribute_taxonomy_name, 'orderby=name&hide_empty=0' );
        $att_val                 = wp_list_pluck( $options, 'name' );
        ob_start();
        ?>
        <tr class="sk-<?php echo $single; ?>attribute-options">
            <td width="20%">
                <input type="text" disabled="disabled" value="<?php echo $attr_name; ?>" class="sk-form-control sk-<?php echo $single; ?>attribute-option-name-label"
                       data-attribute_name="<?php echo wc_sanitize_taxonomy_name( str_replace( 'pa_', '', $attribute_taxonomy_name ) ); ?>">
                <input type="hidden" name="attribute_names[]" value="<?php echo esc_attr( $attribute_taxonomy_name ); ?>" class="sk-<?php echo $single; ?>attribute-option-name">
                <input type="hidden" name="attribute_is_taxonomy[]" value="1">
            </td>
            <td colspan="3"><input type="text" name="attribute_values[]" value="<?php echo implode( ',', $att_val ); ?>" data-preset_attr="<?php echo implode( ',', $att_val ); ?>"
                                   class="sk-form-control sk-<?php echo $single; ?>attribute-option-values"></td>
            <td>
                <button title="<?php _e( 'Clear All', 'sk' ); ?>" class="sk-btn sk-btn-theme clear_attributes"><?php _e( 'Clear', 'sk' ); ?></button>
                <button title="Delete" class="sk-btn sk-btn-theme remove_<?php echo $remove_btn; ?>attribute"><i class="far fa-trash-alt"></i></button>
            </td>
        </tr>
        <?php
        $content = ob_get_clean();
        wp_send_json_success( $content );
    }

    /**
     * Add variation via ajax function
     *
     *
     * @return void
     */
    public static function add_variation() {
        check_ajax_referer( 'add-variation', 'security' );

        if ( ! current_user_can( 'skdar' ) ) {
            die( -1 );
        }

        global $post;

        $post_id = intval( $_POST['post_id'] );
        $post    = get_post( $post_id ); // Set $post global so its available like within the admin screens
        $loop    = intval( $_POST['loop'] );

        $variation = [
            'post_title'   => 'Product #' . $post_id . ' Variation',
            'post_content' => '',
            'post_status'  => 'publish',
            'post_author'  => sk_get_current_user_id(),
            'post_parent'  => $post_id,
            'post_type'    => 'product_variation',
            'menu_order'   => -1,
        ];

        $variation_id = wp_insert_post( $variation );

        do_action( 'sk_create_product_variation', $variation_id );

        if ( $variation_id ) {
            $variation        = get_post( $variation_id );
            $variation_meta   = get_post_meta( $variation_id );
            $variation_data   = [];
            $shipping_classes = get_the_terms( $variation_id, 'product_shipping_class' );
            $variation_fields = [
                '_sku'                   => '',
                '_stock'                 => '',
                '_regular_price'         => '',
                '_sale_price'            => '',
                '_weight'                => '',
                '_length'                => '',
                '_width'                 => '',
                '_height'                => '',
                '_download_limit'        => '',
                '_download_expiry'       => '',
                '_downloadable_files'    => '',
                '_downloadable'          => '',
                '_virtual'               => '',
                '_thumbnail_id'          => '',
                '_sale_price_dates_from' => '',
                '_sale_price_dates_to'   => '',
                '_manage_stock'          => '',
                '_stock_status'          => '',
                '_backorders'            => null,
                '_tax_class'             => null,
                '_variation_description' => '',
            ];

            foreach ( $variation_fields as $field => $value ) {
                $variation_data[ $field ] = isset( $variation_meta[ $field ][0] ) ? maybe_unserialize( $variation_meta[ $field ][0] ) : $value;
            }

            // Add the variation attributes
            $variation_data = array_merge( $variation_data, wc_get_product_variation_attributes( $variation_id ) );

            // Formatting
            $variation_data['_regular_price']    = wc_format_localized_price( $variation_data['_regular_price'] );
            $variation_data['_sale_price']       = wc_format_localized_price( $variation_data['_sale_price'] );
            $variation_data['_weight']           = wc_format_localized_decimal( $variation_data['_weight'] );
            $variation_data['_length']           = wc_format_localized_decimal( $variation_data['_length'] );
            $variation_data['_width']            = wc_format_localized_decimal( $variation_data['_width'] );
            $variation_data['_height']           = wc_format_localized_decimal( $variation_data['_height'] );
            $variation_data['_thumbnail_id']     = absint( $variation_data['_thumbnail_id'] );
            $variation_data['image']             = $variation_data['_thumbnail_id'] ? wp_get_attachment_thumb_url( $variation_data['_thumbnail_id'] ) : '';
            $variation_data['shipping_class']    = $shipping_classes && ! is_wp_error( $shipping_classes ) ? current( $shipping_classes )->term_id : '';
            $variation_data['menu_order']        = $variation->menu_order;
            $variation_data['_stock']            = wc_stock_amount( $variation_data['_stock'] );
            $variation_data['_low_stock_amount'] = wc_format_localized_decimal( $variation_data['_low_stock_amount'] );

            // Get tax classes
            $tax_classes           = WC_Tax::get_tax_classes();
            $tax_class_options     = [];
            $tax_class_options[''] = __( 'Standard', 'sk' );

            if ( ! empty( $tax_classes ) ) {
                foreach ( $tax_classes as $class ) {
                    $tax_class_options[ sanitize_title( $class ) ] = esc_attr( $class );
                }
            }

            // Set backorder options
            $backorder_options = [
                'no'     => __( 'Do not allow', 'sk' ),
                'notify' => __( 'Allow, but notify customer', 'sk' ),
                'yes'    => __( 'Allow', 'sk' ),
            ];

            // set stock status options
            $stock_status_options = [
                'instock'     => __( 'In stock', 'sk' ),
                'outofstock'  => __( 'Out of stock', 'sk' ),
                'onbackorder' => __( 'On backorder', 'sk' ),
            ];

            // Get attributes
            $attributes = (array) maybe_unserialize( get_post_meta( $post_id, '_product_attributes', true ) );

            $parent_data = [
                'id'                   => $post_id,
                'attributes'           => $attributes,
                'tax_class_options'    => $tax_class_options,
                'sku'                  => get_post_meta( $post_id, '_sku', true ),
                'weight'               => wc_format_localized_decimal( get_post_meta( $post_id, '_weight', true ) ),
                'length'               => wc_format_localized_decimal( get_post_meta( $post_id, '_length', true ) ),
                'width'                => wc_format_localized_decimal( get_post_meta( $post_id, '_width', true ) ),
                'height'               => wc_format_localized_decimal( get_post_meta( $post_id, '_height', true ) ),
                'tax_class'            => get_post_meta( $post_id, '_tax_class', true ),
                'backorder_options'    => $backorder_options,
                'stock_status_options' => $stock_status_options,
            ];

            if ( ! $parent_data['weight'] ) {
                $parent_data['weight'] = wc_format_localized_decimal( 0 );
            }

            if ( ! $parent_data['length'] ) {
                $parent_data['length'] = wc_format_localized_decimal( 0 );
            }

            if ( ! $parent_data['width'] ) {
                $parent_data['width'] = wc_format_localized_decimal( 0 );
            }

            if ( ! $parent_data['height'] ) {
                $parent_data['height'] = wc_format_localized_decimal( 0 );
            }

            sk_get_template_part(
                'products/edit/html-product-variation', '', [
                    'pro'            => true,
                    'loop'           => $loop,
                    'variation_id'   => $variation_id,
                    'parent_data'    => $parent_data,
                    'variation_data' => $variation_data,
                    'variation'      => $variation,
                ]
            );
        }

        die();
    }

    /**
     * Link all variations via ajax function
     *
     *
     * @return void
     */
    public function link_all_variations() {
        if ( ! defined( 'WC_MAX_LINKED_VARIATIONS' ) ) {
            define( 'WC_MAX_LINKED_VARIATIONS', 49 );
        }

        check_ajax_referer( 'link-variations', 'security' );

        wc_set_time_limit( 0 );

        $post_id = intval( $_POST['post_id'] );

        if ( ! $post_id ) {
            die();
        }

        $variations = [];

        $_product = wc_get_product( $post_id );

        // Put variation attributes into an array
        foreach ( $_product->get_attributes() as $attribute ) {
            if ( ! $attribute['is_variation'] ) {
                continue;
            }

            $attribute_field_name = 'attribute_' . sanitize_title( $attribute['name'] );

            if ( $attribute['is_taxonomy'] ) {
                $options = wc_get_product_terms( $post_id, $attribute['name'], [ 'fields' => 'slugs' ] );
            } else {
                $options = explode( WC_DELIMITER, $attribute['value'] );
            }

            $options = array_map( 'trim', $options );

            $variations[ $attribute_field_name ] = $options;
        }

        // Quit out if none were found
        if ( sizeof( $variations ) === 0 ) {
            die();
        }

        // Get existing variations so we don't create duplicates
        $available_variations = [];

        foreach ( $_product->get_children() as $child_id ) {
            $child = wc_get_product( $child_id );

            if ( ! empty( $child->variation_id ) ) {
                $available_variations[] = $child->get_variation_attributes();
            }
        }

        // Created posts will all have the following data
        $variation_post_data = [
            'post_title'   => 'Product #' . $post_id . ' Variation',
            'post_content' => '',
            'post_status'  => 'publish',
            'post_author'  => sk_get_current_user_id(),
            'post_parent'  => $post_id,
            'post_type'    => 'product_variation',
        ];

        $variation_ids       = [];
        $added               = 0;
        $possible_variations = wc_array_cartesian( $variations );

        foreach ( $possible_variations as $variation ) {

            // Check if variation already exists
            if ( in_array( $variation, $available_variations ) ) {
                continue;
            }

            $variation_id = wp_insert_post( $variation_post_data );

            $variation_ids[] = $variation_id;

            foreach ( $variation as $key => $value ) {
                update_post_meta( $variation_id, $key, $value );
            }

            // Save stock status
            update_post_meta( $variation_id, '_stock_status', 'instock' );

            ++$added;

            do_action( 'sk_product_variation_linked', $variation_id );

            if ( $added > WC_MAX_LINKED_VARIATIONS ) {
                break;
            }
        }

        delete_transient( 'wc_product_children_' . $post_id );

        echo $added;

        die();
    }

    /**
     * SK Pre Define Attribute Render
     *
     *
     * @return void
     */
    public function sk_pre_define_attribute() {
        $attribute               = $_POST;
        $attribute_taxonomy_name = wc_attribute_taxonomy_name( $attribute['name'] );
        $tax                     = get_taxonomy( $attribute_taxonomy_name );
        $options                 = get_terms( $attribute_taxonomy_name, 'orderby=name&hide_empty=0' );
        $i                       = $_POST['row'];
        ob_start();
        ?>
        <div class="inputs-box woocommerce_attribute" data-count="<?php echo $i; ?>">
            <div class="box-header">
                <input type="text" disabled="disabled" value="<?php echo $attribute['name']; ?>">
                <input type="hidden" name="attribute_names[<?php echo $i; ?>]" value="<?php echo esc_attr( $attribute_taxonomy_name ); ?>">
                <input type="hidden" name="attribute_is_taxonomy[<?php echo $i; ?>]" value="1">
                <input type="hidden" name="attribute_position[<?php echo $i; ?>]" class="attribute_position" value="<?php echo esc_attr( $i ); ?>" />
                <span class="actions">
                    <button class="row-remove btn pull-right btn-danger btn-sm"><?php _e( 'Remove', 'sk' ); ?></button>
                </span>
            </div>
            <div class="box-inside clearfix">
                <div class="attribute-config">
                    <ul class="list-unstyled ">
                        <li>
                            <label class="checkbox-inline">
                                <input type="checkbox" class="checkbox"
                                    <?php
                                    $tax = '';
                                    checked( apply_filters( 'default_attribute_visibility', false, $tax ), true );
                                    ?>
                                       name="attribute_visibility[<?php echo $i; ?>]" value="1" /> <?php _e( 'Visible on the product page', 'sk' ); ?>
                            </label>
                        </li>
                        <li class="enable_variation" <?php echo ( $_POST['type'] === 'simple' ) ? 'style="display:none;"' : ''; ?>>
                            <label class="checkbox-inline">
                                <input type="checkbox" class="checkbox"
                                    <?php
                                    checked( apply_filters( 'default_attribute_variation', false, $tax ), true );
                                    ?>
                                       name="attribute_variation[<?php echo $i; ?>]" value="1" /> <?php _e( 'Used for variations', 'sk' ); ?></label>
                        </li>
                    </ul>
                </div>
                <div class="attribute-options">
                    <ul class="option-couplet list-unstyled ">
                        <?php
                        if ( $options ) {
                            foreach ( $options as $count => $option ) {
                                ?>
                                <li>
                                    <input type="text" class="option" placeholder="<?php _e( 'Option...', 'sk' ); ?>" name="attribute_values[<?php echo $i; ?>][<?php echo $count; ?>]" value="<?php echo esc_attr( $option->name ); ?>">
                                    <span class="item-action actions">
                                        <a href="#" class="row-add">+</a>
                                        <a href="#" class="row-remove">-</a>
                                    </span>
                                </li>
                                <?php
                            }
                        } else {
                            ?>
                            <li>
                                <input type="text" class="option" name="attribute_values[<?php echo $i; ?>][0]" placeholder="<?php _e( 'Option...', 'sk' ); ?>">
                                <span class="item-action actions">
                                    <a href="#" class="row-add">+</a>
                                    <a href="#" class="row-remove">-</a>
                                </span>
                            </li>
                            <?php
                        }
                        ?>
                    </ul>
                </div> <!-- .attribute-options -->
            </div> <!-- .box-inside -->
        </div> <!-- .input-box -->
        <?php
        $response = ob_get_clean();

        return wp_send_json_success( $response );
    }

    public function sk_json_search_products_tags() {
        $return = [];
        $name   = ! empty( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
        $page   = ! empty( $_GET['page'] ) ? absint( $_GET['page'] ) : 1;
        $offset = ( $page - 1 ) * 10;

        $product_tags = get_terms( [
            'taxonomy'   => 'product_tag',
            'name__like' => $name,
            'hide_empty' => 0,
            'orderby'    => 'name',
            'order'      => 'ASC',
            'number'     => 10,
            'offset'     => $offset,
        ] );

        if ( $product_tags ) {
            foreach ( $product_tags as $tag ) {
                $return[ $tag->term_id ] = $tag->name;
            }
        }

        wp_send_json_success( $return );
    }
}
