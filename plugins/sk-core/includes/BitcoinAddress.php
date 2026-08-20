<?php

namespace SK\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Mainnet Bitcoin address validation.
 *
 * Two hand-rolled regexes had grown for this — one in the BTC login, one for
 * the payout address in sk-payments. Both only described the shape, so
 * "1AAAAAAAAAAAAAAAAAAAAAAAAAA" and "bc1q" plus 38 arbitrary characters passed.
 * This verifies the checksum instead: Base58Check for 1.../3..., BIP-173
 * bech32 for witness v0 and BIP-350 bech32m for v1+ (Taproot).
 *
 * The bundled bitwasp/bech32 is not usable here — its verifyChecksum() only
 * accepts the bech32 constant, so every bc1p address would be rejected.
 */
final class BitcoinAddress {

    private const B58_ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    private const BECH32_ALPHABET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

    /** Checksum constant for bech32 (BIP-173, witness v0). */
    private const BECH32_CONST = 1;

    /** Checksum constant for bech32m (BIP-350, witness v1+). */
    private const BECH32M_CONST = 0x2bc830a3;

    /** Mainnet version bytes: P2PKH (1...) and P2SH (3...). */
    private const B58_VERSIONS = [ 0x00, 0x05 ];

    /**
     * Is this a valid mainnet address?
     */
    public static function is_valid( string $address ): bool {
        $address = trim( $address );

        if ( '' === $address ) {
            return false;
        }

        if ( 0 === stripos( $address, 'bc1' ) ) {
            return self::is_valid_bech32( $address );
        }

        if ( '1' === $address[0] || '3' === $address[0] ) {
            return self::is_valid_base58check( $address );
        }

        return false;
    }

    /**
     * Human-readable address type, for display only. Says nothing about validity.
     */
    public static function type( string $address ): string {
        if ( 0 === strpos( $address, 'bc1q' ) ) {
            return 'SegWit (bech32)';
        }
        if ( 0 === strpos( $address, 'bc1p' ) ) {
            return 'Taproot (bech32m)';
        }
        if ( 0 === strpos( $address, '3' ) ) {
            return 'P2SH';
        }
        if ( 0 === strpos( $address, '1' ) ) {
            return 'Legacy (P2PKH)';
        }

        return 'Unbekannt';
    }

    /* ---- Base58Check ---- */

    private static function is_valid_base58check( string $address ): bool {
        $raw = self::base58_decode( $address );

        // 1 version byte + 20 byte hash + 4 byte checksum.
        if ( null === $raw || 25 !== strlen( $raw ) ) {
            return false;
        }

        $payload  = substr( $raw, 0, 21 );
        $checksum = substr( $raw, 21 );

        if ( substr( hash( 'sha256', hash( 'sha256', $payload, true ), true ), 0, 4 ) !== $checksum ) {
            return false;
        }

        return in_array( ord( $payload[0] ), self::B58_VERSIONS, true );
    }

    /**
     * @return string|null Raw bytes, or null when a character is outside the alphabet.
     */
    private static function base58_decode( string $input ): ?string {
        $bytes = [];

        for ( $i = 0, $len = strlen( $input ); $i < $len; $i++ ) {
            $carry = strpos( self::B58_ALPHABET, $input[ $i ] );
            if ( false === $carry ) {
                return null;
            }

            for ( $j = count( $bytes ) - 1; $j >= 0; $j-- ) {
                $carry        += $bytes[ $j ] * 58;
                $bytes[ $j ]   = $carry & 0xff;
                $carry       >>= 8;
            }

            while ( $carry > 0 ) {
                array_unshift( $bytes, $carry & 0xff );
                $carry >>= 8;
            }
        }

        // Every leading '1' stands for one leading zero byte.
        for ( $i = 0, $len = strlen( $input ); $i < $len && '1' === $input[ $i ]; $i++ ) {
            array_unshift( $bytes, 0 );
        }

        return implode( '', array_map( 'chr', $bytes ) );
    }

    /* ---- bech32 / bech32m ---- */

    private static function is_valid_bech32( string $address ): bool {
        // BIP-173 forbids mixed case.
        if ( $address !== strtolower( $address ) && $address !== strtoupper( $address ) ) {
            return false;
        }

        $address = strtolower( $address );
        $len     = strlen( $address );

        if ( $len < 14 || $len > 90 ) {
            return false;
        }

        $sep = strrpos( $address, '1' );
        if ( false === $sep || 'bc' !== substr( $address, 0, $sep ) ) {
            return false;
        }

        $data_part = substr( $address, $sep + 1 );
        if ( strlen( $data_part ) < 7 ) {
            return false;
        }

        $data = [];
        for ( $i = 0, $dlen = strlen( $data_part ); $i < $dlen; $i++ ) {
            $pos = strpos( self::BECH32_ALPHABET, $data_part[ $i ] );
            if ( false === $pos ) {
                return false;
            }
            $data[] = $pos;
        }

        $version = $data[0];
        if ( $version > 16 ) {
            return false;
        }

        // Witness v0 is bech32, everything above it is bech32m.
        $expected = 0 === $version ? self::BECH32_CONST : self::BECH32M_CONST;
        if ( self::polymod( array_merge( self::hrp_expand( 'bc' ), $data ) ) !== $expected ) {
            return false;
        }

        $program = self::convert_bits( array_slice( $data, 1, count( $data ) - 7 ), 5, 8 );
        if ( null === $program ) {
            return false;
        }

        $size = count( $program );

        if ( 0 === $version ) {
            return 20 === $size || 32 === $size;
        }

        return $size >= 2 && $size <= 40;
    }

    /**
     * @param int[] $values
     */
    private static function polymod( array $values ): int {
        $generator = [ 0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3 ];
        $chk       = 1;

        foreach ( $values as $value ) {
            $top = $chk >> 25;
            $chk = ( ( $chk & 0x1ffffff ) << 5 ) ^ $value;

            for ( $i = 0; $i < 5; $i++ ) {
                if ( ( $top >> $i ) & 1 ) {
                    $chk ^= $generator[ $i ];
                }
            }
        }

        return $chk;
    }

    /**
     * @return int[]
     */
    private static function hrp_expand( string $hrp ): array {
        $high = [];
        $low  = [];

        for ( $i = 0, $len = strlen( $hrp ); $i < $len; $i++ ) {
            $high[] = ord( $hrp[ $i ] ) >> 5;
            $low[]  = ord( $hrp[ $i ] ) & 31;
        }

        return array_merge( $high, [ 0 ], $low );
    }

    /**
     * Regroup bit widths without padding, as the witness program requires.
     *
     * @param int[] $data
     * @return int[]|null Null when the leftover bits make the input invalid.
     */
    private static function convert_bits( array $data, int $from, int $to ): ?array {
        $acc  = 0;
        $bits = 0;
        $out  = [];
        $max  = ( 1 << $to ) - 1;

        foreach ( $data as $value ) {
            if ( $value < 0 || ( $value >> $from ) !== 0 ) {
                return null;
            }

            $acc   = ( $acc << $from ) | $value;
            $bits += $from;

            while ( $bits >= $to ) {
                $bits -= $to;
                $out[] = ( $acc >> $bits ) & $max;
            }
        }

        // Leftover bits must be fewer than one output group and all zero.
        if ( $bits >= $from || ( ( $acc << ( $to - $bits ) ) & $max ) !== 0 ) {
            return null;
        }

        return $out;
    }
}
