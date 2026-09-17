<?php

namespace SK\Modules\Escrow;

defined( 'ABSPATH' ) || exit;

/**
 * The deadlines of the rulebook (§3) and what runs out when one passes.
 *
 * Shipping and delivery are the only facts: the shipping entry the seller
 * makes on the row (carrier and number, see sk-payments' Shipping) and the
 * carrier's status stored under metadata.escrow.tracking — entered by an
 * admin from the carrier's page for now, by a tracking service later. A
 * passed deadline escalates the row to a dispute with the transaction the
 * rule prescribes already built; the marketplace signs it and the party it
 * favours counter-signs, as in every other dispute. Nothing moves money by
 * itself.
 */
final class Deadlines {

    /** §3: business days the seller has to ship after the deposit confirmed. */
    const SHIP_BUSINESS_DAYS = 3;

    /** §3: days after shipping within which the carrier must scan the delivery. */
    const DELIVERY_DAYS = 14;

    /** §3: days after the delivery scan within which the buyer may report a problem. */
    const REPORT_DAYS = 3;

    /** §10: a parcel lighter than the listing by more than this share counts double. */
    const WEIGHT_TOLERANCE_PERCENT = 30;

    // ── Calendar ────────────────────────────────────────────────────────

    /** Swiss public holidays observed nationwide, as Y-m-d. */
    public static function holidays( int $year ): array {
        $easter = easter_date( $year );

        return [
            sprintf( '%d-01-01', $year ),
            sprintf( '%d-01-02', $year ),
            wp_date( 'Y-m-d', $easter - 2 * DAY_IN_SECONDS ),   // Good Friday
            wp_date( 'Y-m-d', $easter + DAY_IN_SECONDS ),       // Easter Monday
            wp_date( 'Y-m-d', $easter + 39 * DAY_IN_SECONDS ),  // Ascension
            wp_date( 'Y-m-d', $easter + 50 * DAY_IN_SECONDS ),  // Whit Monday
            sprintf( '%d-08-01', $year ),
            sprintf( '%d-12-25', $year ),
            sprintf( '%d-12-26', $year ),
        ];
    }

    public static function is_business_day( int $ts ): bool {
        $dow = (int) wp_date( 'N', $ts );

        return $dow <= 5 && ! in_array( wp_date( 'Y-m-d', $ts ), self::holidays( (int) wp_date( 'Y', $ts ) ), true );
    }

    /** The same time of day, $days business days later. */
    public static function add_business_days( int $ts, int $days ): int {
        while ( $days > 0 ) {
            $ts += DAY_IN_SECONDS;

            if ( self::is_business_day( $ts ) ) {
                $days--;
            }
        }

        return $ts;
    }

    // ── Facts on the row ────────────────────────────────────────────────

    /** @return array{carrier:string,number:string,at:string}|null */
    public static function shipping( object $row ): ?array {
        $meta = Rows::all_meta( $row );
        $ship = $meta['shipping'] ?? null;

        if ( ! is_array( $ship ) || empty( $ship['at'] ) ) {
            return null;
        }

        return [
            'carrier' => (string) ( $ship['carrier'] ?? '' ),
            'number'  => (string) ( $ship['number'] ?? '' ),
            'at'      => (string) $ship['at'],
        ];
    }

    /**
     * The carrier's word on the parcel, as stored on the row.
     *
     * @return array{state:string,delivered_at:int,signed:bool,weight_g:int,source:string,at:int}
     */
    public static function tracking( object $row ): array {
        $t = Rows::meta( $row )['tracking'] ?? [];
        $t = is_array( $t ) ? $t : [];

        return [
            'state'        => (string) ( $t['state'] ?? '' ),
            'delivered_at' => (int) ( $t['delivered_at'] ?? 0 ),
            'signed'       => ! empty( $t['signed'] ),
            'weight_g'     => (int) ( $t['weight_g'] ?? 0 ),
            'source'       => (string) ( $t['source'] ?? '' ),
            'at'           => (int) ( $t['at'] ?? 0 ),
        ];
    }

    /** Store what the carrier says. $by: user id of the admin, 0 for a service. */
    public static function record_tracking( string $hash, string $state, int $delivered_at, bool $signed, int $weight_g, string $source, int $by = 0 ): void {
        Rows::save_meta( $hash, [
            'tracking' => [
                'state'        => $state,
                'delivered_at' => 'delivered' === $state ? $delivered_at : 0,
                'signed'       => $signed,
                'weight_g'     => max( 0, $weight_g ),
                'source'       => $source,
                'by'           => $by,
                'at'           => time(),
            ],
        ] );
    }

    public static function delivered_at( object $row ): int {
        $t = self::tracking( $row );

        return 'delivered' === $t['state'] ? $t['delivered_at'] : 0;
    }

    /**
     * May the buyer still report a problem (§3, third row)? Empty when yes,
     * else the reason. Replaces the instant purchase's own window for
     * escrow rows.
     */
    public static function may_report( object $row ): string {
        if ( (string) $row->status !== 'confirmed' ) {
            return __( 'Dieser Handel ist nicht mehr offen.', 'sk-core' );
        }

        $delivered = self::delivered_at( $row );

        if ( $delivered && time() > $delivered + self::REPORT_DAYS * DAY_IN_SECONDS ) {
            return sprintf(
                /* translators: %d: days */
                __( 'Das Meldefenster von %d Tagen nach der Zustellung ist abgelaufen (§3 des Regelwerks).', 'sk-core' ),
                self::REPORT_DAYS
            );
        }

        return '';
    }

    // ── The clock ───────────────────────────────────────────────────────

    /** Every funded escrow that is still open, against the deadlines. */
    public static function run(): void {
        foreach ( Rows::by_status( [ 'confirmed' ], 200 ) as $row ) {
            self::check( $row );
        }
    }

    public static function check( object $row ): void {
        $meta = Rows::meta( $row );

        // A transaction is already being signed (the buyer released, the
        // seller refunds): the parties are ahead of the clock.
        if ( (string) $row->status !== 'confirmed' || ! empty( $meta['psbt_type'] ) || empty( $row->confirmed_at ) ) {
            return;
        }

        $confirmed = (int) strtotime( (string) $row->confirmed_at );
        $ship      = self::shipping( $row );

        if ( ! $ship ) {
            if ( time() > self::add_business_days( $confirmed, self::SHIP_BUSINESS_DAYS ) ) {
                self::escalate(
                    $row,
                    'refund',
                    sprintf( __( '§3: nicht innerhalb von %d Werktagen nach Zahlungseingang versendet. Kaufpreis und Gebühr gehen an den Käufer zurück.', 'sk-core' ), self::SHIP_BUSINESS_DAYS ),
                    (int) $row->vendor_id,
                    'not_shipped'
                );
            }

            return;
        }

        $delivered = self::delivered_at( $row );

        if ( ! $delivered ) {
            if ( time() > (int) strtotime( $ship['at'] ) + self::DELIVERY_DAYS * DAY_IN_SECONDS ) {
                self::escalate(
                    $row,
                    'refund_fee',
                    sprintf( __( '§3: %d Tage nach dem Versand liegt kein Zustellscan vor. Der Kaufpreis geht an den Käufer zurück.', 'sk-core' ), self::DELIVERY_DAYS )
                );
            }

            return;
        }

        self::weight_check( $row );

        if ( time() > $delivered + self::REPORT_DAYS * DAY_IN_SECONDS ) {
            self::escalate(
                $row,
                'payout',
                sprintf( __( '§3: zugestellt und innerhalb von %d Tagen kein Problem gemeldet. Der Kaufpreis geht an den Verkäufer.', 'sk-core' ), self::REPORT_DAYS )
            );
        }
    }

    /**
     * A deadline passed: the row becomes a dispute with the transaction
     * the rule prescribes already built, the escrow is frozen at the API,
     * an incident is recorded where the rule says so, everyone is told.
     */
    private static function escalate( object $row, string $type, string $reason, int $incident_user = 0, string $why = '' ): void {
        if ( ! Rows::set_status( $row->payment_hash, 'confirmed', 'disputed' ) ) {
            return;
        }

        Rows::save_meta(
            $row->payment_hash,
            [ 'escalation' => [ 'type' => $type, 'reason' => $reason, 'at' => time() ] ],
            [ 'dispute_reason' => $reason, 'dispute_at' => current_time( 'mysql' ), 'dispute_user_id' => 0 ]
        );

        $fresh = Rows::get( $row->payment_hash );
        Actions::freeze( $fresh );

        $res = Actions::build( $fresh, $type );

        if ( ! is_wp_error( $res ) && ! empty( $res['psbt'] ) ) {
            Rows::save_meta( $row->payment_hash, [ 'psbt_type' => $type, 'psbt' => (string) $res['psbt'], 'signed' => [] ] );
        } else {
            Rows::save_meta( $row->payment_hash, [ 'escalation_error' => is_wp_error( $res ) ? $res->get_error_message() : 'no psbt' ] );
        }

        if ( $incident_user > 0 ) {
            Rules::incident( $incident_user, $why, $row->payment_hash );
        }

        Notify::escalated( Rows::get( $row->payment_hash ), $type, $reason );
    }

    /**
     * §10, once per row: the carrier's parcel weight against the listing's.
     * Only the outbound parcel and only where a weight was recorded.
     */
    public static function weight_check( object $row ): void {
        $meta = Rows::meta( $row );

        if ( ! empty( $meta['weight_checked'] ) ) {
            return;
        }

        $parcel = self::tracking( $row )['weight_g'];
        $listed = self::listed_weight_g( (int) $row->product_id );

        if ( $parcel <= 0 || $listed <= 0 ) {
            return;
        }

        $short = $parcel < $listed * ( 100 - self::WEIGHT_TOLERANCE_PERCENT ) / 100;

        Rows::save_meta( $row->payment_hash, [ 'weight_checked' => [ 'parcel_g' => $parcel, 'listed_g' => $listed, 'short' => $short, 'at' => time() ] ] );

        if ( $short ) {
            Rules::incident( (int) $row->vendor_id, 'weight', $row->payment_hash, 2 );
        }
    }

    /** The listing's shipping weight in grams, from WooCommerce's weight field. */
    public static function listed_weight_g( int $product_id ): int {
        $weight = (float) get_post_meta( $product_id, '_weight', true );

        if ( $weight <= 0 ) {
            return 0;
        }

        $factor = [ 'kg' => 1000, 'g' => 1, 'lbs' => 453.592, 'oz' => 28.3495 ][ (string) get_option( 'woocommerce_weight_unit', 'kg' ) ] ?? 1000;

        return (int) round( $weight * $factor );
    }
}
