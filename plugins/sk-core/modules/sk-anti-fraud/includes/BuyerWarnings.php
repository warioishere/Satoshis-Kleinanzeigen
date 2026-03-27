<?php

namespace SK\Modules\AntiFraud;

defined( 'ABSPATH' ) || exit;

class BuyerWarnings {

    public function __construct() {
        add_action( 'woocommerce_single_product_summary', [ $this, 'maybe_show_warning' ], 5 );
    }

    public function maybe_show_warning() {
        global $product;
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return;
        }

        $vendor_id = (int) get_post_field( 'post_author', $product->get_id() );
        if ( ! $vendor_id ) {
            return;
        }

        // Don't warn about own products.
        if ( is_user_logged_in() && get_current_user_id() === $vendor_id ) {
            return;
        }

        $threshold = (int) sk_get_option( 'sk_antifraud_warning_threshold', 'sk_antifraud', '5' );

        // Check reputation.
        global $wpdb;
        $valid_tx = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT valid_transactions FROM {$wpdb->prefix}sk_reputation_scores WHERE vendor_id = %d",
            $vendor_id
        ) );

        if ( $valid_tx >= $threshold ) {
            return;
        }

        include SK_ANTIFRAUD_PATH . '/templates/buyer-warning.php';
    }
}
