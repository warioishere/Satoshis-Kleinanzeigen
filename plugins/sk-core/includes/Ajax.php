<?php

namespace SK\Core;

use WC_Meta_Box_Product_Data;
use WC_Product_Variable;
use WC_Tax;

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

        // Variation Handle for Vendor frontend
        add_action( 'wp_ajax_sk_add_variation', [ $this, 'add_variation' ] );
        add_action( 'wp_ajax_sk_link_all_variations', [ $this, 'link_all_variations' ] );
        add_action( 'wp_ajax_sk_pre_define_attribute', [ $this, 'sk_pre_define_attribute' ] );
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
