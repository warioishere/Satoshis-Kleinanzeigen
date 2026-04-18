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

    private static bool $bootstrapped = false;

    public static function register_module( DashboardModule $module ): void {
        self::$modules[] = $module;
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

        add_filter( 'sk_get_dashboard_nav',    [ self::class, 'inject_menus' ], 50 );
        add_filter( 'sk_dashboard_nav_active', [ self::class, 'inject_active' ], 50, 3 );
        add_filter( 'sk_query_var_filter',     [ self::class, 'inject_query_vars' ], 50 );
        add_action( 'sk_load_custom_template', [ self::class, 'dispatch_template' ], 50 );

        // Settings tabs — wired in Phase 4, empty pass-through for now.
        add_filter( 'sk_get_dashboard_settings_nav', [ self::class, 'inject_tabs' ], 50 );
        add_action( 'sk_render_settings_content',    [ self::class, 'dispatch_tab' ], 50 );
    }

    /**
     * Iterate modules and yield only top-level menus (no parent) with valid config.
     *
     * @return \Generator<array>
     */
    private static function top_level_menus(): \Generator {
        foreach ( self::$modules as $module ) {
            $config = $module->config();
            if ( ! is_array( $config ) || empty( $config ) ) {
                continue;
            }
            if ( ! empty( $config['parent'] ) ) {
                continue;
            }
            yield $config;
        }
    }

    /**
     * Iterate modules and yield only settings tabs (parent set) with valid config.
     *
     * @return \Generator<array>
     */
    private static function tabs(): \Generator {
        foreach ( self::$modules as $module ) {
            $config = $module->config();
            if ( ! is_array( $config ) || empty( $config ) ) {
                continue;
            }
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
        foreach ( self::top_level_menus() as $config ) {
            $slug = $config['slug'] ?? '';
            if ( ! $slug ) {
                continue;
            }
            $url_slug = $config['url_slug'] ?? $slug;

            if ( isset( $request ) && false !== strpos( (string) $request, $url_slug ) ) {
                return $slug;
            }
            if ( ! empty( $active ) && in_array( $slug, (array) $active, true ) ) {
                return $slug;
            }
            if ( get_query_var( $url_slug ) ) {
                return $slug;
            }
        }
        return $active_menu;
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
     * Settings-Tabs injection (Phase 4 — currently a no-op pass-through).
     */
    public static function inject_tabs( array $tabs ): array {
        // TODO Phase 4: implement tab rendering from registry.
        // For Phase 1, tabs still flow through sk_get_dashboard_settings_nav
        // as before (tabs() generator is empty until modules migrate).
        foreach ( self::tabs() as $_config ) {
            // Placeholder — no tabs registered yet.
        }
        return $tabs;
    }

    /**
     * Settings tab template dispatch (Phase 4 — currently a no-op).
     */
    public static function dispatch_tab(): void {
        // TODO Phase 4.
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
