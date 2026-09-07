<?php

namespace SK\Modules\Donations;

defined( 'ABSPATH' ) || exit;

/**
 * Reads paid donations directly from the BTCPay server.
 *
 * Necessary because the crowdfund apps run on the BTCPay server and never
 * touch WooCommerce. Without this class the stats would show only the
 * smaller part: since 2025, crowdfunding brought in roughly 4.1M sats,
 * WooCommerce 215,000.
 *
 * Counting explicitly starts only from a cutoff date (Donations::count_since).
 * The large sums from the 2025 build-up phase are past and would otherwise
 * keep the coverage bar permanently at 100 percent.
 *
 * Credentials come from the WooCommerce BTCPay plugin. If the key is renewed
 * there, this query stops working — which is why every failure is
 * non-critical: it returns 0 and the WooCommerce figures still stand.
 */
final class BtcPay {

    /** How long a fetch result is cached. */
    const CACHE_TTL = 900;

    /**
     * Descriptions that are not a donation: the contact-details feewall
     * sells contact access, which doesn't belong in the donation pot.
     *
     * Configurable instead of hardcoded — crowdfunds come and go, and a
     * rename of the feewall would otherwise silently count as a donation.
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
     * Sum of paid crowdfund donations in a time range.
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
     * Paid invoices that are actually donations.
     *
     * @return array<int,array>
     */
    public static function fetch( int $from_ts ): array {
        $url   = rtrim( (string) get_option( 'btcpay_gf_url' ), '/' );
        $key   = (string) get_option( 'btcpay_gf_api_key' );
        $store = (string) get_option( 'btcpay_gf_store_id' );

        $out  = [];
        $skip = 0;

        // Paged, but capped: an infinite loop must never block a page request.
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

        // Anything with a WooCommerce order number is already counted via
        // WooCommerce — otherwise every donation would be counted twice.
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
     * Amount in sats. EUR and CHF invoices are not converted — an estimated
     * exchange rate would be worse in a numeric display than a missing figure.
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
     * Which descriptions currently come from the server? For the admin
     * display, so it's visible what's counted and what isn't.
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
