<?php

namespace SK\Core\Admin;

use SK\Core\Modules\TableRate\SkGoogleDistanceMatrixAPI;

/**
 * Ajax handling for SK in Admin area
 */
class Ajax {

    public function __construct() {
        add_action( 'wp_ajax_rewrite_product_variations_author', [ $this, 'rewrite_product_variations_author' ] );
        add_action( 'wp_ajax_sk_get_distance_btwn_address', [ $this, 'get_distance_btwn_address' ] );
    }

    /**
     * Rewrite product variations author via ajax.
     */
    public function rewrite_product_variations_author() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'sk_admin' ) ) {
            wp_send_json_error( __( 'Nonce verification failed', 'sk' ), 403 );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'You don\'t have enough permission', 'sk' ), 403 );
        }

        $page         = ! empty( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
        $bg_processor = sk()->bg_process->rewrite_variable_products_author;

        $args = [
            'updating' => 'sk_update_variable_product_variations_author_ids',
            'page'     => $page,
        ];

        $bg_processor->push_to_queue( $args )->save()->dispatch();

        wp_send_json_success(
            [
                'process' => 'running',
                'message' => __( 'Variable product variations author ids rewriting queued successfully', 'sk' ),
            ]
        );
    }

    /**
     * Get distance between two address to check if Distance Matrix API is working or not
     */
    public function get_distance_btwn_address() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'sk_admin' ) ) {
            wp_send_json_error( __( 'Nonce verification failed', 'sk' ), 403 );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'You don\'t have enough permission', 'sk' ), 403 );
        }

        if ( ! sk_ext()->module->is_active( 'table_rate_shipping' ) ) {
            wp_send_json_error( __( 'Table Rate Shipping module is not active', 'sk' ), 403 );
        }

        $address1 = isset( $_POST['address1'] ) ? sanitize_text_field( wp_unslash( $_POST['address1'] ) ) : '';
        $address2 = isset( $_POST['address2'] ) ? sanitize_text_field( wp_unslash( $_POST['address2'] ) ) : '';

        if ( empty( $address1 ) ) {
            wp_send_json_error( __( 'Address 1 is empty', 'sk' ), 403 );
        }

        if ( empty( $address2 ) ) {
            wp_send_json_error( __( 'Address 2 is empty', 'sk' ), 403 );
        }

        $gmap_api_key = trim( sk_get_option( 'gmap_api_key', 'sk_appearance', '' ) );
        if ( empty( $gmap_api_key ) ) {
            wp_send_json_error( __( 'Google Map API key is not set', 'sk' ), 403 );
        }

        $api      = new SkGoogleDistanceMatrixAPI( $gmap_api_key, false );
        $distance = $api->get_distance( $address1, $address2, false );

        if ( isset( $distance->status ) && 'OK' === $distance->status ) {
            wp_send_json_success( __( 'Distance Matrix API is enabled.', 'sk' ) );
        }

        $message = sprintf(
            '<strong>%s:</strong> %s, <strong>%s:</strong> %s',
            __( 'Error Code', 'sk' ),
            $distance->status,
            __( 'Error Message', 'sk' ),
            $distance->error_message
        );

        wp_send_json_error( $message, 403 );
    }
}
