<?php

namespace SK\Core;

use WC_Meta_Box_Product_Data;
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

        // Single product Design ajax
        add_action( 'wp_ajax_sk_load_order_items', [ $this, 'load_order_items' ] );

        add_action( 'wp_ajax_sk_toggle_seller', [ $this, 'toggle_seller_status' ] );
    }

    /**
     * May the current user edit this product?
     *
     * The 'skdar' capability only says "has a vendor dashboard" — every seller
     * has it. Without an author check on top, any seller could edit or delete
     * the products of any other seller by passing their product ID.
     *
     * @param int $product_id
     * @return bool
     */
    public static function can_edit_product( $product_id ) {
        $product_id = absint( $product_id );

        if ( ! $product_id ) {
            return false;
        }

        $post = get_post( $product_id );

        if ( ! $post || 'product' !== $post->post_type ) {
            return false;
        }

        if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_others_products' ) ) {
            return true;
        }

        return (int) $post->post_author === (int) sk_get_current_user_id();
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
