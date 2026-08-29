<?php

namespace SK\Modules\Payments;

defined( 'ABSPATH' ) || exit;

class StoreSettings {

    public function __construct() {
        // Felder werden direkt im store-form.php Template gerendert (kein Hook nötig).
        add_action( 'sk_store_profile_saved', [ $this, 'save_field' ], 15, 3 );

        // AJAX test handlers.
        add_action( 'wp_ajax_skp_test_btcaddr', [ $this, 'ajax_test_btcaddr' ] );
        add_action( 'wp_ajax_skp_test_xpub', [ $this, 'ajax_test_xpub' ] );
        add_action( 'wp_ajax_skp_test_nwc', [ $this, 'ajax_test_nwc' ] );
        add_action( 'wp_ajax_skp_test_lndhub', [ $this, 'ajax_test_lndhub' ] );
        add_action( 'wp_ajax_skp_test_lnaddr', [ $this, 'ajax_test_lnaddr' ] );

        // Erklaerfenster fuer eine abgewiesene Adresse, plus die Meldung des
        // letzten Speicherversuchs.
        add_action( 'wp_footer', [ $this, 'render_reject_modal' ] );
    }

    /**
     * These endpoints exist so a vendor can check their own wallet settings.
     * Three of them reach out to a host the caller supplies, so they are for
     * sellers only and bounded — otherwise any account can use the site as an
     * unmetered request proxy.
     */
    private function guard_test_endpoint(): void {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Nicht eingeloggt.' ] );
        }

        $user_id = get_current_user_id();

        if ( ! function_exists( 'sk_is_user_seller' ) || ! sk_is_user_seller( $user_id ) ) {
            wp_send_json_error( [ 'message' => 'Nur für Anbieter.' ] );
        }

        if ( ! sk_rate_limit( 'wallet-test:' . $user_id, 10 ) ) {
            wp_send_json_error( [ 'message' => 'Zu viele Tests, bitte kurz warten.' ] );
        }
    }

    public function ajax_test_btcaddr() {
        check_ajax_referer( 'skp_test_connection', 'nonce' );
        $this->guard_test_endpoint();

        $value = sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) );
        if ( empty( $value ) ) {
            wp_send_json_error( [ 'message' => 'Keine Adresse angegeben.' ] );
        }

        if ( ! self::is_valid_btc_address( $value ) ) {
            wp_send_json_error( [ 'message' => 'Ungültiges Bitcoin-Adressformat.' ] );
        }

        wp_send_json_success( [ 'message' => 'Adresse gültig (' . self::get_btc_address_type( $value ) . ')' ] );
    }

    public function ajax_test_xpub() {
        check_ajax_referer( 'skp_test_connection', 'nonce' );
        $this->guard_test_endpoint();

        $value = sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) );
        if ( empty( $value ) ) {
            wp_send_json_error( [ 'message' => 'Kein xpub angegeben.' ] );
        }

        if ( ! self::is_valid_xpub( $value ) ) {
            wp_send_json_error( [ 'message' => 'Ungültiges xpub/ypub/zpub-Format.' ] );
        }

        // Try to derive a test address (index 0).
        $address = Onchain\XpubDerivation::derive_address( $value, 0 );
        if ( is_wp_error( $address ) ) {
            wp_send_json_error( [ 'message' => $address->get_error_message() ] );
        }

        wp_send_json_success( [ 'message' => 'xpub gültig — erste Adresse: ' . substr( $address, 0, 12 ) . '...' ] );
    }

    public function ajax_test_nwc() {
        check_ajax_referer( 'skp_test_connection', 'nonce' );
        $this->guard_test_endpoint();

        $value = sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) );
        if ( empty( $value ) ) {
            wp_send_json_error( [ 'message' => 'Kein NWC-String angegeben.' ] );
        }

        $client = NWC\Client::from_connection_string( $value );
        if ( is_wp_error( $client ) ) {
            wp_send_json_error( [ 'message' => $client->get_error_message() ] );
        }

        $info = $client->get_info();
        if ( is_wp_error( $info ) ) {
            wp_send_json_error( [ 'message' => 'Verbindung fehlgeschlagen: ' . $info->get_error_message() ] );
        }

        wp_send_json_success( [ 'message' => 'NWC-Verbindung erfolgreich' ] );
    }

    public function ajax_test_lndhub() {
        check_ajax_referer( 'skp_test_connection', 'nonce' );
        $this->guard_test_endpoint();

        $value = sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) );
        if ( empty( $value ) ) {
            wp_send_json_error( [ 'message' => 'Kein LNDHub-String angegeben.' ] );
        }

        $client = LNDHub\Client::from_connection_string( $value );
        if ( is_wp_error( $client ) ) {
            wp_send_json_error( [ 'message' => $client->get_error_message() ] );
        }

        $info = $client->get_info();
        if ( is_wp_error( $info ) ) {
            wp_send_json_error( [ 'message' => 'Authentifizierung fehlgeschlagen: ' . $info->get_error_message() ] );
        }

        wp_send_json_success( [ 'message' => 'LNDHub-Verbindung erfolgreich' ] );
    }

    /**
     * Erklaerfenster ausgeben — nur auf der Seite mit den Shopdaten.
     */
    public function render_reject_modal(): void {
        if ( ! function_exists( 'sk_is_seller_dashboard' ) || ! sk_is_seller_dashboard() ) {
            return;
        }

        global $wp;

        if ( ( $wp->query_vars['settings'] ?? '' ) !== 'store' ) {
            return;
        }

        $user_id = get_current_user_id();
        $message = get_transient( 'skp_lnaddr_msg_' . $user_id );

        if ( $message ) {
            delete_transient( 'skp_lnaddr_msg_' . $user_id );
            printf(
                '<script>window.skpLnaddrRejected = %s;</script>',
                wp_json_encode( $message )
            );
        }

        include SK_PAYMENTS_PATH . '/templates/lnaddr-reject-modal.php';
    }

    /**
     * Kann diese Adresse eine Zahlung nachweisen?
     *
     * LUD-21: die Antwort auf eine Invoice-Anfrage traegt eine verify-Adresse,
     * ueber die sich spaeter abfragen laesst, ob die Rechnung beglichen wurde.
     * Ohne sie erfaehrt SK nie, ob gezahlt wurde — und ohne das laesst sich
     * keine Provision durchsetzen. Die Preimage-Einreichung durch den Kaeufer
     * beweist zwar dasselbe, setzt aber dessen Mitwirkung voraus und taugt
     * deshalb nicht als Grundlage.
     *
     * Gefragt wird mit dem Mindestbetrag des Anbieters, nicht mit einem festen
     * Wert: viele lehnen zu kleine Betraege ab, und eine Ablehnung saehe sonst
     * wie fehlende Unterstuetzung aus.
     *
     * @return true|\WP_Error true wenn nachweisbar; WP_Error mit Grund sonst.
     */
    public static function check_lud21( string $address ) {
        $meta = LNURL\Resolver::resolve( $address );

        if ( is_wp_error( $meta ) ) {
            return new \WP_Error( 'lnaddr_unreachable', $meta->get_error_message() );
        }

        if ( empty( $meta['callback'] ) ) {
            return new \WP_Error( 'lnaddr_unreachable', __( 'Die Adresse antwortet nicht wie eine Lightning-Adresse.', 'sk-core' ) );
        }

        $amount = isset( $meta['minSendable'] ) ? (int) $meta['minSendable'] : 1000;
        $probe  = LNURL\Resolver::request_invoice( $meta['callback'], max( 1000, $amount ) );

        if ( is_wp_error( $probe ) ) {
            return new \WP_Error( 'lnaddr_unreachable', $probe->get_error_message() );
        }

        if ( empty( $probe['verify'] ) ) {
            return new \WP_Error( 'lnaddr_no_lud21', self::lud21_hint() );
        }

        return true;
    }

    /**
     * Was der Haendler stattdessen tun kann.
     */
    private static function lud21_hint(): string {
        return __( 'Diese Wallet kann nicht bestätigen, ob eine Rechnung bezahlt wurde (LUD-21 fehlt). Ohne diesen Nachweis lässt sich der Verkauf nicht abrechnen. Wallets, die es können: Alby, Blink, Coinos, BTCPay Server. Nicht möglich ist es unter anderem mit Wallet of Satoshi.', 'sk-core' );
    }

    public function ajax_test_lnaddr() {
        check_ajax_referer( 'skp_test_connection', 'nonce' );
        $this->guard_test_endpoint();

        $value = sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) );
        if ( empty( $value ) ) {
            wp_send_json_error( [ 'message' => 'Keine Adresse angegeben.' ] );
        }

        if ( ! self::is_valid_lightning_address( $value ) && ! self::is_valid_lnurl( $value ) ) {
            wp_send_json_error( [ 'message' => 'Ungültiges Format. Erwartet: user@domain.com oder lnurl1...' ] );
        }

        $result = LNURL\Resolver::resolve( $value );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        $lud21 = self::check_lud21( $value );

        if ( is_wp_error( $lud21 ) ) {
            wp_send_json_error( [ 'message' => $lud21->get_error_message() ] );
        }

        $msg = 'Adresse gültig und nachweisbar';
        if ( ! empty( $result['callback'] ) ) {
            $min = isset( $result['minSendable'] ) ? (int) ceil( $result['minSendable'] / 1000 ) : 0;
            $max = isset( $result['maxSendable'] ) ? (int) floor( $result['maxSendable'] / 1000 ) : 0;
            $msg .= " — {$min}–{$max} Sats";
        }

        wp_send_json_success( [ 'message' => $msg ] );
    }

    public function save_field( int $store_id, array $sk_settings = [], array $prev_settings = [] ) {
        $settings = get_user_meta( $store_id, 'sk_profile_settings', true );
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }

        $this->save_btc_address( $store_id, $settings );
        $this->save_xpub( $store_id, $settings );
        $this->save_nwc( $store_id, $settings );
        $this->save_lndhub( $store_id, $settings );

        if ( ! isset( $_POST['lightning_address'] ) ) {
            return;
        }

        $raw = sanitize_text_field( wp_unslash( $_POST['lightning_address'] ) );

        if ( empty( $raw ) ) {
            $settings['lightning_address'] = '';
            update_user_meta( $store_id, 'sk_profile_settings', $settings );
            return;
        }

        if ( ! self::is_valid_lightning_address( $raw ) && ! self::is_valid_lnurl( $raw ) ) {
            $settings['lightning_address'] = $prev_settings['lightning_address'] ?? '';
            update_user_meta( $store_id, 'sk_profile_settings', $settings );
            return;
        }

        /*
         * Ohne LUD-21 wird die Adresse nicht uebernommen.
         *
         * Sie zu speichern und nur zu markieren, hiesse: der Haendler bietet
         * Lightning an, und beim Verkauf stellt sich heraus, dass die Zahlung
         * nicht nachweisbar ist. Die Ablehnung gehoert an die Stelle, an der
         * sie sich noch aendern laesst.
         */
        $lud21 = self::check_lud21( $raw );

        if ( is_wp_error( $lud21 ) ) {
            $settings['lightning_address'] = $prev_settings['lightning_address'] ?? '';
            $settings['lightning_lud21']   = false;

            update_user_meta( $store_id, 'sk_profile_settings', $settings );

            if ( function_exists( 'sk_add_notice' ) ) {
                sk_add_notice( $lud21->get_error_message(), 'error' );
            }

            set_transient( 'skp_lnaddr_msg_' . $store_id, $lud21->get_error_message(), 120 );

            return;
        }

        $settings['lightning_address'] = $raw;
        $settings['lightning_lud21']   = true;

        update_user_meta( $store_id, 'sk_profile_settings', $settings );
    }

    private function save_nwc( int $store_id, array &$settings ) {
        if ( ! empty( $_POST['nwc_remove'] ) && $_POST['nwc_remove'] === '1' ) {
            delete_user_meta( $store_id, 'sk_nwc_connection' );
            $settings['lightning_nwc'] = false;
            update_user_meta( $store_id, 'sk_profile_settings', $settings );
            return;
        }

        $nwc_raw = sanitize_text_field( wp_unslash( $_POST['nwc_connection'] ?? '' ) );
        if ( empty( $nwc_raw ) ) {
            return;
        }

        $client = NWC\Client::from_connection_string( $nwc_raw );
        if ( is_wp_error( $client ) ) {
            $settings['lightning_nwc'] = false;
            update_user_meta( $store_id, 'sk_profile_settings', $settings );
            return;
        }

        $info = $client->get_info();
        $nwc_works = ! is_wp_error( $info );

        $encrypted = Secret::encrypt( $nwc_raw );
        if ( $encrypted === '' ) {
            // Never overwrite a working connection with an empty value.
            return;
        }

        update_user_meta( $store_id, 'sk_nwc_connection', $encrypted );
        $settings['lightning_nwc'] = $nwc_works;
        update_user_meta( $store_id, 'sk_profile_settings', $settings );
    }

    public static function get_nwc_client( int $vendor_id ) {
        // Upgrades legacy CBC ciphertext to GCM on first read.
        $connection_string = Secret::from_user_meta( $vendor_id, 'sk_nwc_connection' );
        if ( empty( $connection_string ) ) {
            return null;
        }

        $client = NWC\Client::from_connection_string( $connection_string );
        return is_wp_error( $client ) ? null : $client;
    }

    public static function has_nwc( int $vendor_id ): bool {
        $settings = get_user_meta( $vendor_id, 'sk_profile_settings', true );
        return is_array( $settings ) && ! empty( $settings['lightning_nwc'] );
    }

    private function save_lndhub( int $store_id, array &$settings ) {
        if ( ! empty( $_POST['lndhub_remove'] ) && $_POST['lndhub_remove'] === '1' ) {
            delete_user_meta( $store_id, 'sk_lndhub_connection' );
            $settings['lightning_lndhub'] = false;
            update_user_meta( $store_id, 'sk_profile_settings', $settings );
            return;
        }

        $raw = sanitize_text_field( wp_unslash( $_POST['lndhub_connection'] ?? '' ) );
        if ( empty( $raw ) ) {
            return;
        }

        $client = LNDHub\Client::from_connection_string( $raw );
        if ( is_wp_error( $client ) ) {
            $settings['lightning_lndhub'] = false;
            update_user_meta( $store_id, 'sk_profile_settings', $settings );
            return;
        }

        $info = $client->get_info();
        $works = ! is_wp_error( $info );

        $encrypted = Secret::encrypt( $raw );
        if ( $encrypted === '' ) {
            // Never overwrite a working connection with an empty value.
            return;
        }

        update_user_meta( $store_id, 'sk_lndhub_connection', $encrypted );
        $settings['lightning_lndhub'] = $works;
        update_user_meta( $store_id, 'sk_profile_settings', $settings );
    }

    public static function get_lndhub_client( int $vendor_id ) {
        // Upgrades legacy CBC ciphertext to GCM on first read.
        $connection_string = Secret::from_user_meta( $vendor_id, 'sk_lndhub_connection' );
        if ( empty( $connection_string ) ) {
            return null;
        }

        $client = LNDHub\Client::from_connection_string( $connection_string );
        return is_wp_error( $client ) ? null : $client;
    }

    public static function has_lndhub( int $vendor_id ): bool {
        $settings = get_user_meta( $vendor_id, 'sk_profile_settings', true );
        return is_array( $settings ) && ! empty( $settings['lightning_lndhub'] );
    }

    // ── Onchain: Save + Validate ──

    private function save_btc_address( int $store_id, array &$settings ) {
        if ( ! isset( $_POST['btc_address'] ) ) {
            return;
        }

        $raw = sanitize_text_field( wp_unslash( $_POST['btc_address'] ) );

        if ( empty( $raw ) ) {
            $settings['btc_address'] = '';
            update_user_meta( $store_id, 'sk_profile_settings', $settings );
            return;
        }

        if ( ! self::is_valid_btc_address( $raw ) ) {
            return;
        }

        $settings['btc_address'] = $raw;
        update_user_meta( $store_id, 'sk_profile_settings', $settings );
    }

    private function save_xpub( int $store_id, array &$settings ) {
        if ( ! empty( $_POST['xpub_remove'] ) && $_POST['xpub_remove'] === '1' ) {
            delete_user_meta( $store_id, 'sk_xpub' );
            delete_user_meta( $store_id, 'sk_xpub_index' );
            $settings['btc_xpub_verified'] = false;
            update_user_meta( $store_id, 'sk_profile_settings', $settings );
            return;
        }

        $raw = sanitize_text_field( wp_unslash( $_POST['btc_xpub'] ?? '' ) );
        if ( empty( $raw ) ) {
            return;
        }

        if ( ! self::is_valid_xpub( $raw ) ) {
            $settings['btc_xpub_verified'] = false;
            update_user_meta( $store_id, 'sk_profile_settings', $settings );
            return;
        }

        // Test derivation.
        $test = Onchain\XpubDerivation::derive_address( $raw, 0 );
        $valid = ! is_wp_error( $test );

        $encrypted = Secret::encrypt( $raw );
        if ( $encrypted === '' ) {
            // Never overwrite a stored xpub with an empty value.
            return;
        }

        update_user_meta( $store_id, 'sk_xpub', $encrypted );

        if ( ! get_user_meta( $store_id, 'sk_xpub_index', true ) ) {
            update_user_meta( $store_id, 'sk_xpub_index', 0 );
        }

        $settings['btc_xpub_verified'] = $valid;
        update_user_meta( $store_id, 'sk_profile_settings', $settings );
    }

    /**
     * Checksum-verified — a mistyped payout address would otherwise be saved
     * and paid out to nowhere.
     */
    public static function is_valid_btc_address( string $value ): bool {
        return \SK\Core\BitcoinAddress::is_valid( $value );
    }

    public static function get_btc_address_type( string $value ): string {
        return \SK\Core\BitcoinAddress::type( $value );
    }

    /**
     * Mainnet account keys only, checksum-verified. The derivation falls
     * through to P2PKH for anything that is not zpub or ypub, so a testnet
     * tpub would silently produce addresses nobody can spend from — and a
     * mistyped key produces a whole chain of them.
     */
    public static function is_valid_xpub( string $value ): bool {
        return \SK\Core\BitcoinAddress::is_valid_xpub( $value );
    }

    public static function get_btc_address( int $vendor_id ): string {
        $settings = get_user_meta( $vendor_id, 'sk_profile_settings', true );
        return ( is_array( $settings ) && ! empty( $settings['btc_address'] ) )
            ? $settings['btc_address']
            : '';
    }

    public static function has_xpub( int $vendor_id ): bool {
        return ! empty( get_user_meta( $vendor_id, 'sk_xpub', true ) );
    }

    /**
     * Get the next onchain address for a vendor.
     * xpub: derives next address and increments index.
     * Static address: returns the stored btc_address.
     */
    public static function get_next_onchain_address( int $vendor_id ): string {
        if ( self::has_xpub( $vendor_id ) ) {
            // Upgrades legacy CBC ciphertext to GCM on first read.
            $xpub = Secret::from_user_meta( $vendor_id, 'sk_xpub' );

            // Atomic index increment to prevent race condition.
            global $wpdb;
            $updated = $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->usermeta} SET meta_value = meta_value + 1
                 WHERE user_id = %d AND meta_key = 'sk_xpub_index'",
                $vendor_id
            ) );

            // Fehlt die Zeile, gibt es nichts zu erhoehen — dann hier anlegen,
            // sonst waere der Index gleich -1.
            if ( ! $updated ) {
                update_user_meta( $vendor_id, 'sk_xpub_index', 1 );
            }

            /*
             * Der Zaehler wird per SQL erhoeht, damit zwei gleichzeitige Kaeufe
             * nicht dieselbe Stelle bekommen. Am Objekt-Cache geht das aber
             * vorbei: get_user_meta lieferte danach weiter den alten Wert —
             * auf dieser Installation 4, waehrend in der Tabelle 6 stand. Jeder
             * Kaeufer bekam dadurch dieselbe Adresse, obwohl der xpub gerade
             * dafuer da ist, fuer jede Zahlung eine eigene zu liefern.
             */
            wp_cache_delete( $vendor_id, 'user_meta' );

            // Read the value AFTER increment — subtract 1 to get the index we just claimed.
            $index = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->usermeta}
                 WHERE user_id = %d AND meta_key = 'sk_xpub_index'",
                $vendor_id
            ) ) - 1;

            if ( $index < 0 ) {
                $index = 0;
            }

            $address = Onchain\XpubDerivation::derive_address( $xpub, $index );
            if ( ! is_wp_error( $address ) ) {
                return $address;
            }
        }

        return self::get_btc_address( $vendor_id );
    }

    /**
     * Check if vendor accepts any onchain payment.
     */
    public static function has_onchain( int $vendor_id ): bool {
        return ! empty( self::get_btc_address( $vendor_id ) ) || self::has_xpub( $vendor_id );
    }

    /**
     * Check if vendor accepts any Lightning payment.
     */
    public static function has_lightning( int $vendor_id ): bool {
        return self::has_nwc( $vendor_id ) || self::has_lndhub( $vendor_id ) || ! empty( self::get_lightning_address( $vendor_id ) );
    }

    // ── Lightning: Validate ──

    public static function is_valid_lightning_address( string $value ): bool {
        return (bool) preg_match( '/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $value );
    }

    public static function is_valid_lnurl( string $value ): bool {
        return stripos( $value, 'lnurl1' ) === 0 && strlen( $value ) > 20;
    }

    public static function get_lightning_address( int $vendor_id ): string {
        $settings = get_user_meta( $vendor_id, 'sk_profile_settings', true );
        return ( is_array( $settings ) && ! empty( $settings['lightning_address'] ) )
            ? $settings['lightning_address']
            : '';
    }

    public static function get_reputation( int $vendor_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'sk_reputation_scores';

        $table_exists = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
        );

        if ( ! $table_exists ) {
            return null;
        }

        $rep = $wpdb->get_row(
            $wpdb->prepare( "SELECT valid_transactions, valid_volume_sats FROM {$table} WHERE vendor_id = %d", $vendor_id )
        );

        if ( ! $rep || $rep->valid_transactions < 1 ) {
            return null;
        }

        $rep->badge       = '';
        $rep->badge_label = '';

        if ( $rep->valid_transactions >= 100 ) {
            $rep->badge       = '⚡⚡⚡';
            $rep->badge_label = 'Lightning Veteran';
        } elseif ( $rep->valid_transactions >= 25 ) {
            $rep->badge       = '⚡⚡';
            $rep->badge_label = 'Lightning Händler';
        } elseif ( $rep->valid_transactions >= 5 ) {
            $rep->badge       = '⚡';
            $rep->badge_label = 'Lightning Starter';
        }

        return $rep;
    }

}
