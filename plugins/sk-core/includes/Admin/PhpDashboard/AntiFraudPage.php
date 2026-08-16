<?php

namespace SK\Core\Admin\PhpDashboard;

use SK\Modules\AntiFraud\AntifraudSettings;
use SK\Modules\AntiFraud\Suspension;

/**
 * Anti-Fraud tab in the SK dashboard — settings plus the suspension list.
 *
 * The settings used to sit in SK → Einstellungen; they moved here so the
 * configuration and the vendors it took offline are in one place.
 */
class AntiFraudPage extends AbstractPage {

    public function get_slug(): string {
        return 'antifraud';
    }

    public function get_title(): string {
        return __( 'Anti-Fraud', 'sk-core' );
    }

    public function get_menu_position(): int {
        return 12;
    }

    /**
     * Only meaningful while the module is loaded.
     */
    public static function is_available(): bool {
        return class_exists( AntifraudSettings::class );
    }

    private function get_sub_tab(): string {
        $sub = isset( $_GET['sub'] ) ? sanitize_key( $_GET['sub'] ) : 'general';

        return in_array( $sub, [ 'general', 'suspended' ], true ) ? $sub : 'general';
    }

    public function render(): void {
        $sub      = $this->get_sub_tab();
        $base_url = admin_url( 'admin.php?page=sk&tab=antifraud' );

        $fields    = AntifraudSettings::get_fields();
        $opts      = AntifraudSettings::get_options();
        $suspended = Suspension::get_suspended();

        include sk()->plugin_path() . '/templates/admin/php-dashboard/antifraud.php';
    }

    public function handle_post(): void {
        if ( ! isset( $_POST['sk_antifraud_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['sk_antifraud_nonce'], 'sk_antifraud_action' ) ) {
            wp_die( __( 'Security check failed.', 'sk-core' ) );
        }

        if ( ! current_user_can( $this->get_capability() ) ) {
            return;
        }

        $action = isset( $_POST['antifraud_action'] ) ? sanitize_text_field( wp_unslash( $_POST['antifraud_action'] ) ) : '';
        $args   = [ 'page' => 'sk', 'tab' => 'antifraud' ];

        if ( 'save_settings' === $action ) {
            $raw    = isset( $_POST['sk_antifraud'] ) ? (array) wp_unslash( $_POST['sk_antifraud'] ) : [];
            $values = [];

            foreach ( AntifraudSettings::get_fields() as $name => $field ) {
                if ( 'sub_section' === $field['type'] ) {
                    continue;
                }

                if ( 'switcher' === $field['type'] ) {
                    $values[ $name ] = ( isset( $raw[ $name ] ) && 'on' === $raw[ $name ] ) ? 'on' : 'off';
                } else {
                    $values[ $name ] = sanitize_text_field( $raw[ $name ] ?? '' );
                }
            }

            update_option( AntifraudSettings::OPTION, $values );

            $args['sub']   = 'general';
            $args['saved'] = 'true';

        } elseif ( 'unsuspend' === $action ) {
            $vendor_id = isset( $_POST['vendor_id'] ) ? absint( $_POST['vendor_id'] ) : 0;

            if ( $vendor_id ) {
                $restored          = Suspension::unsuspend( $vendor_id );
                $args['restored']  = $restored;
            }

            $args['sub'] = 'suspended';

        } else {
            return;
        }

        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }
}
