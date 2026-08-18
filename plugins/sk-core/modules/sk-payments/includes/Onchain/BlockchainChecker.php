<?php

namespace SK\Modules\Payments\Onchain;

defined( 'ABSPATH' ) || exit;

/**
 * Check Bitcoin blockchain for incoming payments.
 *
 * Priority:
 *   1. Fulcrum (Electrum Protocol via SSL) — eigener Server, kein Rate-Limit
 *   2. mempool.space REST API — öffentlicher Fallback
 */
class BlockchainChecker {

    const FULCRUM_HOST = 'private-fulcrum.yourdevice.ch';
    const FULCRUM_PORT = 50002;
    const FULCRUM_TIMEOUT = 8;

    /**
     * SHA256 fingerprint of the Fulcrum server's DER certificate.
     *
     * Fulcrum serves a self-signed certificate, so CA validation cannot work —
     * we pin the certificate instead. Without a pin, anyone able to MITM the
     * connection could fake a payment confirmation.
     *
     * Read the current value with:
     *   openssl s_client -connect private-fulcrum.yourdevice.ch:50002 \
     *     </dev/null 2>/dev/null | openssl x509 -outform der | sha256sum
     *
     * Override in wp-config.php via SK_FULCRUM_CERT_SHA256 after a cert change.
     * A mismatch makes the Fulcrum connection fail and falls back to
     * mempool.space, so payments keep working — just slower.
     */
    const FULCRUM_CERT_SHA256 = '1a6004674fe3330ba67cf157896ef87bad1229ba457d7992ea29c2c9a53d0597';

    /**
     * Check if an address received at least $expected_sats.
     */
    /**
     * @param string[] $exclude_txids Transactions already booked against another
     *                                payment to this address. Without this one
     *                                transaction could settle several payments,
     *                                which is exactly what happens when a vendor
     *                                uses one static address for everything.
     */
    public static function check_payment( string $address, int $expected_sats, string $since = '', array $exclude_txids = [] ): array {
        // 1. Fulcrum (Electrum Protocol).
        $result = self::check_fulcrum( $address, $expected_sats, $since, $exclude_txids );

        // 2. Fallback: mempool.space.
        if ( is_wp_error( $result ) ) {
            $result = self::check_mempool( $address, $expected_sats, $since, $exclude_txids );
        }

        if ( is_wp_error( $result ) ) {
            return [
                'confirmed'     => false,
                'txid'          => null,
                'amount_sats'   => 0,
                'confirmations' => 0,
                'error'         => $result->get_error_message(),
            ];
        }

        return $result;
    }

    /**
     * Query Fulcrum via Electrum Protocol (JSON-RPC over SSL/TCP).
     *
     * Uses blockchain.scripthash.get_history + blockchain.transaction.get
     * to find incoming payments to the address.
     */
    private static function check_fulcrum( string $address, int $expected_sats, string $since, array $exclude_txids = [] ) {
        $scripthash = self::address_to_scripthash( $address );
        if ( is_wp_error( $scripthash ) ) {
            return $scripthash;
        }

        // Open SSL connection to Fulcrum, pinned to its self-signed cert.
        $fingerprint = defined( 'SK_FULCRUM_CERT_SHA256' )
            ? strtolower( (string) SK_FULCRUM_CERT_SHA256 )
            : self::FULCRUM_CERT_SHA256;

        $ctx = stream_context_create( [
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
                'peer_fingerprint' => [ 'sha256' => $fingerprint ],
            ],
        ] );

        $socket = @stream_socket_client(
            'ssl://' . self::FULCRUM_HOST . ':' . self::FULCRUM_PORT,
            $errno,
            $errstr,
            self::FULCRUM_TIMEOUT,
            STREAM_CLIENT_CONNECT,
            $ctx
        );

        if ( ! $socket ) {
            return new \WP_Error( 'fulcrum_connect', "Fulcrum-Verbindung fehlgeschlagen: {$errstr}" );
        }

        stream_set_timeout( $socket, self::FULCRUM_TIMEOUT );

        // 1. Get transaction history for this scripthash.
        $history = self::electrum_call( $socket, 'blockchain.scripthash.get_history', [ $scripthash ] );
        if ( is_wp_error( $history ) ) {
            fclose( $socket );
            return $history;
        }

        if ( empty( $history ) ) {
            fclose( $socket );
            return [
                'confirmed'     => false,
                'txid'          => null,
                'amount_sats'   => 0,
                'confirmations' => 0,
            ];
        }

        // Get current block height for confirmation count.
        $tip = self::electrum_call( $socket, 'blockchain.headers.subscribe', [] );
        $current_height = 0;
        if ( ! is_wp_error( $tip ) && isset( $tip['height'] ) ) {
            $current_height = (int) $tip['height'];
        }

        // 2. Check each transaction (newest first).
        $history = array_reverse( $history );

        foreach ( $history as $entry ) {
            $txid   = $entry['tx_hash'] ?? '';
            $height = (int) ( $entry['height'] ?? 0 );

            if ( empty( $txid ) || in_array( $txid, $exclude_txids, true ) ) {
                continue;
            }

            // Get full transaction.
            $tx_raw = self::electrum_call( $socket, 'blockchain.transaction.get', [ $txid, true ] );
            if ( is_wp_error( $tx_raw ) ) {
                continue;
            }

            // Check time filter.
            $since_ts = self::to_timestamp( $since );
            if ( $since_ts ) {
                $tx_time = $tx_raw['time'] ?? $tx_raw['blocktime'] ?? 0;
                if ( $tx_time && $tx_time < $since_ts ) {
                    continue;
                }
            }

            // Sum outputs to our address.
            $received = 0;
            foreach ( $tx_raw['vout'] ?? [] as $vout ) {
                $addresses = $vout['scriptPubKey']['addresses'] ?? [];
                // Newer format uses 'address' (singular).
                if ( empty( $addresses ) && ! empty( $vout['scriptPubKey']['address'] ) ) {
                    $addresses = [ $vout['scriptPubKey']['address'] ];
                }
                if ( in_array( $address, $addresses, true ) ) {
                    // Value is in BTC, convert to sats.
                    $received += (int) round( ( $vout['value'] ?? 0 ) * 100000000 );
                }
            }

            if ( $received >= $expected_sats ) {
                $confirmed     = $height > 0;
                $confirmations = ( $confirmed && $current_height > 0 ) ? $current_height - $height + 1 : 0;

                fclose( $socket );
                return [
                    'confirmed'     => $confirmed,
                    'txid'          => $txid,
                    'amount_sats'   => $received,
                    'confirmations' => $confirmations,
                ];
            }
        }

        fclose( $socket );
        return [
            'confirmed'     => false,
            'txid'          => null,
            'amount_sats'   => 0,
            'confirmations' => 0,
        ];
    }

    /**
     * Send a JSON-RPC call over an Electrum Protocol socket.
     */
    private static function electrum_call( $socket, string $method, array $params ) {
        static $id = 0;
        $id++;

        $request = wp_json_encode( [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'method'  => $method,
            'params'  => $params,
        ] ) . "\n";

        $written = @fwrite( $socket, $request );
        if ( $written === false ) {
            return new \WP_Error( 'fulcrum_write', 'Fulcrum: Schreibfehler.' );
        }

        $response_line = @fgets( $socket, 1048576 ); // 1MB max line.
        if ( $response_line === false ) {
            $info = stream_get_meta_data( $socket );
            if ( ! empty( $info['timed_out'] ) ) {
                return new \WP_Error( 'fulcrum_timeout', 'Fulcrum: Timeout.' );
            }
            return new \WP_Error( 'fulcrum_read', 'Fulcrum: Lesefehler.' );
        }

        $response = json_decode( $response_line, true );
        if ( ! is_array( $response ) ) {
            return new \WP_Error( 'fulcrum_parse', 'Fulcrum: Ungültige Response.' );
        }

        if ( isset( $response['error'] ) ) {
            $msg = $response['error']['message'] ?? 'Unbekannter Fehler';
            return new \WP_Error( 'fulcrum_error', "Fulcrum: {$msg}" );
        }

        return $response['result'] ?? null;
    }

    /**
     * Convert a Bitcoin address to an Electrum scripthash.
     *
     * scripthash = SHA256(scriptPubKey) reversed (little-endian hex).
     */
    private static function address_to_scripthash( string $address ) {
        $script = self::address_to_scriptpubkey( $address );
        if ( is_wp_error( $script ) ) {
            return $script;
        }

        $hash = hash( 'sha256', hex2bin( $script ) );

        // Reverse byte order (little-endian).
        return implode( '', array_reverse( str_split( $hash, 2 ) ) );
    }

    /**
     * Convert a Bitcoin address to its scriptPubKey (hex).
     */
    private static function address_to_scriptpubkey( string $address ) {
        // bech32 / bech32m (bc1q... / bc1p...).
        if ( strpos( $address, 'bc1' ) === 0 ) {
            $decoded = self::bech32_decode_address( $address );
            if ( is_wp_error( $decoded ) ) {
                return $decoded;
            }
            $version = $decoded['version'];
            $program = $decoded['program'];
            $prog_hex = bin2hex( $program );
            $prog_len = strlen( $program );

            // OP_version OP_PUSH_len program
            $version_hex = sprintf( '%02x', $version === 0 ? 0x00 : ( 0x50 + $version ) );
            $len_hex     = sprintf( '%02x', $prog_len );

            return $version_hex . $len_hex . $prog_hex;
        }

        // Base58Check (1... or 3...).
        $decoded = self::base58check_decode( $address );
        if ( is_wp_error( $decoded ) ) {
            return $decoded;
        }

        $version_byte = ord( $decoded[0] );
        $hash         = bin2hex( substr( $decoded, 1 ) );

        if ( $version_byte === 0x00 ) {
            // P2PKH: OP_DUP OP_HASH160 PUSH20 <hash> OP_EQUALVERIFY OP_CHECKSIG
            return '76a914' . $hash . '88ac';
        } elseif ( $version_byte === 0x05 ) {
            // P2SH: OP_HASH160 PUSH20 <hash> OP_EQUAL
            return 'a914' . $hash . '87';
        }

        return new \WP_Error( 'unknown_address', 'Unbekanntes Adressformat.' );
    }

    /**
     * Decode a bech32/bech32m address to version + witness program.
     */
    private static function bech32_decode_address( string $address ) {
        $address = strtolower( $address );
        $charset = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

        $sep = strrpos( $address, '1' );
        if ( $sep === false || $sep < 1 ) {
            return new \WP_Error( 'bech32_invalid', 'Ungültiges bech32-Format.' );
        }

        $data_part = substr( $address, $sep + 1 );
        // Remove checksum (last 6 chars).
        $data_no_checksum = substr( $data_part, 0, -6 );

        $values = [];
        for ( $i = 0, $len = strlen( $data_no_checksum ); $i < $len; $i++ ) {
            $pos = strpos( $charset, $data_no_checksum[ $i ] );
            if ( $pos === false ) {
                return new \WP_Error( 'bech32_char', 'Ungültiges bech32-Zeichen.' );
            }
            $values[] = $pos;
        }

        if ( empty( $values ) ) {
            return new \WP_Error( 'bech32_empty', 'Leere bech32-Daten.' );
        }

        $version = $values[0];
        $program_5bit = array_slice( $values, 1 );

        $program_bytes = self::convert_bits( $program_5bit, 5, 8, false );
        if ( $program_bytes === null ) {
            return new \WP_Error( 'bech32_convert', 'bech32 Bit-Konvertierung fehlgeschlagen.' );
        }

        $program = '';
        foreach ( $program_bytes as $byte ) {
            $program .= chr( $byte );
        }

        return [
            'version' => $version,
            'program' => $program,
        ];
    }

    private static function convert_bits( array $data, int $from, int $to, bool $pad ): ?array {
        $acc    = 0;
        $bits   = 0;
        $result = [];
        $max    = ( 1 << $to ) - 1;

        foreach ( $data as $value ) {
            if ( $value < 0 || $value >> $from ) {
                return null;
            }
            $acc  = ( $acc << $from ) | $value;
            $bits += $from;
            while ( $bits >= $to ) {
                $bits    -= $to;
                $result[] = ( $acc >> $bits ) & $max;
            }
        }

        if ( $pad && $bits > 0 ) {
            $result[] = ( $acc << ( $to - $bits ) ) & $max;
        } elseif ( ! $pad && ( $bits >= $from || ( ( $acc << ( $to - $bits ) ) & $max ) ) ) {
            return null;
        }

        return $result;
    }

    private static function base58check_decode( string $input ) {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $num      = gmp_init( 0 );

        for ( $i = 0; $i < strlen( $input ); $i++ ) {
            $pos = strpos( $alphabet, $input[ $i ] );
            if ( $pos === false ) {
                return new \WP_Error( 'base58_invalid', 'Ungültiges Base58-Zeichen.' );
            }
            $num = gmp_add( gmp_mul( $num, gmp_init( 58 ) ), gmp_init( $pos ) );
        }

        $hex = gmp_strval( $num, 16 );
        if ( strlen( $hex ) % 2 !== 0 ) {
            $hex = '0' . $hex;
        }

        $data = hex2bin( $hex );

        for ( $i = 0; $i < strlen( $input ) && $input[ $i ] === '1'; $i++ ) {
            $data = "\x00" . $data;
        }

        $payload  = substr( $data, 0, -4 );
        $checksum = substr( $data, -4 );
        $expected = substr( hash( 'sha256', hash( 'sha256', $payload, true ), true ), 0, 4 );

        if ( $checksum !== $expected ) {
            return new \WP_Error( 'base58_checksum', 'Base58Check Prüfsumme ungültig.' );
        }

        return $payload;
    }

    /**
     * Payment timestamps are stored in site time while WordPress runs PHP in
     * UTC, so strtotime() on them was off by the site's offset.
     */
    private static function to_timestamp( string $site_local ): int {
        if ( $site_local === '' ) {
            return 0;
        }

        return (int) strtotime( get_gmt_from_date( $site_local ) . ' UTC' );
    }

    /**
     * Fallback: mempool.space REST API.
     */
    private static function check_mempool( string $address, int $expected_sats, string $since, array $exclude_txids = [] ) {
        $response = wp_remote_get( 'https://mempool.space/api/address/' . rawurlencode( $address ) . '/txs', [
            'timeout' => 8,
            'headers' => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return new \WP_Error( 'mempool_http', "mempool.space HTTP {$code}" );
        }

        $txs = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $txs ) ) {
            return new \WP_Error( 'mempool_parse', 'Ungültige Response von mempool.space' );
        }

        $since_ts = self::to_timestamp( $since );

        foreach ( $txs as $tx ) {
            if ( ! empty( $tx['txid'] ) && in_array( $tx['txid'], $exclude_txids, true ) ) {
                continue;
            }

            $tx_time = $tx['status']['block_time'] ?? ( ! empty( $tx['status']['confirmed'] ) ? time() : 0 );
            if ( $since_ts && $tx_time && $tx_time < $since_ts ) {
                continue;
            }

            $received = 0;
            foreach ( $tx['vout'] ?? [] as $vout ) {
                if ( ( $vout['scriptpubkey_address'] ?? '' ) === $address ) {
                    $received += (int) $vout['value'];
                }
            }

            if ( $received >= $expected_sats ) {
                $confirmed    = ! empty( $tx['status']['confirmed'] );
                $block_height = $tx['status']['block_height'] ?? 0;
                $confirmations = 0;

                if ( $confirmed && $block_height > 0 ) {
                    $tip = self::get_mempool_tip();
                    if ( $tip > 0 ) {
                        $confirmations = $tip - $block_height + 1;
                    }
                }

                return [
                    'confirmed'     => $confirmed,
                    'txid'          => $tx['txid'] ?? null,
                    'amount_sats'   => $received,
                    'confirmations' => $confirmations,
                ];
            }
        }

        return [
            'confirmed'     => false,
            'txid'          => null,
            'amount_sats'   => 0,
            'confirmations' => 0,
        ];
    }

    private static function get_mempool_tip(): int {
        $cached = get_transient( 'sk_btc_block_tip' );
        if ( $cached !== false ) {
            return (int) $cached;
        }

        $response = wp_remote_get( 'https://mempool.space/api/blocks/tip/height', [ 'timeout' => 5 ] );
        if ( is_wp_error( $response ) ) {
            return 0;
        }

        $tip = (int) wp_remote_retrieve_body( $response );
        if ( $tip > 0 ) {
            set_transient( 'sk_btc_block_tip', $tip, 60 );
        }

        return $tip;
    }
}
