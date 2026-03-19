<?php

namespace SK\Core\Admin\PhpDashboard;

class StoreReviewsPage extends AbstractPage {

    public function get_slug(): string {
        return 'store-reviews';
    }

    public function get_title(): string {
        return __( 'Store Reviews', 'sk' );
    }

    public function is_pro(): bool {
        return true;
    }

    public function get_menu_position(): int {
        return 5;
    }

    public function render(): void {
        $paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $per_page = 20;

        $query = new \WP_Query( [
            'post_type'      => 'sk_store_reviews',
            'post_status'    => [ 'publish', 'pending', 'trash' ],
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        $reviews     = $query->posts;
        $total_items = $query->found_posts;
        $total_pages = $query->max_num_pages;

        include sk()->plugin_path() . '/templates/admin/php-dashboard/store-reviews.php';
    }

    public function handle_post(): void {
        if ( ! isset( $_POST['sk_review_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['sk_review_nonce'], 'sk_review_action' ) ) {
            wp_die( __( 'Security check failed.', 'sk' ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $action  = isset( $_POST['review_action'] ) ? sanitize_text_field( $_POST['review_action'] ) : '';
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

        if ( ! $post_id ) {
            return;
        }

        if ( $action === 'approve' ) {
            wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ] );
        } elseif ( $action === 'trash' ) {
            wp_trash_post( $post_id );
        }

        wp_safe_redirect( add_query_arg( [
            'page'  => 'sk',
            'tab'   => 'store-reviews',
            'saved' => 'true',
        ], admin_url( 'admin.php' ) ) );
        exit;
    }
}
