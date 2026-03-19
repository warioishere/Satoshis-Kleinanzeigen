<?php

namespace SK\Modules\Payments\Onchain;

defined( 'ABSPATH' ) || exit;

/**
 * BIP32 public-key-only child derivation for xpub/ypub/zpub.
 *
 * Derives m/0/{index} (receive) addresses without needing the private key.
 * Supports:
 *   xpub → P2PKH (1...) addresses
 *   ypub → P2SH-P2WPKH (3...) addresses
 *   zpub → native SegWit bech32 (bc1q...) addresses
 */
class XpubDerivation {

    /**
     * Derive a Bitcoin address from an xpub/ypub/zpub at m/0/{index}.
     *
     * @param string $xpub   Extended public key (xpub6.../ypub6.../zpub6...).
     * @param int    $index  Child index (0, 1, 2, ...).
     * @return string|\WP_Error  Bitcoin address.
     */
    public static function derive_address( string $xpub, int $index ) {
        $decoded = self::decode_xpub( $xpub );
        if ( is_wp_error( $decoded ) ) {
            return $decoded;
        }

        $prefix = substr( $xpub, 0, 4 );

        // Derive m/0 (receive chain).
        $child0 = self::derive_child( $decoded['key'], $decoded['chain_code'], 0 );
        if ( is_wp_error( $child0 ) ) {
            return $child0;
        }

        // Derive m/0/{index}.
        $child = self::derive_child( $child0['key'], $child0['chain_code'], $index );
        if ( is_wp_error( $child ) ) {
            return $child;
        }

        $pubkey = $child['key'];

        if ( $prefix === 'zpub' ) {
            return self::pubkey_to_bech32( $pubkey );
        } elseif ( $prefix === 'ypub' ) {
            return self::pubkey_to_p2sh_p2wpkh( $pubkey );
        } else {
            return self::pubkey_to_p2pkh( $pubkey );
        }
    }

    /**
     * Decode a base58check-encoded extended public key.
     */
    private static function decode_xpub( string $xpub ) {
        $raw = self::base58check_decode( $xpub );
        if ( is_wp_error( $raw ) ) {
            return $raw;
        }

        if ( strlen( $raw ) !== 78 ) {
            return new \WP_Error( 'xpub_invalid', 'xpub hat ungültige Länge.' );
        }

        // Bytes: 4 version + 1 depth + 4 fingerprint + 4 child_num + 32 chain_code + 33 key
        $chain_code = substr( $raw, 13, 32 );
        $key        = substr( $raw, 45, 33 );

        // Key must start with 0x02 or 0x03 (compressed public key).
        $first_byte = ord( $key[0] );
        if ( $first_byte !== 0x02 && $first_byte !== 0x03 ) {
            return new \WP_Error( 'xpub_invalid_key', 'xpub enthält keinen gültigen öffentlichen Schlüssel.' );
        }

        return [
            'key'        => $key,
            'chain_code' => $chain_code,
        ];
    }

    /**
     * BIP32 public child derivation (non-hardened only).
     */
    private static function derive_child( string $parent_key, string $parent_chain, int $index ) {
        if ( $index >= 0x80000000 ) {
            return new \WP_Error( 'hardened', 'Hardened Derivation nicht möglich mit xpub.' );
        }

        $data = $parent_key . pack( 'N', $index );
        $hmac = hash_hmac( 'sha512', $data, $parent_chain, true );

        $il = substr( $hmac, 0, 32 );
        $ir = substr( $hmac, 32, 32 );

        // Point addition: child_key = point(il) + parent_key on secp256k1.
        $child_key = self::point_add_scalar( $parent_key, $il );
        if ( is_wp_error( $child_key ) ) {
            return $child_key;
        }

        return [
            'key'        => $child_key,
            'chain_code' => $ir,
        ];
    }

    /**
     * Add a scalar (32 bytes) to a compressed public key point on secp256k1.
     * Returns a new compressed public key.
     */
    private static function point_add_scalar( string $compressed_pubkey, string $scalar ): string|\WP_Error {
        if ( ! extension_loaded( 'gmp' ) ) {
            return new \WP_Error( 'gmp_missing', 'PHP GMP Extension fehlt — wird für xpub-Derivation benötigt.' );
        }

        // secp256k1 parameters.
        $p  = gmp_init( 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F', 16 );
        $n  = gmp_init( 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141', 16 );
        $gx = gmp_init( '79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798', 16 );
        $gy = gmp_init( '483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8', 16 );

        // Decompress parent public key.
        $prefix = ord( $compressed_pubkey[0] );
        $x = gmp_init( bin2hex( substr( $compressed_pubkey, 1, 32 ) ), 16 );
        $y_sq = gmp_mod( gmp_add( gmp_powm( $x, gmp_init( 3 ), $p ), gmp_init( 7 ) ), $p );
        $y = gmp_powm( $y_sq, gmp_div_q( gmp_add( $p, gmp_init( 1 ) ), gmp_init( 4 ) ), $p );

        if ( ( gmp_intval( gmp_mod( $y, gmp_init( 2 ) ) ) === 0 ) !== ( $prefix === 0x02 ) ) {
            $y = gmp_sub( $p, $y );
        }

        // Compute scalar * G.
        $scalar_int = gmp_init( bin2hex( $scalar ), 16 );
        if ( gmp_cmp( $scalar_int, $n ) >= 0 ) {
            return new \WP_Error( 'scalar_overflow', 'Scalar overflow bei Derivation.' );
        }

        $sg = self::ec_multiply( $gx, $gy, $scalar_int, $p, $n );

        // Add: parent_point + scalar*G.
        $result = self::ec_add( $x, $y, $sg[0], $sg[1], $p );

        // Compress result.
        $rx_hex = str_pad( gmp_strval( $result[0], 16 ), 64, '0', STR_PAD_LEFT );
        $prefix_byte = ( gmp_intval( gmp_mod( $result[1], gmp_init( 2 ) ) ) === 0 ) ? '02' : '03';

        return hex2bin( $prefix_byte . $rx_hex );
    }

    /**
     * Elliptic curve point multiplication (double-and-add).
     */
    private static function ec_multiply( \GMP $gx, \GMP $gy, \GMP $k, \GMP $p, \GMP $n ): array {
        $rx = null;
        $ry = null;
        $qx = $gx;
        $qy = $gy;

        while ( gmp_cmp( $k, gmp_init( 0 ) ) > 0 ) {
            if ( gmp_intval( gmp_mod( $k, gmp_init( 2 ) ) ) === 1 ) {
                if ( $rx === null ) {
                    $rx = $qx;
                    $ry = $qy;
                } else {
                    $r = self::ec_add( $rx, $ry, $qx, $qy, $p );
                    $rx = $r[0];
                    $ry = $r[1];
                }
            }
            $d = self::ec_double( $qx, $qy, $p );
            $qx = $d[0];
            $qy = $d[1];
            $k = gmp_div_q( $k, gmp_init( 2 ) );
        }

        return [ $rx, $ry ];
    }

    /**
     * Elliptic curve point addition.
     */
    private static function ec_add( \GMP $x1, \GMP $y1, \GMP $x2, \GMP $y2, \GMP $p ): array {
        if ( gmp_cmp( $x1, $x2 ) === 0 && gmp_cmp( $y1, $y2 ) === 0 ) {
            return self::ec_double( $x1, $y1, $p );
        }

        $dx = gmp_mod( gmp_sub( $x2, $x1 ), $p );
        if ( gmp_cmp( $dx, gmp_init( 0 ) ) < 0 ) {
            $dx = gmp_add( $dx, $p );
        }

        $dy = gmp_mod( gmp_sub( $y2, $y1 ), $p );
        $s  = gmp_mod( gmp_mul( $dy, gmp_invert( $dx, $p ) ), $p );

        $rx = gmp_mod( gmp_sub( gmp_sub( gmp_mul( $s, $s ), $x1 ), $x2 ), $p );
        if ( gmp_cmp( $rx, gmp_init( 0 ) ) < 0 ) {
            $rx = gmp_add( $rx, $p );
        }

        $ry = gmp_mod( gmp_sub( gmp_mul( $s, gmp_sub( $x1, $rx ) ), $y1 ), $p );
        if ( gmp_cmp( $ry, gmp_init( 0 ) ) < 0 ) {
            $ry = gmp_add( $ry, $p );
        }

        return [ $rx, $ry ];
    }

    /**
     * Elliptic curve point doubling.
     */
    private static function ec_double( \GMP $x, \GMP $y, \GMP $p ): array {
        $s = gmp_mod(
            gmp_mul(
                gmp_add( gmp_mul( gmp_init( 3 ), gmp_mul( $x, $x ) ), gmp_init( 0 ) ), // a=0 for secp256k1
                gmp_invert( gmp_mul( gmp_init( 2 ), $y ), $p )
            ),
            $p
        );

        $rx = gmp_mod( gmp_sub( gmp_mul( $s, $s ), gmp_mul( gmp_init( 2 ), $x ) ), $p );
        if ( gmp_cmp( $rx, gmp_init( 0 ) ) < 0 ) {
            $rx = gmp_add( $rx, $p );
        }

        $ry = gmp_mod( gmp_sub( gmp_mul( $s, gmp_sub( $x, $rx ) ), $y ), $p );
        if ( gmp_cmp( $ry, gmp_init( 0 ) ) < 0 ) {
            $ry = gmp_add( $ry, $p );
        }

        return [ $rx, $ry ];
    }

    /**
     * Compressed pubkey → native SegWit bech32 address (bc1q...).
     */
    private static function pubkey_to_bech32( string $pubkey ): string {
        $hash160 = self::hash160( $pubkey );
        return self::segwit_encode( 'bc', 0, $hash160 );
    }

    /**
     * Compressed pubkey → P2SH-P2WPKH address (3...).
     */
    private static function pubkey_to_p2sh_p2wpkh( string $pubkey ): string {
        $hash160    = self::hash160( $pubkey );
        $redeem     = "\x00\x14" . $hash160; // OP_0 + PUSH20 + hash160
        $script_hash = self::hash160( $redeem );
        return self::base58check_encode( "\x05" . $script_hash );
    }

    /**
     * Compressed pubkey → P2PKH address (1...).
     */
    private static function pubkey_to_p2pkh( string $pubkey ): string {
        $hash160 = self::hash160( $pubkey );
        return self::base58check_encode( "\x00" . $hash160 );
    }

    /**
     * RIPEMD160(SHA256(data)).
     */
    private static function hash160( string $data ): string {
        return hash( 'ripemd160', hash( 'sha256', $data, true ), true );
    }

    /**
     * Base58Check encode.
     */
    private static function base58check_encode( string $data ): string {
        $checksum = substr( hash( 'sha256', hash( 'sha256', $data, true ), true ), 0, 4 );
        $data    .= $checksum;

        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $num      = gmp_init( bin2hex( $data ), 16 );
        $result   = '';

        while ( gmp_cmp( $num, gmp_init( 0 ) ) > 0 ) {
            list( $num, $rem ) = gmp_div_qr( $num, gmp_init( 58 ) );
            $result = $alphabet[ gmp_intval( $rem ) ] . $result;
        }

        // Leading zero bytes.
        for ( $i = 0; $i < strlen( $data ) && $data[ $i ] === "\x00"; $i++ ) {
            $result = '1' . $result;
        }

        return $result;
    }

    /**
     * Base58Check decode.
     */
    private static function base58check_decode( string $input ) {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $num      = gmp_init( 0 );

        for ( $i = 0; $i < strlen( $input ); $i++ ) {
            $pos = strpos( $alphabet, $input[ $i ] );
            if ( $pos === false ) {
                return new \WP_Error( 'base58_invalid', "Ungültiges Base58-Zeichen: {$input[$i]}" );
            }
            $num = gmp_add( gmp_mul( $num, gmp_init( 58 ) ), gmp_init( $pos ) );
        }

        $hex = gmp_strval( $num, 16 );
        if ( strlen( $hex ) % 2 !== 0 ) {
            $hex = '0' . $hex;
        }

        $data = hex2bin( $hex );

        // Leading 1s = leading zero bytes.
        for ( $i = 0; $i < strlen( $input ) && $input[ $i ] === '1'; $i++ ) {
            $data = "\x00" . $data;
        }

        // Verify checksum.
        $payload  = substr( $data, 0, -4 );
        $checksum = substr( $data, -4 );
        $expected = substr( hash( 'sha256', hash( 'sha256', $payload, true ), true ), 0, 4 );

        if ( $checksum !== $expected ) {
            return new \WP_Error( 'base58_checksum', 'Base58Check Prüfsumme ungültig.' );
        }

        return $payload;
    }

    /**
     * Bech32/SegWit encoding.
     */
    private static function segwit_encode( string $hrp, int $version, string $program ): string {
        $values = [ $version ];
        $data   = array_values( unpack( 'C*', $program ) );
        $conv   = self::convert_bits( $data, 8, 5, true );

        if ( $conv === null ) {
            return '';
        }

        $values = array_merge( $values, $conv );
        $checksum = self::bech32_checksum( $hrp, $values );
        $values   = array_merge( $values, $checksum );

        $charset = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
        $result  = $hrp . '1';
        foreach ( $values as $v ) {
            $result .= $charset[ $v ];
        }

        return $result;
    }

    private static function bech32_checksum( string $hrp, array $data ): array {
        $values = self::bech32_hrp_expand( $hrp );
        $values = array_merge( $values, $data, [ 0, 0, 0, 0, 0, 0 ] );
        $polymod = self::bech32_polymod( $values ) ^ 1;
        $checksum = [];
        for ( $i = 0; $i < 6; $i++ ) {
            $checksum[] = ( $polymod >> ( 5 * ( 5 - $i ) ) ) & 31;
        }
        return $checksum;
    }

    private static function bech32_hrp_expand( string $hrp ): array {
        $result = [];
        for ( $i = 0; $i < strlen( $hrp ); $i++ ) {
            $result[] = ord( $hrp[ $i ] ) >> 5;
        }
        $result[] = 0;
        for ( $i = 0; $i < strlen( $hrp ); $i++ ) {
            $result[] = ord( $hrp[ $i ] ) & 31;
        }
        return $result;
    }

    private static function bech32_polymod( array $values ): int {
        $gen = [ 0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3 ];
        $chk = 1;
        foreach ( $values as $v ) {
            $b = $chk >> 25;
            $chk = ( ( $chk & 0x1ffffff ) << 5 ) ^ $v;
            for ( $i = 0; $i < 5; $i++ ) {
                if ( ( $b >> $i ) & 1 ) {
                    $chk ^= $gen[ $i ];
                }
            }
        }
        return $chk;
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
}
