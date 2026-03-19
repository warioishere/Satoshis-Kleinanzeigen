<?php

namespace SK\Modules\Payments\LNURL;

defined( 'ABSPATH' ) || exit;

class ExchangeRate {

    private static $cache_ttl = 60;

    public static function get_btc_rate( string $currency = 'EUR' ) {
        $currency = strtoupper( $currency );
        if ( ! in_array( $currency, [ 'EUR', 'CHF' ], true ) ) {
            $currency = 'EUR';
        }

        $transient_key = 'sk_btc_' . strtolower( $currency ) . '_rate';
        $cached = get_transient( $transient_key );

        if ( $cached !== false ) {
            return (float) $cached;
        }

        $rate = self::fetch_from_mempool( $currency );

        if ( is_wp_error( $rate ) ) {
            $rate = self::fetch_from_yadio( $currency );
        }

        if ( is_wp_error( $rate ) ) {
            return $rate;
        }

        set_transient( $transient_key, $rate, self::$cache_ttl );

        return (float) $rate;
    }

    public static function get_btc_eur_rate() {
        return self::get_btc_rate( 'EUR' );
    }

    public static function fiat_to_sats( float $amount, string $currency = 'EUR' ) {
        $rate = self::get_btc_rate( $currency );

        if ( is_wp_error( $rate ) ) {
            return $rate;
        }

        if ( $rate <= 0 ) {
            return new \WP_Error( 'invalid_rate', 'Ungültiger Wechselkurs.' );
        }

        $btc  = $amount / $rate;
        $sats = (int) round( $btc * 100000000 );

        return $sats;
    }

    public static function eur_to_sats( float $eur ) {
        return self::fiat_to_sats( $eur, 'EUR' );
    }

    public static function sats_to_fiat( int $sats, string $currency = 'EUR' ) {
        $rate = self::get_btc_rate( $currency );

        if ( is_wp_error( $rate ) ) {
            return $rate;
        }

        $btc = $sats / 100000000;
        return round( $btc * $rate, 2 );
    }

    public static function sats_to_eur( int $sats ) {
        return self::sats_to_fiat( $sats, 'EUR' );
    }

    private static function fetch_from_mempool( string $currency = 'EUR' ) {
        $response = wp_remote_get( 'https://mempool.space/api/v1/prices', [
            'timeout' => 5,
            'headers' => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $body[ $currency ] ) && $body[ $currency ] > 0 ) {
            return (float) $body[ $currency ];
        }

        return new \WP_Error( 'mempool_no_rate', "Kein {$currency}-Kurs von mempool.space." );
    }

    private static function fetch_from_yadio( string $currency = 'EUR' ) {
        $response = wp_remote_get( 'https://api.yadio.io/exs/' . $currency, [
            'timeout' => 5,
            'headers' => [ 'Accept' => 'application/json' ],
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $body[ $currency ] ) && $body[ $currency ] > 0 ) {
            return (float) $body[ $currency ];
        }

        if ( ! empty( $body['BTC'][ $currency ] ) && $body['BTC'][ $currency ] > 0 ) {
            return (float) $body['BTC'][ $currency ];
        }

        return new \WP_Error( 'yadio_no_rate', "Kein {$currency}-Kurs von Yadio." );
    }
}
