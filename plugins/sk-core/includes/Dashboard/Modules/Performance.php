<?php

namespace SK\Core\Dashboard\Modules;

/**
 * Dashboard performance optimizations.
 *
 * Two toggles:
 *   - Turbo-Navigation: prefetch + mousedown navigation
 *   - Seiten-Cache: Redis HTML caching (served via template_redirect)
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

        // Turbo navigation JS
        add_action( 'wp_footer', [ $this, 'output_turbo_navigation' ], 99 );
    }

    /* ---- Helpers ---- */

    public function turbo_navigation_enabled(): bool {
        return (bool) get_option( 'sk_turbo_navigation_enabled', 1 );
    }

    public function page_cache_enabled(): bool {
        return (bool) get_option( 'sk_page_cache_enabled', 1 );
    }

    /* ---- init defaults ---- */

    public function init_defaults(): void {
        if ( false === get_option( 'sk_turbo_navigation_enabled', false ) ) {
            add_option( 'sk_turbo_navigation_enabled', 1 );
        }
        if ( false === get_option( 'sk_page_cache_enabled', false ) ) {
            add_option( 'sk_page_cache_enabled', 1 );
        }
    }

    /* ---- Admin settings ---- */

    public function register_settings(): void {
        $options = [
            'sk_turbo_navigation_enabled',
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

        add_settings_field( 'sk_turbo_field', esc_html__( 'Turbo-Navigation', 'sk-core' ), function () {
            ?>
            <label>
                <input type="checkbox" name="sk_turbo_navigation_enabled" value="1" <?php checked( $this->turbo_navigation_enabled() ); ?>>
                <?php esc_html_e( 'Prefetcht Dashboard-Seiten und navigiert schneller (Hover + Mousedown).', 'sk-core' ); ?>
            </label>
            <?php
        }, 'sk-dashboard-performance', 'sk_dashboard_performance_main' );

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
        update_option( 'sk_turbo_navigation_enabled',        isset( $_POST['sk_turbo_navigation_enabled'] ) ? 1 : 0 );
        update_option( 'sk_page_cache_enabled',              isset( $_POST['sk_page_cache_enabled'] ) ? 1 : 0 );
        $redirect = wp_get_referer() ?: home_url( '/' );
        wp_safe_redirect( add_query_arg( 'sk_dash_opt_updated', '1', $redirect ) );
        exit;
    }

    public function render_form(): string {
        if ( ! current_user_can( 'manage_options' ) ) {
            return '';
        }
        $turbo = $this->turbo_navigation_enabled();
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
                <label class="sk-toggle"><input type="checkbox" name="sk_turbo_navigation_enabled" value="1" <?php checked( $turbo ); ?>><span><?php esc_html_e( 'Turbo-Navigation', 'sk-core' ); ?></span></label>
                <p class="sk-desc"><?php esc_html_e( 'Prefetcht Dashboard-Seiten und navigiert schneller.', 'sk-core' ); ?></p>
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

    private function get_user_hash(): string {
        foreach ( $_COOKIE as $k => $v ) {
            if ( str_starts_with( $k, 'wordpress_logged_in_' ) ) {
                return md5( $v );
            }
        }
        return '';
    }

    private function get_cache_key( string $user_hash, string $uri ): string {
        $version     = (int) wp_cache_get( 'sk_dcv_' . $user_hash, 'sk_page_cache' );
        $file_ver    = (int) wp_cache_get( 'sk_dcv_files', 'sk_page_cache' );
        return 'sk_dc_' . $user_hash . '_' . $version . '_' . $file_ver . '_' . md5( $uri );
    }

    public function maybe_serve_cached_page(): void {
        if ( ! $this->page_cache_enabled() ) {
            return;
        }
        if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'GET' ) {
            return;
        }
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if ( false === strpos( $uri, '/dashboard/' ) ) {
            return;
        }

        foreach ( [ 'message=', 'updated=', 'saved=', 'deleted=', 'error=', 'trashed=', 'sk_dash_opt_updated' ] as $p ) {
            if ( false !== strpos( $uri, $p ) ) {
                return;
            }
        }

        $user_hash = $this->get_user_hash();
        if ( '' === $user_hash ) {
            return;
        }

        $key    = $this->get_cache_key( $user_hash, $uri );
        $cached = wp_cache_get( $key, 'sk_page_cache' );

        if ( false !== $cached && is_string( $cached ) && '' !== $cached ) {
            header( 'Content-Type: text/html; charset=UTF-8' );
            header( 'X-SK-Cache: HIT' );
            echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            exit;
        }

        header( 'X-SK-Cache: MISS' );
    }

    public function maybe_start_output_buffer(): void {
        if ( ! $this->page_cache_enabled() ) {
            return;
        }
        if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'GET' ) {
            return;
        }
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if ( false === strpos( $uri, '/dashboard/' ) ) {
            return;
        }
        $user_hash = $this->get_user_hash();
        if ( '' === $user_hash ) {
            return;
        }

        $key = $this->get_cache_key( $user_hash, $uri );
        ob_start( static function ( string $html ) use ( $key ): string {
            if ( ! empty( $html ) && false !== stripos( $html, '</html>' ) ) {
                wp_cache_set( $key, $html, 'sk_page_cache', 300 );
            }
            return $html;
        } );
    }

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
            if ( false !== strpos( $uri, 'admin-ajax.php' ) || false !== strpos( $uri, 'admin-post.php' ) || false !== strpos( $uri, '/wp-json/' ) ) {
                $needs_bust = true;
            }
        }
        if ( ! $needs_bust ) {
            return;
        }
        $user_hash = $this->get_user_hash();
        if ( '' !== $user_hash ) {
            wp_cache_set( 'sk_dcv_' . $user_hash, time(), 'sk_page_cache', 3600 );
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

    /* ---- Turbo Navigation ---- */

    public function output_turbo_navigation(): void {
        if ( ! function_exists( 'sk_is_seller_dashboard' ) || ! sk_is_seller_dashboard() ) {
            return;
        }
        if ( ! $this->turbo_navigation_enabled() ) {
            return;
        }
        ?>
        <style id="sk-turbo-css">
            #sk-turbo-progress{position:fixed;top:0;left:0;width:0;height:3px;background:linear-gradient(90deg,#f05025,#ff7043);z-index:999999;pointer-events:none;opacity:0}
            #sk-turbo-progress.loading{opacity:1;width:70%;transition:width 2s cubic-bezier(.1,.05,.1,1)}
            #sk-turbo-progress.done{width:100%;transition:width .15s ease}
            #sk-turbo-progress.hide{opacity:0;transition:opacity .3s ease .15s}
        </style>
        <script id="sk-turbo-js">
        (function(){
            'use strict';
            var sidebar = document.querySelector('.sk-dash-sidebar');
            if (!sidebar) return;
            var bar = document.createElement('div');
            bar.id = 'sk-turbo-progress';
            document.body.appendChild(bar);
            function progressShow(){ bar.className=''; void bar.offsetWidth; bar.className='loading'; }
            function ok(a){
                if (!a||!a.href) return false;
                try { if (new URL(a.href).origin!==location.origin) return false; } catch(_){ return false; }
                if (a.href.indexOf('#')!==-1) return false;
                if (a.target&&a.target!=='_self') return false;
                var li=a.closest('li[data-react-route]');
                if (li&&li.getAttribute('data-react-route')) return false;
                if (a.href.indexOf('wp-login')!==-1||a.href.indexOf('wp-admin')!==-1) return false;
                return true;
            }
            var warmed={};
            function warmCache(url){ if(warmed[url]) return; warmed[url]=true; fetch(url,{credentials:'same-origin',priority:'low',mode:'same-origin'}).catch(function(){}); }
            sidebar.addEventListener('mouseenter',function(e){ var a=e.target.closest&&e.target.closest('a[href]'); if(ok(a)&&a.href!==location.href) warmCache(a.href); },true);
            sidebar.addEventListener('touchstart',function(e){ var a=e.target.closest&&e.target.closest('a[href]'); if(ok(a)&&a.href!==location.href) warmCache(a.href); },{passive:true,capture:true});
            var navigating=false;
            sidebar.addEventListener('mousedown',function(e){
                if(e.button!==0||e.ctrlKey||e.metaKey||e.shiftKey||e.altKey) return;
                var a=e.target.closest&&e.target.closest('a[href]');
                if(!ok(a)||a.href===location.href) return;
                warmCache(a.href); progressShow(); navigating=true; location.href=a.href;
            });
            sidebar.addEventListener('click',function(e){
                if(e.ctrlKey||e.metaKey||e.shiftKey||e.altKey) return;
                var a=e.target.closest&&e.target.closest('a[href]');
                if(!ok(a)) return;
                if(navigating){e.preventDefault();return;}
                if(a.href!==location.href){warmCache(a.href);progressShow();}
            });
            if('requestIdleCallback' in window){
                requestIdleCallback(function(){
                    var links=sidebar.querySelectorAll('.sk-dashboard-menu a[href]'),i=0;
                    function next(){ if(i>=links.length) return; var a=links[i++]; if(ok(a)&&a.href!==location.href) warmCache(a.href); setTimeout(next,300); }
                    next();
                },{timeout:3000});
            }
        })();
        </script>
        <?php
    }
}
