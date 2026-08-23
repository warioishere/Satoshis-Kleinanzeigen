<?php

namespace SK\Modules\Donations;

use SK\Core\Admin\PhpDashboard\AbstractPage;

defined( 'ABSPATH' ) || exit;

/**
 * SK → Spenden.
 */
class AdminPage extends AbstractPage {

    public function get_slug(): string {
        return 'donations';
    }

    public function get_title(): string {
        return __( 'Spenden', 'sk-core' );
    }

    public function get_menu_position(): int {
        return 8;
    }

    public function render(): void {
        $goal      = Donations::goal();
        $month     = Donations::received_this_month();
        $total     = Donations::received_total();
        $coverage  = Donations::coverage();
        $dashboard  = Placement::dashboard_enabled();
        $sold_modal = Placement::sold_modal_enabled();
        $notice    = isset( $_GET['sk_donations_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['sk_donations_notice'] ) ) : '';

        $orders = wc_get_orders(
            [
                'limit'      => 25,
                'orderby'    => 'date',
                'order'      => 'DESC',
                'meta_key'   => Donations::ORDER_FLAG,
                'meta_value' => 1,
            ]
        );

        // Zwölf Monate Verlauf, ältester zuerst.
        $history = [];
        for ( $i = 11; $i >= 0; $i-- ) {
            $start = gmdate( 'Y-m-01 00:00:00', strtotime( "-{$i} months", current_time( 'timestamp' ) ) );
            $end   = gmdate( 'Y-m-t 23:59:59', strtotime( $start ) );

            $history[ gmdate( 'Y-m', strtotime( $start ) ) ] = Donations::sum_between( $start, $end );
        }

        include SK_DONATIONS_PATH . '/templates/admin-donations.php';
    }

    public function handle_post(): void {
        if ( ! isset( $_POST['sk_donations_nonce'] ) || ! wp_verify_nonce( $_POST['sk_donations_nonce'], 'sk_donations_action' ) ) {
            return;
        }
        if ( ! current_user_can( $this->get_capability() ) ) {
            return;
        }

        $action = isset( $_POST['sk_donations_action'] ) ? sanitize_text_field( wp_unslash( $_POST['sk_donations_action'] ) ) : '';
        $notice = '';

        if ( $action === 'save' ) {
            Donations::set_goal( isset( $_POST['sk_donations_goal'] ) ? absint( $_POST['sk_donations_goal'] ) : 0 );
            update_option( Placement::OPTION_DASHBOARD, isset( $_POST['sk_donations_dashboard'] ) ? 1 : 0 );
            update_option( Placement::OPTION_SOLD_MODAL, isset( $_POST['sk_donations_sold_modal'] ) ? 1 : 0 );
            $notice = 'saved';
        }

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'                 => 'sk',
                    'tab'                  => $this->get_slug(),
                    'sk_donations_notice'  => $notice,
                ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }
}
