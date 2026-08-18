<?php

namespace SK\Modules\Reputation;

defined( 'ABSPATH' ) || exit;

class Calculator {

    public static function check_sybil( object $payment ): array {
        $flags = [];

        if ( self::check_ip_overlap( $payment ) ) {
            $flags[] = 'same_network';
        }

        if ( self::check_circular( $payment ) ) {
            $flags[] = 'circular_payment';
        }

        if ( self::check_ring( $payment ) ) {
            $flags[] = 'ring_detected';
        }

        if ( self::check_burst( $payment ) ) {
            $flags[] = 'burst_new_accounts';
        }

        return $flags;
    }

    /**
     * How a payment may have been confirmed for it to count.
     *
     * 'vendor' is the manual button in the seller dashboard — nothing is
     * checked there, so it must never build reputation.
     */
    const VERIFIED_SOURCES = [ 'nwc', 'lndhub', 'lnurl', 'onchain' ];

    public static function is_reputation_valid( object $payment ): bool {
        // Reputation claims that Bitcoin actually moved. Only a settled
        // invoice or a confirmed on-chain transaction proves that.
        if ( ! in_array( (string) ( $payment->confirmed_via ?? '' ), self::VERIFIED_SOURCES, true ) ) {
            return false;
        }

        if ( empty( $payment->product_id ) ) {
            return false;
        }

        // The listing only has to still exist. Requiring 'publish' would drop
        // exactly the normal case on a classifieds site: item sold, listing
        // taken down, reputation credited a week later.
        $product_post = get_post( $payment->product_id );
        if ( ! $product_post ) {
            return false;
        }

        $published_at = (int) get_post_time( 'U', true, $product_post );
        $payment_at   = self::to_timestamp( $payment->created_at );

        if ( ! $published_at || ! $payment_at || ( $payment_at - $published_at ) < DAY_IN_SECONDS ) {
            return false;
        }

        if ( $payment->amount_sats < 1000 ) {
            return false;
        }

        $buyer = get_userdata( $payment->buyer_id );
        if ( ! $buyer ) {
            return false;
        }

        $registered = strtotime( $buyer->user_registered . ' UTC' );
        if ( ! $registered || ( time() - $registered ) < 7 * DAY_IN_SECONDS ) {
            return false;
        }

        $flags = self::check_sybil( $payment );
        if ( ! empty( $flags ) ) {
            return false;
        }

        return true;
    }

    /**
     * Payment timestamps are stored in site time, everything they are compared
     * against is UTC. Without this the 24h and 7 day rules drift by the site's
     * offset, and by another hour across DST.
     */
    private static function to_timestamp( ?string $site_local ): int {
        if ( empty( $site_local ) ) {
            return 0;
        }

        return (int) strtotime( get_gmt_from_date( $site_local ) . ' UTC' );
    }

    public static function recalculate_vendor( int $vendor_id ) {
        global $wpdb;
        $table     = $wpdb->prefix . 'sk_lightning_payments';
        $rep_table = $wpdb->prefix . 'sk_reputation_scores';

        $total = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) as cnt, COALESCE(SUM(amount_sats), 0) as vol
             FROM {$table} WHERE vendor_id = %d AND status IN ('confirmed', 'delivered', 'disputed')",
            $vendor_id
        ) );

        $valid = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) as cnt, COALESCE(SUM(amount_sats), 0) as vol
             FROM {$table} WHERE vendor_id = %d AND reputation_valid = 1",
            $vendor_id
        ) );

        $unique_buyers = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT buyer_id) FROM {$table}
             WHERE vendor_id = %d AND reputation_valid = 1",
            $vendor_id
        ) );

        $score = self::compute_score(
            (int) $valid->cnt,
            $unique_buyers,
            (int) $valid->vol
        );

        $wpdb->replace( $rep_table, [
            'vendor_id'          => $vendor_id,
            'total_transactions' => (int) $total->cnt,
            'valid_transactions' => (int) $valid->cnt,
            'unique_buyers'      => $unique_buyers,
            'total_volume_sats'  => (int) $total->vol,
            'valid_volume_sats'  => (int) $valid->vol,
            'reputation_score'   => $score,
            'last_calculated_at' => current_time( 'mysql' ),
        ], [ '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s' ] );
    }

    private static function compute_score( int $valid_tx, int $unique_buyers, int $volume_sats ): int {
        $score = 0;
        $score += min( $unique_buyers * 10, 500 );
        $score += min( $valid_tx * 5, 250 );
        $score += min( (int) ( $volume_sats / 10000 ), 250 );
        return $score;
    }

    /**
     * Two signals, both from the only address we ever see — the buyer's.
     *
     * The vendor's own IP is never recorded (they are not in the request), so
     * the first check can only catch a vendor who also bought from that same
     * connection. The second catches the more common shape: several distinct
     * "buyers" of one vendor sharing one address. The threshold is three,
     * because two people behind one CGNAT or office line is entirely normal.
     */
    private static function check_ip_overlap( object $payment ): bool {
        if ( empty( $payment->buyer_ip_hash ) ) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sk_lightning_payments';

        $vendor_bought_here = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE buyer_id = %d AND buyer_ip_hash = %s AND id != %d",
            $payment->vendor_id,
            $payment->buyer_ip_hash,
            $payment->id
        ) );

        if ( $vendor_bought_here > 0 ) {
            return true;
        }

        $identities_on_one_line = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT buyer_id) FROM {$table}
             WHERE vendor_id = %d AND buyer_ip_hash = %s
             AND status IN ('confirmed', 'delivered')",
            $payment->vendor_id,
            $payment->buyer_ip_hash
        ) );

        return $identities_on_one_line >= 3;
    }

    private static function check_circular( object $payment ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'sk_lightning_payments';

        $reverse = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE vendor_id = %d AND buyer_id = %d
             AND created_at >= %s
             AND status IN ('confirmed', 'pending')",
            $payment->buyer_id,
            $payment->vendor_id,
            wp_date( 'Y-m-d H:i:s', strtotime( $payment->created_at ) - 30 * DAY_IN_SECONDS )
        ) );

        return $reverse > 0;
    }

    private static function check_ring( object $payment ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'sk_lightning_payments';
        $cutoff = wp_date( 'Y-m-d H:i:s', strtotime( $payment->created_at ) - 7 * DAY_IN_SECONDS );

        $intermediaries = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT vendor_id FROM {$table}
             WHERE buyer_id = %d AND created_at >= %s AND status IN ('confirmed', 'pending')",
            $payment->vendor_id,
            $cutoff
        ) );

        if ( empty( $intermediaries ) ) {
            return false;
        }

        $placeholders = implode( ',', array_fill( 0, count( $intermediaries ), '%d' ) );
        $args = array_merge( $intermediaries, [ $payment->buyer_id, $cutoff ] );

        $ring = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE buyer_id IN ({$placeholders}) AND vendor_id = %d AND created_at >= %s
             AND status IN ('confirmed', 'pending')",
            ...$args
        ) );

        return $ring > 0;
    }

    /**
     * Only settled payments count here. Counting pending rows as well let
     * anyone flag a competitor for free: register six accounts, request six
     * invoices, never pay, and every genuine payment to that vendor in the
     * same 24 hours loses its reputation.
     */
    private static function check_burst( object $payment ): bool {
        global $wpdb;
        $table  = $wpdb->prefix . 'sk_lightning_payments';

        $payment_ts   = self::to_timestamp( $payment->created_at ) ?: time();
        $window_start = gmdate( 'Y-m-d H:i:s', $payment_ts - DAY_IN_SECONDS );
        $new_account  = gmdate( 'Y-m-d H:i:s', $payment_ts - 14 * DAY_IN_SECONDS );

        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT p.buyer_id) FROM {$table} p
             INNER JOIN {$wpdb->users} u ON u.ID = p.buyer_id
             WHERE p.vendor_id = %d
             AND p.status IN ('confirmed', 'delivered')
             AND p.created_at >= %s
             AND u.user_registered >= %s",
            $payment->vendor_id,
            get_date_from_gmt( $window_start ),
            $new_account
        ) );

        return $count > 5;
    }
}
