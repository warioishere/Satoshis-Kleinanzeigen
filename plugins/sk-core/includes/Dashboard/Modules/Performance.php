<?php

namespace SK\Core\Dashboard\Modules;

use SK\Core\Dashboard\PageCache;

/**
 * Dashboard performance optimizations.
 *
 * Serves dashboard pages from a per-user Redis HTML cache and drops assets that
 * the current page does not need.
 */
class Performance {

    public function __construct() {
        self::migrate_legacy_option();

        // SK Admin → Settings → General
        add_filter( 'sk_settings_general_site_options', [ $this, 'add_field' ], 9 );

        add_action( 'admin_post_sk_dashboard_optimizations_save', [ $this, 'handle_frontend_save' ] );
        add_filter( 'the_content', [ $this, 'append_form_to_settings_page' ] );

        // Page cache (serve cached HTML early in request)
        add_action( 'template_redirect', [ $this, 'maybe_serve_cached_page' ], 1 );
        add_action( 'template_redirect', [ $this, 'maybe_start_output_buffer' ], 2 );

        // Cache bust on POST
        add_action( 'init', [ $this, 'maybe_bust_cache_on_post' ] );

    }

    /* ---- Helpers ---- */

    public function page_cache_enabled(): bool {
        return PageCache::is_enabled();
    }

    /**
     * The old scalar option (Settings → Dashboard Performance, "1"/"0")
     * migrated into sk_general once, then dropped.
     */
    private static function migrate_legacy_option(): void {
        $legacy = get_option( 'sk_page_cache_enabled', null );

        if ( null === $legacy ) {
            return;
        }

        $section = get_option( 'sk_general' );
        $section = is_array( $section ) ? $section : [];

        if ( ! isset( $section['sk_page_cache_enabled'] ) ) {
            $section['sk_page_cache_enabled'] = $legacy ? 'on' : 'off';
            update_option( 'sk_general', $section );
        }

        delete_option( 'sk_page_cache_enabled' );
    }

    /* ---- Admin settings ---- */

    public function add_field( $settings_fields ) {
        $settings_fields['sk_page_cache_enabled'] = [
            'name'    => 'sk_page_cache_enabled',
            'label'   => __( 'Dashboard Seiten-Cache', 'sk-core' ),
            'type'    => 'switcher',
            'default' => 'on',
            'desc'    => __( 'Speichert Dashboard-Seiten im Redis-Cache (5 Min. TTL) für sofortiges Laden.', 'sk-core' ),
        ];

        return $settings_fields;
    }

    public function handle_frontend_save(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'sk-core' ) );
        }
        check_admin_referer( 'sk_dashboard_optimizations_action' );

        $section = get_option( 'sk_general' );
        $section = is_array( $section ) ? $section : [];
        $section['sk_page_cache_enabled'] = isset( $_POST['sk_page_cache_enabled'] ) ? 'on' : 'off';
        update_option( 'sk_general', $section );

        $redirect = wp_get_referer() ?: home_url( '/' );
        wp_safe_redirect( add_query_arg( 'sk_dash_opt_updated', '1', $redirect ) );
        exit;
    }

    public function render_form(): string {
        if ( ! current_user_can( 'manage_options' ) ) {
            return '';
        }
        $cache = $this->page_cache_enabled();
        $msg   = '';
        if ( isset( $_GET['sk_dash_opt_updated'] ) ) {
            $msg = '<div class="sk-dash-opt-notice">' . esc_html__( 'Einstellungen gespeichert.', 'sk-core' ) . '</div>';
        }
        ob_start();
        ?>
        <div class="sk-dash-opt-settings">
            <h2><?php esc_html_e( 'Dashboard Performance', 'sk-core' ); ?></h2>
            <?php echo $msg; // phpcs:ignore ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'sk_dashboard_optimizations_action' ); ?>
                <input type="hidden" name="action" value="sk_dashboard_optimizations_save">
                <label class="sk-toggle"><input type="checkbox" name="sk_page_cache_enabled" value="1" <?php checked( $cache ); ?>><span><?php esc_html_e( 'Seiten-Cache', 'sk-core' ); ?></span></label>
                <p class="sk-desc"><?php esc_html_e( 'Speichert Dashboard-Seiten im Redis-Cache (5 Min.) für sofortiges Laden.', 'sk-core' ); ?></p>
                <p><button type="submit" class="button button-primary"><?php esc_html_e( 'Speichern', 'sk-core' ); ?></button></p>
            </form>
        </div>
        <style>
            .sk-dash-opt-settings{background:#1f2933;color:#fff;padding:24px;border-radius:8px;margin:24px 0;max-width:520px}
            .sk-dash-opt-settings h2{margin-top:0}
            .sk-dash-opt-notice{background:#16a34a;color:#fff;padding:10px 14px;border-radius:4px;margin-bottom:16px}
            .sk-toggle{display:flex;align-items:center;gap:10px;font-weight:600;margin-top:14px}
            .sk-toggle input[type="checkbox"]{transform:scale(1.3)}
            .sk-desc{color:#9ca3af;margin:4px 0 0 30px;font-size:13px}
        </style>
        <?php
        return ob_get_clean();
    }

    public function append_form_to_settings_page( string $content ): string {
        if ( ! is_page() ) {
            return $content;
        }
        $page = get_queried_object();
        if ( ! $page || empty( $page->post_name ) ) {
            return $content;
        }
        $slugs = apply_filters( 'sk_dashboard_optimizations_frontend_setting_slugs', [ 'frontend-settings', 'frontend-einstellungen' ] );
        if ( in_array( $page->post_name, $slugs, true ) ) {
            $content .= $this->render_form();
        }
        return $content;
    }

    /* ---- Page cache (Redis) ---- */

    public function maybe_serve_cached_page(): void {
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        if ( ! PageCache::is_enabled()
            || ! PageCache::is_cacheable_request( $_SERVER['REQUEST_METHOD'] ?? '', $uri ) ) {
            return;
        }

        $user_hash = PageCache::user_hash();
        if ( '' === $user_hash ) {
            return;
        }

        $cached = wp_cache_get( PageCache::cache_key( $user_hash, $uri ), PageCache::GROUP );

        if ( false !== $cached && is_string( $cached ) && '' !== $cached ) {
            header( 'Content-Type: text/html; charset=UTF-8' );
            header( 'X-SK-Cache: HIT' );
            echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            exit;
        }

        header( 'X-SK-Cache: MISS' );
    }

    public function maybe_start_output_buffer(): void {
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        if ( ! PageCache::is_enabled()
            || ! PageCache::is_cacheable_request( $_SERVER['REQUEST_METHOD'] ?? '', $uri ) ) {
            return;
        }

        $user_hash = PageCache::user_hash();
        if ( '' === $user_hash ) {
            return;
        }

        $key = PageCache::cache_key( $user_hash, $uri );

        ob_start( static function ( string $html ) use ( $key ): string {
            if ( ! empty( $html ) && false !== stripos( $html, '</html>' ) ) {
                wp_cache_set( $key, $html, PageCache::GROUP, PageCache::TTL );
            }

            return $html;
        } );
    }

    /**
     * Invalidate this visitor's cached pages after they changed something.
     */
    public function maybe_bust_cache_on_post(): void {
        if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
            return;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $ref = $_SERVER['HTTP_REFERER'] ?? '';

        $needs_bust = false;

        if ( false !== strpos( $uri, '/dashboard' ) ) {
            $needs_bust = true;
        } elseif ( false !== strpos( $ref, '/dashboard/' ) ) {
            // A dashboard page posting to ajax, admin-post or the REST API.
            if ( false !== strpos( $uri, 'admin-ajax.php' )
                || false !== strpos( $uri, 'admin-post.php' )
                || false !== strpos( $uri, '/wp-json/' ) ) {
                $needs_bust = true;
            }
        }

        if ( ! $needs_bust ) {
            return;
        }

        $user_hash = PageCache::user_hash();

        if ( '' !== $user_hash ) {
            wp_cache_set( 'sk_dcv_' . $user_hash, time(), PageCache::GROUP, HOUR_IN_SECONDS );
        }
    }
}
