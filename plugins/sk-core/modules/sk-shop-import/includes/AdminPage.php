<?php

namespace SK\Modules\ShopImport;

use SK\Core\Admin\PhpDashboard\AbstractPage;

defined( 'ABSPATH' ) || exit;

/**
 * SK → Händler: prüfen und für den Katalogimport freischalten.
 */
class AdminPage extends AbstractPage {

    public function get_slug(): string {
        return 'dealers';
    }

    public function get_title(): string {
        return __( 'Händler', 'sk-core' );
    }

    public function get_menu_position(): int {
        return 10;
    }

    public function render(): void {
        $notice = isset( $_GET['sk_dealer_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['sk_dealer_notice'] ) ) : '';
        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

        $vendors = get_users(
            [
                'role'    => 'seller',
                'number'  => $search !== '' ? 40 : 0,
                'search'  => $search !== '' ? '*' . $search . '*' : '',
                'include' => $search === '' ? wp_list_pluck( Dealer::all(), 'ID' ) : [],
                'orderby' => 'display_name',
            ]
        );

        include SK_SHOP_IMPORT_PATH . '/templates/admin-dealers.php';
    }

    public function handle_post(): void {
        if ( ! isset( $_POST['sk_dealer_nonce'] ) || ! wp_verify_nonce( $_POST['sk_dealer_nonce'], 'sk_dealer_action' ) ) {
            return;
        }
        if ( ! current_user_can( $this->get_capability() ) ) {
            return;
        }

        $vendor_id = isset( $_POST['vendor_id'] ) ? absint( $_POST['vendor_id'] ) : 0;
        if ( $vendor_id > 0 ) {
            Dealer::set_verified( $vendor_id, ! empty( $_POST['verified'] ) );
            Dealer::set_enabled( $vendor_id, ! empty( $_POST['import'] ) );

            $url = isset( $_POST['shop_url'] ) ? esc_url_raw( wp_unslash( $_POST['shop_url'] ) ) : '';
            update_user_meta( $vendor_id, Dealer::META_SHOP_URL, $url );
        }

        wp_safe_redirect(
            add_query_arg(
                [ 'page' => 'sk', 'tab' => $this->get_slug(), 'sk_dealer_notice' => 'saved' ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }
}
