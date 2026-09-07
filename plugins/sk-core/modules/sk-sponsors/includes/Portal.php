<?php

namespace SK\Modules\Sponsors;

defined( 'ABSPATH' ) || exit;

/**
 * Self-service page for sponsors at /sponsor/<token>/.
 *
 * Deliberately doesn't show just a payment form, but first the value
 * received: clicks in the last 30 days, balance status, remaining months.
 * Someone deciding to renew needs to see what for — a bare amount field
 * doesn't answer that.
 *
 * Access via a secret token instead of a user account: sponsors are
 * companies that don't want to register just to renew.
 */
class Portal {

    const QUERY_VAR = 'sk_sponsor_token';
    const PREFIX    = 'sponsor';

    public function __construct() {
        add_action( 'init', [ $this, 'add_rewrite_rule' ] );
        add_filter( 'query_vars', [ $this, 'add_query_var' ] );
        add_action( 'template_redirect', [ $this, 'maybe_handle_post' ], 1 );
        add_action( 'template_redirect', [ $this, 'prepare_page' ], 2 );
        add_filter( 'template_include', [ $this, 'override_template' ], 99 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 20 );
    }

    public function add_rewrite_rule(): void {
        add_rewrite_rule(
            '^' . self::PREFIX . '/([A-Za-z0-9]{24,64})/?$',
            'index.php?' . self::QUERY_VAR . '=$matches[1]',
            'top'
        );
    }

    public function add_query_var( $vars ): array {
        $vars[] = self::QUERY_VAR;

        return $vars;
    }

    public static function url_for( int $sponsor_id ): string {
        return home_url( '/' . self::PREFIX . '/' . PostType::token( $sponsor_id ) . '/' );
    }

    private function current_sponsor(): ?\WP_Post {
        $token = (string) get_query_var( self::QUERY_VAR );

        return $token === '' ? null : PostType::by_token( $token );
    }

    /**
     * Betrag entgegennehmen, Rechnung anlegen, zur Zahlung weiterleiten.
     */
    public function maybe_handle_post(): void {
        $sponsor = $this->current_sponsor();
        if ( ! $sponsor ) {
            return;
        }

        if ( strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'POST' ) {
            return;
        }

        $sats = isset( $_POST['sk_topup_sats'] ) ? absint( $_POST['sk_topup_sats'] ) : 0;
        if ( $sats <= 0 ) {
            wp_safe_redirect( add_query_arg( 'fehler', 'betrag', self::url_for( (int) $sponsor->ID ) ) );
            exit;
        }

        $order = TopUp::create_invoice( (int) $sponsor->ID, $sats, (string) get_post_meta( $sponsor->ID, PostType::META_EMAIL, true ) );

        if ( is_wp_error( $order ) ) {
            wp_safe_redirect( add_query_arg( 'fehler', 'rechnung', self::url_for( (int) $sponsor->ID ) ) );
            exit;
        }

        // Straight into the order's BTCPay payment page.
        wp_safe_redirect( $order->get_checkout_payment_url() );
        exit;
    }

    /**
     * Fix up the status code.
     *
     * There's no post at this address, so WordPress would otherwise treat it
     * as a 404 — which would mislead search engines and caching layers. If
     * the token matches nobody, the request lands on the homepage instead of
     * some arbitrary other page with status 200; the same behavior as an
     * unknown /go/ slug.
     */
    public function prepare_page(): void {
        $token = (string) get_query_var( self::QUERY_VAR );
        if ( $token === '' ) {
            return;
        }

        if ( ! $this->current_sponsor() ) {
            wp_safe_redirect( home_url( '/' ), 302 );
            exit;
        }

        global $wp_query;
        $wp_query->is_404 = false;
        status_header( 200 );
        nocache_headers();
    }

    public function enqueue(): void {
        if ( ! $this->current_sponsor() ) {
            return;
        }

        wp_enqueue_style(
            'sk-sponsors',
            SK_SPONSORS_URL . '/assets/css/sk-sponsors.css',
            [],
            SK_SPONSORS_VERSION
        );
    }

    public function override_template( $template ) {
        $sponsor = $this->current_sponsor();
        if ( ! $sponsor ) {
            return $template;
        }

        $own = SK_SPONSORS_PATH . '/templates/portal.php';

        return file_exists( $own ) ? $own : $template;
    }
}
