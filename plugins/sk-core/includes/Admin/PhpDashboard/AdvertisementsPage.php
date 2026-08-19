<?php

namespace SK\Core\Admin\PhpDashboard;

class AdvertisementsPage extends AbstractPage {

    public function get_slug(): string {
        return 'advertisements';
    }

    public function get_title(): string {
        return __( 'Advertisements', 'sk-core' );
    }

    public function is_pro(): bool {
        return true;
    }

    public function get_menu_position(): int {
        return 8;
    }

    public function render(): void {
        global $wpdb;

        $paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $per_page = 20;
        $offset   = ( $paged - 1 ) * $per_page;
        $table    = $wpdb->prefix . 'sk_advertised_products';

        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table;
        if ( ! $table_exists ) {
            $advertisements = [];
            $total_items    = 0;
            $total_pages    = 0;
        } else {
            $total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
            $total_pages = ceil( $total_items / $per_page );

            $advertisements = $wpdb->get_results( $wpdb->prepare(
                "SELECT a.*, p.post_title as product_title, p.post_author as vendor_id
                 FROM {$table} a
                 LEFT JOIN {$wpdb->posts} p ON a.product_id = p.ID
                 ORDER BY a.id DESC
                 LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ) );
        }

        include sk()->plugin_path() . '/templates/admin/php-dashboard/advertisements.php';
    }

    public function handle_post(): void {
        // Advertisements are managed via the module.
    }
}
