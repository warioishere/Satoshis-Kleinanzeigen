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
