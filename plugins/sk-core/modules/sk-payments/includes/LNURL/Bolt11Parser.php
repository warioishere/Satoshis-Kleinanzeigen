<?php

namespace SK\Modules\Payments\LNURL;

defined( 'ABSPATH' ) || exit;

class Bolt11Parser {

    public static function get_payment_hash( string $bolt11 ) {
        $bolt11 = strtolower( trim( $bolt11 ) );

        if ( strpos( $bolt11, 'lightning:' ) === 0 ) {
            $bolt11 = substr( $bolt11, 10 );
        }

        $charset = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

        $sep = strrpos( $bolt11, '1' );
        if ( $sep === false ) {
            return new \WP_Error( 'bolt11_invalid', 'Ungültiges bolt11-Format.' );
        }

        $data_part = substr( $bolt11, $sep + 1 );

        if ( strlen( $data_part ) < 120 ) {
            return new \WP_Error( 'bolt11_too_short', 'bolt11 zu kurz.' );
        }

        $data_part = substr( $data_part, 0, -110 );

        $values = [];
        for ( $i = 0, $len = strlen( $data_part ); $i < $len; $i++ ) {
            $pos = strpos( $charset, $data_part[ $i ] );
            if ( $pos === false ) {
                return new \WP_Error( 'bolt11_invalid_char', "Ungültiges Bech32-Zeichen an Position {$i}" );
            }
            $values[] = $pos;
        }

        $offset = 7;

        while ( $offset + 3 <= count( $values ) ) {
            $tag = $values[ $offset ];
            $offset++;

            if ( $offset + 1 >= count( $values ) ) {
                break;
            }
            $data_length = ( $values[ $offset ] << 5 ) | $values[ $offset + 1 ];
            $offset += 2;

            if ( $offset + $data_length > count( $values ) ) {
                break;
            }

            if ( $tag === 1 && $data_length === 52 ) {
                $hash_5bit = array_slice( $values, $offset, $data_length );
                $hash_bytes = self::convert_bits( $hash_5bit, 5, 8, false );

                if ( $hash_bytes === null || count( $hash_bytes ) !== 32 ) {
                    return new \WP_Error( 'bolt11_hash_decode', 'Payment-Hash konnte nicht dekodiert werden.' );
                }

                $hex = '';
                foreach ( $hash_bytes as $byte ) {
                    $hex .= sprintf( '%02x', $byte );
                }

                return $hex;
            }

            $offset += $data_length;
        }

        return new \WP_Error( 'bolt11_no_hash', 'Kein Payment-Hash in bolt11 gefunden.' );
    }

    private static function convert_bits( array $data, int $from_bits, int $to_bits, bool $pad = true ): ?array {
        $acc    = 0;
        $bits   = 0;
        $result = [];
        $max    = ( 1 << $to_bits ) - 1;

        foreach ( $data as $value ) {
            if ( $value < 0 || $value >> $from_bits ) {
                return null;
            }
            $acc  = ( $acc << $from_bits ) | $value;
            $bits += $from_bits;
            while ( $bits >= $to_bits ) {
                $bits    -= $to_bits;
                $result[] = ( $acc >> $bits ) & $max;
            }
        }

        if ( $pad ) {
            if ( $bits > 0 ) {
                $result[] = ( $acc << ( $to_bits - $bits ) ) & $max;
            }
        } elseif ( $bits >= $from_bits || ( ( $acc << ( $to_bits - $bits ) ) & $max ) ) {
            return null;
        }

        return $result;
    }
}
