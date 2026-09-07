<?php

namespace SK\Modules\Sponsors;

defined( 'ABSPATH' ) || exit;

/**
 * Checks whether a sponsor links back.
 *
 * Background: a measurement in August 2026 found that of 14 checked sponsor
 * targets, exactly one linked back to satoshiskleinanzeigen.space. A free
 * placement without a backlink is therefore a pure one-way street — this
 * class makes the state visible instead of assuming it.
 */
final class Backlink {

    const META_OK      = '_sk_sponsor_backlink_ok';
    const META_CHECKED = '_sk_sponsor_backlink_checked';

    /**
     * Backlink manually confirmed by the operator.
     *
     * Needed because not every site is reachable from this server:
     * yourdevice.ch, for instance, is on the same network, and the route to
     * its public IP doesn't loop back. Cloudflare rules can also block
     * server-side requests. A checked box wins over the automatic check.
     */
    const META_MANUAL = '_sk_sponsor_backlink_manual';

    /** links back */
    const OK          = 1;
    /** does not link back */
    const MISSING     = 0;
    /** fundamentally not checkable (chat or short link) */
    const UNCHECKABLE = -1;
    /** request failed (timeout, error status) */
    const UNREACHABLE = -2;

    /**
     * External requests per run.
     *
     * Generous enough that one click covers the whole set — with eight, the
     * rest silently stayed "unchecked" and the button seemed broken. One
     * request takes about a second; the upper limit only guards against a
     * very long list running into a server time limit.
     */
    const BATCH = 30;

    /** Seconds per external request. */
    const TIMEOUT = 4;

    /**
     * How many sponsors have never been checked?
     */
    public static function unchecked_count(): int {
        $sponsors = get_posts(
            [
                'post_type'      => PostType::POST_TYPE,
                'post_status'    => [ 'publish', 'draft' ],
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ]
        );

        $open = 0;
        foreach ( $sponsors as $id ) {
            if ( self::status( (int) $id ) === null ) {
                $open++;
            }
        }

        return $open;
    }

    /**
     * Checks the sponsors that have gone unchecked the longest.
     *
     * @return array{checked:int,ok:int,open:int}
     */
    public static function check_batch(): array {
        $sponsors = get_posts(
            [
                'post_type'      => PostType::POST_TYPE,
                'post_status'    => [ 'publish', 'draft' ],
                'posts_per_page' => -1,
            ]
        );

        // Sort by check date in PHP, never-checked first. Using
        // meta_key + orderby would create an INNER JOIN that excludes
        // exactly the unchecked sponsors — the ones this is about.
        usort(
            $sponsors,
            static function ( $a, $b ) {
                $ca = (string) get_post_meta( $a->ID, self::META_CHECKED, true );
                $cb = (string) get_post_meta( $b->ID, self::META_CHECKED, true );

                return strcmp( $ca, $cb );
            }
        );

        $sponsors = array_slice( $sponsors, 0, self::BATCH );

        $result = [ 'checked' => 0, 'ok' => 0, 'open' => 0 ];

        foreach ( $sponsors as $sponsor ) {
            if ( self::check( (int) $sponsor->ID ) ) {
                $result['ok']++;
            }
            $result['checked']++;
        }

        $result['open'] = self::unchecked_count();

        return $result;
    }

    /**
     * Fetches the target URL and searches it for our own domain.
     */
    public static function check( int $sponsor_id ): bool {
        // A manually confirmed backlink is never overwritten.
        if ( (int) get_post_meta( $sponsor_id, self::META_MANUAL, true ) === 1 ) {
            return true;
        }

        $url = (string) get_post_meta( $sponsor_id, PostType::META_URL, true );

        update_post_meta( $sponsor_id, self::META_CHECKED, current_time( 'mysql' ) );

        // Telegram and other chat targets can't be meaningfully checked.
        $host = (string) wp_parse_url( $url, PHP_URL_HOST );
        if ( $url === '' || $host === '' || self::is_unverifiable( $host ) ) {
            update_post_meta( $sponsor_id, self::META_OK, self::UNCHECKABLE );
            return false;
        }

        $response = wp_remote_get(
            $url,
            [
                'timeout'     => self::TIMEOUT,
                'redirection' => 3,
                'user-agent'  => 'Mozilla/5.0 (compatible; SK-Sponsors-Backlinkcheck/1.0)',
            ]
        );

        // Separate from UNCHECKABLE: a timeout doesn't mean the site doesn't
        // link back — it means this server couldn't reach it.
        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) >= 400 ) {
            update_post_meta( $sponsor_id, self::META_OK, self::UNREACHABLE );
            return false;
        }

        $body = (string) wp_remote_retrieve_body( $response );
        $own  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
        // Also match the bare domain when the site runs under "new.".
        $needle = preg_replace( '/^(www|new|staging)\./', '', $own );

        $found = $needle !== '' && stripos( $body, $needle ) !== false;
        update_post_meta( $sponsor_id, self::META_OK, $found ? self::OK : self::MISSING );

        return $found;
    }

    private static function is_unverifiable( string $host ): bool {
        foreach ( [ 't.me', 'telegram.me', 'bit.ly', 'linktr.ee' ] as $needle ) {
            if ( stripos( $host, $needle ) !== false ) {
                return true;
            }
        }

        return false;
    }

    /**
     * OK / MISSING / UNCHECKABLE / UNREACHABLE, or null if unchecked.
     */
    public static function status( int $sponsor_id ): ?int {
        if ( (int) get_post_meta( $sponsor_id, self::META_MANUAL, true ) === 1 ) {
            return self::OK;
        }

        $raw = get_post_meta( $sponsor_id, self::META_OK, true );

        return $raw === '' ? null : (int) $raw;
    }

    public static function is_manual( int $sponsor_id ): bool {
        return (int) get_post_meta( $sponsor_id, self::META_MANUAL, true ) === 1;
    }

    /**
     * Label for display.
     *
     * @return array{0:string,1:string} text and color
     */
    public static function label( int $sponsor_id ): array {
        if ( self::is_manual( $sponsor_id ) ) {
            return [ __( 'ja (bestätigt)', 'sk-core' ), '#008a20' ];
        }

        switch ( self::status( $sponsor_id ) ) {
            case self::OK:
                return [ __( 'ja', 'sk-core' ), '#008a20' ];
            case self::MISSING:
                return [ __( 'nein', 'sk-core' ), '#d63638' ];
            case self::UNCHECKABLE:
                return [ __( 'nicht prüfbar', 'sk-core' ), '#646970' ];
            case self::UNREACHABLE:
                return [ __( 'nicht erreichbar', 'sk-core' ), '#dba617' ];
            default:
                return [ __( 'ungeprüft', 'sk-core' ), '#646970' ];
        }
    }
}
