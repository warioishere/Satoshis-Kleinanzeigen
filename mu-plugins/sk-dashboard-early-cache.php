<?php
/**
 * Early dashboard page cache — serves Redis-cached HTML before any plugin loads.
 *
 * Fires at muplugins_loaded (after mu-plugins, before WooCommerce / sk-core).
 * On a cache HIT the full plugin stack is never loaded → ~500ms faster.
 *
 * The rules — key, TTL, which requests are cacheable — live in sk-core's
 * PageCache class, which Performance.php uses as well. Do not reimplement them
 * here: when the two layers drifted apart, this one refused to serve pages the
 * other had written.
 */
add_action( 'muplugins_loaded', function () {
    $rules = dirname( __DIR__ ) . '/plugins/sk-core/includes/Dashboard/PageCache.php';

    // Without sk-core there is nothing to cache for.
    if ( ! file_exists( $rules ) ) {
        return;
    }

    require_once $rules;

    $cache = \SK\Core\Dashboard\PageCache::class;

    $uri = $_SERVER['REQUEST_URI'] ?? '';

    if ( ! $cache::is_cacheable_request( $_SERVER['REQUEST_METHOD'] ?? '', $uri ) ) {
        return;
    }

    $user_hash = $cache::user_hash();
    if ( '' === $user_hash ) {
        return; // not logged in — no cache for anonymous users
    }

    if ( ! $cache::is_enabled() ) {
        return;
    }

    $cache::refresh_file_version();

    $cached = wp_cache_get( $cache::cache_key( $user_hash, $uri ), $cache::GROUP );

    if ( false !== $cached && is_string( $cached ) && '' !== $cached ) {
        header( 'Content-Type: text/html; charset=UTF-8' );
        header( 'X-SK-Cache: HIT-EARLY' );
        echo $cached; // phpcs:ignore
        exit;
    }

    header( 'X-SK-Cache: MISS' );
}, 1 );
