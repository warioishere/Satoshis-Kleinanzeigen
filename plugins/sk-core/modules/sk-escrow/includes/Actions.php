<?php

namespace SK\Modules\Escrow;

defined( 'ABSPATH' ) || exit;

/**
 * Everything a party does with an escrow after the request: accept or
 * decline, poll the deposit, fetch a PSBT to sign, hand back a partial
 * signature, report a problem. All AJAX, all bound to the row's two parties.
 *
 * The API decides what a PSBT may pay (stored outputs, sweep of the escrow
 * UTXOs); this side only decides who may ask for which transaction when.
 */
final class Actions {

    public function __construct() {
        foreach ( [ 'accept', 'decline', 'cancel', 'status', 'psbt', 'partial' ] as $a ) {
            add_action( 'wp_ajax_weo_' . $a, [ $this, 'ajax_' . $a ] );
        }
        add_action( 'sk_payment_disputed', [ __CLASS__, 'on_disputed' ] );
    }

    // ---- request handling ----

    /** @return array{0:object,1:string} row and the caller's role */
    private function party( string ...$statuses ): array {
        check_ajax_referer( 'weo_escrow', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Nicht eingeloggt.', 'sk-core' ) ] );
        }

        $user_id = get_current_user_id();
        if ( function_exists( 'sk_rate_limit' ) && ! sk_rate_limit( 'weo:' . $user_id, 40 ) ) {
            wp_send_json_error( [ 'message' => __( 'Zu viele Anfragen, bitte kurz warten.', 'sk-core' ) ] );
        }

        $row = Rows::get( sanitize_text_field( wp_unslash( $_POST['hash'] ?? '' ) ) );
        if ( ! $row ) {
            wp_send_json_error( [ 'message' => __( 'Treuhand nicht gefunden.', 'sk-core' ) ] );
        }

        $role = Rows::role( $row, $user_id );
        if ( $role === '' ) {
            wp_send_json_error( [ 'message' => __( 'Keine Berechtigung für diesen Handel.', 'sk-core' ) ] );
        }

        if ( $statuses && ! in_array( (string) $row->status, $statuses, true ) ) {
            wp_send_json_error( [ 'message' => __( 'Dieser Schritt ist im aktuellen Zustand nicht möglich.', 'sk-core' ) ] );
        }

        return [ $row, $role ];
    }

    private function require_role( string $role, string $expected ): void {
        if ( $role !== $expected ) {
            wp_send_json_error( [ 'message' => __( 'Keine Berechtigung für diesen Schritt.', 'sk-core' ) ] );
        }
    }

    public function ajax_accept(): void {
        [ $row, $role ] = $this->party( 'requested' );
        $this->require_role( $role, 'seller' );

        if ( ! weo_enabled() ) {
            wp_send_json_error( [ 'message' => __( 'Die Treuhand ist derzeit nicht verfügbar.', 'sk-core' ) ] );
        }

        $seller_xpub = weo_normalize_xpub( wp_unslash( $_POST['xpub'] ?? '' ) );
        if ( is_wp_error( $seller_xpub ) ) {
            wp_send_json_error( [ 'message' => __( 'Ungültiger xpub. Bitte einen Schlüssel erzeugen oder einen Mainnet-xpub eintragen.', 'sk-core' ) ] );
        }

        $payout = weo_sanitize_btc_address( wp_unslash( $_POST['payout_address'] ?? '' ) );
        if ( ! weo_validate_btc_address( $payout ) ) {
            wp_send_json_error( [ 'message' => __( 'Bitte eine gültige Auszahlungsadresse (bc1…) angeben.', 'sk-core' ) ] );
        }

        $escrow_xpub = weo_normalize_xpub( weo_get_option( 'escrow_xpub' ) );
        if ( is_wp_error( $escrow_xpub ) ) {
            wp_send_json_error( [ 'message' => __( 'Der Marktplatz-Schlüssel ist nicht korrekt konfiguriert.', 'sk-core' ) ] );
        }

        $meta = Rows::meta( $row );
        if ( empty( $meta['buyer_xpub'] ) || $meta['buyer_xpub'] === $seller_xpub || $seller_xpub === $escrow_xpub ) {
            wp_send_json_error( [ 'message' => __( 'Die drei Schlüssel müssen verschieden sein.', 'sk-core' ) ] );
        }

        $order_id = Rows::order_id( $row );
        $res      = weo_api_post( '/orders', [
            'order_id'   => $order_id,
            'amount_sat' => (int) $row->amount_sats,
            'buyer'      => [ 'xpub' => $meta['buyer_xpub'] ],
            'seller'     => [ 'xpub' => $seller_xpub ],
            'escrow'     => [ 'xpub' => $escrow_xpub ],
            'min_conf'   => max( 0, (int) weo_get_option( 'min_conf', 2 ) ),
        ] );
        if ( is_wp_error( $res ) || empty( $res['escrow_address'] ) || empty( $res['descriptor'] ) ) {
            wp_send_json_error( [ 'message' => is_wp_error( $res ) ? $res->get_error_message() : __( 'Escrow-API hat keine Adresse geliefert.', 'sk-core' ) ] );
        }

        // The descriptor must carry exactly the keys we sent, in this order;
        // the buyer's browser recomputes the address from it.
        if ( strpos( $res['descriptor'], $meta['buyer_xpub'] . '/0/' ) === false || strpos( $res['descriptor'], $seller_xpub . '/0/' ) === false ) {
            wp_send_json_error( [ 'message' => __( 'Descriptor der Escrow-API passt nicht zu den Schlüsseln.', 'sk-core' ) ] );
        }

        $status  = weo_api_get( '/orders/' . rawurlencode( $order_id ) . '/status' );
        $fee_est = is_wp_error( $status ) ? 0 : (int) ( $status['fee_est_sat'] ?? 0 );
        $deposit = (int) $row->amount_sats + $fee_est;
        $bip21   = 'bitcoin:' . $res['escrow_address'] . '?amount=' . number_format( $deposit / 100000000, 8, '.', '' );

        if ( ! Rows::set_status( $row->payment_hash, 'requested', 'pending', [ 'verify_url' => $res['escrow_address'], 'payment_request' => $bip21 ] ) ) {
            wp_send_json_error( [ 'message' => __( 'Anfrage wurde bereits bearbeitet.', 'sk-core' ) ] );
        }

        $escrow = [
            'seller_xpub'    => $seller_xpub,
            'payout_address' => $payout,
            'descriptor'     => (string) $res['descriptor'],
            'address'        => (string) $res['escrow_address'],
            'watch_id'       => (string) ( $res['watch_id'] ?? '' ),
            'order_id'       => $order_id,
            'fee_est_sat'    => $fee_est,
            'deposit_sat'    => $deposit,
            'state'          => 'awaiting_deposit',
            'accepted_at'    => current_time( 'mysql' ),
        ];
        Rows::save_meta( $row->payment_hash, $escrow );
        update_user_meta( (int) $row->vendor_id, Dashboard::PAYOUT_META, $payout );

        Notify::accepted( Rows::get( $row->payment_hash ), $escrow );

        wp_send_json_success( [ 'message' => __( 'Angenommen. Der Käufer wurde über die Einzahlungsadresse informiert.', 'sk-core' ) ] );
    }

    public function ajax_decline(): void {
        [ $row, $role ] = $this->party( 'requested' );
        $this->require_role( $role, 'seller' );

        $reason = trim( mb_substr( sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) ), 0, 300 ) );
        $note   = __( 'Vom Verkäufer abgelehnt', 'sk-core' ) . ( $reason !== '' ? ': ' . $reason : '' );

        if ( ! Rows::set_status( $row->payment_hash, 'requested', 'expired' ) ) {
            wp_send_json_error( [ 'message' => __( 'Anfrage wurde bereits bearbeitet.', 'sk-core' ) ] );
        }
        Rows::save_meta( $row->payment_hash, [ 'note' => $note ] );
        Notify::declined( $row, $reason );

        wp_send_json_success( [ 'message' => __( 'Anfrage abgelehnt.', 'sk-core' ) ] );
    }

    public function ajax_cancel(): void {
        [ $row, $role ] = $this->party( 'requested' );
        $this->require_role( $role, 'buyer' );

        if ( ! Rows::set_status( $row->payment_hash, 'requested', 'expired' ) ) {
            wp_send_json_error( [ 'message' => __( 'Anfrage wurde bereits bearbeitet.', 'sk-core' ) ] );
        }
        Rows::save_meta( $row->payment_hash, [ 'note' => __( 'Vom Käufer zurückgezogen', 'sk-core' ) ] );
        Notify::cancelled( $row );

        wp_send_json_success( [ 'message' => __( 'Anfrage zurückgezogen.', 'sk-core' ) ] );
    }

    // ---- state from the API ----

    /**
     * Pull the API state into the row. Used by the buyer's polling, the
     * webhook and the cron, so every path applies the same transitions.
     */
    public static function sync( object $row ): object {
        if ( ! in_array( (string) $row->status, [ 'pending', 'confirmed', 'delivered', 'disputed' ], true ) ) {
            return $row;
        }

        $meta = Rows::meta( $row );
        if ( empty( $meta['order_id'] ) ) {
            return $row;
        }

        $st = weo_api_get( '/orders/' . rawurlencode( $meta['order_id'] ) . '/status' );
        if ( is_wp_error( $st ) || empty( $st['state'] ) ) {
            return $row;
        }

        $state   = (string) $st['state'];
        $funding = is_array( $st['funding'] ?? null ) ? $st['funding'] : [];
        $update  = [ 'state' => $state ];

        if ( $funding ) {
            $update['funded_sat']   = (int) ( $funding['total_sat'] ?? 0 );
            $update['funding_txid'] = (string) ( $funding['utxos'][0]['txid'] ?? '' );
            $update['confirmations'] = (int) ( $funding['confirmations'] ?? 0 );
        }
        if ( ! empty( $st['deadline_ts'] ) ) {
            $update['deadline_ts'] = (int) $st['deadline_ts'];
        }
        Rows::save_meta( $row->payment_hash, $update );

        $funded_states = [ 'escrow_funded', 'signing', 'completed', 'refunded', 'dispute' ];
        if ( $row->status === 'pending' && in_array( $state, $funded_states, true ) ) {
            $now = current_time( 'mysql' );
            if ( Rows::set_status( $row->payment_hash, 'pending', 'confirmed', [
                'confirmed_at'      => $now,
                'confirmed_via'     => 'escrow',
                'preimage'          => (string) ( $update['funding_txid'] ?? '' ),
                'preimage_verified' => '1',
                'reputation_at'     => wp_date( 'Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS ),
            ] ) ) {
                $fresh = Rows::get( $row->payment_hash );
                do_action( 'sk_payment_confirmed', $row->payment_hash, 'escrow' );
                Notify::funded( $fresh, Rows::meta( $fresh ) );
            }
        }

        // The API escalates a signing deadline to a dispute on its own.
        if ( $state === 'dispute' && in_array( (string) $row->status, [ 'confirmed', 'delivered' ], true ) ) {
            if ( Rows::set_status( $row->payment_hash, (string) $row->status, 'disputed' ) ) {
                Rows::save_meta( $row->payment_hash, [], [ 'dispute_reason' => __( 'Frist abgelaufen (automatisch)', 'sk-core' ), 'dispute_at' => current_time( 'mysql' ) ] );
                Notify::disputed( Rows::get( $row->payment_hash ) );
            }
        }

        return Rows::get( $row->payment_hash ) ?: $row;
    }

    public function ajax_status(): void {
        [ $row ] = $this->party();
        $row  = self::sync( $row );
        $meta = Rows::meta( $row );

        wp_send_json_success( [
            'status'        => (string) $row->status,
            'state'         => (string) ( $meta['state'] ?? '' ),
            'funded_sat'    => (int) ( $meta['funded_sat'] ?? 0 ),
            'deposit_sat'   => (int) ( $meta['deposit_sat'] ?? 0 ),
            'confirmations' => (int) ( $meta['confirmations'] ?? 0 ),
            'settled_txid'  => (string) ( $meta['settled_txid'] ?? '' ),
            'label'         => Rows::state_label( $row ),
        ] );
    }

    // ---- signing ----

    /**
     * Which transaction this party may sign now, or an error text.
     *
     * payout: the buyer opens it by confirming receipt (status confirmed),
     *         the seller counter-signs it (status delivered).
     * refund: the seller opens it while the deposit sits in escrow (status
     *         confirmed), the buyer counter-signs it.
     */
    private function allowed_type( object $row, string $role, string $type ): string {
        $meta   = Rows::meta( $row );
        $signed = (array) ( $meta['signed'] ?? [] );
        $open   = (string) ( $meta['psbt_type'] ?? '' );

        if ( ! empty( $meta['settled_txid'] ) ) {
            return __( 'Dieser Handel ist bereits abgeschlossen.', 'sk-core' );
        }
        if ( in_array( $role, $signed, true ) && $open === $type ) {
            return __( 'Du hast bereits signiert. Die Gegenpartei ist dran.', 'sk-core' );
        }
        if ( $open !== '' && $open !== $type ) {
            return __( 'Für diesen Handel läuft bereits eine andere Transaktion.', 'sk-core' );
        }

        // In a dispute only the admin opens a transaction; the favoured
        // party counter-signs it.
        if ( $row->status === 'disputed' ) {
            $favoured = $type === 'refund' ? 'buyer' : 'seller';
            if ( $open === $type && $role === $favoured ) {
                return '';
            }
            return __( 'Im Dispute entscheidet der Marktplatz, welche Transaktion gebaut wird.', 'sk-core' );
        }

        if ( $type === 'payout' ) {
            if ( $role === 'buyer' && $row->status === 'confirmed' ) {
                return '';
            }
            if ( $role === 'seller' && $row->status === 'delivered' && $open === 'payout' ) {
                return '';
            }
        }
        if ( $type === 'refund' && $row->status === 'confirmed' ) {
            if ( $role === 'seller' ) {
                return '';
            }
            if ( $role === 'buyer' && $open === 'refund' ) {
                return '';
            }
        }

        return __( 'Dieser Schritt ist im aktuellen Zustand nicht möglich.', 'sk-core' );
    }

    private function posted_type(): string {
        $type = sanitize_key( wp_unslash( $_POST['type'] ?? 'payout' ) );

        return $type === 'refund' ? 'refund' : 'payout';
    }

    public function ajax_psbt(): void {
        [ $row, $role ] = $this->party( 'confirmed', 'delivered', 'disputed' );
        $type = $this->posted_type();

        $error = $this->allowed_type( $row, $role, $type );
        if ( $error !== '' ) {
            wp_send_json_error( [ 'message' => $error ] );
        }

        $meta = Rows::meta( $row );
        $meta = $this->ensure_psbt( $row, $meta, $type );

        wp_send_json_success( [
            'psbt'       => $meta['psbt'],
            'type'       => $type,
            'descriptor' => $meta['descriptor'],
            'address'    => $meta['address'],
            'role'       => $role,
        ] );
    }

    /** Build the transaction at the API if none is open yet, and remember it. */
    private function ensure_psbt( object $row, array $meta, string $type ): array {
        if ( ( $meta['psbt_type'] ?? '' ) === $type && ! empty( $meta['psbt'] ) ) {
            return $meta;
        }

        $order_id = $meta['order_id'];
        if ( $type === 'refund' ) {
            $res = weo_api_post( '/psbt/build_refund', [
                'order_id'    => $order_id,
                'address'     => $meta['refund_address'],
                'rbf'         => true,
                'target_conf' => 3,
            ] );
        } else {
            $res = weo_api_post( '/psbt/build', [
                'order_id'    => $order_id,
                'outputs'     => [ $meta['payout_address'] => (int) $row->amount_sats ],
                'rbf'         => true,
                'target_conf' => 3,
            ] );
        }

        if ( is_wp_error( $res ) || empty( $res['psbt'] ) ) {
            wp_send_json_error( [ 'message' => is_wp_error( $res ) ? $res->get_error_message() : __( 'PSBT konnte nicht erstellt werden.', 'sk-core' ) ] );
        }

        $update = [ 'psbt_type' => $type, 'psbt' => (string) $res['psbt'], 'signed' => [] ];
        Rows::save_meta( $row->payment_hash, $update );

        return array_merge( $meta, $update );
    }

    public function ajax_partial(): void {
        [ $row, $role ] = $this->party( 'confirmed', 'delivered', 'disputed' );
        $type = $this->posted_type();

        $error = $this->allowed_type( $row, $role, $type );
        if ( $error !== '' ) {
            wp_send_json_error( [ 'message' => $error ] );
        }

        $partial = trim( (string) wp_unslash( $_POST['psbt'] ?? '' ) );
        if ( $partial === '' || strlen( $partial ) > 200000 || base64_decode( $partial, true ) === false ) {
            wp_send_json_error( [ 'message' => __( 'Ungültige PSBT (Base64 erwartet).', 'sk-core' ) ] );
        }

        $meta = Rows::meta( $row );
        if ( ( $meta['psbt_type'] ?? '' ) !== $type || empty( $meta['psbt'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Bitte zuerst die PSBT abrufen.', 'sk-core' ) ] );
        }

        $order_id = $meta['order_id'];
        $merge    = weo_api_post( '/psbt/merge', [ 'order_id' => $order_id, 'partials' => [ $partial ] ] );
        if ( is_wp_error( $merge ) || empty( $merge['psbt'] ) ) {
            wp_send_json_error( [ 'message' => is_wp_error( $merge ) ? $merge->get_error_message() : __( 'PSBT konnte nicht zusammengeführt werden.', 'sk-core' ) ] );
        }

        $dec = weo_api_post( '/psbt/decode', [ 'psbt' => $merge['psbt'] ] );
        if ( is_wp_error( $dec ) ) {
            wp_send_json_error( [ 'message' => $dec->get_error_message() ] );
        }

        $sign_count = (int) ( $dec['sign_count'] ?? 0 );
        $signed     = array_values( array_unique( array_merge( (array) ( $meta['signed'] ?? [] ), [ $role ] ) ) );
        Rows::save_meta( $row->payment_hash, [ 'signed' => $signed, 'sign_count' => $sign_count ] );

        $fresh = Rows::get( $row->payment_hash );

        // First signature on a payout is the buyer's receipt confirmation.
        if ( $type === 'payout' && $role === 'buyer' && $fresh->status === 'confirmed' ) {
            if ( Rows::set_status( $row->payment_hash, 'confirmed', 'delivered' ) ) {
                $fresh = Rows::get( $row->payment_hash );
                Notify::released( $fresh );
            }
        }
        if ( $type === 'refund' && $role === 'seller' && count( $signed ) === 1 ) {
            Notify::refund_started( $fresh );
        }

        if ( $sign_count < 2 ) {
            wp_send_json_success( [ 'sign_count' => $sign_count, 'message' => __( 'Signatur gespeichert. Die Gegenpartei muss noch signieren.', 'sk-core' ) ] );
        }

        $txid = self::settle( $fresh, $merge['psbt'], $type );
        if ( is_wp_error( $txid ) ) {
            wp_send_json_error( [ 'message' => $txid->get_error_message(), 'sign_count' => $sign_count ] );
        }

        wp_send_json_success( [ 'sign_count' => $sign_count, 'settled_txid' => $txid, 'message' => __( 'Beide Signaturen vorhanden, Transaktion gesendet.', 'sk-core' ) ] );
    }

    /**
     * Finalize and broadcast a fully signed PSBT, then close the row.
     * Shared with the admin's dispute resolution.
     *
     * @return string|\WP_Error txid
     */
    public static function settle( object $row, string $psbt, string $type ) {
        $meta     = Rows::meta( $row );
        $order_id = $meta['order_id'];

        $fin = weo_api_post( '/psbt/finalize', [ 'order_id' => $order_id, 'psbt' => $psbt ] );
        if ( is_wp_error( $fin ) || empty( $fin['hex'] ) ) {
            return is_wp_error( $fin ) ? $fin : new \WP_Error( 'weo_finalize', __( 'Finalisierung fehlgeschlagen.', 'sk-core' ) );
        }

        $tx = weo_api_post( '/tx/broadcast', [ 'order_id' => $order_id, 'hex' => $fin['hex'] ] );
        if ( is_wp_error( $tx ) || empty( $tx['txid'] ) ) {
            return is_wp_error( $tx ) ? $tx : new \WP_Error( 'weo_broadcast', __( 'Broadcast fehlgeschlagen.', 'sk-core' ) );
        }

        $txid = (string) $tx['txid'];
        Rows::save_meta( $row->payment_hash, [ 'settled_txid' => $txid, 'settled_at' => current_time( 'mysql' ), 'state' => $type === 'refund' ? 'refunded' : 'completed' ] );

        if ( $type === 'refund' ) {
            Rows::set_status( $row->payment_hash, (string) $row->status, 'refunded' );
        } else {
            // Disputes resolved in favour of the seller end up here too.
            if ( $row->status !== 'delivered' ) {
                Rows::set_status( $row->payment_hash, (string) $row->status, 'delivered' );
            }
            self::credit( Rows::get( $row->payment_hash ) );
        }

        Notify::settled( Rows::get( $row->payment_hash ), $type, $txid );

        return $txid;
    }

    /**
     * A settled payout is a delivered, provably paid sale: same reputation
     * and commission handling as a confirmed Lightning delivery.
     */
    private static function credit( object $row ): void {
        global $wpdb;

        $valid = false;
        $flags = [];
        if ( class_exists( '\SK\Modules\Reputation\Calculator' ) ) {
            $valid = \SK\Modules\Reputation\Calculator::is_reputation_valid( $row );
            $flags = \SK\Modules\Reputation\Calculator::check_sybil( $row );
        }

        $wpdb->update( Rows::table(), [
            'reputation_at'    => current_time( 'mysql' ),
            'reputation_valid' => $valid ? 1 : 0,
            'reputation_flags' => $flags ? wp_json_encode( $flags ) : null,
            'reputation_state' => $valid ? 'credited' : 'rejected',
        ], [ 'payment_hash' => $row->payment_hash ] );

        if ( $valid && class_exists( '\SK\Modules\Reputation\Calculator' ) ) {
            \SK\Modules\Reputation\Calculator::recalculate_vendor( (int) $row->vendor_id );
        }

        $fresh = Rows::get( $row->payment_hash );
        if ( $fresh ) {
            do_action( 'sk_payment_delivered', $fresh );
        }
    }

    // ---- disputes ----

    /** "Problem melden" on an escrow row freezes the order at the API as well. */
    public static function on_disputed( string $hash ): void {
        $row = Rows::get( $hash );
        if ( ! $row || $row->status !== 'disputed' ) {
            return;
        }

        $meta = Rows::meta( $row );
        if ( ! empty( $meta['order_id'] ) ) {
            $res = weo_api_post( '/psbt/finalize', [ 'order_id' => $meta['order_id'], 'psbt' => '', 'state' => 'dispute' ] );
            Rows::save_meta( $hash, [ 'state' => is_wp_error( $res ) ? ( $meta['state'] ?? '' ) : 'dispute', 'dispute_api_error' => is_wp_error( $res ) ? $res->get_error_message() : '' ] );
        }

        Notify::disputed( $row );
    }
}
