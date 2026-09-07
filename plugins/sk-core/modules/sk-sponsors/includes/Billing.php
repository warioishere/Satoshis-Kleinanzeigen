<?php

namespace SK\Modules\Sponsors;

defined( 'ABSPATH' ) || exit;

/**
 * Monthly deduction from the sponsor balance.
 *
 * DISABLED by default (option sk_sponsors_billing_enabled). As long as it's
 * off, nothing happens: no deduction, and is_running() doesn't check the
 * balance. This lets the sponsor placement run in production before pricing
 * has been decided.
 *
 * Billed per calendar month, not per time interval: the run remembers the
 * last billed month per sponsor. This way a double-triggered cron doesn't
 * charge twice, and a missed cron catches up.
 */
final class Billing {

    const OPTION_ENABLED  = 'sk_sponsors_billing_enabled';
    const CRON_HOOK       = 'sk_sponsors_run_billing';
    const META_LAST_MONTH = '_sk_sponsor_last_charged_month';

    public function __construct() {
        add_action( self::CRON_HOOK, [ __CLASS__, 'run' ] );
        // The backlink check runs in the same daily run so the column
        // stays current without a manual click.
        add_action( self::CRON_HOOK, [ Backlink::class, 'check_batch' ], 20 );

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
        }
    }

    public static function is_enabled(): bool {
        return (bool) get_option( self::OPTION_ENABLED, false );
    }

    /**
     * Charges the monthly rate for every paying sponsor.
     *
     * @return array{charged:int,sats:int,exhausted:int}
     */
    public static function run(): array {
        $result = [ 'charged' => 0, 'sats' => 0, 'exhausted' => 0 ];

        if ( ! self::is_enabled() ) {
            return $result;
        }

        $month = current_time( 'Y-m' );

        $sponsors = get_posts(
            [
                'post_type'      => PostType::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
            ]
        );

        foreach ( $sponsors as $sponsor ) {
            $monthly = (int) get_post_meta( $sponsor->ID, PostType::META_MONTHLY, true );
            if ( $monthly <= 0 ) {
                continue; // Free placement.
            }

            if ( (string) get_post_meta( $sponsor->ID, self::META_LAST_MONTH, true ) === $month ) {
                continue; // Already billed this month.
            }

            $balance = (int) get_post_meta( $sponsor->ID, PostType::META_BALANCE, true );
            $taken   = min( $monthly, $balance );
            $rest    = $balance - $taken;

            update_post_meta( $sponsor->ID, PostType::META_BALANCE, $rest );
            update_post_meta( $sponsor->ID, self::META_LAST_MONTH, $month );

            self::log( (int) $sponsor->ID, -$taken, $rest, sprintf( 'Monatsrate %s', $month ) );

            $result['charged']++;
            $result['sats'] += $taken;

            if ( $rest < $monthly ) {
                $result['exhausted']++;
                /**
                 * The balance is no longer enough for the next month.
                 * A reminder can be hooked in here later.
                 */
                do_action( 'sk_sponsors_balance_low', (int) $sponsor->ID, $rest, $monthly );
            }
        }

        return $result;
    }

    /**
     * Top up balance (prepayment).
     */
    public static function top_up( int $sponsor_id, int $sats, string $note = '' ): int {
        $sats = max( 0, $sats );
        if ( $sats === 0 ) {
            return (int) get_post_meta( $sponsor_id, PostType::META_BALANCE, true );
        }

        $new = (int) get_post_meta( $sponsor_id, PostType::META_BALANCE, true ) + $sats;
        update_post_meta( $sponsor_id, PostType::META_BALANCE, $new );
        self::log( $sponsor_id, $sats, $new, $note !== '' ? $note : __( 'Guthaben aufgeladen', 'sk-core' ) );

        return $new;
    }

    /**
     * Every balance movement is logged.
     *
     * Without a record, a paying partner can't be shown what their balance
     * was spent on — and a dispute about that is more costly than the table.
     */
    public static function log( int $sponsor_id, int $delta, int $balance_after, string $note ): void {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'sk_sponsor_ledger',
            [
                'sponsor_id'    => $sponsor_id,
                'delta_sats'    => $delta,
                'balance_after' => $balance_after,
                'note'          => mb_substr( $note, 0, 190 ),
                'created_at'    => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%d', '%s', '%s' ]
        );
    }

    /**
     * @return array<int,object>
     */
    public static function ledger( int $sponsor_id, int $limit = 24 ): array {
        global $wpdb;

        return (array) $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . $wpdb->prefix . 'sk_sponsor_ledger WHERE sponsor_id = %d ORDER BY id DESC LIMIT %d',
                $sponsor_id,
                $limit
            )
        );
    }

    public static function unschedule(): void {
        $next = wp_next_scheduled( self::CRON_HOOK );
        if ( $next ) {
            wp_unschedule_event( $next, self::CRON_HOOK );
        }
    }
}
