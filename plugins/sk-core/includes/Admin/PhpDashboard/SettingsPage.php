<?php

namespace SK\Core\Admin\PhpDashboard;

use SK\Core\Admin\Settings;

class SettingsPage extends AbstractPage {

    public function get_slug(): string {
        return 'settings';
    }

    public function get_title(): string {
        return __( 'Settings', 'sk-core' );
    }

    public function get_menu_position(): int {
        return 1;
    }

    public function render(): void {
        $settings_instance = sk_get_container()->get( Settings::class );
        $sections          = $settings_instance->get_settings_sections();
        $all_fields        = $settings_instance->get_settings_fields();

        $current_section = isset( $_GET['section'] ) ? sanitize_text_field( $_GET['section'] ) : 'sk_general';

        // Validate section exists.
        $valid_section_ids = array_column( $sections, 'id' );
        if ( ! in_array( $current_section, $valid_section_ids, true ) ) {
            $current_section = 'sk_general';
        }

        $fields = isset( $all_fields[ $current_section ] ) ? $all_fields[ $current_section ] : [];
        $values = get_option( $current_section, [] );

        include sk()->plugin_path() . '/templates/admin/php-dashboard/settings.php';
    }

    public function handle_post(): void {
        if ( ! isset( $_POST['sk_php_settings_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['sk_php_settings_nonce'], 'sk_php_settings_save' ) ) {
            wp_die( __( 'Security check failed.', 'sk-core' ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'You do not have permission to save settings.', 'sk-core' ) );
        }

        $section = isset( $_POST['sk_settings_section'] ) ? sanitize_text_field( $_POST['sk_settings_section'] ) : '';
        if ( empty( $section ) ) {
            return;
        }

        $settings_instance = sk_get_container()->get( Settings::class );
        $all_fields        = $settings_instance->get_settings_fields();
        $field_defs        = isset( $all_fields[ $section ] ) ? $all_fields[ $section ] : [];
        $old_values        = get_option( $section, [] );

        $new_values = [];

        foreach ( $field_defs as $field ) {
            if ( in_array( $field['type'], [ 'sub_section' ], true ) ) {
                continue;
            }

            $name = $field['name'];

            if ( $field['type'] === 'multicheck' ) {
                $new_values[ $name ] = isset( $_POST[ $section ][ $name ] ) ? array_map( 'sanitize_text_field', (array) $_POST[ $section ][ $name ] ) : [];
            } elseif ( $field['type'] === 'wpeditor' ) {
                $new_values[ $name ] = isset( $_POST[ $section ][ $name ] ) ? wp_kses_post( $_POST[ $section ][ $name ] ) : '';
            } elseif ( $field['type'] === 'switcher' ) {
                $new_values[ $name ] = isset( $_POST[ $section ][ $name ] ) && $_POST[ $section ][ $name ] === 'on' ? 'on' : 'off';
            } elseif ( $field['type'] === 'number' ) {
                $new_values[ $name ] = isset( $_POST[ $section ][ $name ] ) ? sanitize_text_field( $_POST[ $section ][ $name ] ) : '';
            } elseif ( $field['type'] === 'charges' ) {
                $new_values[ $name ] = isset( $_POST[ $section ][ $name ] ) ? array_map( function( $method_charges ) {
                    return array_map( 'sanitize_text_field', (array) $method_charges );
                }, (array) $_POST[ $section ][ $name ] ) : [];
            } else {
                $new_values[ $name ] = isset( $_POST[ $section ][ $name ] ) ? sanitize_text_field( $_POST[ $section ][ $name ] ) : '';
            }
        }

        // Sanitize via Settings class if available.
        $new_values = $settings_instance->sanitize_options( $new_values, 'edit' );

        do_action( 'sk_before_saving_settings', $section, $new_values, $old_values );

        update_option( $section, $new_values );

        do_action( 'sk_after_saving_settings', $section, $new_values, $old_values );

        // Flush rewrite rules if custom_store_url changed.
        if ( $section === 'sk_general' ) {
            $old_store_url = isset( $old_values['custom_store_url'] ) ? $old_values['custom_store_url'] : '';
            $new_store_url = isset( $new_values['custom_store_url'] ) ? $new_values['custom_store_url'] : '';
            if ( $old_store_url !== $new_store_url ) {
                flush_rewrite_rules();
            }
        }

        wp_safe_redirect( add_query_arg( [
            'page'    => 'sk',
            'tab'     => 'settings',
            'section' => $section,
            'saved'   => 'true',
        ], admin_url( 'admin.php' ) ) );
        exit;
    }
}
