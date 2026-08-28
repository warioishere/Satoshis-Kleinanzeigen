<?php

namespace SK\Modules\Payments\Admin;

use SK\Modules\Payments\LNURL\Resolver;
use SK\Modules\Payments\LNURL\ExchangeRate;

defined( 'ABSPATH' ) || exit;

class AdminPage {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ], 20 );
        add_action( 'admin_post_skl_resolve_dispute', [ $this, 'handle_dispute_action' ] );
    }

    public function add_menu() {
        add_submenu_page(
            'sk',
            'SK Payments',
            'SK Payments',
            'manage_options',
            'sk-payments',
            [ $this, 'render_page' ]
        );
    }

    public function render_page() {
        require SK_PAYMENTS_TEMPLATES . '/admin-overview.php';
    }

    public function handle_dispute_action() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Keine Berechtigung.' );
        }

        check_admin_referer( 'skl_dispute_action' );

        $payment_id = absint( $_POST['payment_id'] ?? 0 );
        $action     = sanitize_text_field( $_POST['dispute_action'] ?? '' );

        if ( ! $payment_id || ! in_array( $action, [ 'confirm_dispute', 'reject_dispute' ], true ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=sk-payments&msg=invalid' ) );
            exit;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sk_lightning_payments';

        $payment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $payment_id ) );

        if ( $action === 'confirm_dispute' ) {
            $wpdb->update(
                $table,
                [
                    'reputation_valid' => 0,
                    'reputation_at'    => null,
                    'reputation_state' => 'rejected',
                ],
                [ 'id' => $payment_id ],
                [ '%d', '%s', '%s' ],
                [ '%d' ]
            );
        } else {
            $rep_at = $payment && $payment->confirmed_at
                ? wp_date( 'Y-m-d H:i:s', strtotime( $payment->confirmed_at ) + 7 * DAY_IN_SECONDS )
                : null;

            $wpdb->update(
                $table,
                [
                    'status'           => 'confirmed',
                    'reputation_at'    => $rep_at,
                    'reputation_state' => 'pending',
                ],
                [ 'id' => $payment_id ],
                [ '%s', '%s', '%s' ],
                [ '%d' ]
            );
        }

        // Zeroing reputation_valid alone left the points standing in the score
        // table, so a confirmed dispute changed nothing the buyer could see.
        if ( $payment && class_exists( 'SK\Modules\Reputation\Calculator' ) ) {
            \SK\Modules\Reputation\Calculator::recalculate_vendor( (int) $payment->vendor_id );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=sk-payments&msg=updated' ) );
        exit;
    }

    public static function get_system_status(): array {
        $status = [];

        $test = Resolver::resolve( 'satoshi@getalby.com' );
        $status['lnurl_resolve'] = ! is_wp_error( $test );

        $rate = ExchangeRate::get_btc_eur_rate();
        $status['exchange_rate']       = ! is_wp_error( $rate );
        $status['exchange_rate_value'] = is_wp_error( $rate ) ? 0 : $rate;

        $status['cron_registered'] = (bool) wp_next_scheduled( 'sk_recalculate_reputation_scores' );
        $status['cron_next']       = wp_next_scheduled( 'sk_recalculate_reputation_scores' );

        $status['vendor_chat'] = class_exists( 'SK\Core\Dashboard\Modules\VendorChat' );

        global $wpdb;
        $status['vendors_with_ln'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta}
             WHERE meta_key = 'sk_profile_settings'
             AND meta_value LIKE '%lightning_address%'
             AND meta_value NOT LIKE '%\"lightning_address\";\"%\";%'
             AND meta_value NOT LIKE '%\"lightning_address\";s:0:%'"
        );

        return $status;
    }

    public static function get_stats(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'sk_lightning_payments';

        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( ! $table_exists ) {
            return [
                'total_requests'   => 0,
                'paid_total'       => 0,
                'paid_7d'          => 0,
                'paid_30d'         => 0,
                'paid_volume'      => 0,
                'open_disputes'    => 0,
                'rep_credited'     => 0,
            ];
        }

        return [
            'total_requests'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ),
            'paid_total'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'confirmed'" ),
            'paid_7d'          => (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE status = 'confirmed' AND confirmed_at >= %s",
                gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS )
            ) ),
            'paid_30d'         => (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE status = 'confirmed' AND confirmed_at >= %s",
                gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS )
            ) ),
            'paid_volume'      => (int) $wpdb->get_var( "SELECT COALESCE(SUM(amount_sats), 0) FROM {$table} WHERE status = 'confirmed'" ),
            'open_disputes'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'disputed'" ),
            'rep_credited'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE reputation_valid = 1" ),
        ];
    }
}
