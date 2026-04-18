<?php

namespace SK\Modules\Payments\Dashboard;

use SK\Core\Dashboard\DashboardModule;

defined( 'ABSPATH' ) || exit;

class TransactionsPage extends DashboardModule {

    public function config(): ?array {
        return [
            'slug'       => 'lightning-transactions',
            'title'      => 'Käufe/Verkäufe',
            'icon'       => '<i class="fas fa-bolt"></i>',
            'pos'        => 31,
            'permission' => 'sk_view_overview_menu',
            'template'   => [ $this, 'render_dashboard' ],
        ];
    }

    public function render_dashboard( $query_vars ): void {
        require SK_PAYMENTS_TEMPLATES . '/dashboard-transactions.php';
    }
}
