<?php

namespace SK\Modules\Reputation;

defined( 'ABSPATH' ) || exit;

/**
 * The operator's view of the Nostr reports: the admin page, the "fetch
 * now" button and the daily mail about new reports. Reading the relays
 * and deciding what counts is Reports' job.
 */
final class ReportsAdmin {

    const PAGE          = 'sk-reputation';
    const FETCH_ACTION  = 'sk_reputation_fetch_reports';
    const MAIL_THROTTLE = 'sk_reputation_reports_mail';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ], 25 );
        add_action( 'admin_post_' . self::FETCH_ACTION, [ $this, 'handle_fetch_now' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    public function add_menu(): void {
        add_submenu_page(
            'sk',
            __( 'SK Reputation', 'sk-core' ),
            __( 'SK Reputation', 'sk-core' ),
            'manage_options',
            self::PAGE,
            [ $this, 'render_page' ]
        );
    }

    public function enqueue( string $hook_suffix ): void {
        if ( false === strpos( $hook_suffix, self::PAGE ) ) {
            return;
        }

        wp_enqueue_style(
            'sk-reputation-admin',
            SK_REPUTATION_URL . '/assets/css/sk-reputation-admin.css',
            [],
            SK_REPUTATION_VERSION
        );
    }

    public function render_page(): void {
        $vendors  = Reports::vendors_with_reports();
        $last_run = get_option( Reports::RUN_OPTION, [] );

        require SK_REPUTATION_TEMPLATES . '/admin-reports.php';
    }

    /**
     * "Jetzt abrufen": the run happens in this request. With a handful of
     * relays and pages it takes seconds; the button says so.
     */
    public function handle_fetch_now(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Keine Berechtigung.', 'sk-core' ) );
        }

        check_admin_referer( self::FETCH_ACTION );

        Reports::fetch();

        wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE . '&fetched=1' ) );
        exit;
    }

    /**
     * One mail a day at most, naming up to twenty new reports.
     *
     * @param array<int, array> $new Reports with their vendor_id.
     */
    public static function notify( array $new ): void {
        if ( empty( $new ) || get_transient( self::MAIL_THROTTLE ) ) {
            return;
        }

        set_transient( self::MAIL_THROTTLE, 1, DAY_IN_SECONDS );

        $lines = [];

        foreach ( array_slice( $new, 0, 20 ) as $r ) {
            $vendor   = get_userdata( (int) $r['vendor_id'] );
            $reporter = substr( $r['reporter'], 0, 12 ) . '…';

            if ( ! empty( $r['reporter_user'] ) ) {
                $by       = get_userdata( (int) $r['reporter_user'] );
                $reporter = sprintf(
                    /* translators: 1: vendor user id, 2: vendor display name, 3: shortened Nostr key */
                    __( 'Anbieter #%1$d %2$s (%3$s)', 'sk-core' ),
                    (int) $r['reporter_user'],
                    $by ? $by->display_name : '?',
                    $reporter
                );
            }

            $lines[] = sprintf(
                /* translators: 1: reported vendor name, 2: vendor user id, 3: report type, 4: reporter, 5: date, 6: quoted report text or empty */
                __( "%1\$s (#%2\$d): %3\$s von %4\$s am %5\$s\n%6\$s", 'sk-core' ),
                $vendor ? $vendor->display_name : '?',
                $r['vendor_id'],
                $r['type'],
                $reporter,
                wp_date( 'd.m.Y', $r['created_at'] ),
                $r['content'] !== '' ? '  „' . $r['content'] . '“' : ''
            );
        }

        wp_mail(
            get_option( 'admin_email' ),
            /* translators: %d: number of new reports */
            sprintf( __( '[SK Reputation] %d neue Nostr-Meldungen', 'sk-core' ), count( $new ) ),
            implode( "\n\n", $lines ) . "\n\n" . admin_url( 'admin.php?page=' . self::PAGE )
        );
    }
}
