<?php

namespace SK\Core\Admin\PhpDashboard;

class ModulesPage extends AbstractPage {

    public function get_slug(): string {
        return 'modules';
    }

    public function get_title(): string {
        return __( 'Modules', 'sk-core' );
    }

    public function is_pro(): bool {
        return true;
    }

    public function get_menu_position(): int {
        return 4;
    }

    public function render(): void {
        $all_modules    = [];
        $active_modules = [];

        if ( function_exists( 'sk_ext' ) && sk_ext()->module ) {
            $all_modules    = sk_ext()->module->get_all_modules();
            $active_modules = sk_ext()->module->get_active_modules();
        }

        include sk()->plugin_path() . '/templates/admin/php-dashboard/modules.php';
    }

    public function handle_post(): void {
        // Modules use AJAX toggle, not form POST.
    }

    /**
     * Register AJAX handler for module toggle.
     */
    public static function register_ajax(): void {
        add_action( 'wp_ajax_sk_php_toggle_module', [ __CLASS__, 'ajax_toggle_module' ] );
    }

    public static function ajax_toggle_module(): void {
        check_ajax_referer( 'sk_php_toggle_module', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $module_id = isset( $_POST['module_id'] ) ? sanitize_text_field( $_POST['module_id'] ) : '';
        $active    = isset( $_POST['active'] ) ? sanitize_text_field( $_POST['active'] ) : '';

        if ( empty( $module_id ) || ! function_exists( 'sk_ext' ) || ! sk_ext()->module ) {
            wp_send_json_error( 'Invalid request' );
        }

        if ( $active === '1' ) {
            sk_ext()->module->activate_modules( [ $module_id ] );
        } else {
            sk_ext()->module->deactivate_modules( [ $module_id ] );
        }

        wp_send_json_success();
    }
}
