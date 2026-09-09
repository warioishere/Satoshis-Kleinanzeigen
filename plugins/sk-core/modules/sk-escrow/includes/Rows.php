<?php

namespace SK\Modules\Escrow;

defined( 'ABSPATH' ) || exit;

/**
 * An escrow is a row in sk_lightning_payments with context "escrow".
 *
 * Column use, kept parallel to the onchain rows so the shared dashboard
 * needs no special cases: amount_sats = price, verify_url = escrow address,
 * payment_request = bitcoin: URI for the deposit, preimage = funding txid,
 * confirmed_via = "escrow". Everything escrow-specific lives under
 * metadata.escrow (see meta()).
 *
 * status column:
 *   requested  buyer asked, seller has not accepted yet
 *   pending    accepted, waiting for the deposit
 *   confirmed  deposit confirmed (funded)
 *   delivered  buyer confirmed receipt and signed the payout
 *   refunded   refund broadcast
 *   disputed   problem reported, admin resolves
 *   expired    request declined, withdrawn or timed out
 *
 * metadata.escrow keys:
 *   buyer_xpub, refund_address, seller_xpub, payout_address, descriptor,
 *   address, watch_id, order_id, deposit_sat, fee_est_sat, state (API state),
 *   funding_txid, requested_at, accepted_at, psbt_type (payout|refund),
 *   signed (list of roles), settled_txid, note (decline reason)
 */
final class Rows {

    const CONTEXT = 'escrow';

    public static function table(): string {
        global $wpdb;

        return $wpdb->prefix . 'sk_lightning_payments';
    }

    public static function get( string $hash ): ?object {
        global $wpdb;

        if ( ! preg_match( '/^[0-9a-f]{64}$/', $hash ) ) {
            return null;
        }

        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE payment_hash = %s', $hash ) );

        return $row && $row->context === self::CONTEXT ? $row : null;
    }

    /**
     * The API identifies orders by a 32-character id; the hash is 64.
     */
    public static function order_id( object $row ): string {
        return 'e' . substr( $row->payment_hash, 0, 31 );
    }

    public static function get_by_order_id( string $order_id ): ?object {
        global $wpdb;

        if ( ! preg_match( '/^e[0-9a-f]{31}$/', $order_id ) ) {
            return null;
        }

        $row = $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . " WHERE context = %s AND payment_hash LIKE %s LIMIT 1",
            self::CONTEXT,
            $wpdb->esc_like( substr( $order_id, 1 ) ) . '%'
        ) );

        return $row ?: null;
    }

    public static function all_meta( object $row ): array {
        $meta = json_decode( (string) $row->metadata, true );

        return is_array( $meta ) ? $meta : [];
    }

    public static function meta( object $row ): array {
        $meta = self::all_meta( $row );

        return isset( $meta['escrow'] ) && is_array( $meta['escrow'] ) ? $meta['escrow'] : [];
    }

    /** Merge values into metadata.escrow (and optionally other top-level metadata keys). */
    public static function save_meta( string $hash, array $escrow, array $top = [] ): void {
        global $wpdb;

        $row = self::get( $hash );
        if ( ! $row ) {
            return;
        }

        $meta           = self::all_meta( $row );
        $meta['escrow'] = array_merge( self::meta( $row ), $escrow );
        foreach ( $top as $k => $v ) {
            $meta[ $k ] = $v;
        }

        $wpdb->update( self::table(), [ 'metadata' => wp_json_encode( $meta ) ], [ 'payment_hash' => $hash ], [ '%s' ], [ '%s' ] );
    }

    /**
     * Status change guarded by the expected current status, so two requests
     * cannot both apply the same transition.
     */
    public static function set_status( string $hash, string $from, string $to, array $cols = [] ): bool {
        global $wpdb;

        $set    = [ 'status = %s' ];
        $params = [ $to ];
        foreach ( $cols as $col => $val ) {
            $set[]    = "{$col} = %s";
            $params[] = $val;
        }
        $params[] = $hash;
        $params[] = $from;

        $updated = $wpdb->query( $wpdb->prepare(
            'UPDATE ' . self::table() . ' SET ' . implode( ', ', $set ) . ' WHERE payment_hash = %s AND status = %s',
            ...$params
        ) );

        return (bool) $updated;
    }

    public static function role( object $row, int $user_id ): string {
        if ( $user_id && $user_id === (int) $row->buyer_id ) {
            return 'buyer';
        }
        if ( $user_id && $user_id === (int) $row->vendor_id ) {
            return 'seller';
        }

        return '';
    }

    /** @return object[] */
    public static function by_status( array $statuses, int $limit = 100 ): array {
        global $wpdb;

        $marks = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . " WHERE context = %s AND status IN ({$marks}) ORDER BY created_at ASC LIMIT %d",
            self::CONTEXT,
            ...array_merge( $statuses, [ $limit ] )
        ) ) ?: [];
    }

    /** Human label for the escrow-specific states shown in cards and mails. */
    public static function state_label( object $row ): string {
        $meta = self::meta( $row );

        switch ( $row->status ) {
            case 'requested':
                return __( 'Anfrage offen – der Verkäufer muss annehmen', 'sk-core' );
            case 'pending':
                return __( 'Angenommen – Einzahlung in die Treuhand offen', 'sk-core' );
            case 'confirmed':
                return __( 'Einzahlung bestätigt – Ware kann verschickt werden', 'sk-core' );
            case 'delivered':
                if ( ! empty( $meta['settled_txid'] ) ) {
                    return __( 'Abgeschlossen – Auszahlung gesendet', 'sk-core' );
                }
                return __( 'Erhalt bestätigt – Auszahlung wartet auf die zweite Signatur', 'sk-core' );
            case 'refunded':
                return __( 'Erstattet', 'sk-core' );
            case 'disputed':
                return __( 'Problem gemeldet – der Marktplatz entscheidet', 'sk-core' );
            case 'expired':
                return ! empty( $meta['note'] ) ? $meta['note'] : __( 'Anfrage beendet', 'sk-core' );
        }

        return (string) $row->status;
    }
}
