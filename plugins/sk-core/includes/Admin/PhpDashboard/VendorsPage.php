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
            // Suche über User-Felder + sk_store_name Meta zusammenführen.
            $by_user_fields = new \WP_User_Query( [
                'role'           => 'seller',
                'search'         => '*' . $search . '*',
                'search_columns' => [ 'user_login', 'user_email', 'display_name', 'user_nicename' ],
                'fields'         => 'ID',
                'number'         => -1,
            ] );

            $by_store_name = new \WP_User_Query( [
                'role'       => 'seller',
                'meta_query' => [ [
                    'key'     => 'sk_store_name',
                    'value'   => $search,
                    'compare' => 'LIKE',
                ] ],
                'fields'     => 'ID',
                'number'     => -1,
            ] );

            $ids = array_unique( array_merge(
                (array) $by_user_fields->get_results(),
                (array) $by_store_name->get_results()
            ) );

            // `include` leer == alle — daher Placeholder 0 wenn Suche nichts findet.
            $args['include'] = empty( $ids ) ? [ 0 ] : $ids;
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

        $notice = 'saved';

        if ( $action === 'enable_selling' ) {
            update_user_meta( $user_id, 'sk_enable_selling', 'yes' );
        } elseif ( $action === 'disable_selling' ) {
            update_user_meta( $user_id, 'sk_enable_selling', 'no' );
        } elseif ( $action === 'draft_products' ) {
            $count = $this->set_vendor_products_to_draft( $user_id );
            $notice = 'drafted_' . $count;
        }

        wp_safe_redirect( add_query_arg( [
            'page'  => 'sk',
            'tab'   => 'vendors',
            'saved' => $notice,
        ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Set all published products of a vendor to draft.
     *
     * @return int Number of products moved.
     */
    private function set_vendor_products_to_draft( int $user_id ): int {
        $product_ids = get_posts( [
            'post_type'      => 'product',
            'author'         => $user_id,
            'post_status'    => 'publish',
            'numberposts'    => -1,
            'fields'         => 'ids',
            'suppress_filters' => true,
        ] );

        $count = 0;
        foreach ( $product_ids as $pid ) {
            $res = wp_update_post( [ 'ID' => (int) $pid, 'post_status' => 'draft' ], true );
            if ( ! is_wp_error( $res ) ) {
                $count++;
            }
        }
        return $count;
    }
}
