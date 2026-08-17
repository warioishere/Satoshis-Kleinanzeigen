<?php

namespace SK\Modules\AntiFraud;

defined( 'ABSPATH' ) || exit;

/**
 * Guards against abusive use of the report function.
 *
 * Reports can take a vendor offline, so the reporting itself needs protecting:
 * without limits a single person — or three throwaway accounts — could delist a
 * competitor. Blocks are applied before the report is stored.
 */
class ReportGuards {

    /** Transient prefix for the per IP hourly counter. */
    const RATE_TRANSIENT = 'sk_af_reports_ip_';

    public function __construct() {
        add_filter( 'sk_report_abuse_pre_create_report', [ $this, 'check' ], 10, 2 );
    }

    /**
     * @param null|\WP_Error $blocked
     * @param array          $args
     *
     * @return null|\WP_Error
     */
    public function check( $blocked, $args ) {
        if ( is_wp_error( $blocked ) ) {
            return $blocked;
        }

        $product_id = (int) ( $args['product_id'] ?? 0 );
        $user_id    = (int) ( $args['customer_id'] ?? 0 );

        // One report per user and listing.
        if ( $user_id && $product_id && self::has_reported( $user_id, $product_id ) ) {
            return new \WP_Error(
                'sk_af_already_reported',
                __( 'Du hast dieses Inserat bereits gemeldet.', 'sk-core' )
            );
        }

        // Hourly cap per IP.
        $max_per_hour = (int) sk_get_option( 'sk_antifraud_reports_per_hour', 'sk_antifraud', '5' );

        if ( $max_per_hour > 0 ) {
            $key   = self::RATE_TRANSIENT . md5( self::get_client_ip() );
            $count = (int) get_transient( $key );

            if ( $count >= $max_per_hour ) {
                return new \WP_Error(
                    'sk_af_rate_limited',
                    __( 'Du hast in kurzer Zeit zu viele Meldungen abgeschickt. Bitte versuche es später erneut.', 'sk-core' )
                );
            }

            set_transient( $key, $count + 1, HOUR_IN_SECONDS );
        }

        return null;
    }

    /**
     * Did this user already report this listing?
     */
    public static function has_reported( int $user_id, int $product_id ): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'sk_report_abuse_reports';

        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE customer_id = %d AND product_id = %d",
            $user_id,
            $product_id
        ) );
    }

    /**
     * Is this account old enough for its reports to count towards auto-suspend?
     */
    public static function is_eligible_reporter( int $user_id ): bool {
        if ( ! $user_id ) {
            return false;
        }

        $user = get_userdata( $user_id );

        if ( ! $user ) {
            return false;
        }

        $min_days   = (int) sk_get_option( 'sk_antifraud_reporter_min_age', 'sk_antifraud', '14' );
        $registered = strtotime( $user->user_registered );

        if ( ! $registered || $min_days <= 0 ) {
            return true;
        }

        return ( time() - $registered ) >= $min_days * DAY_IN_SECONDS;
    }

    /**
     * Client IP for rate limiting.
     *
     * Resolved centrally by sk_get_client_ip(), which ignores spoofable proxy
     * headers unless the request really came through a proxy — otherwise the
     * rate limit here could be bypassed by sending a new X-Forwarded-For.
     */
    public static function get_client_ip(): string {
        $ip = function_exists( 'sk_get_client_ip' ) ? sk_get_client_ip() : '';

        return $ip !== '' ? $ip : 'unknown';
    }
}
