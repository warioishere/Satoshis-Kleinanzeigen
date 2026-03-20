<?php

namespace SK\Modules\Reputation;

use SK\Modules\Payments\StoreSettings;

defined( 'ABSPATH' ) || exit;

class ProofPage {

    public function __construct() {
        add_filter( 'sk_store_tabs', [ $this, 'add_store_tab' ], 10, 2 );
        add_action( 'sk_rewrite_rules_loaded', [ $this, 'add_rewrite_rule' ] );
        add_filter( 'query_vars', [ $this, 'add_query_var' ] );
        add_filter( 'template_include', [ $this, 'load_template' ], 100 );
    }

    public function add_store_tab( array $tabs, int $store_id ): array {
        if ( ! class_exists( StoreSettings::class ) ) {
            return $tabs;
        }

        // Show tab if vendor has any payment method configured (LN or Onchain).
        $has_payments = StoreSettings::has_lightning( $store_id ) || StoreSettings::has_onchain( $store_id );

        if ( ! $has_payments ) {
            return $tabs;
        }

        $tabs['lightning_proof'] = [
            'title' => 'Reputation',
            'url'   => sk_get_store_url( $store_id, 'lightning-proof' ),
        ];

        return $tabs;
    }

    public function add_rewrite_rule( $store_base ) {
        add_rewrite_rule(
            $store_base . '/([^/]+)/lightning-proof/?$',
            'index.php?' . $store_base . '=$matches[1]&lightning_proof=true',
            'top'
        );
    }

    public function add_query_var( array $vars ): array {
        $vars[] = 'lightning_proof';
        return $vars;
    }

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

        return SK_REPUTATION_TEMPLATES . '/store-lightning-proof.php';
    }

    public static function get_proofs( int $vendor_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'sk_lightning_payments';

        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( ! $table_exists ) {
            return [];
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT payment_hash, amount_sats, payment_request,
                    created_at, confirmed_at, product_id, context
             FROM {$table}
             WHERE vendor_id = %d AND reputation_valid = 1
             ORDER BY confirmed_at DESC",
            $vendor_id
        ) );
    }
}
