<?php

namespace SK_Lightning\LNDHub;

defined( 'ABSPATH' ) || exit;

/**
 * LNDHub REST API Client.
 *
 * Compatible with BlueWallet LNDHub, LNbits LNDHub extension,
 * Alby lndhub.go, BTCPay LNDHub plugin.
 */
class Client {

    private string $base_url;
    private string $login;
    private string $password;
    private ?string $access_token = null;

    /**
     * Parse a LNDHub connection string.
     *
     * Format: lndhub://login:password@https://server.com
     *
     * @param string $connection_string
     * @return self|WP_Error
     */
    public static function from_connection_string( string $connection_string ) {
        $connection_string = trim( $connection_string );

        // Remove scheme prefix.
        $without_scheme = preg_replace( '#^lndhub://#i', '', $connection_string );
        if ( ! $without_scheme || $without_scheme === $connection_string ) {
            return new \WP_Error( 'lndhub_invalid', 'Ungültiges LNDHub-Format. Muss mit lndhub:// beginnen.' );
        }

        // Split credentials and server URL.
        $at_pos = strpos( $without_scheme, '@' );
        if ( $at_pos === false ) {
            return new \WP_Error( 'lndhub_invalid', 'LNDHub-String muss login:password@url enthalten.' );
        }

        $credentials = substr( $without_scheme, 0, $at_pos );
        $base_url    = substr( $without_scheme, $at_pos + 1 );

        $cred_parts = explode( ':', $credentials, 2 );
        if ( count( $cred_parts ) !== 2 || empty( $cred_parts[0] ) || empty( $cred_parts[1] ) ) {
            return new \WP_Error( 'lndhub_invalid', 'LNDHub Login und Passwort fehlen.' );
        }

        if ( empty( $base_url ) || ! filter_var( $base_url, FILTER_VALIDATE_URL ) ) {
            return new \WP_Error( 'lndhub_invalid', 'Ungültige LNDHub Server-URL.' );
        }

        $client = new self();
        $client->login    = $cred_parts[0];
        $client->password = $cred_parts[1];
        $client->base_url = rtrim( $base_url, '/' );

        return $client;
    }

    /**
     * Authenticate and get access token.
     *
     * @return string|WP_Error  Access token.
     */
    private function authenticate() {
        if ( $this->access_token ) {
            return $this->access_token;
        }

        $response = wp_remote_post( $this->base_url . '/auth', [
            'timeout' => 10,
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( [
                'login'    => $this->login,
                'password' => $this->password,
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['access_token'] ) ) {
            $error = $body['message'] ?? $body['error'] ?? 'Authentifizierung fehlgeschlagen';
            return new \WP_Error( 'lndhub_auth_failed', $error );
        }

        $this->access_token = $body['access_token'];
        return $this->access_token;
    }

    /**
     * Create a Lightning invoice.
     *
     * @param int    $amount_sats  Amount in satoshis.
     * @param string $description  Invoice description.
     * @return array|WP_Error  { pr (bolt11), payment_hash, ... }
     */
    public function make_invoice( int $amount_sats, string $description = '' ) {
        $token = $this->authenticate();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $response = wp_remote_post( $this->base_url . '/addinvoice', [
            'timeout' => 10,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'body' => wp_json_encode( [
                'amt'  => $amount_sats,
                'memo' => $description,
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['payment_request'] ) && empty( $body['pay_req'] ) ) {
            $error = $body['message'] ?? $body['error'] ?? 'Invoice-Erstellung fehlgeschlagen';
            return new \WP_Error( 'lndhub_invoice_failed', $error );
        }

        return [
            'pr'           => $body['payment_request'] ?? $body['pay_req'],
            'payment_hash' => $body['r_hash'] ?? $body['payment_hash'] ?? '',
        ];
    }

    /**
     * Check if an invoice has been paid.
     *
     * @param string $payment_hash  The payment hash to look up.
     * @return array|WP_Error  { settled (bool), preimage, ... }
     */
    public function lookup_invoice( string $payment_hash ) {
        $token = $this->authenticate();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $response = wp_remote_get( $this->base_url . '/getuserinvoices', [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $body ) ) {
            return new \WP_Error( 'lndhub_lookup_failed', 'Ungültige Response.' );
        }

        // Search for our invoice by payment hash.
        foreach ( $body as $invoice ) {
            $hash = $invoice['r_hash'] ?? $invoice['payment_hash'] ?? '';
            // r_hash can be hex or base64 depending on implementation.
            // Only try base64 decode if hash is NOT already hex (contains non-hex chars).
            $match = ( $hash === $payment_hash );
            if ( ! $match && ! empty( $hash ) && ! ctype_xdigit( $hash ) ) {
                $decoded = base64_decode( $hash, true );
                $match = ( $decoded !== false && bin2hex( $decoded ) === $payment_hash );
            }
            if ( $match ) {
                return [
                    'settled'      => ! empty( $invoice['ispaid'] ),
                    'preimage'     => $invoice['preimage'] ?? null,
                    'payment_hash' => $payment_hash,
                ];
            }
        }

        return [
            'settled'      => false,
            'preimage'     => null,
            'payment_hash' => $payment_hash,
        ];
    }

    /**
     * Test the connection.
     *
     * @return array|WP_Error
     */
    public function get_info() {
        $token = $this->authenticate();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        return [ 'authenticated' => true ];
    }

    /**
     * Encrypt a LNDHub connection string for safe storage.
     */
    public static function encrypt_connection_string( string $connection_string ): string {
        $key = wp_salt( 'auth' );
        $iv  = random_bytes( 16 );
        $encrypted = openssl_encrypt( $connection_string, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
        return base64_encode( $iv . $encrypted );
    }

    /**
     * Decrypt a stored LNDHub connection string.
     */
    public static function decrypt_connection_string( string $encrypted ): string {
        $key  = wp_salt( 'auth' );
        $data = base64_decode( $encrypted );
        $iv   = substr( $data, 0, 16 );
        $encrypted_data = substr( $data, 16 );
        return openssl_decrypt( $encrypted_data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
    }
}
