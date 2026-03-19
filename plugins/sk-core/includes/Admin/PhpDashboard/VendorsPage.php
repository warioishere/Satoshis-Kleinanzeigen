<?php

namespace SK\Core\Admin\PhpDashboard;

class VendorsPage extends AbstractPage {

    public function get_slug(): string {
        return 'vendors';
    }

    public function get_title(): string {
        return __( 'Vendors', 'sk-core' );
    }

    public function get_menu_position(): int {
        return 2;
    }

    public function render(): void {
        $paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $per_page = 20;
        $offset   = ( $paged - 1 ) * $per_page;

        $args = [
            'role'    => 'seller',
            'number'  => $per_page,
            'offset'  => $offset,
            'orderby' => 'registered',
            'order'   => 'DESC',
        ];

        $search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        if ( ! empty( $search ) ) {
            $args['search']         = '*' . $search . '*';
            $args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
        }

        $user_query  = new \WP_User_Query( $args );
        $vendors     = $user_query->get_results();
        $total_items = $user_query->get_total();
        $total_pages = ceil( $total_items / $per_page );

        include sk()->plugin_path() . '/templates/admin/php-dashboard/vendors.php';
    }

    public function handle_post(): void {
        if ( ! isset( $_POST['sk_vendor_action_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['sk_vendor_action_nonce'], 'sk_vendor_action' ) ) {
            wp_die( __( 'Security check failed.', 'sk-core' ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $action  = isset( $_POST['vendor_action'] ) ? sanitize_text_field( $_POST['vendor_action'] ) : '';
        $user_id = isset( $_POST['vendor_id'] ) ? absint( $_POST['vendor_id'] ) : 0;

        if ( ! $user_id ) {
            return;
        }

        if ( $action === 'enable_selling' ) {
            update_user_meta( $user_id, 'sk_enable_selling', 'yes' );
        } elseif ( $action === 'disable_selling' ) {
            update_user_meta( $user_id, 'sk_enable_selling', 'no' );
        }

        wp_safe_redirect( add_query_arg( [
            'page'  => 'sk',
            'tab'   => 'vendors',
            'saved' => 'true',
        ], admin_url( 'admin.php' ) ) );
        exit;
    }
}
