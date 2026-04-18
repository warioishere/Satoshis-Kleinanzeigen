<?php

namespace SK\Core\Dashboard;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract base class for dashboard modules.
 *
 * Subclasses declare their dashboard entry via config() and register
 * any extra hooks (AJAX, enqueue, etc.) via register_extras().
 *
 * Usage:
 *
 *   class Merkliste extends DashboardModule {
 *       public function config(): ?array {
 *           return [
 *               'slug'       => 'merkliste',
 *               'title'      => __( 'Merkliste', 'sk-core' ),
 *               'icon'       => '<i class="fas fa-thumbtack"></i>',
 *               'pos'        => 56,
 *               'permission' => 'sk_view_overview_menu',
 *               'template'   => 'dashboard/merkliste/dashboard-merkliste',
 *           ];
 *       }
 *
 *       protected function register_extras(): void {
 *           add_action( 'wp_ajax_sk_merkliste_remove', [ $this, 'ajax_remove' ] );
 *       }
 *   }
 */
abstract class DashboardModule {

    public function __construct() {
        DashboardRegistry::register_module( $this );
        $this->register_extras();
    }

    /**
     * Declarative config for this module's dashboard entry.
     *
     * Evaluated lazily on each dashboard render so modules can gate
     * registration on runtime context (e.g. current user, feature flags).
     * Return null or empty array to skip registration this request.
     *
     * Recognized keys:
     *   slug       — unique registry key (required)
     *   title      — sidebar label (required for visible menus)
     *   icon       — HTML icon markup
     *   url_slug   — URL segment (defaults to slug)
     *   url        — fully qualified URL override
     *   pos        — sort position in sidebar
     *   permission — WP capability required
     *   template   — sk_get_template_part() path
     *   parent     — if set, entry is a settings tab under this parent
     *   heading    — (tabs) page heading
     *   helper     — (tabs) helper text
     *
     * @return array|null
     */
    abstract public function config(): ?array;

    /**
     * Override to register module-specific hooks (AJAX, enqueue, etc.)
     * that are outside the dashboard nav pattern.
     */
    protected function register_extras(): void {}
}
