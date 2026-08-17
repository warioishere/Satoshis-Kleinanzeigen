<?php

namespace SK\Modules\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Client IP for payment fraud signals.
 *
 * Thin wrapper around sk_get_client_ip() (which decides whether the request's
 * proxy headers may be trusted at all) that never returns an empty value, so
 * two unidentifiable buyers cannot collapse into the same buyer_ip_hash.
 */
class ClientIp {

    /**
     * Client IP, or an unguessable placeholder if none can be determined.
     */
    public static function get(): string {
        $ip = function_exists( 'sk_get_client_ip' ) ? sk_get_client_ip() : '';

        if ( $ip !== '' ) {
            return $ip;
        }

        // No usable IP: return something unique, so unrelated buyers never
        // share a hash and get counted as the same person by the Sybil check.
        return 'unknown-' . wp_generate_password( 16, false );
    }

    /**
     * SHA256 of the client IP, as stored in buyer_ip_hash.
     */
    public static function hash(): string {
        return hash( 'sha256', self::get() );
    }
}
