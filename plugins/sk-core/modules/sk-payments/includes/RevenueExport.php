<?php

namespace SK\Modules\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Umsatzliste als Datei zum Herunterladen.
 */
final class RevenueExport {

    const ACTION = 'sk_payments_revenue_csv';

    public function __construct() {
        add_action( 'admin_post_' . self::ACTION, [ __CLASS__, 'download' ] );
    }

    public static function url( string $role, ?int $year ): string {
        return wp_nonce_url(
            add_query_arg(
                [
                    'action' => self::ACTION,
                    'rolle'  => $role,
                    'jahr'   => $year ?: '',
                ],
                admin_url( 'admin-post.php' )
            ),
            self::ACTION
        );
    }

    public static function download(): void {
        if ( ! is_user_logged_in() ) {
            wp_die( esc_html__( 'Nicht eingeloggt.', 'sk-core' ), '', [ 'response' => 401 ] );
        }

        check_admin_referer( self::ACTION );

        $user_id = get_current_user_id();

        /*
         * Die Auswertung gehoert erst ab dem Hai-Paket dazu, nicht schon ab
         * Delphin wie Import und Ausfuehrungen — deshalb eine eigene Grenze
         * und nicht is_shop_pack().
         */
        if ( ! class_exists( \SK\Modules\ShopImport\Variants::class )
            || ! \SK\Modules\ShopImport\Variants::revenue_allowed( $user_id ) ) {
            wp_die( esc_html__( 'Die Umsatzauswertung gehört ab dem Hai-Paket dazu.', 'sk-core' ), '', [ 'response' => 403 ] );
        }

        $role = isset( $_GET['rolle'] ) && $_GET['rolle'] === 'purchases' ? 'purchases' : 'sales';
        $year = isset( $_GET['jahr'] ) ? (int) $_GET['jahr'] : 0;

        $rows = Revenue::rows( $user_id, $role, $year ?: null );
        $csv  = Revenue::csv( $rows );

        $name = sprintf(
            '%s-%s.csv',
            $role === 'purchases' ? 'kaeufe' : 'verkaeufe',
            $year ?: 'alle'
        );

        nocache_headers();
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $name . '"' );
        header( 'Content-Length: ' . strlen( $csv ) );

        echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput
        exit;
    }
}
