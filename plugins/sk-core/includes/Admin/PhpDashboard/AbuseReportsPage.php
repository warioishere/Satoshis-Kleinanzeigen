<?php

namespace SK\Core\Admin\PhpDashboard;

class AbuseReportsPage extends AbstractPage {

    public function get_slug(): string {
        return 'abuse-reports';
    }

    public function get_title(): string {
        return __( 'Abuse Reports', 'sk-core' );
    }

    public function is_pro(): bool {
        return true;
    }

    public function get_menu_position(): int {
        return 9;
    }

    public function render(): void {
        global $wpdb;

        $paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $per_page = 20;
        $offset   = ( $paged - 1 ) * $per_page;
        $table    = $wpdb->prefix . 'sk_report_abuse_reports';

        // Check if table exists.
        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table;
        if ( ! $table_exists ) {
            $reports      = [];
            $total_items  = 0;
            $total_pages  = 0;
        } else {
            $total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
            $total_pages = ceil( $total_items / $per_page );

            $reports = $wpdb->get_results( $wpdb->prepare(
                "SELECT r.*, p.post_title as product_title
                 FROM {$table} r
                 LEFT JOIN {$wpdb->posts} p ON r.product_id = p.ID
                 ORDER BY r.id DESC
                 LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ) );
        }

        include sk()->plugin_path() . '/templates/admin/php-dashboard/abuse-reports.php';
    }

    public function handle_post(): void {
        if ( ! isset( $_POST['sk_abuse_report_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['sk_abuse_report_nonce'], 'sk_abuse_report_action' ) ) {
            wp_die( __( 'Security check failed.', 'sk-core' ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $action = isset( $_POST['report_action'] ) ? sanitize_text_field( $_POST['report_action'] ) : '';

        if ( $action === 'delete' ) {
            global $wpdb;
            $report_id = isset( $_POST['report_id'] ) ? absint( $_POST['report_id'] ) : 0;
            if ( $report_id ) {
                $wpdb->delete( $wpdb->prefix . 'sk_report_abuse_reports', [ 'id' => $report_id ], [ '%d' ] );
            }
        }

        wp_safe_redirect( add_query_arg( [
            'page'    => 'sk',
            'tab'     => 'abuse-reports',
            'deleted' => 'true',
        ], admin_url( 'admin.php' ) ) );
        exit;
    }
}
