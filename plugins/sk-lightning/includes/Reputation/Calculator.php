<?php

namespace SK_Lightning\Reputation;

defined( 'ABSPATH' ) || exit;

class Calculator {

    /**
     * Run all Sybil checks on a payment and return flags.
     *
     * @param object $payment  Row from sk_lightning_payments.
     * @return array  Array of flag strings (empty = clean).
     */
    public static function check_sybil( object $payment ): array {
        $flags = [];

        // Check 1: IP/Fingerprint overlap with vendor.
        if ( self::check_ip_overlap( $payment ) ) {
            $flags[] = 'same_network';
        }

        // Check 2: Circular payments (A→B + B→A).
        if ( self::check_circular( $payment ) ) {
            $flags[] = 'circular_payment';
        }

        // Check 3: Ring detection (A→B→C→A).
        if ( self::check_ring( $payment ) ) {
            $flags[] = 'ring_detected';
        }

        // Check 4: Burst from new accounts.
        if ( self::check_burst( $payment ) ) {
            $flags[] = 'burst_new_accounts';
        }

        return $flags;
    }

    /**
     * Validate whether a payment qualifies for reputation.
     *
     * @param object $payment  Row from sk_lightning_payments.
     * @return bool
     */
    public static function is_reputation_valid( object $payment ): bool {
        // 1. Must have a product.
        if ( empty( $payment->product_id ) ) {
            return false;
        }

        // 2. Product must have been published >= 24h before payment.
        $product_post = get_post( $payment->product_id );
        if ( ! $product_post || $product_post->post_status !== 'publish' ) {
            return false;
        }
        $published_at = strtotime( $product_post->post_date_gmt );
        $payment_at   = strtotime( $payment->created_at );
        if ( ( $payment_at - $published_at ) < DAY_IN_SECONDS ) {
            return false;
        }

        // 3. Amount >= 1000 sats.
        if ( $payment->amount_sats < 1000 ) {
            return false;
        }

        // 4. Buyer account >= 7 days old.
        $buyer = get_userdata( $payment->buyer_id );
        if ( ! $buyer ) {
            return false;
        }
        $registered = strtotime( $buyer->user_registered );
        if ( ( time() - $registered ) < 7 * DAY_IN_SECONDS ) {
            return false;
        }

        // 5. No Sybil flags.
        $flags = self::check_sybil( $payment );
        if ( ! empty( $flags ) ) {
            return false;
        }

        return true;
    }

    /**
     * Recalculate reputation scores for a vendor.
     */
    public static function recalculate_vendor( int $vendor_id ) {
        global $wpdb;
        $table     = $wpdb->prefix . 'sk_lightning_payments';
        $rep_table = $wpdb->prefix . 'sk_reputation_scores';

        $total = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) as cnt, COALESCE(SUM(amount_sats), 0) as vol
             FROM {$table} WHERE vendor_id = %d AND status IN ('confirmed', 'disputed')",
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

    /**
     * Compute a reputation score from validated metrics.
     */
    private static function compute_score( int $valid_tx, int $unique_buyers, int $volume_sats ): int {
        // Simple weighted score.
        $score = 0;
        $score += min( $unique_buyers * 10, 500 );       // max 500 from unique buyers
        $score += min( $valid_tx * 5, 250 );              // max 250 from tx count
        $score += min( (int) ( $volume_sats / 10000 ), 250 ); // max 250 from volume
        return $score;
    }

    /**
     * Check 1: IP overlap between buyer and vendor.
     */
    private static function check_ip_overlap( object $payment ): bool {
        if ( empty( $payment->buyer_ip_hash ) ) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sk_lightning_payments';

        // Check if vendor has any payments (as buyer) from the same IP.
        $match = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE buyer_id = %d AND buyer_ip_hash = %s AND id != %d",
            $payment->vendor_id,
            $payment->buyer_ip_hash,
            $payment->id
        ) );

        return $match > 0;
    }

    /**
     * Check 2: Circular payments (A pays B and B pays A within 30 days).
     */
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

    /**
     * Check 3: Ring detection (A→B, B→C, C→A within 7 days).
     */
    private static function check_ring( object $payment ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'sk_lightning_payments';
        $cutoff = wp_date( 'Y-m-d H:i:s', strtotime( $payment->created_at ) - 7 * DAY_IN_SECONDS );

        // Find vendors that our vendor paid (B→C).
        $intermediaries = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT vendor_id FROM {$table}
             WHERE buyer_id = %d AND created_at >= %s AND status IN ('confirmed', 'pending')",
            $payment->vendor_id,
            $cutoff
        ) );

        if ( empty( $intermediaries ) ) {
            return false;
        }

        // Check if any of those intermediaries paid our buyer (C→A).
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
     * Check 4: Burst of payments from new accounts.
     */
    private static function check_burst( object $payment ): bool {
        global $wpdb;
        $table  = $wpdb->prefix . 'sk_lightning_payments';
        $cutoff = wp_date( 'Y-m-d H:i:s', strtotime( $payment->created_at ) - DAY_IN_SECONDS );

        // Count distinct new accounts (<14 days) that paid this vendor in last 24h.
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
