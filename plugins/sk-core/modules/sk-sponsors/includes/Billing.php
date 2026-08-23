<?php

namespace SK\Modules\Sponsors;

defined( 'ABSPATH' ) || exit;

/**
 * Monatliche Abbuchung vom Sponsorenguthaben.
 *
 * Standardmäßig ABGESCHALTET (Option sk_sponsors_billing_enabled). Solange sie
 * aus ist, passiert nichts: keine Abbuchung, und is_running() prüft das
 * Guthaben nicht. So kann die Sponsorenfläche produktiv laufen, bevor über
 * Preise entschieden ist.
 *
 * Abgerechnet wird pro Kalendermonat, nicht per Zeitintervall: Der Lauf merkt
 * sich je Sponsor den zuletzt abgerechneten Monat. Damit bucht ein doppelt
 * ausgelöster Cron nicht zweimal ab, und ein ausgefallener Cron holt nach.
 */
final class Billing {

    const OPTION_ENABLED  = 'sk_sponsors_billing_enabled';
    const CRON_HOOK       = 'sk_sponsors_run_billing';
    const META_LAST_MONTH = '_sk_sponsor_last_charged_month';

    public function __construct() {
        add_action( self::CRON_HOOK, [ __CLASS__, 'run' ] );
        // Ruecklinkpruefung laeuft im selben taeglichen Lauf mit, damit die
        // Spalte auch ohne Knopfdruck aktuell bleibt.
        add_action( self::CRON_HOOK, [ Backlink::class, 'check_batch' ], 20 );

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
        }
    }

    public static function is_enabled(): bool {
        return (bool) get_option( self::OPTION_ENABLED, false );
    }

    /**
     * Bucht für jeden zahlenden Sponsor die Monatsrate ab.
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
                continue; // Gratisplatz.
            }

            if ( (string) get_post_meta( $sponsor->ID, self::META_LAST_MONTH, true ) === $month ) {
                continue; // In diesem Monat schon abgerechnet.
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
                 * Das Guthaben reicht nicht mehr für den nächsten Monat.
                 * Hier lässt sich später eine Erinnerung anhängen.
                 */
                do_action( 'sk_sponsors_balance_low', (int) $sponsor->ID, $rest, $monthly );
            }
        }

        return $result;
    }

    /**
     * Guthaben aufladen (Vorkasse).
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
     * Jede Guthabenbewegung wird protokolliert.
     *
     * Ohne Beleg lässt sich einem zahlenden Partner nicht erklären, wofür sein
     * Guthaben verbraucht wurde — und Streit darüber ist teurer als die Tabelle.
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
