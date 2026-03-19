<?php

namespace SK\Core\Admin\PhpDashboard;

class SubscriptionsPage extends AbstractPage {

    public function get_slug(): string {
        return 'subscriptions';
    }

    public function get_title(): string {
        return __( 'Subscriptions', 'sk' );
    }

    public function is_pro(): bool {
        return true;
    }

    public function get_menu_position(): int {
        return 6;
    }

    public function render(): void {
        $paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $per_page = 20;

        $products    = [];
        $total_items = 0;
        $total_pages = 0;

        if ( function_exists( 'wc_get_products' ) ) {
            $products = wc_get_products( [
                'type'   => 'product_pack',
                'limit'  => $per_page,
                'page'   => $paged,
                'return' => 'objects',
            ] );

            // Count total for pagination.
            $count_products = wc_get_products( [
                'type'   => 'product_pack',
                'limit'  => -1,
                'return' => 'ids',
            ] );
            $total_items = count( $count_products );
            $total_pages = ceil( $total_items / $per_page );
        }

        include sk()->plugin_path() . '/templates/admin/php-dashboard/subscriptions.php';
    }

    public function handle_post(): void {
        // Subscriptions are WC products — managed via WC product editor.
    }
}
