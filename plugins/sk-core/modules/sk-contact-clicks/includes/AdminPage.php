<?php

namespace SK\Modules\ContactClicks;

use SK\Core\Admin\PhpDashboard\AbstractPage;

defined( 'ABSPATH' ) || exit;

/**
 * SK → Kontaktklicks.
 */
class AdminPage extends AbstractPage {

    public function get_slug(): string {
        return 'contact-clicks';
    }

    public function get_title(): string {
        return __( 'Kontaktklicks', 'sk-core' );
    }

    public function get_menu_position(): int {
        return 9;
    }

    public function render(): void {
        $days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;
        $days = in_array( $days, [ 7, 30, 90, 365 ], true ) ? $days : 30;

        $to   = current_time( 'Y-m-d' );
        $from = gmdate( 'Y-m-d', strtotime( $to . ' -' . ( $days - 1 ) . ' days' ) );

        $totals    = Stats::totals( $from, $to );
        $channels  = Stats::by_channel( $from, $to );
        $products  = Stats::top_products( $from, $to, 20 );
        $daily     = Stats::daily( $from, $to );
        $views     = Stats::total_views();
        $first_day = Stats::first_day();

        $labels = [
            'tg'    => 'Telegram',
            'nostr' => 'Nostr',
            'mail'  => 'E-Mail',
            'tel'   => 'Telefon',
            'x'     => 'X/Twitter',
            'chat'  => 'Chat',
            'web'   => 'Website',
        ];

        include SK_CC_PATH . '/templates/admin-contact-clicks.php';
    }

    public function handle_post(): void {
        // Nur Anzeige, nichts zu speichern.
    }
}
