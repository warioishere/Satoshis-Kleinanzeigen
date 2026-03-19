<?php

namespace SK_Lightning;

defined( 'ABSPATH' ) || exit;

class ProofPage {

    public function __construct() {
        // Register "⚡ LN Reputation" tab on vendor store page.
        add_filter( 'sk_store_tabs', [ $this, 'add_store_tab' ], 10, 2 );

        // Register rewrite rule: /store/vendorx/lightning-proof/
        add_action( 'sk_rewrite_rules_loaded', [ $this, 'add_rewrite_rule' ] );

        // Register query variable.
        add_filter( 'query_vars', [ $this, 'add_query_var' ] );

        // Load template when lightning_proof query var is set.
        // Priority 100 = after sk-core's store_template (99) so we can override it.
        add_filter( 'template_include', [ $this, 'load_template' ], 100 );
    }

    /**
     * Add "⚡ LN Reputation" tab to vendor store page tabs.
     */
    public function add_store_tab( array $tabs, int $store_id ): array {
        $address = StoreSettings::get_lightning_address( $store_id );

        if ( empty( $address ) ) {
            return $tabs;
        }

        $tabs['lightning_proof'] = [
            'title' => '⚡ LN Reputation',
            'url'   => sk_get_store_url( $store_id, 'lightning-proof' ),
        ];

        return $tabs;
    }

    /**
     * Add rewrite rule for /store/vendorx/lightning-proof/
     */
    public function add_rewrite_rule( $store_base ) {
        add_rewrite_rule(
            $store_base . '/([^/]+)/lightning-proof/?$',
            'index.php?' . $store_base . '=$matches[1]&lightning_proof=true',
            'top'
        );
    }

    /**
     * Register the lightning_proof query variable.
     */
    public function add_query_var( array $vars ): array {
        $vars[] = 'lightning_proof';
        return $vars;
    }

    /**
     * Load the proof template when query var is set.
     */
    public function load_template( $template ) {
        if ( ! get_query_var( 'lightning_proof' ) ) {
            return $template;
        }

        $custom_store_url = sk_get_option( 'custom_store_url', 'sk_general', 'store' );
        $store_name       = get_query_var( $custom_store_url );

        if ( empty( $store_name ) ) {
            return $template;
        }

        $seller = get_user_by( 'slug', $store_name );
        if ( ! $seller ) {
            return get_404_template();
        }

        return SK_LIGHTNING_DIR . 'templates/store-lightning-proof.php';
    }

    /**
     * Get all reputation-valid payments for a vendor (public data only).
     */
    public static function get_proofs( int $vendor_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'sk_lightning_payments';

        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( ! $table_exists ) {
            return [];
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT payment_hash, amount_sats, payment_request,
                    created_at, confirmed_at, product_id
             FROM {$table}
             WHERE vendor_id = %d AND reputation_valid = 1
             ORDER BY confirmed_at DESC",
            $vendor_id
        ) );
    }
}
