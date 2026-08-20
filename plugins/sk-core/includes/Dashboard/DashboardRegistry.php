<?php

namespace SK\Core\Dashboard;

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard Registry — central place where dashboard modules and settings tabs
 * register themselves declaratively.
 *
 * Replaces the old pattern where each module wired up 4 separate filter/action
 * hooks (sk_get_dashboard_nav, sk_dashboard_nav_active, sk_query_var_filter,
 * sk_load_custom_template) individually.
 *
 * Modules extending \SK\Core\Dashboard\DashboardModule register themselves
 * via register_module(); their config() is evaluated lazily on each hook run.
 */
class DashboardRegistry {

    /** @var DashboardModule[] */
    private static array $modules = [];

    /**
     * Static configs registered without a DashboardModule instance.
     * Used for built-in base menus (Dashboard, Products, Settings) that have
     * no per-module class of their own.
     *
     * @var array<array>
     */
    private static array $configs = [];

    private static bool $bootstrapped = false;

    public static function register_module( DashboardModule $module ): void {
        self::$modules[] = $module;
    }

    /**
     * Register a config array directly (no DashboardModule instance).
     * Supports the same keys as DashboardModule::config().
     */
    public static function register_config( array $config ): void {
        self::$configs[] = $config;
    }

    /**
     * Wire the registry into the existing dashboard hook pipeline.
     * Called once from ModuleLoader before any module is instantiated.
     */
    public static function bootstrap(): void {
        if ( self::$bootstrapped ) {
            return;
        }
        self::$bootstrapped = true;

        self::register_base_menus();

        add_filter( 'sk_get_dashboard_nav',    [ self::class, 'inject_menus' ], 50 );
        add_filter( 'sk_dashboard_nav_active', [ self::class, 'inject_active' ], 50, 3 );
        add_filter( 'sk_query_var_filter',     [ self::class, 'inject_query_vars' ], 50 );
        add_action( 'sk_load_custom_template', [ self::class, 'dispatch_template' ], 50 );

        // Settings-Tabs: heading + helper + content dispatcher.
        // Note: sk_get_dashboard_settings_nav filter is NOT wired — settings
        // sub-tabs are not rendered as a visible tab strip; each tab is a
        // plain URL endpoint.
        add_filter( 'sk_dashboard_settings_heading_title', [ self::class, 'inject_tab_heading' ], 50, 2 );
        add_filter( 'sk_dashboard_settings_helper_text',   [ self::class, 'inject_tab_helper' ], 50, 2 );
        add_action( 'sk_render_settings_content',          [ self::class, 'dispatch_tab' ], 50 );
    }

    /**
     * Register the 3 always-present base navigation entries.
     * Previously hardcoded in functions-dashboard-navigation.php.
     */
    private static function register_base_menus(): void {
        self::register_config( [
            'slug'       => 'dashboard',
            'title'      => __( 'Dashboard', 'sk-core' ),
            'icon'       => '<i class="fas fa-tachometer-alt"></i>',
            'icon_name'  => 'House',
            'url'        => sk_get_navigation_url(),
            'pos'        => 10,
            'permission' => 'sk_view_overview_menu',
        ] );

        self::register_config( [
            'slug'       => 'products',
            'title'      => __( 'Products', 'sk-core' ),
            'icon'       => '<i class="fas fa-briefcase"></i>',
            'icon_name'  => 'Box',
            'url'        => sk_get_navigation_url( 'products' ),
            'pos'        => 30,
            'permission' => 'sk_view_product_menu',
        ] );

        self::register_config( [
            'slug'       => 'settings',
            'title'      => __( 'Einstellungen', 'sk-core' ),
            'icon'       => '<i class="fas fa-cog"></i>',
            'icon_name'  => 'Settings',
            'url'        => sk_get_navigation_url( 'settings/store' ),
            'pos'        => 200,
            'permission' => 'sk_view_store_settings_menu',
        ] );
    }

    /**
     * Iterate ALL registered configs (from modules + static register_config calls).
     *
     * @return \Generator<array>
     */
    private static function all_configs(): \Generator {
        foreach ( self::$modules as $module ) {
            $config = $module->config();
            if ( is_array( $config ) && ! empty( $config ) ) {
                yield $config;
            }
        }
        foreach ( self::$configs as $config ) {
            yield $config;
        }
    }

    /**
     * Iterate only top-level menus (no parent) with valid config.
     *
     * @return \Generator<array>
     */
    private static function top_level_menus(): \Generator {
        foreach ( self::all_configs() as $config ) {
            if ( ! empty( $config['parent'] ) ) {
                continue;
            }
            yield $config;
        }
    }

    /**
     * Iterate only settings tabs (parent set) with valid config.
     *
     * @return \Generator<array>
     */
    private static function tabs(): \Generator {
        foreach ( self::all_configs() as $config ) {
            if ( empty( $config['parent'] ) ) {
                continue;
            }
            yield $config;
        }
    }

    public static function inject_menus( array $nav ): array {
        foreach ( self::top_level_menus() as $config ) {
            $slug = $config['slug'] ?? '';
            if ( ! $slug ) {
                continue;
            }

            $url_slug = $config['url_slug'] ?? $slug;
            $url      = $config['url']
                ?? ( function_exists( 'sk_get_navigation_url' ) ? sk_get_navigation_url( $url_slug ) : home_url( "/sk-dashboard/{$url_slug}/" ) );

            $nav[ $slug ] = [
                'title'      => $config['title'] ?? '',
                'icon'       => $config['icon'] ?? '',
                'url'        => $url,
                'pos'        => $config['pos'] ?? 50,
                'permission' => $config['permission'] ?? 'read',
            ];
        }
        return $nav;
    }

    public static function inject_active( $active_menu, $request, $active ) {
        // Wrap request with slashes so we can match whole path segments.
        $request_str = '/' . trim( (string) $request, '/' ) . '/';

        $fallback = '';

        foreach ( self::top_level_menus() as $config ) {
            $slug = $config['slug'] ?? '';
            if ( ! $slug ) {
                continue;
            }
            $url_slug = $config['url_slug'] ?? $slug;

            // The root 'dashboard' slug appears in EVERY vendor-dashboard URL
            // (since all child pages live under /mein-konto/dashboard/...).
            // Treat it as the fallback when no other menu claims active.
            if ( $slug === 'dashboard' ) {
                $fallback = $slug;
                continue;
            }

            // Precise path-segment match: /$url_slug/ must appear in the URL.
            if ( $url_slug && strpos( $request_str, '/' . $url_slug . '/' ) !== false ) {
                return $slug;
            }
            if ( ! empty( $active ) && in_array( $slug, (array) $active, true ) ) {
                return $slug;
            }
            if ( $url_slug && get_query_var( $url_slug ) ) {
                return $slug;
            }
        }

        return $fallback ?: $active_menu;
    }

    public static function inject_query_vars( array $vars ): array {
        foreach ( self::top_level_menus() as $config ) {
            $url_slug = $config['url_slug'] ?? ( $config['slug'] ?? '' );
            if ( $url_slug && ! in_array( $url_slug, $vars, true ) ) {
                $vars[] = $url_slug;
            }
        }
        return $vars;
    }

    public static function dispatch_template( $query_vars ): void {
        if ( ! is_array( $query_vars ) ) {
            return;
        }
        foreach ( self::top_level_menus() as $config ) {
            $url_slug = $config['url_slug'] ?? ( $config['slug'] ?? '' );
            $template = $config['template'] ?? null;

            if ( ! $url_slug || ! $template ) {
                continue;
            }
            if ( ! isset( $query_vars[ $url_slug ] ) ) {
                continue;
            }

            // Permission gate, same as dispatch_tab(). Without it the capability
            // in a module's config only hid the menu entry, never the page.
            $permission = $config['permission'] ?? '';
            if ( $permission && ! current_user_can( $permission ) ) {
                sk_get_template_part(
                    'global/sk-error', '', [
                        'deleted' => false,
                        'message' => __( 'You have no permission to view this page', 'sk-core' ),
                    ]
                );
                return;
            }

            // Callable template: module renders it itself (needed for modules
            // with their own template paths outside sk-core/templates/, or
            // that need to prep variables).
            if ( is_callable( $template ) ) {
                call_user_func( $template, $query_vars );
                return;
            }

            // String template slug: resolve via sk_get_template_part.
            $template_args = $config['template_args'] ?? [];
            if ( is_callable( $template_args ) ) {
                $template_args = call_user_func( $template_args, $query_vars );
            }
            sk_get_template_part( $template, '', (array) $template_args );
            return;
        }
    }

    /**
     * Settings-tab heading — Registry returns config['heading'] if the tab
     * matches; otherwise passes through.
     */
    public static function inject_tab_heading( $heading, $query_var ) {
        foreach ( self::tabs() as $config ) {
            $url_key = $config['url_key'] ?? $config['slug'];
            if ( $url_key === $query_var && ! empty( $config['heading'] ) ) {
                return is_callable( $config['heading'] )
                    ? call_user_func( $config['heading'], $heading, $query_var )
                    : $config['heading'];
            }
        }
        return $heading;
    }

    /**
     * Settings-tab helper text.
     */
    public static function inject_tab_helper( $helper, $query_var ) {
        foreach ( self::tabs() as $config ) {
            $url_key = $config['url_key'] ?? $config['slug'];
            if ( $url_key === $query_var && ! empty( $config['helper'] ) ) {
                return is_callable( $config['helper'] )
                    ? call_user_func( $config['helper'], $helper, $query_var )
                    : $config['helper'];
            }
        }
        return $helper;
    }

    /**
     * Settings-tab template dispatch — renders the tab matching
     * $wp->query_vars['settings'].
     */
    public static function dispatch_tab(): void {
        global $wp;
        if ( empty( $wp->query_vars['settings'] ) ) {
            return;
        }
        $current = $wp->query_vars['settings'];

        foreach ( self::tabs() as $config ) {
            $url_key  = $config['url_key'] ?? $config['slug'];
            $template = $config['template'] ?? null;

            if ( $url_key !== $current || ! $template ) {
                continue;
            }

            // Permission gate.
            $permission = $config['permission'] ?? '';
            if ( $permission && ! current_user_can( $permission ) ) {
                sk_get_template_part(
                    'global/sk-error', '', [
                        'deleted' => false,
                        'message' => __( 'You have no permission to view this page', 'sk-core' ),
                    ]
                );
                return;
            }

            if ( is_callable( $template ) ) {
                call_user_func( $template, $wp->query_vars );
                return;
            }

            $template_args = $config['template_args'] ?? [];
            if ( is_callable( $template_args ) ) {
                $template_args = call_user_func( $template_args, $wp->query_vars );
            }
            sk_get_template_part( $template, '', (array) $template_args );
            return;
        }
    }

    /**
     * Public accessor — used by tests and for Phase 6 when we remove the old
     * filter-based pipeline.
     *
     * @return DashboardModule[]
     */
    public static function all_modules(): array {
        return self::$modules;
    }
}
