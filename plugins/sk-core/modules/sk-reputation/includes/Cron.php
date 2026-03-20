<?php

namespace SK\Modules\Reputation;

defined( 'ABSPATH' ) || exit;

class Cron {

    public function __construct() {
        add_action( 'sk_recalculate_reputation_scores', [ $this, 'process' ] );
        add_action( 'sk_recalculate_reputation_scores', [ $this, 'expire_old_invoices' ] );
        add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_interval' ] );
    }

    public static function schedule() {
        if ( ! wp_next_scheduled( 'sk_recalculate_reputation_scores' ) ) {
            wp_schedule_event( time(), 'six_hours', 'sk_recalculate_reputation_scores' );
        }
    }

    public static function add_cron_interval( $schedules ) {
        $schedules['six_hours'] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display'  => __( 'Alle 6 Stunden', 'sk-core' ),
        ];
        return $schedules;
    }

    public function process() {
        global $wpdb;
        $table = $wpdb->prefix . 'sk_lightning_payments';

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

            // Fire commission hook for 7-day timeout path.
            if ( $valid ) {
                do_action( 'sk_payment_reputation_credited', $payment );
            }

            if ( in_array( 'burst_new_accounts', $flags, true ) ) {
                $this->notify_admin_burst( $payment );
            }
        }

        foreach ( array_keys( $vendors_to_recalc ) as $vendor_id ) {
            Calculator::recalculate_vendor( $vendor_id );
        }

        // Check pending commission invoices + enforcement.
        if ( class_exists( 'SK\Modules\Payments\Commission\Generator' ) ) {
            \SK\Modules\Payments\Commission\Generator::check_pending_invoices();
        }
        if ( class_exists( 'SK\Modules\Payments\Commission\Enforcement' ) ) {
            \SK\Modules\Payments\Commission\Enforcement::process();
        }

        update_option( 'sk_reputation_cron_last_run', current_time( 'mysql' ) );
    }

    public function expire_old_invoices() {
        global $wpdb;
        $table = $wpdb->prefix . 'sk_lightning_payments';

        // Lightning: expire after 15 minutes.
        $ln_cutoff = wp_date( 'Y-m-d H:i:s', time() - 15 * MINUTE_IN_SECONDS );
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$table} SET status = 'expired'
             WHERE status = 'pending' AND context != 'onchain' AND created_at <= %s",
            $ln_cutoff
        ) );

        // Onchain: expire after 48 hours (blocks can be slow, mempool congestion).
        $oc_cutoff = wp_date( 'Y-m-d H:i:s', time() - 48 * HOUR_IN_SECONDS );
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$table} SET status = 'expired'
             WHERE status = 'pending' AND context = 'onchain' AND created_at <= %s",
            $oc_cutoff
        ) );
    }

    private function notify_admin_burst( object $payment ) {
        $admin_email = get_option( 'admin_email' );
        $vendor      = get_userdata( $payment->vendor_id );
        $vendor_name = $vendor ? $vendor->display_name : '#' . $payment->vendor_id;

        wp_mail(
            $admin_email,
            '[SK Reputation] Burst-Erkennung: Viele Zahlungen von neuen Accounts',
            sprintf(
                "Vendor: %s (ID: %d)\n" .
                "Es wurden mehr als 5 Zahlungen von neuen Accounts (<14 Tage) innerhalb von 24h erkannt.\n" .
                "Bitte prüfe dies unter: %s",
                $vendor_name,
                $payment->vendor_id,
                admin_url( 'admin.php?page=sk-payments&status=disputed' )
            )
        );
    }
}
