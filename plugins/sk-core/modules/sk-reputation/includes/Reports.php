<?php

namespace SK\Modules\Reputation;

use SK\Core\Trust\VendorKey;

defined( 'ABSPATH' ) || exit;

/**
 * Nostr reports (kind 1984, NIP-56) against vendors — the operator's view.
 *
 * Buyers see reports only from their own contacts, in their browser (see
 * sk-social-graph.js). This class serves moderation: once a day it reads
 * the reports against every proven vendor key from the site's relays and
 * keeps those whose reporter sits in the marketplace's web of trust — the
 * keys the marketplace key follows, the keys those follow, and the
 * proven keys of registered vendors. Anything else is noise: the reports
 * found in the wild were an automated NSFW bot, an empty profanity report
 * and a "wrong click", none of them from anyone known.
 *
 * Nothing here runs while a page renders, and nothing is shown to buyers.
 */
class Reports {

    const CRON_HOOK   = 'sk_reputation_fetch_reports';
    const REPORTS_META = 'sk_nostr_reports';
    const TIME_META   = 'sk_nostr_reports_time';
    const WOT_KEY     = 'sk_reputation_wot';
    const RUN_OPTION  = 'sk_reputation_reports_run';
    const MAIL_THROTTLE = 'sk_reputation_reports_mail';

    /** Formerly the ids of every kept report; the stored reports carry them now. */
    const LEGACY_SEEN_OPTION = 'sk_reputation_report_ids';

    /** Report types that matter on a classifieds site. */
    const TYPES = [ 'spam', 'impersonation', 'illegal', 'malware' ];

    /** Reports older than this are ignored, and stored ones expire. */
    const MAX_AGE = 365 * DAY_IN_SECONDS;

    /**
     * A run normally reads only what arrived since the last one, with this
     * much overlap for late deliveries and skewed clocks; every so often a
     * run covers the whole MAX_AGE window again.
     */
    const SINCE_MARGIN  = 2 * DAY_IN_SECONDS;
    const FULL_INTERVAL = WEEK_IN_SECONDS;

    const RELAY_TIMEOUT   = 10;
    const KEYS_PER_FILTER = 50;

    /**
     * One filter per REQ, paged with `until` while a page comes back full,
     * so a flood against one key cannot push the reports against another
     * key in the same chunk out of a shared limit. Pages and total are
     * capped: a flood costs time, not correctness, and never skips a relay.
     */
    const PAGE_LIMIT  = 500;
    const MAX_PAGES   = 4;
    const MAX_EVENTS  = 10000;

    /** Web of trust keys are kept as this many leading hex characters. */
    const WOT_PREFIX = 16;

    public function __construct() {
        add_action( self::CRON_HOOK, [ __CLASS__, 'fetch' ] );
        add_action( 'admin_menu', [ $this, 'add_menu' ], 25 );
        add_action( 'admin_post_sk_reputation_fetch_reports', [ $this, 'handle_fetch_now' ] );

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            self::schedule();
        }
    }

    public static function schedule(): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
        }
    }

    public static function unschedule(): void {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    // ── Admin ────────────────────────────────────────────────────────────

    public function add_menu(): void {
        add_submenu_page(
            'sk',
            __( 'SK Reputation', 'sk-core' ),
            __( 'SK Reputation', 'sk-core' ),
            'manage_options',
            'sk-reputation',
            [ $this, 'render_page' ]
        );
    }

    public function render_page(): void {
        $vendors  = self::vendors_with_reports();
        $last_run = get_option( self::RUN_OPTION, [] );

        require SK_REPUTATION_TEMPLATES . '/admin-reports.php';
    }

    public function handle_fetch_now(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Keine Berechtigung.' );
        }

        check_admin_referer( 'sk_reputation_fetch_reports' );

        self::fetch();

        wp_safe_redirect( admin_url( 'admin.php?page=sk-reputation&fetched=1' ) );
        exit;
    }

    /**
     * @return array<int, array{vendor: \WP_User, reports: array, time: int}>
     */
    public static function vendors_with_reports(): array {
        global $wpdb;

        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value <> ''",
            self::REPORTS_META
        ) );

        $out = [];

        foreach ( $ids as $id ) {
            $user    = get_userdata( (int) $id );
            $reports = self::for_vendor( (int) $id );

            if ( $user && ! empty( $reports ) ) {
                $out[ (int) $id ] = [
                    'vendor'  => $user,
                    'reports' => $reports,
                    'time'    => (int) get_user_meta( (int) $id, self::TIME_META, true ),
                ];
            }
        }

        uasort( $out, static fn( $a, $b ) => count( $b['reports'] ) <=> count( $a['reports'] ) );

        return $out;
    }

    /** @return array<int, array{id: string, reporter: string, type: string, created_at: int, content: string}> */
    public static function for_vendor( int $vendor_id ): array {
        $raw = get_user_meta( $vendor_id, self::REPORTS_META, true );

        return is_array( $raw ) ? $raw : [];
    }

    // ── The fetch ────────────────────────────────────────────────────────

    /**
     * Cron: read the reports against every proven vendor key, keep the
     * ones from the web of trust, merge them into what is stored per
     * vendor, mail the new ones.
     *
     * Merge, never replace: what a run does not see again — because a
     * relay was down, or because a flood of junk filled the page — stays.
     * A stored report leaves only by age (MAX_AGE).
     */
    public static function fetch(): void {
        $started  = microtime( true );
        $now      = time();
        $last_run = (array) get_option( self::RUN_OPTION, [] );
        $targets  = self::vendor_keys();

        delete_option( self::LEGACY_SEEN_OPTION );

        if ( empty( $targets ) ) {
            update_option( self::RUN_OPTION, [ 'time' => $now, 'targets' => 0, 'events' => 0, 'kept' => 0, 'wot' => 0 ] + $last_run, false );

            return;
        }

        $wot = self::wot();

        // Incremental since the last run, a full pass once a week.
        $full  = empty( $last_run['time'] ) || empty( $last_run['full_time'] ) || $now - (int) $last_run['full_time'] > self::FULL_INTERVAL;
        $since = $full ? $now - self::MAX_AGE : max( $now - self::MAX_AGE, (int) $last_run['time'] - self::SINCE_MARGIN );

        $events = [];
        $pages  = 0;

        foreach ( self::relays() as $relay ) {
            foreach ( array_chunk( array_keys( $targets ), self::KEYS_PER_FILTER ) as $chunk ) {
                $pages += self::read_pages( $relay, $chunk, $since, $events );
            }
        }

        // What each vendor already has, by report id.
        $stored = [];

        foreach ( array_unique( $targets ) as $vendor_id ) {
            foreach ( self::for_vendor( $vendor_id ) as $report ) {
                if ( is_array( $report ) && is_string( $report['id'] ?? null ) ) {
                    $stored[ $vendor_id ][ $report['id'] ] = $report;
                }
            }
        }

        $new = [];

        foreach ( $events as $event ) {
            $report = self::accept( $event, $targets, $wot );

            if ( null === $report ) {
                continue;
            }

            $vendor_id = $report['vendor_id'];
            unset( $report['vendor_id'] );

            if ( ! isset( $stored[ $vendor_id ][ $report['id'] ] ) ) {
                $new[] = [ 'vendor_id' => $vendor_id ] + $report;
            }

            $stored[ $vendor_id ][ $report['id'] ] = $report;
        }

        $cutoff = $now - self::MAX_AGE;
        $kept   = 0;

        foreach ( array_unique( $targets ) as $vendor_id ) {
            $list = array_values( array_filter( $stored[ $vendor_id ] ?? [], static function ( $report ) use ( $cutoff ) {
                return (int) ( $report['created_at'] ?? 0 ) >= $cutoff;
            } ) );

            if ( ! empty( $list ) ) {
                usort( $list, static fn( $a, $b ) => $b['created_at'] <=> $a['created_at'] );
                update_user_meta( $vendor_id, self::REPORTS_META, $list );
                update_user_meta( $vendor_id, self::TIME_META, $now );
                $kept += count( $list );
            } else {
                delete_user_meta( $vendor_id, self::REPORTS_META );
                delete_user_meta( $vendor_id, self::TIME_META );
            }
        }

        update_option( self::RUN_OPTION, [
            'time'      => $now,
            'full_time' => $full ? $now : (int) ( $last_run['full_time'] ?? 0 ),
            'since'     => $since,
            'targets'   => count( $targets ),
            'events'    => count( $events ),
            'pages'     => $pages,
            'new'       => count( $new ),
            'kept'      => $kept,
            'wot'       => count( $wot ),
            'seconds'   => round( microtime( true ) - $started, 1 ),
        ], false );

        if ( ! empty( $new ) ) {
            self::notify_admin( $new );
        }
    }

    /**
     * The reports against one chunk of keys from one relay, newest first,
     * page by page while a page comes back full. Events land in $events by
     * id. Returns the number of pages read.
     *
     * @param string[]            $chunk
     * @param array<string,array> $events
     */
    private static function read_pages( string $relay, array $chunk, int $since, array &$events ): int {
        $until = null;
        $pages = 0;

        while ( $pages < self::MAX_PAGES ) {
            $filter = [
                'kinds' => [ 1984 ],
                '#p'    => $chunk,
                'since' => $since,
                'limit' => self::PAGE_LIMIT,
            ];

            if ( null !== $until ) {
                $filter['until'] = $until;
            }

            $batch = self::req( $relay, [ $filter ] );
            $pages++;

            $oldest = null;
            $added  = 0;

            foreach ( $batch as $event ) {
                if ( ! is_string( $event['id'] ?? null ) || ! is_int( $event['created_at'] ?? null ) ) {
                    continue;
                }

                if ( ! isset( $events[ $event['id'] ] ) ) {
                    $events[ $event['id'] ] = $event;
                    $added++;
                }

                $oldest = null === $oldest ? $event['created_at'] : min( $oldest, $event['created_at'] );
            }

            // A short page is the last one; a page that brought nothing new
            // (all events share the boundary timestamp) ends it too, as
            // does the overall cap — the first page is always read.
            if ( count( $batch ) < self::PAGE_LIMIT || 0 === $added || null === $oldest || $oldest <= $since || count( $events ) >= self::MAX_EVENTS ) {
                break;
            }

            // Inclusive boundary: events sharing that second are deduplicated by id.
            $until = $oldest;
        }

        return $pages;
    }

    /**
     * One report, if it is worth keeping.
     *
     * @param array<string, int> $targets pubkey => vendor id
     * @param array<string, int> $wot     key prefix => 1
     */
    private static function accept( array $event, array $targets, array $wot ): ?array {
        foreach ( [ 'id', 'pubkey', 'sig' ] as $field ) {
            if ( empty( $event[ $field ] ) || ! is_string( $event[ $field ] ) ) {
                return null;
            }
        }

        if ( 1984 !== (int) ( $event['kind'] ?? 0 ) || ! is_int( $event['created_at'] ?? null ) ) {
            return null;
        }

        $reporter = strtolower( $event['pubkey'] );

        if ( ! isset( $wot[ substr( $reporter, 0, self::WOT_PREFIX ) ] ) ) {
            return null;
        }

        $content = is_string( $event['content'] ?? null ) ? $event['content'] : '';

        // Automated classifiers report by score, not by judgement.
        if ( 0 === stripos( ltrim( $content ), 'automated' ) ) {
            return null;
        }

        $target = '';
        $type   = '';

        foreach ( (array) ( $event['tags'] ?? [] ) as $tag ) {
            if ( is_array( $tag ) && 'p' === ( $tag[0] ?? '' ) ) {
                $candidate = strtolower( (string) ( $tag[1] ?? '' ) );

                if ( isset( $targets[ $candidate ] ) ) {
                    $target = $candidate;
                    $type   = strtolower( (string) ( $tag[2] ?? '' ) );
                    break;
                }
            }
        }

        if ( '' === $target || ! in_array( $type, self::TYPES, true ) ) {
            return null;
        }

        // Nobody reports themself in a way worth counting.
        if ( $reporter === $target ) {
            return null;
        }

        if ( ! class_exists( '\swentel\nostr\Event\Event' ) ) {
            return null;
        }

        try {
            $event['tags']    = (array) ( $event['tags'] ?? [] );
            $event['content'] = $content;

            if ( ! ( new \swentel\nostr\Event\Event() )->verify( (object) $event ) ) {
                return null;
            }
        } catch ( \Throwable $e ) {
            return null;
        }

        return [
            'vendor_id'  => $targets[ $target ],
            'id'         => strtolower( $event['id'] ),
            'reporter'   => $reporter,
            'type'       => $type,
            'created_at' => (int) $event['created_at'],
            'content'    => mb_substr( wp_strip_all_tags( $content ), 0, 300 ),
        ];
    }

    /**
     * pubkey => vendor id, for every vendor with a proven key.
     *
     * @return array<string, int>
     */
    public static function vendor_keys(): array {
        global $wpdb;

        $ids = $wpdb->get_col(
            "SELECT DISTINCT user_id FROM {$wpdb->usermeta}
             WHERE ( meta_key = 'nostr_public_key' OR meta_key = '" . esc_sql( VendorKey::BOUND_META ) . "' ) AND meta_value <> ''"
        );

        $platform = class_exists( 'SK\Modules\NostrMarket\Bridge\ChatBridge' ) ? \SK\Modules\NostrMarket\Bridge\ChatBridge::PLATFORM_USER_ID : 1;
        $ids[]    = $platform;

        $out = [];

        foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
            $key = VendorKey::bound( $id );

            if ( '' !== $key && ! isset( $out[ $key ] ) ) {
                $out[ $key ] = $id;
            }
        }

        return $out;
    }

    /**
     * The web of trust as key prefixes: the marketplace's follows, their
     * follows, and every proven vendor key. Rebuilt once a day.
     *
     * @return array<string, int>
     */
    public static function wot(): array {
        $cached = get_transient( self::WOT_KEY );

        if ( is_array( $cached ) && ! empty( $cached ) ) {
            return $cached;
        }

        $wot = [];

        foreach ( array_keys( self::vendor_keys() ) as $key ) {
            $wot[ substr( $key, 0, self::WOT_PREFIX ) ] = 1;
        }

        $marketplace = self::marketplace_pubkey();

        if ( '' !== $marketplace ) {
            $degree1 = self::follows_of( [ $marketplace ] );

            foreach ( $degree1 as $key ) {
                $wot[ substr( $key, 0, self::WOT_PREFIX ) ] = 1;
            }

            foreach ( self::follows_of( $degree1 ) as $key ) {
                $wot[ substr( $key, 0, self::WOT_PREFIX ) ] = 1;
            }
        }

        set_transient( self::WOT_KEY, $wot, DAY_IN_SECONDS );

        return $wot;
    }

    /**
     * Union of the p tags of the newest kind 3 of each author, from every relay.
     *
     * @param string[] $authors
     * @return string[]
     */
    private static function follows_of( array $authors ): array {
        if ( empty( $authors ) ) {
            return [];
        }

        $latest  = RelayReader::latest( 3, $authors );
        $follows = [];

        foreach ( $latest as $event ) {
            foreach ( (array) ( $event['tags'] ?? [] ) as $tag ) {
                if ( is_array( $tag ) && 'p' === ( $tag[0] ?? '' ) && preg_match( '/^[0-9a-fA-F]{64}$/', (string) ( $tag[1] ?? '' ) ) ) {
                    $follows[ strtolower( $tag[1] ) ] = 1;
                }
            }
        }

        return array_keys( $follows );
    }

    private static function marketplace_pubkey(): string {
        if ( sk_module_active( 'sk_nostr_market' ) && class_exists( 'SK\Modules\NostrMarket\EventSender' ) ) {
            return strtolower( (string) \SK\Modules\NostrMarket\EventSender::get_pubkey() );
        }

        return '';
    }

    /** @return string[] */
    private static function relays(): array {
        return RelayReader::relays();
    }

    /** @return array<int, array> */
    private static function req( string $relay, array $filters ): array {
        return RelayReader::req( $relay, $filters, self::RELAY_TIMEOUT, self::PAGE_LIMIT );
    }

    private static function notify_admin( array $new ): void {
        if ( get_transient( self::MAIL_THROTTLE ) ) {
            return;
        }

        set_transient( self::MAIL_THROTTLE, 1, DAY_IN_SECONDS );

        $lines = [];

        foreach ( array_slice( $new, 0, 20 ) as $r ) {
            $vendor  = get_userdata( (int) $r['vendor_id'] );
            $lines[] = sprintf(
                "%s (#%d): %s von %s… am %s\n%s",
                $vendor ? $vendor->display_name : '?',
                $r['vendor_id'],
                $r['type'],
                substr( $r['reporter'], 0, 12 ),
                wp_date( 'd.m.Y', $r['created_at'] ),
                $r['content'] !== '' ? '  „' . $r['content'] . '“' : ''
            );
        }

        wp_mail(
            get_option( 'admin_email' ),
            sprintf( '[SK Reputation] %d neue Nostr-Meldungen aus dem Web of Trust', count( $new ) ),
            implode( "\n\n", $lines ) . "\n\n" . admin_url( 'admin.php?page=sk-reputation' )
        );
    }
}
