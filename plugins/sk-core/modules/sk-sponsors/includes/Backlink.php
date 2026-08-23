<?php

namespace SK\Modules\Sponsors;

defined( 'ABSPATH' ) || exit;

/**
 * Prüft, ob ein Sponsor zurückverlinkt.
 *
 * Hintergrund: Eine Messung im August 2026 ergab, dass von 14 geprüften
 * Sponsorenzielen genau eines auf satoshiskleinanzeigen.space verlinkt. Ein
 * Gratisplatz ohne Rücklink ist damit eine reine Einbahnstraße — diese Klasse
 * macht den Zustand sichtbar, statt ihn zu vermuten.
 */
final class Backlink {

    const META_OK      = '_sk_sponsor_backlink_ok';
    const META_CHECKED = '_sk_sponsor_backlink_checked';

    /**
     * Vom Betreiber von Hand bestaetigter Rueckling.
     *
     * Noetig, weil nicht jede Seite von diesem Server aus erreichbar ist:
     * yourdevice.ch etwa liegt im selben Netz, und der Weg auf dessen
     * oeffentliche IP wird nicht zurueckgeschleift. Auch Cloudflare-Regeln
     * koennen serverseitige Abrufe blocken. Ein gesetztes Haekchen gewinnt
     * gegen die automatische Pruefung.
     */
    const META_MANUAL = '_sk_sponsor_backlink_manual';

    /** verlinkt */
    const OK          = 1;
    /** verlinkt nicht */
    const MISSING     = 0;
    /** prinzipiell nicht pruefbar (Chat- oder Kurzlink) */
    const UNCHECKABLE = -1;
    /** Abruf fehlgeschlagen (Timeout, Fehlerstatus) */
    const UNREACHABLE = -2;

    /**
     * Externe Abrufe pro Durchlauf.
     *
     * Grosszuegig genug, dass ein Klick den ganzen Bestand erfasst — bei acht
     * Stueck blieb der Rest kommentarlos auf "ungeprueft" stehen und der Knopf
     * wirkte kaputt. Ein Abruf dauert rund eine Sekunde, die Obergrenze
     * schuetzt nur davor, dass eine sehr lange Liste in ein Server-Zeitlimit
     * laeuft.
     */
    const BATCH = 30;

    /** Sekunden je externem Abruf. */
    const TIMEOUT = 4;

    /**
     * Wie viele Sponsoren wurden noch nie geprüft?
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
     * Prüft die am längsten nicht geprüften Sponsoren.
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

        // Nach Prüfdatum in PHP sortieren, nie geprüfte zuerst. Über
        // meta_key + orderby entstünde ein INNER JOIN, der genau die
        // ungeprüften Sponsoren ausschliesst — also die, um die es geht.
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
     * Holt die Ziel-URL und sucht darin die eigene Domain.
     */
    public static function check( int $sponsor_id ): bool {
        // Ein von Hand bestaetigter Rueckling wird nicht ueberschrieben.
        if ( (int) get_post_meta( $sponsor_id, self::META_MANUAL, true ) === 1 ) {
            return true;
        }

        $url = (string) get_post_meta( $sponsor_id, PostType::META_URL, true );

        update_post_meta( $sponsor_id, self::META_CHECKED, current_time( 'mysql' ) );

        // Telegram- und andere Chat-Ziele lassen sich nicht sinnvoll prüfen.
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

        // Getrennt von UNCHECKABLE: Ein Timeout heisst nicht, dass die Seite
        // nicht verlinkt — er heisst, dass dieser Server sie nicht erreicht.
        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) >= 400 ) {
            update_post_meta( $sponsor_id, self::META_OK, self::UNREACHABLE );
            return false;
        }

        $body = (string) wp_remote_retrieve_body( $response );
        $own  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
        // Auch die blanke Domain treffen, wenn die Seite unter "new." läuft.
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
     * OK / MISSING / UNCHECKABLE / UNREACHABLE, oder null wenn ungeprüft.
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
     * Beschriftung für die Anzeige.
     *
     * @return array{0:string,1:string} Text und Farbe
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
