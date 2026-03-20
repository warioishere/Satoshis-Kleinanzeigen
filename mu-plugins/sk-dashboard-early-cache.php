<?php
/**
 * Early dashboard page cache — serves Redis-cached HTML before any plugin loads.
 *
 * Fires at muplugins_loaded (after mu-plugins, before WooCommerce / sk-pro).
 * On a cache HIT the full plugin stack is never loaded → ~500ms faster.
 * Cache keys and TTL must match Performance.php exactly.
 */
add_action( 'muplugins_loaded', function () {
    // Only GET requests
    if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'GET' ) {
        return;
    }

    $uri = $_SERVER['REQUEST_URI'] ?? '';

    // Only dashboard URLs
    if ( false === strpos( $uri, '/dashboard/' ) ) {
        return;
    }

    // Skip URLs with query params that indicate dynamic/fresh content
    foreach ( [ 'message=', 'updated=', 'saved=', 'deleted=', 'error=', 'trashed=', 'sk_dash_opt_updated', 'action=', '_sk_edit_product_nonce=' ] as $p ) {
        if ( false !== strpos( $uri, $p ) ) {
            return;
        }
    }

    // Derive user hash from login cookie (same as Performance.php)
    $user_hash = '';
    foreach ( $_COOKIE as $k => $v ) {
        if ( str_starts_with( $k, 'wordpress_logged_in_' ) ) {
            $user_hash = md5( $v );
            break;
        }
    }
    if ( '' === $user_hash ) {
        return; // not logged in — no cache for anonymous users
    }

    // Check if page cache is enabled (option loaded via object cache)
    if ( ! (bool) get_option( 'sk_page_cache_enabled', 1 ) ) {
        return;
    }

    // Auto-bust cache when watched files change
    $watched = [
        __DIR__ . '/nostr-login-box.php',
        dirname( __DIR__ ) . '/plugins/sk-core/includes/BuyNow.php',
        dirname( __DIR__ ) . '/plugins/sk-core/assets/js/sk-buynow.js',
    ];
    $current_mtime = 0;
    foreach ( $watched as $f ) {
        if ( file_exists( $f ) ) {
            $current_mtime = max( $current_mtime, (int) filemtime( $f ) );
        }
    }
    $stored_mtime = (int) wp_cache_get( 'sk_dcv_files_mtime', 'sk_page_cache' );
    if ( $current_mtime !== $stored_mtime ) {
        wp_cache_set( 'sk_dcv_files_mtime', $current_mtime, 'sk_page_cache', 86400 );
        wp_cache_set( 'sk_dcv_files', $current_mtime, 'sk_page_cache', 86400 );
    }

    // Build cache key (same algorithm as Performance.php::get_cache_key)
    $version  = (int) wp_cache_get( 'sk_dcv_' . $user_hash, 'sk_page_cache' );
    $file_ver = (int) wp_cache_get( 'sk_dcv_files', 'sk_page_cache' );
    $key      = 'sk_dc_' . $user_hash . '_' . $version . '_' . $file_ver . '_' . md5( $uri );

    $cached = wp_cache_get( $key, 'sk_page_cache' );
    if ( false !== $cached && is_string( $cached ) && '' !== $cached ) {
        header( 'Content-Type: text/html; charset=UTF-8' );
        header( 'X-SK-Cache: HIT-EARLY' );
        echo $cached; // phpcs:ignore
        exit;
    }

    header( 'X-SK-Cache: MISS' );
}, 1 );
