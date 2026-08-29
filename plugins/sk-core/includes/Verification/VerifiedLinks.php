<?php

namespace SK\Core\Verification;

defined( 'ABSPATH' ) || exit;

/**
 * Bestätigte Verweise — die Vertrauensebene der Plattform.
 *
 * Wer eine Seite besitzt, kann das belegen: er trägt sie hier ein und setzt
 * dort einen Verweis zurück auf sein SK-Profil. Erst beides zusammen trägt.
 * Der Eintrag hier sagt "dieses Konto beansprucht diese Adresse", der Verweis
 * dort sagt "diese Adresse gehört zu diesem Konto". Eine Richtung allein wäre
 * wertlos: einen Verweis auf ein fremdes Profil kann jeder setzen, und eine
 * Behauptung ohne Gegenprobe ist keine.
 *
 * Bewusst im Kern und nicht im Shop-Import: die Bestätigung ist eine Aussage
 * über den Nutzer, nicht über einen Katalogimport. Sie trägt das Abzeichen am
 * Profil, und der Import fragt sie nur ab. Läge sie im Importmodul, fände sie
 * niemand, der kein Händler ist — und die Ebene könnte nie wachsen.
 *
 * Was sie beweist: Kontrolle über eine Adresse. Nicht mehr. Eine
 * Wegwerf-Domain ist schnell registriert, ein Abzeichen ersetzt deshalb keine
 * Prüfung durch einen Menschen, wo es um Geld geht.
 */
final class VerifiedLinks {

    /** User-Meta: Liste der beanspruchten Adressen. */
    const META = '_sk_verified_links';

    /** User-Meta: der geheime Beleg dieses Nutzers. */
    const META_TOKEN = '_sk_verify_token';

    /**
     * User-Meta: bis wann die Bestaetigung gilt, als Zeitstempel.
     *
     * Abgeleitet aus der Liste, aber flach — die Liste selbst ist serialisiert
     * und in SQL nicht sinnvoll zu sortieren. Die Anbieterliste ordnet danach.
     */
    const META_UNTIL = '_sk_verified_until';

    /** verlinkt zurück */
    const OK = 1;
    /** erreichbar, aber kein Beleg gefunden */
    const MISSING = 0;
    /** Abruf fehlgeschlagen — sagt nichts über den Beleg aus */
    const UNREACHABLE = -2;

    /** Wie lange eine Bestätigung gilt. Adressen wechseln den Besitzer. */
    const MAX_AGE = 90 * DAY_IN_SECONDS;

    /** Sekunden je Abruf. */
    const TIMEOUT = 8;

    /** Höchstens so viel vom Dokument durchsuchen. */
    const MAX_BODY = 512000;

    /** Mehr Adressen braucht niemand, und es begrenzt die Abrufe. */
    const MAX_LINKS = 5;

    /**
     * Das Ziel, auf das ein Verweis zeigen muss.
     *
     * Die Shopseite, wo es eine gibt — sie ist die öffentliche Seite des
     * Kontos. Sonst die Autorenseite.
     */
    public static function target_url( int $user_id ): string {
        if ( function_exists( 'sk_get_store_url' ) ) {
            $store = (string) sk_get_store_url( $user_id );

            if ( $store !== '' ) {
                return $store;
            }
        }

        return (string) get_author_posts_url( $user_id );
    }

    /**
     * Der geheime Beleg für Orte, an denen kein rel="me" überlebt.
     *
     * GitHub etwa entfernt beim Rendern von Markdown die rel-Angabe, ein
     * README kann den Verweis also gar nicht führen. Ein unrätselbarer
     * Textbaustein geht überall — und weil er nicht zu erraten ist, kann ihn
     * auch niemand versehentlich auf seiner Seite stehen haben.
     */
    public static function token( int $user_id ): string {
        $token = (string) get_user_meta( $user_id, self::META_TOKEN, true );

        if ( $token === '' ) {
            $token = 'sk-verify-' . wp_generate_password( 24, false, false );
            update_user_meta( $user_id, self::META_TOKEN, $token );
        }

        return $token;
    }

    /** Der Schnipsel für den <head> einer eigenen Seite. */
    public static function snippet( int $user_id ): string {
        return '<link rel="me" href="' . esc_url( self::target_url( $user_id ) ) . '">';
    }

    /**
     * Alle Einträge eines Nutzers.
     *
     * @return array<int,array{url:string,host:string,status:int,checked:int,confirmed:int}>
     */
    public static function all( int $user_id ): array {
        $rows = get_user_meta( $user_id, self::META, true );

        if ( ! is_array( $rows ) ) {
            return [];
        }

        $clean = [];

        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) || empty( $row['url'] ) ) {
                continue;
            }

            $clean[] = [
                'url'       => (string) $row['url'],
                'host'      => self::host( (string) $row['url'] ),
                'status'    => isset( $row['status'] ) ? (int) $row['status'] : self::MISSING,
                'checked'   => (int) ( $row['checked'] ?? 0 ),
                'confirmed' => (int) ( $row['confirmed'] ?? 0 ),
            ];
        }

        return $clean;
    }

    /**
     * Nur die gültigen: bestätigt und nicht zu alt.
     *
     * @return array<int,array>
     */
    public static function confirmed( int $user_id ): array {
        $gueltig = [];

        foreach ( self::all( $user_id ) as $row ) {
            if ( $row['status'] === self::OK && $row['confirmed'] > 0
                && ( time() - $row['confirmed'] ) <= self::MAX_AGE ) {
                $gueltig[] = $row;
            }
        }

        return $gueltig;
    }

    /**
     * Die bestätigten Hosts, jeder einmal.
     *
     * Zwei Adressen auf derselben Domain sind eine Domain — in der Anzeige
     * stand sie sonst doppelt.
     *
     * @return string[]
     */
    public static function confirmed_hosts( int $user_id ): array {
        return array_values( array_unique( wp_list_pluck( self::confirmed( $user_id ), 'host' ) ) );
    }

    /** Trägt dieser Nutzer das Abzeichen? */
    public static function is_verified( int $user_id ): bool {
        return ! empty( self::confirmed( $user_id ) );
    }

    /**
     * Ist diese Adresse durch eine Bestätigung gedeckt?
     *
     * Verglichen wird der Host, nicht der genaue Pfad: wer example.com
     * bestätigt hat, hat die Domain belegt, nicht eine einzelne Unterseite.
     */
    public static function covers( int $user_id, string $url ): bool {
        $host = self::host( $url );

        if ( $host === '' ) {
            return false;
        }

        foreach ( self::confirmed( $user_id ) as $row ) {
            if ( $row['host'] === $host ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Eine Adresse aufnehmen. Prüft noch nicht.
     *
     * @return true|\WP_Error
     */
    public static function add( int $user_id, string $url ) {
        $url = esc_url_raw( trim( $url ) );

        if ( $url === '' || ! in_array( wp_parse_url( $url, PHP_URL_SCHEME ), [ 'http', 'https' ], true ) ) {
            return new \WP_Error( 'sk_verify_url', __( 'Bitte gib eine vollständige Adresse an, zum Beispiel https://meine-seite.de.', 'sk-core' ) );
        }

        if ( self::host( $url ) === '' ) {
            return new \WP_Error( 'sk_verify_host', __( 'Aus dieser Adresse lässt sich kein Hostname lesen.', 'sk-core' ) );
        }

        $rows = self::all( $user_id );

        foreach ( $rows as $row ) {
            if ( self::normalize( $row['url'] ) === self::normalize( $url ) ) {
                return new \WP_Error( 'sk_verify_dup', __( 'Diese Adresse steht schon in deiner Liste.', 'sk-core' ) );
            }
        }

        if ( count( $rows ) >= self::MAX_LINKS ) {
            return new \WP_Error(
                'sk_verify_max',
                sprintf(
                    /* translators: %d: Höchstzahl der Adressen. */
                    __( 'Mehr als %d Adressen gehen nicht. Entferne zuerst eine.', 'sk-core' ),
                    self::MAX_LINKS
                )
            );
        }

        $rows[] = [ 'url' => $url, 'status' => self::MISSING, 'checked' => 0, 'confirmed' => 0 ];

        self::save( $user_id, $rows );

        return true;
    }

    /** Eine Adresse wieder entfernen. */
    public static function remove( int $user_id, string $url ): void {
        $ziel = self::normalize( $url );
        $rows = [];

        foreach ( self::all( $user_id ) as $row ) {
            if ( self::normalize( $row['url'] ) !== $ziel ) {
                $rows[] = $row;
            }
        }

        self::save( $user_id, $rows );
    }

    /**
     * Eine Adresse prüfen und das Ergebnis festhalten.
     *
     * @return int Einer der drei Zustände.
     */
    public static function check( int $user_id, string $url ): int {
        $ziel     = self::normalize( $url );
        $ergebnis = self::probe( $user_id, $url );
        $rows     = self::all( $user_id );

        foreach ( $rows as &$row ) {
            if ( self::normalize( $row['url'] ) !== $ziel ) {
                continue;
            }

            $row['status']  = $ergebnis;
            $row['checked'] = time();

            if ( $ergebnis === self::OK ) {
                $row['confirmed'] = time();
            }
        }
        unset( $row );

        self::save( $user_id, $rows );

        return $ergebnis;
    }

    /**
     * Der eigentliche Abruf.
     *
     * Zwei Belege werden anerkannt: ein `rel="me"` auf die eigene Profilseite
     * — der saubere Weg für eine eigene Website — und der geheime
     * Textbaustein irgendwo im Dokument, für Orte wie GitHub, die rel beim
     * Rendern entfernen.
     */
    private static function probe( int $user_id, string $url ): int {
        $response = wp_safe_remote_get(
            $url,
            [
                'timeout'     => self::TIMEOUT,
                'redirection' => 3,
                'user-agent'  => 'Mozilla/5.0 (compatible; SK-Linkpruefung/1.0)',
            ]
        );

        // Getrennt von MISSING: ein Timeout heisst nicht, dass der Beleg
        // fehlt — er heisst, dass dieser Server die Seite nicht erreicht.
        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) >= 400 ) {
            return self::UNREACHABLE;
        }

        $body = substr( (string) wp_remote_retrieve_body( $response ), 0, self::MAX_BODY );

        if ( $body === '' ) {
            return self::MISSING;
        }

        if ( strpos( $body, self::token( $user_id ) ) !== false ) {
            return self::OK;
        }

        return self::links_back( $body, self::target_url( $user_id ) ) ? self::OK : self::MISSING;
    }

    /**
     * Steht im Dokument ein rel="me" auf genau diese Adresse?
     *
     * Bewusst nicht die blosse Suche nach der Domain im Quelltext: daran
     * haengt ein Abzeichen, und eine beliebige Erwähnung ist kein Anspruch.
     */
    public static function links_back( string $body, string $target ): bool {
        $ziel = self::normalize( $target );

        if ( $ziel === '' || $body === '' ) {
            return false;
        }

        if ( ! preg_match_all( '#<(?:a|link)\s[^>]*>#i', $body, $tags ) ) {
            return false;
        }

        foreach ( $tags[0] as $tag ) {
            if ( ! preg_match( '#\srel\s*=\s*["\']?([^"\'>]*)#i', $tag, $rel ) ) {
                continue;
            }

            // rel darf mehrere Werte tragen: rel="me noopener".
            $werte = preg_split( '/\s+/', mb_strtolower( trim( $rel[1] ) ) );

            if ( ! is_array( $werte ) || ! in_array( 'me', $werte, true ) ) {
                continue;
            }

            if ( ! preg_match( '#\shref\s*=\s*["\']([^"\']+)#i', $tag, $href ) ) {
                continue;
            }

            if ( self::normalize( html_entity_decode( $href[1], ENT_QUOTES, 'UTF-8' ) ) === $ziel ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adressen vergleichbar machen: Schema, www und Schrägstrich am Ende
     * sollen keinen Unterschied machen.
     */
    private static function normalize( string $url ): string {
        $url = trim( $url );

        if ( $url === '' ) {
            return '';
        }

        $parts = wp_parse_url( $url );
        $host  = mb_strtolower( (string) ( $parts['host'] ?? '' ) );
        $host  = (string) preg_replace( '/^www\./', '', $host );
        $path  = rtrim( (string) ( $parts['path'] ?? '' ), '/' );

        return $host === '' ? '' : $host . $path;
    }

    /** Host einer Adresse, ohne www. */
    public static function host( string $url ): string {
        $url = trim( $url );

        if ( $url !== '' && ! preg_match( '#^https?://#i', $url ) ) {
            $url = 'https://' . ltrim( $url, '/' );
        }

        $host = mb_strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

        return (string) preg_replace( '/^www\./', '', $host );
    }

    private static function save( int $user_id, array $rows ): void {
        update_user_meta( $user_id, self::META, array_values( $rows ) );

        self::refresh_until( $user_id );
    }

    /**
     * Das flache Ablaufdatum nachfuehren.
     *
     * Gespeichert wird der spaeteste Ablauf ueber alle bestaetigten Adressen.
     * Ein Vergleich gegen die aktuelle Zeit genuegt damit in SQL, und eine
     * abgelaufene Bestaetigung faellt von selbst hinten runter.
     */
    private static function refresh_until( int $user_id ): void {
        $bis = 0;

        foreach ( self::all( $user_id ) as $row ) {
            if ( $row['status'] === self::OK && $row['confirmed'] > 0 ) {
                $bis = max( $bis, $row['confirmed'] + self::MAX_AGE );
            }
        }

        if ( $bis > 0 ) {
            update_user_meta( $user_id, self::META_UNTIL, $bis );
        } else {
            delete_user_meta( $user_id, self::META_UNTIL );
        }
    }
}
