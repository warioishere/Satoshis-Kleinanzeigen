<?php

namespace SK\Core\Admin;

use SK\Core\Contracts\Hookable;
use SK\Core\Admin\PhpDashboard\AbstractPage;

class PhpDashboard implements Hookable {

    /**
     * @var AbstractPage[]
     */
    private array $pages = [];

    public function register_hooks(): void {
        add_action( 'admin_menu', [ $this, 'register_submenu_items' ], 15 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ], 20 );
        add_action( 'admin_init', [ $this, 'handle_post' ] );
    }

    public function enqueue_styles(): void {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'toplevel_page_sk' ) {
            return;
        }

        $css_path = plugin_dir_path( SK_CORE_FILE ) . 'assets/css/sk-php-dashboard.css';
        wp_enqueue_style(
            'sk-php-dashboard',
            SK_CORE_ASSETS . '/css/sk-php-dashboard.css',
            [ 'wp-admin' ],
            file_exists( $css_path ) ? filemtime( $css_path ) : SK_CORE_VERSION
        );

    }

    /**
     * Get all registered pages.
     *
     * @return AbstractPage[]
     */
    public function get_pages(): array {
        if ( empty( $this->pages ) ) {
            $this->pages = apply_filters( 'sk_php_dashboard_pages', $this->get_default_pages() );
        }

        return $this->pages;
    }

    /**
     * Default pages provided by sk-lite.
     */
    private function get_default_pages(): array {
        return [
            'settings' => new PhpDashboard\SettingsPage(),
            'vendors'  => new PhpDashboard\VendorsPage(),
        ];
    }

    /**
     * Register submenu items with ?tab= URLs.
     */
    public function register_submenu_items(): void {
        global $submenu;

        $capability = sk_admin_menu_capability();
        if ( ! current_user_can( $capability ) ) {
            return;
        }

        // Clear existing sk submenu items that use hash routes.
        if ( isset( $submenu['sk'] ) ) {
            $submenu['sk'] = [];
        }

        foreach ( $this->get_pages() as $page ) {
            if ( $page->is_pro() && ! sk()->is_pro_exists() ) {
                continue;
            }
            $submenu['sk'][] = [
                $page->get_title(),
                $page->get_capability(),
                'admin.php?page=sk&tab=' . $page->get_slug(),
            ];
        }
    }

    /**
     * Handle POST submissions.
     */
    public function handle_post(): void {
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
            return;
        }

        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'sk' ) {
            return;
        }

        $tab  = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'settings';
        $page = $this->get_page( $tab );

        if ( $page && current_user_can( $page->get_capability() ) ) {
            $page->handle_post();
        }
    }

    /**
     * Render the current tab.
     */
    public function render(): void {
        $tab          = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'settings';
        $current_page = $this->get_page( $tab );

        if ( ! $current_page ) {
            $current_page = $this->get_page( 'settings' );
            $tab          = 'settings';
        }

        // Build navigation tabs.
        $tabs = [];
        foreach ( $this->get_pages() as $page ) {
            if ( $page->is_pro() && ! sk()->is_pro_exists() ) {
                continue;
            }
            $tabs[ $page->get_slug() ] = $page->get_title();
        }

        include sk()->plugin_path() . '/templates/admin/php-dashboard/layout.php';
    }

    /**
     * Get a page by slug.
     */
    private function get_page( string $slug ): ?AbstractPage {
        $pages = $this->get_pages();
        return $pages[ $slug ] ?? null;
    }
}
