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
        // Initialize defaults
        add_action( 'init', [ $this, 'init_defaults' ] );

        // Admin settings page
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_post_sk_dashboard_optimizations_save', [ $this, 'handle_frontend_save' ] );
        add_filter( 'the_content', [ $this, 'append_form_to_settings_page' ] );

        // Page cache (serve cached HTML early in request)
        add_action( 'template_redirect', [ $this, 'maybe_serve_cached_page' ], 1 );
        add_action( 'template_redirect', [ $this, 'maybe_start_output_buffer' ], 2 );

        // Cache bust on POST
        add_action( 'init', [ $this, 'maybe_bust_cache_on_post' ] );

        // Asset trimmer
        add_action( 'sk_enqueue_scripts', [ $this, 'trim_assets' ], 999 );
    }

    /* ---- Helpers ---- */

    public function page_cache_enabled(): bool {
        return PageCache::is_enabled();
    }

    /* ---- init defaults ---- */

    public function init_defaults(): void {
        if ( false === get_option( 'sk_page_cache_enabled', false ) ) {
            add_option( 'sk_page_cache_enabled', 1 );
        }
    }

    /* ---- Admin settings ---- */

    public function register_settings(): void {
        $options = [
            'sk_page_cache_enabled',
        ];

        foreach ( $options as $option ) {
            register_setting( 'sk_dashboard_performance', $option, [
                'type'              => 'boolean',
                'sanitize_callback' => static function ( $v ) { return $v ? 1 : 0; },
                'default'           => 1,
            ] );
        }

        add_settings_section( 'sk_dashboard_performance_main', '', '__return_null', 'sk-dashboard-performance' );

        add_settings_field( 'sk_cache_field', esc_html__( 'Seiten-Cache', 'sk-core' ), function () {
            ?>
            <label>
                <input type="checkbox" name="sk_page_cache_enabled" value="1" <?php checked( $this->page_cache_enabled() ); ?>>
                <?php esc_html_e( 'Speichert Dashboard-Seiten im Redis-Cache (5 Min. TTL) für sofortiges Laden.', 'sk-core' ); ?>
            </label>
            <?php
        }, 'sk-dashboard-performance', 'sk_dashboard_performance_main' );
    }

    public function add_admin_menu(): void {
        add_options_page(
            esc_html__( 'Dashboard Performance', 'sk-core' ),
            esc_html__( 'Dashboard Performance', 'sk-core' ),
            'manage_options',
            'sk-dashboard-performance',
            [ $this, 'render_admin_page' ]
        );
    }

    public function render_admin_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Dashboard Performance', 'sk-core' ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'sk_dashboard_performance' );
                do_settings_sections( 'sk-dashboard-performance' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function handle_frontend_save(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'sk-core' ) );
        }
        check_admin_referer( 'sk_dashboard_optimizations_action' );
        update_option( 'sk_page_cache_enabled',              isset( $_POST['sk_page_cache_enabled'] ) ? 1 : 0 );
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

    /* ---- Asset trimmer ---- */

    public function trim_assets(): void {
        if ( ! function_exists( 'sk_is_seller_dashboard' ) || ! sk_is_seller_dashboard() ) {
            return;
        }
        global $wp;
        $query_vars = isset( $wp, $wp->query_vars ) && is_array( $wp->query_vars ) ? $wp->query_vars : [];
        if ( array_key_exists( 'page', $query_vars ) ) {
            return;
        }
        $page_map = [
            'settings'          => 'settings',
            'products'          => 'products',
            'orders'            => 'orders',
            'announcement'      => 'announcement',
        ];
        $current_page = 'home';
        foreach ( $page_map as $key => $page ) {
            if ( array_key_exists( $key, $query_vars ) ) {
                $current_page = $page;
                break;
            }
        }
        $groups = [
            [ 'scripts' => [ 'sk-pro-frontend-shipping' ],     'styles' => [ 'sk-pro-frontend-shipping' ],     'needed_on' => [ 'settings' ] ],
            [ 'scripts' => [ 'sk-pro-store-seo' ],              'styles' => [ 'sk-pro-store-seo' ],              'needed_on' => [ 'settings' ] ],
            [ 'scripts' => [ 'sk-date-range-picker' ], 'styles' => [ 'sk-date-range-picker' ], 'needed_on' => [ 'settings', 'products' ] ],
            [ 'scripts' => [ 'sk-maps' ],                       'styles' => [ 'sk-mapbox-gl', 'sk-mapbox-gl-geocoder' ], 'needed_on' => [ 'settings' ] ],
            [ 'scripts' => [ 'wc-password-strength-meter' ],    'styles' => [],                                  'needed_on' => [ 'settings' ] ],
        ];
        $scripts = wp_scripts();
        $styles  = wp_styles();
        $has_script_deps = static function ( $handle ) use ( $scripts ) {
            if ( ! $scripts instanceof \WP_Scripts ) return false;
            foreach ( (array) $scripts->queue as $q ) {
                if ( $q === $handle || ! isset( $scripts->registered[ $q ] ) ) continue;
                if ( in_array( $handle, (array) $scripts->registered[ $q ]->deps, true ) ) return true;
            }
            return false;
        };
        $has_style_deps = static function ( $handle ) use ( $styles ) {
            if ( ! $styles instanceof \WP_Styles ) return false;
            foreach ( (array) $styles->queue as $q ) {
                if ( $q === $handle || ! isset( $styles->registered[ $q ] ) ) continue;
                if ( in_array( $handle, (array) $styles->registered[ $q ]->deps, true ) ) return true;
            }
            return false;
        };
        foreach ( $groups as $g ) {
            if ( in_array( $current_page, $g['needed_on'], true ) ) continue;
            foreach ( $g['scripts'] as $h ) {
                if ( ! $has_script_deps( $h ) ) { wp_dequeue_script( $h ); wp_deregister_script( $h ); }
            }
            foreach ( $g['styles'] as $h ) {
                if ( ! $has_style_deps( $h ) ) { wp_dequeue_style( $h ); wp_deregister_style( $h ); }
            }
        }
    }
}
