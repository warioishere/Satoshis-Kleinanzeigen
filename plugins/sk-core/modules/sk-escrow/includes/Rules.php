<?php

namespace SK\Modules\Escrow;

defined( 'ABSPATH' ) || exit;

/**
 * The numbers of the published rulebook (docs/treuhand-regelwerk-v1.html)
 * and the account side of it: fee, tiers with their limits, incidents.
 *
 * Every escrow row records the rulebook version it was opened under; the
 * numbers here belong to that version and change only with it.
 */
final class Rules {

    /** The rulebook version new escrows are opened under (§12). */
    const VERSION = '1.2';

    /** Service fee: percent of the price, and the floor in sats (§2). */
    const FEE_PERCENT = 10;
    const FEE_MIN_SAT = 3000;

    /** Share of every fee that goes into the goodwill fund (§2). */
    const FUND_SHARE_PERCENT = 50;

    /** Escrow limit per tier in sats (§8). */
    const LIMITS = [ 0 => 200000, 1 => 2000000, 2 => 10000000 ];

    /** Completed trades needed for tier 1 (with or without web of trust) and tier 2 (with it). */
    const TRADES_TIER1 = 3;
    const TRADES_TIER2 = 10;

    /** §8: a trade counts for the tier from this price on, once per counterparty. */
    const TRADE_MIN_SAT = 20000;

    /** Incidents within a year that force tier 0, and that block the escrow (§9). */
    const INCIDENTS_DEMOTE = 1;
    const INCIDENTS_BLOCK  = 3;
    const INCIDENT_WINDOW  = YEAR_IN_SECONDS;

    /** Goodwill claims (§7): tier needed, share of the price, the cap, and one claim per this many seconds. */
    const CLAIM_TIER_MIN = 2;
    const CLAIM_PERCENT  = 25;
    const CLAIM_MAX_SAT  = 250000;
    const CLAIM_EVERY    = YEAR_IN_SECONDS;

    const INCIDENTS_META = 'sk_escrow_incidents';
    const BLOCKED_META   = 'weo_escrow_blocked';
    const CLAIM_AT_META  = 'sk_escrow_claim_at';

    /** Seller's choice (§8): the lowest buyer tier they accept an escrow from. */
    const MIN_BUYER_TIER_META = 'weo_min_buyer_tier';

    // ── Fee ─────────────────────────────────────────────────────────────

    public static function fee_for( int $price_sat ): int {
        return max( self::FEE_MIN_SAT, intdiv( $price_sat * self::FEE_PERCENT, 100 ) );
    }

    public static function fund_share( int $fee_sat ): int {
        return intdiv( $fee_sat * self::FUND_SHARE_PERCENT, 100 );
    }

    // ── Tiers ───────────────────────────────────────────────────────────

    /**
     * Escrows this user completed as buyer or seller: paid out, never
     * disputed. Any price, any counterparty — used only to judge whether
     * a counterparty is real (see trusted_counterparty()).
     */
    public static function raw_trades( int $user_id ): int {
        global $wpdb;

        return (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT COUNT(*) FROM ' . Rows::table() . " WHERE context = %s AND status = 'delivered'
             AND ( buyer_id = %d OR vendor_id = %d )
             AND metadata LIKE %s AND metadata NOT LIKE %s",
            Rows::CONTEXT,
            $user_id,
            $user_id,
            '%"settled_txid":"%',
            '%"dispute_at"%'
        ) );
    }

    /** A counterparty whose trades may count for someone else's tier: in the web of trust, or with trades of their own. */
    public static function trusted_counterparty( int $user_id ): bool {
        return self::in_web_of_trust( $user_id ) || self::raw_trades( $user_id ) >= self::TRADES_TIER1;
    }

    /**
     * Completed trades that count for the tier (§8): paid out, never
     * disputed, at least TRADE_MIN_SAT, each counterparty once, only
     * trusted counterparties, and never with an account that shares a
     * Bitcoin address with this one — two wallets of one person trading
     * with each other are not trades.
     */
    public static function completed_trades( int $user_id ): int {
        global $wpdb;

        $rows = $wpdb->get_results( $wpdb->prepare(
            'SELECT buyer_id, vendor_id, metadata FROM ' . Rows::table() . " WHERE context = %s AND status = 'delivered'
             AND ( buyer_id = %d OR vendor_id = %d ) AND amount_sats >= %d
             AND metadata LIKE %s AND metadata NOT LIKE %s",
            Rows::CONTEXT,
            $user_id,
            $user_id,
            self::TRADE_MIN_SAT,
            '%"settled_txid":"%',
            '%"dispute_at"%'
        ) ) ?: [];

        // Every address this account has used, on its rows and in its settings.
        $mine = array_filter( [
            (string) get_user_meta( $user_id, Dashboard::PAYOUT_META, true ),
            (string) get_user_meta( $user_id, Purchase::REFUND_META, true ),
        ] );
        $sides = [];

        foreach ( $rows as $r ) {
            $e         = json_decode( (string) $r->metadata, true )['escrow'] ?? [];
            $is_buyer  = (int) $r->buyer_id === $user_id;
            $mine[]    = (string) ( $is_buyer ? ( $e['refund_address'] ?? '' ) : ( $e['payout_address'] ?? '' ) );
            $sides[]   = [ 'other' => $is_buyer ? (int) $r->vendor_id : (int) $r->buyer_id, 'theirs' => (string) ( $is_buyer ? ( $e['payout_address'] ?? '' ) : ( $e['refund_address'] ?? '' ) ) ];
        }

        $mine  = array_filter( array_unique( $mine ) );
        $seen  = [];
        $count = 0;

        foreach ( $sides as $s ) {
            $other = $s['other'];
            if ( $other <= 0 || $other === $user_id || isset( $seen[ $other ] ) ) {
                continue;
            }
            $seen[ $other ] = true;

            $theirs = array_filter( [
                $s['theirs'],
                (string) get_user_meta( $other, Dashboard::PAYOUT_META, true ),
                (string) get_user_meta( $other, Purchase::REFUND_META, true ),
            ] );
            if ( array_intersect( $mine, $theirs ) || ! self::trusted_counterparty( $other ) ) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    /** Is the user's proven key inside the marketplace's web of trust? */
    public static function in_web_of_trust( int $user_id ): bool {
        if ( ! class_exists( '\SK\Modules\Reputation\WebOfTrust' ) || ! class_exists( '\SK\Core\Trust\VendorKey' ) ) {
            return false;
        }

        $pubkey = \SK\Core\Trust\VendorKey::bound( $user_id );

        return '' !== $pubkey && \SK\Modules\Reputation\WebOfTrust::contains( $pubkey );
    }

    /** 0, 1 or 2 (§8), forced to 0 by incidents (§9). */
    public static function tier( int $user_id ): int {
        if ( $user_id <= 0 || self::incidents( $user_id ) >= self::INCIDENTS_DEMOTE ) {
            return 0;
        }

        $trades = self::completed_trades( $user_id );
        $wot    = self::in_web_of_trust( $user_id );

        if ( $trades >= self::TRADES_TIER2 && $wot ) {
            return 2;
        }

        return ( $trades >= self::TRADES_TIER1 || $wot ) ? 1 : 0;
    }

    public static function limit( int $user_id ): int {
        return self::LIMITS[ self::tier( $user_id ) ];
    }

    // ── Incidents ───────────────────────────────────────────────────────

    /**
     * Record an incident (§9): not shipped, return refused, a goodwill
     * claim by the other side, or a weight mismatch (weight 2).
     */
    public static function incident( int $user_id, string $why, string $hash, int $weight = 1 ): void {
        if ( $user_id <= 0 ) {
            return;
        }

        $list   = get_user_meta( $user_id, self::INCIDENTS_META, true );
        $list   = is_array( $list ) ? $list : [];
        $list[] = [ 'at' => time(), 'why' => $why, 'hash' => $hash, 'weight' => max( 1, $weight ) ];

        // Keep only what can still count.
        $cutoff = time() - self::INCIDENT_WINDOW;
        $list   = array_values( array_filter( $list, static fn( $i ) => (int) ( $i['at'] ?? 0 ) >= $cutoff ) );

        update_user_meta( $user_id, self::INCIDENTS_META, $list );
    }

    /** Weighted incidents within the window. */
    public static function incidents( int $user_id ): int {
        $list   = get_user_meta( $user_id, self::INCIDENTS_META, true );
        $cutoff = time() - self::INCIDENT_WINDOW;
        $sum    = 0;

        foreach ( is_array( $list ) ? $list : [] as $i ) {
            if ( (int) ( $i['at'] ?? 0 ) >= $cutoff ) {
                $sum += max( 1, (int) ( $i['weight'] ?? 1 ) );
            }
        }

        return $sum;
    }

    /** Blocked from the escrow: three incidents, or set by hand. */
    public static function blocked( int $user_id ): bool {
        return $user_id > 0
            && ( '1' === (string) get_user_meta( $user_id, self::BLOCKED_META, true ) || self::incidents( $user_id ) >= self::INCIDENTS_BLOCK );
    }

    /**
     * Why this user may not open an escrow over $amount now, or '' when
     * they may. Checked for the buyer at the request and for the seller
     * when accepting.
     */
    public static function refusal( int $user_id, int $amount_sat ): string {
        if ( self::blocked( $user_id ) ) {
            return __( 'Für dieses Konto ist die Treuhand gesperrt (§9 des Regelwerks).', 'sk-core' );
        }

        $limit = self::limit( $user_id );

        if ( $amount_sat > $limit ) {
            return sprintf(
                /* translators: 1: tier, 2: limit in sats */
                __( 'Dein Konto ist auf Stufe %1$d und darf über die Treuhand bis %2$s Sats handeln (§8 des Regelwerks).', 'sk-core' ),
                self::tier( $user_id ),
                number_format_i18n( $limit )
            );
        }

        return '';
    }
}
