<?php

namespace SK\Modules\Reputation;

defined( 'ABSPATH' ) || exit;

/**
 * The payment proofs: the list behind the trust page's "Belegte Zahlungen"
 * and the old /store/{slug}/lightning-proof/ address, which now shows the
 * trust page so links out there keep working.
 */
class ProofPage {

    public function __construct() {
        add_action( 'sk_rewrite_rules_loaded', [ $this, 'add_rewrite_rule' ] );
        add_filter( 'query_vars', [ $this, 'add_query_var' ] );
        add_filter( 'template_include', [ $this, 'load_template' ], 100 );
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

        return TrustPage::template_for_current_store( $template );
    }

    /** Upper bound for the public proof list. */
    const PROOF_LIMIT = 200;

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
             ORDER BY confirmed_at DESC
             LIMIT %d",
            $vendor_id,
            self::PROOF_LIMIT
        ) );
    }
}
