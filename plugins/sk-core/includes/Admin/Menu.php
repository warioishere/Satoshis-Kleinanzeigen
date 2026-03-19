<?php

namespace SK\Core\Admin;


class Menu {

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
    }

    /**
     * Add SK admin menu
     *
     *
     * @return void
     */
    public function add_admin_menu() {
        global $submenu;

        $capability = ska_admin_menu_capability();
        if ( ! current_user_can( $capability ) ) {
            return;
        }

        $menu_position = sk_admin_menu_position();
        $slug = 'sk';
        $menu_icon  = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTUiIGhlaWdodD0iMTgiIHZpZXdCb3g9IjAgMCAxNSAxOCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KICAgIDxwYXRoIGQ9Ik0xMC41NDU4IDguNjA0NjVDMTAuNTMzMyAxMS42OTA5IDkuMjA4ODMgMTQuODc3MiA2LjQ3MjQyIDE2LjMyNjZDNC41NDgxOSAxNy4zNTEyIDAgMTguMTM4NCAwIDE0LjgwMjJDMCAxNC44MDIyIDAgMi4zOTQ2MiAwIDIuMzgyMTNDMCAwLjUzMjg2NSAxLjU2MTg4IC0wLjA3OTM5MiAzLjEyMzc2IDAuMDA4MDczMkM1LjQ0NzgzIDAuMTMzMDI0IDcuNjcxOTUgMS4yMjAwOSA5LjAyMTQxIDMuMjE5M0M5Ljg4MzU3IDQuNDkzNzkgMTAuMzU4NCA2LjAzMDY4IDEwLjUwODMgNy41ODAwNkMxMC41MjA4IDcuOTI5OTIgMTAuNTQ1OCA4LjI2NzI5IDEwLjU0NTggOC42MDQ2NVoiIGZpbGw9IiM5RUEzQTgiIGZpbGwtb3BhY2l0eT0iMC41Ii8+CiAgICA8cGF0aCBkPSJNMTQuODU2MiA3LjUxNzY2QzE0LjU5MzggNC45Njg2NyAxMy4wMzIgMi44MzIwMiAxMC44NzAzIDEuNTQ1MDNDOS40MzMzOSAwLjY4Mjg3NSA3LjYzNDExIDAuNDcwNDYxIDYuNjk2OTggMi4xOTQ3OEM2LjY5Njk4IDIuMjA3MjcgMC4zODY5OSAxMy44MDI3IDAuMzg2OTkgMTMuODAyN0MtMC4xNjI3OTEgMTQuODE0OCAtMC4wMzc4NDAyIDE1LjY1MTkgMC40NjE5NjEgMTYuMzM5MkMxLjIxMTY2IDE3LjM2MzcgMi42OTg1NyAxNy44MjYxIDMuOTEwNTkgMTcuOTUxQzUuMDM1MTQgMTguMDc2IDYuMTcyMTkgMTcuOTUxIDcuMjU5MjYgMTcuNzAxMUMxMC41NDU1IDE2Ljk1MTQgMTMuNDU2OCAxNC43MTQ4IDE0LjQ0MzkgMTEuNDY2MUMxNC44MzEyIDEwLjE5MTYgMTQuOTkzNyA4Ljg1NDYzIDE0Ljg1NjIgNy41MTc2NloiIGZpbGw9IiM5RUEzQTgiLz4KICAgIDxwYXRoIGQ9Ik02LjQ3MjM3IDE2LjMzOTNDOS4yMDg3OCAxNC44Nzc0IDEwLjUzMzMgMTEuNjkxMiAxMC41NDU3IDguNjE3NDFDMTAuNTQ1NyA4LjI4MDA1IDEwLjUzMzMgNy45NDI2OCAxMC40OTU4IDcuNjA1MzJDMTAuMzU4MyA2LjA1NTkzIDkuODcxMDIgNC41MTkwNCA5LjAwODg2IDMuMjQ0NTVDOC41MzQwNSAyLjUzMjMzIDcuOTQ2NzggMS45NDUwNyA3LjI4NDU0IDEuNDU3NzZDNy4wNzIxMyAxLjY0NTE5IDYuODcyMjEgMS44OTUwOSA2LjcwOTc3IDIuMjA3NDdDNi43MDk3NyAyLjIxOTk2IDAuMzk5Nzg0IDEzLjgxNTMgMC4zOTk3ODQgMTMuODE1M0MtMC4wMTI1NTI1IDE0LjU2NSAtMC4wMzc1NDQ4IDE1LjIyNzMgMC4xNzQ4NzEgMTUuODAyMUMwLjE3NDg3MSAxNS44MTQ2IDAuMTg3MzY2IDE1LjgyNyAwLjE4NzM2NiAxNS44Mzk1QzAuMTk5ODYyIDE1Ljg2NDUgMC4yMTIzNTUgMTUuOTAyIDAuMjI0ODUgMTUuOTI3QzAuMjM3MzQ1IDE1Ljk1MiAwLjIzNzM0NyAxNS45NjQ1IDAuMjQ5ODQyIDE1Ljk4OTVDMC4yNDk4NDIgMTYuMDAyIDAuMjYyMzM3IDE2LjAxNDUgMC4yNjIzMzcgMTYuMDE0NUMxLjI0OTQ0IDE3LjkxMzcgNC44MjMwMiAxNy4yMTQgNi40NzIzNyAxNi4zMzkzWiIgZmlsbD0iIzlFQTNBOCIvPgogICAgPC9zdmc+';

        $dashboard = add_menu_page(
            __( 'SK', 'sk-core' ),
            __( 'SK', 'sk-core' ),
            $capability,
            $slug,
            [ $this, 'dashboard' ],
            $menu_icon,
            $menu_position
        );

        // Submenu items are now registered by PhpDashboard at priority 5.
        // Fire the hook so sk-pro can register its pages.
        do_action( 'sk_admin_menu', $capability, $menu_position );

        // phpcs:enable

        add_action( $dashboard, [ $this, 'dashboard_script' ] );
    }

    /**
     * Enqueue basic admin styles for the dashboard page.
     */
    public function dashboard_script() {
        wp_enqueue_style( 'sk-admin-css' );
    }

    /**
     * Load Dashboard Template
     *
     *
     * @return void
     */
    public function dashboard() {
        sk_get_container()->get( \SK\Core\Admin\PhpDashboard::class )->render();
    }
}
