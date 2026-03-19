<?php

namespace SK_Lightning\Reputation;

defined( 'ABSPATH' ) || exit;

class Cron {

    public function __construct() {
        add_action( 'sk_recalculate_reputation_scores', [ $this, 'process' ] );
        add_action( 'sk_recalculate_reputation_scores', [ $this, 'expire_old_invoices' ] );
    }

    /**
     * Cron handler: process pending reputation validations.
     */
    public function process() {
        global $wpdb;
        $table = $wpdb->prefix . 'sk_lightning_payments';

        // Two paths to reputation:
        // 1. Buyer clicked "Produkt erhalten" → status = delivered (already processed instantly)
        // 2. Fallback: 7 days passed since confirmed, buyer did nothing → auto-credit
        $pending = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE reputation_valid = 0
                 AND status IN ('confirmed', 'delivered')
                 AND (status = 'delivered' OR (reputation_at IS NOT NULL AND reputation_at <= %s))
                 LIMIT 100",
                current_time( 'mysql' )
            )
        );

        $vendors_to_recalc = [];

        foreach ( $pending as $payment ) {
            $valid = Calculator::is_reputation_valid( $payment );
            $flags = Calculator::check_sybil( $payment );

            $wpdb->update(
                $table,
                [
                    'reputation_valid' => $valid ? 1 : 0,
                    'reputation_flags' => ! empty( $flags ) ? wp_json_encode( $flags ) : null,
                ],
                [ 'id' => $payment->id ],
                [ '%d', '%s' ],
                [ '%d' ]
            );

            $vendors_to_recalc[ $payment->vendor_id ] = true;

            // Send admin notification for burst detection.
            if ( in_array( 'burst_new_accounts', $flags, true ) ) {
                $this->notify_admin_burst( $payment );
            }
        }

        // Recalculate scores for affected vendors.
        foreach ( array_keys( $vendors_to_recalc ) as $vendor_id ) {
            Calculator::recalculate_vendor( $vendor_id );
        }

        // Update last run time.
        update_option( 'sk_lightning_cron_last_run', current_time( 'mysql' ) );
    }

    /**
     * Mark old pending invoices as expired (older than 15 minutes).
     */
    public function expire_old_invoices() {
        global $wpdb;
        $table  = $wpdb->prefix . 'sk_lightning_payments';
        $cutoff = wp_date( 'Y-m-d H:i:s', time() - 15 * MINUTE_IN_SECONDS );

        $wpdb->query( $wpdb->prepare(
            "UPDATE {$table} SET status = 'expired'
             WHERE status = 'pending' AND created_at <= %s",
            $cutoff
        ) );
    }

    /**
     * Notify admin about burst detection.
     */
    private function notify_admin_burst( object $payment ) {
        $admin_email = get_option( 'admin_email' );
        $vendor      = get_userdata( $payment->vendor_id );
        $vendor_name = $vendor ? $vendor->display_name : '#' . $payment->vendor_id;

        wp_mail(
            $admin_email,
            '[SK Lightning] Burst-Erkennung: Viele Zahlungen von neuen Accounts',
            sprintf(
                "Vendor: %s (ID: %d)\n" .
                "Es wurden mehr als 5 Zahlungen von neuen Accounts (<14 Tage) innerhalb von 24h erkannt.\n" .
                "Bitte prüfe dies unter: %s",
                $vendor_name,
                $payment->vendor_id,
                admin_url( 'admin.php?page=sk-lightning&status=disputed' )
            )
        );
    }
}
