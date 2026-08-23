<?php

namespace SK\Modules\Donations;

defined( 'ABSPATH' ) || exit;

/**
 * Liest bezahlte Spenden direkt vom BTCPay-Server.
 *
 * Notwendig, weil die Crowdfund-Apps auf dem BTCPay-Server laufen und
 * WooCommerce nie berühren. Ohne diese Klasse zeigte die Statistik nur den
 * kleineren Teil: Über Crowdfund kamen seit 2025 rund 4,1 Mio Sats herein,
 * über WooCommerce 215.000.
 *
 * Gezählt wird ausdrücklich nur ab einem Stichtag (Donations::count_since).
 * Die grossen Summen aus der Aufbauphase 2025 sind Vergangenheit und würden
 * den Deckungsbalken dauerhaft auf 100 Prozent stehen lassen.
 *
 * Zugangsdaten stammen vom WooCommerce-BTCPay-Plugin. Wird der Schlüssel dort
 * erneuert, greift diese Abfrage nicht mehr — deshalb ist jeder Fehler
 * unkritisch: Es wird 0 zurückgegeben und die WooCommerce-Zahlen stehen
 * weiterhin.
 */
final class BtcPay {

    /** Wie lange ein Abrufergebnis zwischengespeichert wird. */
    const CACHE_TTL = 900;

    /**
     * Beschreibungen, die keine Spende sind: die Kontaktdaten-Feewall
     * verkauft Kontaktzugriffe, das gehört nicht in den Spendentopf.
     *
     * Einstellbar statt fest verdrahtet — Crowdfunds kommen und gehen, und
     * eine Umbenennung der Feewall würde sie sonst still als Spende zählen.
     */
    const OPTION_EXCLUDE  = 'sk_donations_exclude';
    const DEFAULT_EXCLUDE = 'Kontaktzugriff, Pay-Wall, PayWall';

    /**
     * @return string[]
     */
    public static function exclude_patterns(): array {
        $raw   = (string) get_option( self::OPTION_EXCLUDE, self::DEFAULT_EXCLUDE );
        $parts = array_filter( array_map( 'trim', explode( ',', $raw ) ), static fn( $p ) => $p !== '' );

        return array_values( $parts );
    }

    public static function set_exclude_patterns( string $raw ): void {
        $parts = array_filter( array_map( 'trim', explode( ',', $raw ) ), static fn( $p ) => $p !== '' );
        update_option( self::OPTION_EXCLUDE, implode( ', ', $parts ) );
        self::flush_cache();
    }

    public static function is_configured(): bool {
        return get_option( 'btcpay_gf_url' ) && get_option( 'btcpay_gf_api_key' ) && get_option( 'btcpay_gf_store_id' );
    }

    /**
     * Summe der bezahlten Crowdfund-Spenden in einem Zeitraum.
     */
    public static function settled_sats( int $from_ts, int $to_ts ): int {
        if ( ! self::is_configured() ) {
            return 0;
        }

        $key    = 'sk_don_btcpay_' . md5( $from_ts . '-' . $to_ts );
        $cached = get_transient( $key );
        if ( $cached !== false ) {
            return (int) $cached;
        }

        $sum = 0;
        foreach ( self::fetch( $from_ts ) as $invoice ) {
            $created = (int) ( $invoice['createdTime'] ?? 0 );
            if ( $created < $from_ts || $created > $to_ts ) {
                continue;
            }
            $sum += self::sats( $invoice );
        }

        set_transient( $key, $sum, self::CACHE_TTL );

        return $sum;
    }

    /**
     * Bezahlte Rechnungen, die tatsächlich Spenden sind.
     *
     * @return array<int,array>
     */
    public static function fetch( int $from_ts ): array {
        $url   = rtrim( (string) get_option( 'btcpay_gf_url' ), '/' );
        $key   = (string) get_option( 'btcpay_gf_api_key' );
        $store = (string) get_option( 'btcpay_gf_store_id' );

        $out  = [];
        $skip = 0;

        // Seitenweise, aber gedeckelt: eine Endlosschleife darf einen
        // Seitenaufruf nicht blockieren.
        for ( $page = 0; $page < 10; $page++ ) {
            $response = wp_remote_get(
                $url . "/api/v1/stores/{$store}/invoices?startDate={$from_ts}&take=100&skip={$skip}",
                [
                    'timeout' => 12,
                    'headers' => [ 'Authorization' => 'token ' . $key ],
                ]
            );

            if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
                break;
            }

            $batch = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( ! is_array( $batch ) || empty( $batch ) ) {
                break;
            }

            foreach ( $batch as $invoice ) {
                if ( self::is_donation( $invoice ) ) {
                    $out[] = $invoice;
                }
            }

            if ( count( $batch ) < 100 ) {
                break;
            }
            $skip += 100;
        }

        return $out;
    }

    private static function is_donation( array $invoice ): bool {
        if ( ! in_array( (string) ( $invoice['status'] ?? '' ), [ 'Settled', 'Complete' ], true ) ) {
            return false;
        }

        $meta = $invoice['metadata'] ?? [];

        // Alles mit WooCommerce-Bestellnummer zaehlt bereits ueber WooCommerce
        // mit — sonst stuende jede Spende doppelt in der Summe.
        $order_id = (string) ( $meta['orderId'] ?? '' );
        if ( $order_id !== '' && preg_match( '/^(wc|WC)/', $order_id ) ) {
            return false;
        }

        $desc = (string) ( $meta['itemDesc'] ?? '' );
        if ( $desc === '' ) {
            return false;
        }

        foreach ( self::exclude_patterns() as $needle ) {
            if ( stripos( $desc, $needle ) !== false ) {
                return false;
            }
        }

        return self::sats( $invoice ) > 0;
    }

    /**
     * Betrag in Sats. EUR- und CHF-Rechnungen werden nicht umgerechnet —
     * ein geschaetzter Kurs waere in einer Zahlenanzeige schlimmer als eine
     * fehlende Zahl.
     */
    private static function sats( array $invoice ): int {
        $amount   = (float) ( $invoice['amount'] ?? 0 );
        $currency = strtoupper( (string) ( $invoice['currency'] ?? '' ) );

        if ( $currency === 'SATS' ) {
            return (int) round( $amount );
        }
        if ( $currency === 'BTC' ) {
            return (int) round( $amount * 100000000 );
        }

        return 0;
    }

    /**
     * Welche Beschreibungen kommen aktuell vom Server? Fuer die Admin-Anzeige,
     * damit sichtbar ist, was gezaehlt wird und was nicht.
     *
     * @return array<string,array{sats:int,n:int,gezaehlt:bool}>
     */
    public static function sources( int $from_ts ): array {
        if ( ! self::is_configured() ) {
            return [];
        }

        $url   = rtrim( (string) get_option( 'btcpay_gf_url' ), '/' );
        $key   = (string) get_option( 'btcpay_gf_api_key' );
        $store = (string) get_option( 'btcpay_gf_store_id' );

        $out  = [];
        $skip = 0;

        for ( $page = 0; $page < 10; $page++ ) {
            $response = wp_remote_get(
                $url . "/api/v1/stores/{$store}/invoices?startDate={$from_ts}&take=100&skip={$skip}",
                [ 'timeout' => 12, 'headers' => [ 'Authorization' => 'token ' . $key ] ]
            );

            if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
                break;
            }

            $batch = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( ! is_array( $batch ) || empty( $batch ) ) {
                break;
            }

            foreach ( $batch as $invoice ) {
                if ( ! in_array( (string) ( $invoice['status'] ?? '' ), [ 'Settled', 'Complete' ], true ) ) {
                    continue;
                }
                $meta     = $invoice['metadata'] ?? [];
                $order_id = (string) ( $meta['orderId'] ?? '' );
                if ( $order_id !== '' && preg_match( '/^(wc|WC)/', $order_id ) ) {
                    continue;
                }
                $desc = (string) ( $meta['itemDesc'] ?? '' );
                if ( $desc === '' ) {
                    $desc = __( '(ohne Beschreibung)', 'sk-core' );
                }

                $out[ $desc ]['sats']     = ( $out[ $desc ]['sats'] ?? 0 ) + self::sats( $invoice );
                $out[ $desc ]['n']        = ( $out[ $desc ]['n'] ?? 0 ) + 1;
                $out[ $desc ]['gezaehlt'] = self::is_donation( $invoice );
            }

            if ( count( $batch ) < 100 ) {
                break;
            }
            $skip += 100;
        }

        uasort( $out, static fn( $a, $b ) => $b['sats'] <=> $a['sats'] );

        return $out;
    }

    public static function flush_cache(): void {
        global $wpdb;

        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sk_don_btcpay_%' OR option_name LIKE '_transient_timeout_sk_don_btcpay_%'"
        );
    }
}
