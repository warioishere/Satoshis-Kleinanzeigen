<?php
/**
 * Global functions for sk-auth module (no namespace).
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'nostr_login_debug_log' ) ) {
    function nostr_login_debug_log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( '[SK Auth / Nostr] ' . $message );
        }
    }
}

if ( ! function_exists( 'lnurl_auth' ) ) {
    function lnurl_auth() {
        return \SK\Modules\Auth\Lnurl\Plugin::get_instance();
    }
}

if ( ! function_exists( 'sk_auth_registration_allowed' ) ) {
    /**
     * One registration budget per client for all three login methods.
     *
     * Checksum-valid addresses and keypairs are free to generate, so the
     * address check alone does not slow a bot down — this does. Consumes a
     * slot, so call it only where an account is actually about to be created;
     * an existing user signing in must never reach it.
     */
    function sk_auth_registration_allowed(): bool {
        $ip = function_exists( 'sk_get_client_ip' ) ? sk_get_client_ip() : '';

        return sk_rate_limit( 'auth-register:' . md5( $ip ), 3, HOUR_IN_SECONDS );
    }
}

if ( ! function_exists( 'sk_auth_registration_limit_message' ) ) {
    function sk_auth_registration_limit_message(): string {
        return __( 'Zu viele Registrierungen von dieser Verbindung. Bitte versuche es in einer Stunde erneut.', 'sk-core' );
    }
}
