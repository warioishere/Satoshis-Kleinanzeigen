<?php

namespace SK_Lightning\LNURL;

defined( 'ABSPATH' ) || exit;

class Resolver {

    /**
     * Resolve a Lightning address or LNURL to its LNURL-pay metadata.
     *
     * @param string $address_or_lnurl  Lightning address (user@domain) or LNURL (lnurl1...).
     * @return array|WP_Error  { callback, minSendable, maxSendable, metadata, tag }
     */
    public static function resolve( string $address_or_lnurl ) {
        if ( \SK_Lightning\StoreSettings::is_valid_lightning_address( $address_or_lnurl ) ) {
            return self::resolve_lightning_address( $address_or_lnurl );
        }

        if ( \SK_Lightning\StoreSettings::is_valid_lnurl( $address_or_lnurl ) ) {
            return self::resolve_lnurl( $address_or_lnurl );
        }

        return new \WP_Error( 'invalid_address', 'Ungültige Lightning-Adresse oder LNURL.' );
    }

    /**
     * Resolve a Lightning address via .well-known/lnurlp.
     */
    private static function resolve_lightning_address( string $address ) {
        list( $user, $domain ) = explode( '@', $address, 2 );

        $url = "https://{$domain}/.well-known/lnurlp/{$user}";

        $response = wp_remote_get( $url, [
            'timeout' => 10,
            'headers' => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) ) {
            return new \WP_Error( 'resolve_failed', 'LNURL-Resolve fehlgeschlagen: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return new \WP_Error( 'resolve_http_error', "LNURL-Resolve HTTP {$code} für {$address}" );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body ) || ! empty( $body['status'] ) && $body['status'] === 'ERROR' ) {
            $reason = $body['reason'] ?? 'Unbekannter Fehler';
            return new \WP_Error( 'resolve_error', "LNURL-Fehler: {$reason}" );
        }

        if ( empty( $body['callback'] ) ) {
            return new \WP_Error( 'no_callback', 'Keine Callback-URL in LNURL-Response.' );
        }

        return $body;
    }

    /**
     * Resolve a LNURL by Bech32-decoding it to a URL, then fetching.
     */
    private static function resolve_lnurl( string $lnurl ) {
        $url = self::bech32_decode_lnurl( $lnurl );

        if ( is_wp_error( $url ) ) {
            return $url;
        }

        $response = wp_remote_get( $url, [
            'timeout' => 10,
            'headers' => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) ) {
            return new \WP_Error( 'resolve_failed', 'LNURL-Resolve fehlgeschlagen: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return new \WP_Error( 'resolve_http_error', "LNURL-Resolve HTTP {$code}" );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body ) || ( ! empty( $body['status'] ) && $body['status'] === 'ERROR' ) ) {
            $reason = $body['reason'] ?? 'Unbekannter Fehler';
            return new \WP_Error( 'resolve_error', "LNURL-Fehler: {$reason}" );
        }

        if ( empty( $body['callback'] ) ) {
            return new \WP_Error( 'no_callback', 'Keine Callback-URL in LNURL-Response.' );
        }

        return $body;
    }

    /**
     * Request an invoice from LNURL callback.
     *
     * @param string $callback_url  The LNURL callback URL.
     * @param int    $amount_msats  Amount in millisatoshis.
     * @return array|WP_Error  { pr (bolt11), routes, ... }
     */
    public static function request_invoice( string $callback_url, int $amount_msats ) {
        $separator = ( strpos( $callback_url, '?' ) !== false ) ? '&' : '?';
        $url = $callback_url . $separator . 'amount=' . $amount_msats;

        $response = wp_remote_get( $url, [
            'timeout' => 15,
            'headers' => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) ) {
            return new \WP_Error( 'invoice_failed', 'Invoice-Anforderung fehlgeschlagen: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return new \WP_Error( 'invoice_http_error', "Invoice HTTP {$code}" );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body ) || ( ! empty( $body['status'] ) && $body['status'] === 'ERROR' ) ) {
            $reason = $body['reason'] ?? 'Unbekannter Fehler';
            return new \WP_Error( 'invoice_error', "Invoice-Fehler: {$reason}" );
        }

        if ( empty( $body['pr'] ) ) {
            return new \WP_Error( 'no_invoice', 'Keine Invoice in Response.' );
        }

        return $body;
    }

    /**
     * Decode a Bech32-encoded LNURL to its original URL.
     */
    public static function bech32_decode_lnurl( string $lnurl ) {
        $lnurl = strtolower( trim( $lnurl ) );

        // Remove "lightning:" prefix if present.
        if ( strpos( $lnurl, 'lightning:' ) === 0 ) {
            $lnurl = substr( $lnurl, 10 );
        }

        // Bech32 character set.
        $charset = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

        // Find separator (last '1' in the string).
        $sep = strrpos( $lnurl, '1' );
        if ( $sep === false || $sep < 1 ) {
            return new \WP_Error( 'bech32_invalid', 'Ungültiges LNURL Bech32-Format.' );
        }

        $hrp  = substr( $lnurl, 0, $sep );
        $data = substr( $lnurl, $sep + 1 );

        // Remove checksum (last 6 chars).
        $data = substr( $data, 0, -6 );

        // Convert from Bech32 chars to 5-bit values.
        $values = [];
        for ( $i = 0, $len = strlen( $data ); $i < $len; $i++ ) {
            $pos = strpos( $charset, $data[ $i ] );
            if ( $pos === false ) {
                return new \WP_Error( 'bech32_invalid_char', "Ungültiges Bech32-Zeichen: {$data[$i]}" );
            }
            $values[] = $pos;
        }

        // Convert from 5-bit to 8-bit.
        $bytes = self::convert_bits( $values, 5, 8, false );
        if ( $bytes === null ) {
            return new \WP_Error( 'bech32_convert', 'Bech32 Bit-Konvertierung fehlgeschlagen.' );
        }

        $url = '';
        foreach ( $bytes as $byte ) {
            $url .= chr( $byte );
        }

        // Validate it's a URL.
        if ( filter_var( $url, FILTER_VALIDATE_URL ) === false ) {
            return new \WP_Error( 'bech32_not_url', 'Dekodierte LNURL ist keine gültige URL.' );
        }

        return $url;
    }

    /**
     * Convert between bit groups (e.g., 5-bit to 8-bit).
     */
    private static function convert_bits( array $data, int $from_bits, int $to_bits, bool $pad = true ): ?array {
        $acc     = 0;
        $bits    = 0;
        $result  = [];
        $max_val = ( 1 << $to_bits ) - 1;

        foreach ( $data as $value ) {
            if ( $value < 0 || $value >> $from_bits ) {
                return null;
            }
            $acc  = ( $acc << $from_bits ) | $value;
            $bits += $from_bits;
            while ( $bits >= $to_bits ) {
                $bits    -= $to_bits;
                $result[] = ( $acc >> $bits ) & $max_val;
            }
        }

        if ( $pad ) {
            if ( $bits > 0 ) {
                $result[] = ( $acc << ( $to_bits - $bits ) ) & $max_val;
            }
        } elseif ( $bits >= $from_bits || ( ( $acc << ( $to_bits - $bits ) ) & $max_val ) ) {
            return null;
        }

        return $result;
    }
}
