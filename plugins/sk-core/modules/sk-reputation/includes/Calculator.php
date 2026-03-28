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

    public static function is_reputation_valid( object $payment ): bool {
        if ( empty( $payment->product_id ) ) {
            return false;
        }

        $product_post = get_post( $payment->product_id );
        if ( ! $product_post || $product_post->post_status !== 'publish' ) {
            return false;
        }
        $published_at = strtotime( $product_post->post_date_gmt );
        $payment_at   = strtotime( $payment->created_at );
        if ( ( $payment_at - $published_at ) < DAY_IN_SECONDS ) {
            return false;
        }

        if ( $payment->amount_sats < 1000 ) {
            return false;
        }

        $buyer = get_userdata( $payment->buyer_id );
        if ( ! $buyer ) {
            return false;
        }
        $registered = strtotime( $buyer->user_registered );
        if ( ( time() - $registered ) < 7 * DAY_IN_SECONDS ) {
            return false;
        }

        $flags = self::check_sybil( $payment );
        if ( ! empty( $flags ) ) {
            return false;
        }

        return true;
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
            (int) $valid->vol,
            $vendor_id
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

    private static function compute_score( int $valid_tx, int $unique_buyers, int $volume_sats, int $vendor_id = 0 ): int {
        $score = 0;
        $score += min( $unique_buyers * 10, 500 );
        $score += min( $valid_tx * 5, 250 );
        $score += min( (int) ( $volume_sats / 10000 ), 250 );

        // Einundzwanzig Meetup Bonus (max 200 pts).
        if ( $vendor_id > 0 ) {
            $score += MeetupReputation::compute_bonus( $vendor_id );
        }

        return $score;
    }

    private static function check_ip_overlap( object $payment ): bool {
        if ( empty( $payment->buyer_ip_hash ) ) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sk_lightning_payments';

        $match = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE buyer_id = %d AND buyer_ip_hash = %s AND id != %d",
            $payment->vendor_id,
            $payment->buyer_ip_hash,
            $payment->id
        ) );

        return $match > 0;
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

    private static function check_burst( object $payment ): bool {
        global $wpdb;
        $table  = $wpdb->prefix . 'sk_lightning_payments';
        $cutoff = wp_date( 'Y-m-d H:i:s', strtotime( $payment->created_at ) - DAY_IN_SECONDS );

        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT p.buyer_id) FROM {$table} p
             INNER JOIN {$wpdb->users} u ON u.ID = p.buyer_id
             WHERE p.vendor_id = %d
             AND p.created_at >= %s
             AND u.user_registered >= %s",
            $payment->vendor_id,
            $cutoff,
            wp_date( 'Y-m-d H:i:s', time() - 14 * DAY_IN_SECONDS )
        ) );

        return $count > 5;
    }
}
