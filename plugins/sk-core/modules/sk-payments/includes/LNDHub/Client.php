<?php

namespace SK\Modules\Payments\LNDHub;

defined( 'ABSPATH' ) || exit;

class Client {

    private string $base_url;
    private string $login;
    private string $password;
    private ?string $access_token = null;

    public static function from_connection_string( string $connection_string ) {
        $connection_string = trim( $connection_string );

        $without_scheme = preg_replace( '#^lndhub://#i', '', $connection_string );
        if ( ! $without_scheme || $without_scheme === $connection_string ) {
            return new \WP_Error( 'lndhub_invalid', 'Ungültiges LNDHub-Format. Muss mit lndhub:// beginnen.' );
        }

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

    private function authenticate() {
        if ( $this->access_token ) {
            return $this->access_token;
        }

        $response = wp_safe_remote_post( $this->base_url . '/auth', [
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

    public function make_invoice( int $amount_sats, string $description = '' ) {
        $token = $this->authenticate();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $response = wp_safe_remote_post( $this->base_url . '/addinvoice', [
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

    public function lookup_invoice( string $payment_hash ) {
        $token = $this->authenticate();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $response = wp_safe_remote_get( $this->base_url . '/getuserinvoices', [
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

        foreach ( $body as $invoice ) {
            $hash = $invoice['r_hash'] ?? $invoice['payment_hash'] ?? '';
            if ( ! is_string( $hash ) ) {
                $hash = '';
            }
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

    public function get_info() {
        $token = $this->authenticate();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        return [ 'authenticated' => true ];
    }

    /**
     * @see \SK\Modules\Payments\Secret Authenticated encryption (AES-256-GCM).
     */
    public static function encrypt_connection_string( string $connection_string ): string {
        return \SK\Modules\Payments\Secret::encrypt( $connection_string );
    }

    public static function decrypt_connection_string( string $encrypted ): string {
        return \SK\Modules\Payments\Secret::decrypt( $encrypted );
    }
}
