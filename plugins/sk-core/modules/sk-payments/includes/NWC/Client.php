<?php

namespace SK\Modules\Payments\NWC;

use swentel\nostr\Event\Event;
use swentel\nostr\Sign\Sign;
use swentel\nostr\Relay\Relay;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Encryption\Nip04;

defined( 'ABSPATH' ) || exit;

class Client {

    private string $wallet_pubkey;
    private string $relay_url;
    private string $secret;

    public static function from_connection_string( string $connection_string ) {
        $connection_string = trim( $connection_string );

        $without_scheme = preg_replace( '#^nostr\+walletconnect://#i', '', $connection_string );
        if ( ! $without_scheme || $without_scheme === $connection_string ) {
            return new \WP_Error( 'nwc_invalid', 'Ungültiges NWC-Format. Muss mit nostr+walletconnect:// beginnen.' );
        }

        $parts = explode( '?', $without_scheme, 2 );
        if ( count( $parts ) !== 2 ) {
            return new \WP_Error( 'nwc_invalid', 'NWC-String hat keine Parameter.' );
        }

        $wallet_pubkey = $parts[0];
        parse_str( $parts[1], $params );

        $relay_url = $params['relay'] ?? '';
        $secret    = $params['secret'] ?? '';

        if ( empty( $wallet_pubkey ) || strlen( $wallet_pubkey ) !== 64 ) {
            return new \WP_Error( 'nwc_invalid', 'Ungültiger Wallet-Pubkey im NWC-String.' );
        }

        if ( empty( $relay_url ) ) {
            return new \WP_Error( 'nwc_invalid', 'Kein Relay im NWC-String.' );
        }

        if ( empty( $secret ) || strlen( $secret ) !== 64 ) {
            return new \WP_Error( 'nwc_invalid', 'Ungültiger Secret-Key im NWC-String.' );
        }

        $client = new self();
        $client->wallet_pubkey = $wallet_pubkey;
        $client->relay_url     = $relay_url;
        $client->secret        = $secret;

        return $client;
    }

    public function make_invoice( int $amount_sats, string $description = '' ): mixed {
        $params = [
            'amount' => $amount_sats * 1000,
        ];
        if ( $description ) {
            $params['description'] = $description;
        }

        $response = $this->send_request( 'make_invoice', $params );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( ! empty( $response['error'] ) ) {
            $msg = $response['error']['message'] ?? 'Unbekannter NWC-Fehler';
            return new \WP_Error( 'nwc_error', $msg );
        }

        $result = $response['result'] ?? [];

        if ( empty( $result['invoice'] ) ) {
            return new \WP_Error( 'nwc_no_invoice', 'Keine Invoice in NWC-Response.' );
        }

        return [
            'pr'           => $result['invoice'],
            'payment_hash' => $result['payment_hash'] ?? '',
            'expires_at'   => $result['expires_at'] ?? null,
        ];
    }

    public function lookup_invoice( string $payment_hash ): mixed {
        $response = $this->send_request( 'lookup_invoice', [
            'payment_hash' => $payment_hash,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        if ( ! empty( $response['error'] ) ) {
            $msg = $response['error']['message'] ?? 'Unbekannter NWC-Fehler';
            return new \WP_Error( 'nwc_error', $msg );
        }

        $result  = $response['result'] ?? [];
        $settled = ( $result['state'] ?? '' ) === 'settled'
                   || ( $result['settled'] ?? false ) === true;

        return [
            'settled'      => $settled,
            'preimage'     => $result['preimage'] ?? null,
            'payment_hash' => $result['payment_hash'] ?? $payment_hash,
        ];
    }

    public function get_info(): mixed {
        $response = $this->send_request( 'get_info', [] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return $response['result'] ?? [];
    }

    private function send_request( string $method, array $params ) {
        // Nostr libs loaded via sk-core/lib/autoload.php (centralized).
        if ( ! class_exists( '\swentel\nostr\Event\Event' ) ) {
            return new \WP_Error( 'nwc_no_library', 'Nostr PHP Library nicht gefunden. sk-core/lib/ fehlt.' );
        }

        try {
            $payload = wp_json_encode( [
                'method' => $method,
                'params' => (object) $params,
            ] );

            $encrypted = Nip04::encrypt( $payload, $this->secret, $this->wallet_pubkey );

            $event = new Event();
            $event->setKind( 23194 );
            $event->setContent( $encrypted );
            $event->addTag( [ 'p', $this->wallet_pubkey ] );

            $signer = new Sign();
            $signer->signEvent( $event, $this->secret );

            $relay = new Relay( $this->relay_url );
            $relay->setTimeout( 10 );
            $relay->setMessage( new EventMessage( $event ) );
            $relay->send();

            $response = $this->wait_for_response( $event->getId() );

            return $response;
        } catch ( \Exception $e ) {
            return new \WP_Error( 'nwc_exception', 'NWC-Fehler: ' . $e->getMessage() );
        }
    }

    private function wait_for_response( string $request_event_id ) {
        try {
            $client = new \WebSocket\Client( $this->relay_url );

            $sub_id = bin2hex( random_bytes( 8 ) );
            $filter = [
                'kinds'   => [ 23195 ],
                '#e'      => [ $request_event_id ],
                'authors' => [ $this->wallet_pubkey ],
                'limit'   => 1,
            ];

            $client->text( wp_json_encode( [ 'REQ', $sub_id, $filter ] ) );

            $start   = time();
            $timeout = 10;

            while ( time() - $start < $timeout ) {
                $msg = $client->receive();
                if ( $msg === null ) {
                    continue;
                }

                $data = json_decode( $msg->getContent(), true );
                if ( ! is_array( $data ) || $data[0] !== 'EVENT' ) {
                    continue;
                }

                $response_event = $data[2] ?? null;
                if ( ! $response_event || empty( $response_event['content'] ) ) {
                    continue;
                }

                $decrypted = Nip04::decrypt(
                    $response_event['content'],
                    $this->secret,
                    $this->wallet_pubkey
                );

                $client->text( wp_json_encode( [ 'CLOSE', $sub_id ] ) );
                $client->disconnect();

                return json_decode( $decrypted, true );
            }

            $client->disconnect();
            return new \WP_Error( 'nwc_timeout', 'Keine Antwort vom Wallet-Service (Timeout).' );
        } catch ( \Exception $e ) {
            return new \WP_Error( 'nwc_ws_error', 'WebSocket-Fehler: ' . $e->getMessage() );
        }
    }

    public static function encrypt_connection_string( string $connection_string ): string {
        $key = wp_salt( 'auth' );
        $iv  = random_bytes( 16 );
        $encrypted = openssl_encrypt( $connection_string, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
        return base64_encode( $iv . $encrypted );
    }

    public static function decrypt_connection_string( string $encrypted ): string {
        $key  = wp_salt( 'auth' );
        $data = base64_decode( $encrypted );
        $iv   = substr( $data, 0, 16 );
        $encrypted_data = substr( $data, 16 );
        return openssl_decrypt( $encrypted_data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
    }
}
