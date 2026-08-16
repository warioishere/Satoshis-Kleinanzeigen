<?php

namespace SK\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Targeted page cache invalidation.
 *
 * WP Fastest Cache serves static HTML from wp-content/cache/all/<path>/index.html
 * before PHP ever runs. When content disappears from the database — a deleted
 * vendor, a removed listing — that file keeps being served, so the content stays
 * publicly reachable. For a scam takedown that is not acceptable.
 *
 * Only the affected URLs are dropped, never the whole cache: the listing itself
 * and the vendor's store page (recursively, which covers the store sub tabs).
 * URLs are collected before the content is gone — permalinks can't be resolved
 * afterwards — and written out once on shutdown.
 *
 * Note: WPFC's wpfc_clear_cache_by_url() and deleteSpecificCache() do not exist
 * in the installed version, so the cache files are removed directly.
 */
final class PageCache {

    /** Post types whose removal must invalidate the cache. */
    private const WATCHED_TYPES = [ 'product', 'gesuch', 'sk_vendor_post' ];

    /** Cache roots WPFC writes rendered pages to. */
    private const CACHE_ROOTS = [ '/cache/all', '/cache/wpfc-mobile-cache' ];

    /** @var string[] URL paths queued for removal. */
    private static $paths = [];

    /** @var bool Shutdown handler already registered. */
    private static $scheduled = false;

    public static function init(): void {
        // Priority 5: run before sk_delete_user_details() (10) wipes the posts,
        // otherwise the permalinks are already unresolvable.
        add_action( 'delete_user', [ __CLASS__, 'on_user_deleted' ], 5, 1 );

        add_action( 'before_delete_post', [ __CLASS__, 'on_post_removed' ], 5, 1 );
        add_action( 'wp_trash_post',      [ __CLASS__, 'on_post_removed' ], 5, 1 );

        // Content hidden (publish -> draft/pending/private).
        add_action( 'transition_post_status', [ __CLASS__, 'on_status_change' ], 10, 3 );
    }

    // ── Event handlers ─────────────────────────────────────────────────────────

    /**
     * Vendor is about to be deleted — queue their store page and every listing.
     */
    public static function on_user_deleted( $user_id ): void {
        self::queue_store( (int) $user_id );

        $post_ids = get_posts( [
            'author'      => (int) $user_id,
            'post_type'   => self::WATCHED_TYPES,
            'post_status' => 'any',
            'numberposts' => -1,
            'fields'      => 'ids',
        ] );

        foreach ( $post_ids as $post_id ) {
            self::queue_post( (int) $post_id );
        }
    }

    public static function on_post_removed( $post_id ): void {
        $post = get_post( $post_id );

        if ( ! $post || ! in_array( $post->post_type, self::WATCHED_TYPES, true ) ) {
            return;
        }

        self::queue_post( (int) $post_id );
        self::queue_store( (int) $post->post_author );
    }

    public static function on_status_change( $new_status, $old_status, $post ): void {
        if ( ! $post instanceof \WP_Post ) {
            return;
        }
        if ( ! in_array( $post->post_type, self::WATCHED_TYPES, true ) ) {
            return;
        }
        // Only when something stops being publicly visible.
        if ( 'publish' !== $old_status || 'publish' === $new_status ) {
            return;
        }

        self::queue_post( (int) $post->ID );
        self::queue_store( (int) $post->post_author );
    }

    // ── Queueing ───────────────────────────────────────────────────────────────

    public static function queue_post( int $post_id ): void {
        $permalink = get_permalink( $post_id );

        if ( $permalink ) {
            self::queue_url( $permalink );
        }
    }

    public static function queue_store( int $user_id ): void {
        if ( ! $user_id || ! function_exists( 'sk_get_store_url' ) ) {
            return;
        }

        $store_url = sk_get_store_url( $user_id );

        if ( $store_url ) {
            self::queue_url( $store_url );
        }
    }

    public static function queue_url( string $url ): void {
        $path = wp_parse_url( $url, PHP_URL_PATH );

        if ( ! $path || '/' === $path ) {
            return;
        }

        $path = trim( $path, '/' );

        if ( '' === $path ) {
            return;
        }

        self::$paths[ $path ] = true;
        self::schedule_flush();
    }

    /**
     * Register the shutdown flush once per request.
     */
    public static function schedule_flush(): void {
        if ( self::$scheduled ) {
            return;
        }
        self::$scheduled = true;

        add_action( 'shutdown', [ __CLASS__, 'flush_now' ], 99 );
    }

    // ── Flushing ───────────────────────────────────────────────────────────────

    /**
     * Remove the cached HTML of every queued path.
     */
    public static function flush_now(): void {
        if ( empty( self::$paths ) ) {
            return;
        }

        foreach ( array_keys( self::$paths ) as $path ) {
            foreach ( self::CACHE_ROOTS as $root ) {
                $dir = WP_CONTENT_DIR . $root . '/' . $path;

                // Guard against escaping the cache directory.
                $base = realpath( WP_CONTENT_DIR . $root );
                $real = realpath( $dir );

                if ( ! $base || ! $real || strpos( $real, $base ) !== 0 ) {
                    continue;
                }

                self::rmdir_recursive( $real );
            }
        }

        self::$paths = [];
    }

    private static function rmdir_recursive( string $path ): void {
        if ( ! is_dir( $path ) ) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $items as $item ) {
            $item->isDir() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() );
        }

        @rmdir( $path );
    }
}
