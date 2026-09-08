<?php

namespace SK\Modules\Reputation;

use SK\Core\Trust\VendorKey;

defined( 'ABSPATH' ) || exit;

/**
 * The store's trust page: every signal with its source and how to check it.
 *
 * /store/{slug}/vertrauen/ — the "why" behind the chips. The tab appears
 * only when the vendor has at least one signal the server knows about
 * (a proven key, a verified link, zaps received, Lightning proofs); the
 * viewer-relative graph line is added by the browser as everywhere else.
 * A vendor without any signal gets no tab and no page that says so.
 */
class TrustPage {

    const SLUG      = 'vertrauen';
    const QUERY_VAR = 'sk_trust';

    /** The address the payment proofs used to have; it now redirects here. */
    const LEGACY_SLUG      = 'lightning-proof';
    const LEGACY_QUERY_VAR = 'lightning_proof';

    /** Upper bound for the public proof list. */
    const PROOF_LIMIT = 200;

    public function __construct() {
        add_filter( 'sk_store_tabs', [ $this, 'add_store_tab' ], 10, 2 );
        add_action( 'sk_rewrite_rules_loaded', [ $this, 'add_rewrite_rule' ] );
        add_filter( 'query_vars', [ $this, 'add_query_var' ] );
        add_filter( 'template_include', [ $this, 'load_template' ], 100 );
        add_action( 'template_redirect', [ $this, 'redirect_legacy' ] );
    }

    public function add_store_tab( array $tabs, int $store_id ): array {
        if ( ! self::has_signals( $store_id ) ) {
            return $tabs;
        }

        $tabs['trust'] = [
            'title' => __( 'Vertrauen', 'sk-core' ),
            'url'   => sk_get_store_url( $store_id, self::SLUG ),
        ];

        return $tabs;
    }

    public function add_rewrite_rule( $store_base ) {
        add_rewrite_rule(
            $store_base . '/([^/]+)/' . self::SLUG . '/?$',
            'index.php?' . $store_base . '=$matches[1]&' . self::QUERY_VAR . '=true',
            'top'
        );

        // The old proof page: still routed, so it can redirect.
        add_rewrite_rule(
            $store_base . '/([^/]+)/' . self::LEGACY_SLUG . '/?$',
            'index.php?' . $store_base . '=$matches[1]&' . self::LEGACY_QUERY_VAR . '=true',
            'top'
        );
    }

    public function add_query_var( array $vars ): array {
        $vars[] = self::QUERY_VAR;
        $vars[] = self::LEGACY_QUERY_VAR;
        return $vars;
    }

    /**
     * /store/{slug}/lightning-proof/ → /store/{slug}/vertrauen/, permanently.
     * Links out there keep working; the trust page is the one address.
     */
    public function redirect_legacy(): void {
        if ( ! get_query_var( self::LEGACY_QUERY_VAR ) ) {
            return;
        }

        $store_name = get_query_var( sk_get_option( 'custom_store_url', 'sk_general', 'store' ) );
        $store_user = $store_name ? get_user_by( 'slug', $store_name ) : false;

        if ( $store_user ) {
            wp_safe_redirect( sk_get_store_url( (int) $store_user->ID, self::SLUG ), 301 );
            exit;
        }
    }

    /**
     * The verified payments behind "Belegte Zahlungen", newest first.
     *
     * @return object[]
     */
    public static function get_proofs( int $vendor_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'sk_lightning_payments';

        if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
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

    public function load_template( $template ) {
        if ( ! get_query_var( self::QUERY_VAR ) ) {
            return $template;
        }

        return self::template_for_current_store( $template );
    }

    /**
     * The trust template when the URL names an existing store that has at
     * least one signal; otherwise a 404. A vendor without signals gets no
     * tab and no page either — a page with nothing on it would be the
     * negative display the rules forbid.
     */
    public static function template_for_current_store( $template ) {
        $custom_store_url = sk_get_option( 'custom_store_url', 'sk_general', 'store' );
        $store_name       = get_query_var( $custom_store_url );

        if ( empty( $store_name ) ) {
            return $template;
        }

        $store_user = get_user_by( 'slug', $store_name );

        if ( ! $store_user || ! self::has_signals( (int) $store_user->ID ) ) {
            global $wp_query;

            if ( $wp_query instanceof \WP_Query ) {
                $wp_query->set_404();
            }

            status_header( 404 );
            nocache_headers();

            return get_404_template();
        }

        return SK_REPUTATION_TEMPLATES . '/store-trust.php';
    }

    // ── What the page shows ─────────────────────────────────────────────

    public static function has_signals( int $vendor_id ): bool {
        // Trust pages are for stores; a customer account with a Nostr key is
        // not one.
        if ( $vendor_id <= 0 || ! function_exists( 'sk_is_user_seller' ) || ! sk_is_user_seller( $vendor_id ) ) {
            return false;
        }

        if ( '' !== VendorKey::bound( $vendor_id ) ) {
            return true;
        }

        if ( ! empty( self::verified_hosts( $vendor_id ) ) ) {
            return true;
        }

        if ( self::zaps( $vendor_id )['sats'] > 0 ) {
            return true;
        }

        // Only whether a reputation row exists; the proof list itself is
        // loaded by the page, not for the tab.
        return Module::payments_available()
            && class_exists( 'SK\Modules\Payments\StoreSettings' )
            && (bool) \SK\Modules\Payments\StoreSettings::get_reputation( $vendor_id );
    }

    /**
     * The vendor's proven key and how it is proven.
     *
     * @return array{pubkey: string, npub: string, type: string, event: ?array, relays: string[], event_json: string}|null
     */
    public static function nostr( int $vendor_id ): ?array {
        $proof = VendorKey::proof( $vendor_id );

        if ( ! $proof ) {
            return null;
        }

        $npub  = \SK\Core\Nostr\Keys::to_npub( $proof['pubkey'] );
        $event = $proof['event'];

        return [
            'pubkey'     => $proof['pubkey'],
            'npub'       => $npub,
            'type'       => $proof['type'],
            'event'      => $event,
            'relays'     => $proof['relays'],
            'event_json' => $event ? (string) wp_json_encode( $event, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '',
        ];
    }

    /** @return string[] */
    public static function verified_hosts( int $vendor_id ): array {
        if ( ! class_exists( \SK\Core\Verification\VerifiedLinks::class ) ) {
            return [];
        }

        return \SK\Core\Verification\VerifiedLinks::confirmed_hosts( $vendor_id );
    }

    /** @return array{url: string}[] */
    public static function verified_links( int $vendor_id ): array {
        if ( ! class_exists( \SK\Core\Verification\VerifiedLinks::class ) ) {
            return [];
        }

        return \SK\Core\Verification\VerifiedLinks::confirmed( $vendor_id );
    }

    /**
     * @return array{sats: int, count: int, time: int, source: string}
     */
    public static function zaps( int $vendor_id ): array {
        if ( ! sk_module_active( 'sk_zaps' ) || ! class_exists( 'SK\Modules\Zaps\ZapStats' ) ) {
            return [ 'sats' => 0, 'count' => 0, 'time' => 0, 'source' => '' ];
        }

        // The zaps module owns its numbers; the page only shows them.
        return \SK\Modules\Zaps\ZapStats::summary( $vendor_id );
    }

    /**
     * The payment-based numbers, when SK Payments is a source here.
     *
     * @return array{rep: object, proofs: array}|null
     */
    public static function lightning( int $vendor_id ): ?array {
        if ( ! Module::payments_available() || ! class_exists( 'SK\Modules\Payments\StoreSettings' ) ) {
            return null;
        }

        $rep = \SK\Modules\Payments\StoreSettings::get_reputation( $vendor_id );

        if ( ! $rep ) {
            return null;
        }

        return [
            'rep'    => $rep,
            'proofs' => self::get_proofs( $vendor_id ),
        ];
    }
}
