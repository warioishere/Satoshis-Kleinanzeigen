<?php

use SK\Core\Cache;

class SK_Follow_Store_Ajax {

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        add_action( 'wp_ajax_sk_follow_store_toggle_status', array( $this, 'toggle_follow_status' ) );
        add_filter( 'wp_ajax_sk_follow_store_get_current_status', array( $this, 'get_current_status' ) );
    }

    /**
     * Toggle follow store status
     *
     *
     * @return void
     */
    public function toggle_follow_status() {
        if ( empty( $_POST ) || ! isset( $_POST['_nonce'] ) || ! wp_verify_nonce( $_POST['_nonce'], 'sk_follow_store' ) ) {
            wp_send_json_error( new WP_Error( 'invalid_nonce', __( 'Nonce is invalid', 'sk-core' ) ), 403 );
        }

        if ( empty( $_POST['vendor_id'] ) ) {
            wp_send_json_error( new WP_Error( 'missing_required_field', __( 'vendor_id field is required', 'sk-core' ) ), 422 );
        }

        $customer_id = get_current_user_id();
        $vendor_id   = absint( $_POST['vendor_id'] );

        // sk()->vendor->get() answers for any user id, so ask whether the id is
        // a listed store instead of whether the object came back.
        if ( ! sk_follow_store_is_followable_vendor( $vendor_id ) ) {
            wp_send_json_error( new WP_Error( 'invalid_vendor', __( 'Invalid vendor_id', 'sk-core' ) ), 422 );
        }

        if ( $vendor_id === $customer_id ) {
            wp_send_json_error( new WP_Error( 'self_follow', __( 'Du kannst deinem eigenen Shop nicht folgen.', 'sk-core' ) ), 422 );
        }

        if ( ! sk_rate_limit( 'follow-toggle:' . $customer_id, 20 ) ) {
            wp_send_json_error( new WP_Error( 'rate_limited', __( 'Zu viele Anfragen. Bitte kurz warten.', 'sk-core' ) ), 429 );
        }

        $status = sk_follow_store_toggle_status( $vendor_id, $customer_id );

        if ( is_wp_error( $status ) ) {
            wp_send_json_error( $status, 422 );
        }

        wp_send_json_success( array( 'status' => $status ), 200 );
    }

    /**
     * Get current follow status
     *
     *
     * @return void
     */
    public function get_current_status() {
        if ( empty( $_GET['vendor_id'] ) ) {
            wp_send_json_error( array( 'message' => __( 'vendor_id is required.', 'sk-core' ) ), 400 );
        }

        $vendor_id   = absint( $_GET['vendor_id'] );
        $customer_id = get_current_user_id();

        if ( ! $customer_id ) {
            wp_send_json_error( array( 'message' => __( 'You have to logged in to get your status.', 'sk-core' ) ), 400 );
        }

        $is_following = sk_follow_store_is_following_store( $vendor_id, $customer_id );

        wp_send_json_success( array(
            'is_following' => $is_following,
            'nonce'        => wp_create_nonce( 'sk_follow_store' ),
        ) );
    }
}
