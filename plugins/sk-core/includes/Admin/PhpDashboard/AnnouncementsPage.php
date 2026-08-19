<?php

namespace SK\Core\Admin\PhpDashboard;

class AnnouncementsPage extends AbstractPage {

    public function get_slug(): string {
        return 'announcements';
    }

    public function get_title(): string {
        return __( 'Announcements', 'sk-core' );
    }

    public function is_pro(): bool {
        return true;
    }

    public function get_menu_position(): int {
        return 3;
    }

    public function render(): void {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
        $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

        if ( $action === 'new' || $action === 'edit' ) {
            $announcement = null;
            if ( $action === 'edit' && $post_id ) {
                $announcement = get_post( $post_id );
                if ( ! $announcement || $announcement->post_type !== 'sk_announcement' ) {
                    $announcement = null;
                }
            }
            include sk()->plugin_path() . '/templates/admin/php-dashboard/announcement-edit.php';
            return;
        }

        $paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $per_page = 20;

        $query = new \WP_Query( [
            'post_type'      => 'sk_announcement',
            'post_status'    => [ 'publish', 'draft', 'pending' ],
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        $announcements = $query->posts;
        $total_items   = $query->found_posts;
        $total_pages   = $query->max_num_pages;

        include sk()->plugin_path() . '/templates/admin/php-dashboard/announcements.php';
    }

    public function handle_post(): void {
        if ( ! isset( $_POST['sk_announcement_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['sk_announcement_nonce'], 'sk_announcement_save' ) ) {
            wp_die( __( 'Security check failed.', 'sk-core' ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $action  = isset( $_POST['announcement_action'] ) ? sanitize_text_field( $_POST['announcement_action'] ) : '';
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

        // wp_delete_post() and wp_update_post() operate on any post type, so a
        // tampered or stale post_id would delete a product or rewrite a page.
        if ( $post_id && 'sk_announcement' !== get_post_type( $post_id ) ) {
            wp_die( __( 'Invalid announcement.', 'sk-core' ) );
        }

        if ( $action === 'delete' ) {
            if ( $post_id ) {
                $manager = new \SK\Core\Announcement\Manager();
                $manager->delete_announcement( $post_id, true );
                wp_cache_set( 'sk_dcv_files', time(), 'sk_page_cache', 86400 );
            }
            wp_safe_redirect( add_query_arg( [
                'page'    => 'sk',
                'tab'     => 'announcements',
                'deleted' => 'true',
            ], admin_url( 'admin.php' ) ) );
            exit;
        }

        // Save / Update via Announcement Manager (handles vendor assignments).
        $title   = isset( $_POST['announcement_title'] ) ? sanitize_text_field( $_POST['announcement_title'] ) : '';
        $content = isset( $_POST['announcement_content'] ) ? wp_kses_post( $_POST['announcement_content'] ) : '';
        $status  = isset( $_POST['announcement_status'] ) ? sanitize_text_field( $_POST['announcement_status'] ) : 'draft';

        if ( ! in_array( $status, [ 'publish', 'draft' ], true ) ) {
            $status = 'draft';
        }

        $manager = new \SK\Core\Announcement\Manager();
        $args = [
            'title'             => $title,
            'content'           => $content,
            'status'            => $status,
            'announcement_type' => 'all_seller',
        ];

        if ( $post_id ) {
            $args['id'] = $post_id;
        }

        $manager->create_announcement( $args, (bool) $post_id );
        wp_cache_set( 'sk_dcv_files', time(), 'sk_page_cache', 86400 );

        wp_safe_redirect( add_query_arg( [
            'page'  => 'sk',
            'tab'   => 'announcements',
            'saved' => 'true',
        ], admin_url( 'admin.php' ) ) );
        exit;
    }
}
