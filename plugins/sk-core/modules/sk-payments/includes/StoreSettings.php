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
            wp_send_json_error( [ 'message' => 'Nur für Verkäufer.' ] );
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

        $msg = 'Adresse gültig';
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

        $settings['lightning_address'] = $raw;

        $lud21_supported = false;
        $resolve_result = LNURL\Resolver::resolve( $raw );
        if ( ! is_wp_error( $resolve_result ) && ! empty( $resolve_result['callback'] ) ) {
            $min_amount = $resolve_result['minSendable'] ?? 1000;
            $test_invoice = LNURL\Resolver::request_invoice( $resolve_result['callback'], (int) $min_amount );
            if ( ! is_wp_error( $test_invoice ) && ! empty( $test_invoice['verify'] ) ) {
                $lud21_supported = true;
            }
        }
        $settings['lightning_lud21'] = $lud21_supported;

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

    public static function is_valid_btc_address( string $value ): bool {
        return (bool) preg_match( '/^(bc1[a-z0-9]{25,90}|[13][a-km-zA-HJ-NP-Z1-9]{25,34})$/', $value );
    }

    public static function get_btc_address_type( string $value ): string {
        if ( strpos( $value, 'bc1q' ) === 0 ) return 'SegWit (bech32)';
        if ( strpos( $value, 'bc1p' ) === 0 ) return 'Taproot (bech32m)';
        if ( strpos( $value, '3' ) === 0 )    return 'P2SH';
        if ( strpos( $value, '1' ) === 0 )    return 'Legacy (P2PKH)';
        return 'Unbekannt';
    }

    /**
     * Mainnet account keys only. The derivation falls through to P2PKH for
     * anything that is not zpub or ypub, so a testnet tpub would silently
     * produce addresses nobody can spend from.
     */
    public static function is_valid_xpub( string $value ): bool {
        return (bool) preg_match( '/^(xpub|ypub|zpub)[a-km-zA-HJ-NP-Z1-9]{100,120}$/', $value );
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
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->usermeta} SET meta_value = meta_value + 1
                 WHERE user_id = %d AND meta_key = 'sk_xpub_index'",
                $vendor_id
            ) );
            // Read the value AFTER increment — subtract 1 to get the index we just claimed.
            $index = (int) get_user_meta( $vendor_id, 'sk_xpub_index', true ) - 1;

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
