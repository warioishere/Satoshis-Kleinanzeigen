<?php

namespace SK\Core\Admin;


/**
 * Ajax handling for SK in Admin area
 */
class Ajax {

    public function __construct() {
        add_action( 'wp_ajax_rewrite_product_variations_author', [ $this, 'rewrite_product_variations_author' ] );
    }

    /**
     * Rewrite product variations author via ajax.
     */
    public function rewrite_product_variations_author() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'sk_admin' ) ) {
            wp_send_json_error( __( 'Nonce verification failed', 'sk-core' ), 403 );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'You don\'t have enough permission', 'sk-core' ), 403 );
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
                'message' => __( 'Variable product variations author ids rewriting queued successfully', 'sk-core' ),
            ]
        );
    }
}
