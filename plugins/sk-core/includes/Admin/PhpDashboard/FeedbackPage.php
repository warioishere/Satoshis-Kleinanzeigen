<?php

namespace SK\Core\Admin\PhpDashboard;

use SK\Core\Dashboard\Modules\Feedback;

/**
 * Feedback tab in the SK dashboard — entries list + settings.
 *
 * Replaces the standalone "wpsf-admin" top level menu the feedback module
 * used to register on its own.
 */
class FeedbackPage extends AbstractPage {

    const PER_PAGE = 25;

    public function get_slug(): string {
        return 'feedback';
    }

    public function get_title(): string {
        return __( 'Feedback', 'sk-core' );
    }

    public function get_menu_position(): int {
        return 11;
    }

    /**
     * Active sub tab: entries | settings.
     */
    private function get_sub_tab(): string {
        $sub = isset( $_GET['sub'] ) ? sanitize_key( $_GET['sub'] ) : 'entries';

        return in_array( $sub, [ 'entries', 'settings' ], true ) ? $sub : 'entries';
    }

    public function render(): void {
        $sub      = $this->get_sub_tab();
        $opts     = Feedback::get_options();
        $base_url = admin_url( 'admin.php?page=sk&tab=feedback' );

        $paged       = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $entries     = [];
        $total_items = 0;
        $total_pages = 0;

        if ( 'entries' === $sub ) {
            $query = new \WP_Query( [
                'post_type'      => Feedback::CPT,
                'post_status'    => 'publish',
                'posts_per_page' => self::PER_PAGE,
                'paged'          => $paged,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ] );

            $entries     = $query->posts;
            $total_items = (int) $query->found_posts;
            $total_pages = (int) $query->max_num_pages;
        }

        include sk()->plugin_path() . '/templates/admin/php-dashboard/feedback.php';
    }

    public function handle_post(): void {
        if ( ! isset( $_POST['sk_feedback_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['sk_feedback_nonce'], 'sk_feedback_action' ) ) {
            wp_die( __( 'Security check failed.', 'sk-core' ) );
        }

        if ( ! current_user_can( $this->get_capability() ) ) {
            return;
        }

        $action = isset( $_POST['feedback_action'] ) ? sanitize_text_field( wp_unslash( $_POST['feedback_action'] ) ) : '';
        $args   = [ 'page' => 'sk', 'tab' => 'feedback' ];

        if ( 'save_settings' === $action ) {
            $raw = isset( $_POST[ Feedback::OPTS_KEY ] ) ? (array) wp_unslash( $_POST[ Feedback::OPTS_KEY ] ) : [];
            update_option( Feedback::OPTS_KEY, Feedback::sanitize_options( $raw ) );

            $args['sub']   = 'settings';
            $args['saved'] = 'true';
        } elseif ( 'delete_entry' === $action ) {
            $entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;

            if ( $entry_id && get_post_type( $entry_id ) === Feedback::CPT ) {
                wp_delete_post( $entry_id, true );
                $args['deleted'] = 'true';
            }

            $args['sub'] = 'entries';
        } else {
            return;
        }

        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }
}
