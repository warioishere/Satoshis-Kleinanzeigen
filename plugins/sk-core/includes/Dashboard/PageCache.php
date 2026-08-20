<?php

namespace SK\Core\Dashboard;

/**
 * Rules of the dashboard page cache.
 *
 * Two layers serve this cache: mu-plugins/sk-dashboard-early-cache.php, which
 * answers before any plugin loads, and Dashboard\Modules\Performance, which
 * fills it. They have to agree on the key, the TTL and on which requests are
 * cacheable — when they drifted apart, one layer wrote entries the other refused
 * to serve. Both read those rules from here.
 *
 * The mu-plugin requires this file directly, so nothing in this class may depend
 * on the autoloader, on other sk-core classes or on a fully booted WordPress.
 * Everything it uses ($wpdb, the object cache, WP_CONTENT_DIR) is available at
 * muplugins_loaded.
 */
final class PageCache {

    /**
     * Object cache group.
     */
    public const GROUP = 'sk_page_cache';

    /**
     * Lifetime of a cached page in seconds.
     */
    public const TTL = 300;

    /**
     * Query fragments marking a response as one-off. A page carrying any of them
     * is neither served from nor written to the cache — it holds a notice, a
     * nonce or the result of an action.
     *
     * @var string[]
     */
    private const SKIP_PARAMS = [
        'message=',
        'updated=',
        'saved=',
        'deleted=',
        'error=',
        'trashed=',
        'sk_dash_opt_updated',
        'action=',
        '_sk_edit_product_nonce=',
    ];

    /**
     * Is the page cache switched on?
     */
    public static function is_enabled(): bool {
        return (bool) get_option( 'sk_page_cache_enabled', 1 );
    }

    /**
     * May this request use the cache at all?
     *
     * @param string $method Request method.
     * @param string $uri    Request URI.
     */
    public static function is_cacheable_request( string $method, string $uri ): bool {
        if ( 'GET' !== $method ) {
            return false;
        }

        if ( false === strpos( $uri, '/dashboard/' ) ) {
            return false;
        }

        foreach ( self::SKIP_PARAMS as $param ) {
            if ( false !== strpos( $uri, $param ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Identify the visitor by their login cookie.
     *
     * This keys the cache per session rather than per user, which is what keeps
     * one vendor from ever seeing another vendor's HTML.
     *
     * @return string Empty when not logged in.
     */
    public static function user_hash(): string {
        foreach ( $_COOKIE as $name => $value ) { // phpcs:ignore WordPress.Security.NonceVerification
            if ( 0 === strpos( $name, 'wordpress_logged_in_' ) ) {
                return md5( $value );
            }
        }

        return '';
    }

    /**
     * Files whose modification invalidates every cached page.
     *
     * One deploy marker instead of a hand-kept list of source files. Every
     * deploy is a `git reset --hard` in wp-content, which rewrites the index,
     * so any change ships with a fresh version — regardless of which file it
     * touched. The list it replaces only covered the buy-now sources and had
     * gone stale on top of that: it still named mu-plugins/nostr-login-box.php,
     * deleted long before, while four deploys in a row left the version sitting
     * on the previous day's timestamp.
     *
     * Should wp-content ever stop being a checkout, the file_exists() guard in
     * refresh_file_version() skips this and the version stays constant — the
     * cache then ages out via TTL, which is what it did before anyway.
     *
     * @return string[]
     */
    private static function watched_files(): array {
        return [
            WP_CONTENT_DIR . '/.git/index',
        ];
    }

    /**
     * Bump the global cache version when a watched file changed.
     */
    public static function refresh_file_version(): void {
        $current = 0;

        foreach ( self::watched_files() as $file ) {
            if ( file_exists( $file ) ) {
                $current = max( $current, (int) filemtime( $file ) );
            }
        }

        if ( $current === (int) wp_cache_get( 'sk_dcv_files_mtime', self::GROUP ) ) {
            return;
        }

        wp_cache_set( 'sk_dcv_files_mtime', $current, self::GROUP, DAY_IN_SECONDS );
        wp_cache_set( 'sk_dcv_files', $current, self::GROUP, DAY_IN_SECONDS );
    }

    /**
     * Build the cache key.
     *
     * Two version counters are folded in: one per visitor, bumped when that
     * visitor changes something, and one global, bumped by refresh_file_version()
     * and by content that everybody sees.
     *
     * @param string $user_hash From user_hash().
     * @param string $uri       Request URI.
     */
    public static function cache_key( string $user_hash, string $uri ): string {
        $user_version = (int) wp_cache_get( 'sk_dcv_' . $user_hash, self::GROUP );
        $file_version = (int) wp_cache_get( 'sk_dcv_files', self::GROUP );

        return 'sk_dc_' . $user_hash . '_' . $user_version . '_' . $file_version . '_' . md5( $uri );
    }
}
