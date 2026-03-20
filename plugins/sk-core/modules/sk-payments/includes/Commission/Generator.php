<?php

namespace SK\Modules\Payments\Commission;

use SK\Modules\Payments\LNDHub\Client as LNDHubClient;

defined( 'ABSPATH' ) || exit;

/**
 * Generates commission invoices for the marketplace.
 *
 * Triggered when a payment is verified:
 *   - Buyer clicks "Produkt erhalten" (instant)
 *   - 7-day timeout via Cron
 *
 * Creates a Lightning invoice on the marketplace LNDHub wallet
 * and tracks payment status.
 */
class Generator {

    public function __construct() {
        // Hook into payment delivery confirmation (instant path).
        add_action( 'sk_payment_delivered', [ $this, 'create_commission' ], 10, 1 );

        // Hook into cron reputation processing (7-day timeout path).
        add_action( 'sk_payment_reputation_credited', [ $this, 'create_commission' ], 10, 1 );

        // Own cron hook — runs independently of reputation module.
        add_action( 'sk_commission_check', [ __CLASS__, 'cron_check' ] );
        if ( ! wp_next_scheduled( 'sk_commission_check' ) ) {
            wp_schedule_event( time(), 'six_hours', 'sk_commission_check' );
        }

        // Admin menu for commission overview.
        add_action( 'admin_menu', [ $this, 'add_admin_page' ], 21 );
    }

    /**
     * Independent cron: check pending invoices + run enforcement.
     */
    public static function cron_check() {
        self::check_pending_invoices();
        if ( class_exists( Enforcement::class ) ) {
            Enforcement::process();
        }
    }

    /**
     * Create a commission invoice for a verified payment.
     *
     * @param object $payment Row from sk_lightning_payments.
     */
    public function create_commission( $payment ) {
        if ( ! self::is_enabled() ) {
            return;
        }

        if ( ! is_object( $payment ) || empty( $payment->payment_hash ) ) {
            return;
        }

        // Check if commission already exists for this payment.
        global $wpdb;
        $table = $wpdb->prefix . 'sk_commissions';

        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE payment_hash = %s",
            $payment->payment_hash
        ) );

        if ( $exists ) {
            return;
        }

        $rate = self::get_rate();
        if ( $rate <= 0 ) {
            return;
        }

        $commission_sats = (int) max( 1, round( $payment->amount_sats * $rate / 100 ) );

        // Create invoice on marketplace LNDHub.
        $client = self::get_marketplace_client();
        $invoice_pr   = '';
        $invoice_hash = '';

        if ( $client ) {
            $vendor = get_userdata( $payment->vendor_id );
            $vendor_name = $vendor ? $vendor->display_name : '#' . $payment->vendor_id;
            $product_title = $payment->product_id ? get_the_title( $payment->product_id ) : '';
            $memo = "Kommission {$rate}%: {$vendor_name}";
            if ( $product_title ) {
                $memo .= " — {$product_title}";
            }

            $result = $client->make_invoice( $commission_sats, $memo );
            if ( ! is_wp_error( $result ) ) {
                $invoice_pr   = $result['pr'];
                $invoice_hash = $result['payment_hash'] ?? '';

                // Fallback: extract payment_hash from bolt11 if LNDHub didn't return it.
                if ( empty( $invoice_hash ) && ! empty( $invoice_pr ) ) {
                    $extracted = \SK\Modules\Payments\LNURL\Bolt11Parser::get_payment_hash( $invoice_pr );
                    if ( ! is_wp_error( $extracted ) ) {
                        $invoice_hash = $extracted;
                    }
                }
            }
        }

        $wpdb->insert( $table, [
            'payment_id'              => $payment->id,
            'payment_hash'            => $payment->payment_hash,
            'vendor_id'               => $payment->vendor_id,
            'original_amount_sats'    => $payment->amount_sats,
            'commission_rate'         => $rate,
            'commission_sats'         => $commission_sats,
            'invoice_payment_request' => $invoice_pr,
            'invoice_payment_hash'    => $invoice_hash,
            'status'                  => $invoice_pr ? 'invoiced' : 'pending',
            'created_at'              => current_time( 'mysql' ),
        ], [ '%d', '%s', '%d', '%d', '%f', '%d', '%s', '%s', '%s', '%s' ] );
    }

    /**
     * Check if a commission invoice has been paid. Called by cron.
     */
    public static function check_pending_invoices() {
        if ( ! self::is_enabled() ) {
            return;
        }

        $client = self::get_marketplace_client();
        if ( ! $client ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sk_commissions';

        $pending = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE status = 'invoiced' AND invoice_payment_hash != '' LIMIT 50"
        );

        foreach ( $pending as $commission ) {
            $result = $client->lookup_invoice( $commission->invoice_payment_hash );

            if ( ! is_wp_error( $result ) && ! empty( $result['settled'] ) ) {
                $wpdb->update(
                    $table,
                    [
                        'status'  => 'paid',
                        'paid_at' => current_time( 'mysql' ),
                    ],
                    [ 'id' => $commission->id ],
                    [ '%s', '%s' ],
                    [ '%d' ]
                );
            }
        }
    }

    public function add_admin_page() {
        add_submenu_page(
            'sk',
            'Kommissionen',
            'Kommissionen',
            'manage_options',
            'sk-commissions',
            [ $this, 'render_admin_page' ]
        );
    }

    public function render_admin_page() {
        require SK_PAYMENTS_TEMPLATES . '/admin-commissions.php';
    }

    public static function is_enabled(): bool {
        return sk_get_option( 'sk_commission_enabled', 'sk_lightning', 'off' ) === 'on';
    }

    public static function get_rate(): float {
        return (float) sk_get_option( 'sk_commission_rate', 'sk_lightning', '2' );
    }

    public static function get_marketplace_client(): ?LNDHubClient {
        $connection = \SK\Modules\Payments\Admin\AdminSettings::get_marketplace_lndhub();
        if ( empty( $connection ) ) {
            return null;
        }

        $client = LNDHubClient::from_connection_string( $connection );
        return is_wp_error( $client ) ? null : $client;
    }

    /**
     * Create the commissions table.
     */
    public static function create_table() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $table   = $wpdb->prefix . 'sk_commissions';

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            payment_id BIGINT UNSIGNED NOT NULL,
            payment_hash VARCHAR(64) NOT NULL,
            vendor_id BIGINT UNSIGNED NOT NULL,
            original_amount_sats BIGINT UNSIGNED NOT NULL,
            commission_rate DECIMAL(5,2) NOT NULL,
            commission_sats BIGINT UNSIGNED NOT NULL,
            invoice_payment_request TEXT NULL,
            invoice_payment_hash VARCHAR(64) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            paid_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY payment_hash (payment_hash),
            KEY vendor_id (vendor_id),
            KEY status (status)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Get commission stats for admin dashboard.
     */
    public static function get_stats(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'sk_commissions';

        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( ! $table_exists ) {
            return [
                'total'       => 0,
                'invoiced'    => 0,
                'paid'        => 0,
                'pending'     => 0,
                'total_sats'  => 0,
                'paid_sats'   => 0,
                'unpaid_sats' => 0,
            ];
        }

        return [
            'total'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ),
            'invoiced'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'invoiced'" ),
            'paid'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'paid'" ),
            'pending'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" ),
            'total_sats'  => (int) $wpdb->get_var( "SELECT COALESCE(SUM(commission_sats), 0) FROM {$table}" ),
            'paid_sats'   => (int) $wpdb->get_var( "SELECT COALESCE(SUM(commission_sats), 0) FROM {$table} WHERE status = 'paid'" ),
            'unpaid_sats' => (int) $wpdb->get_var( "SELECT COALESCE(SUM(commission_sats), 0) FROM {$table} WHERE status IN ('pending', 'invoiced')" ),
        ];
    }
}
