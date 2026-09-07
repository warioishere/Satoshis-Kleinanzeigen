<?php

namespace SK\Core\Verification;

defined( 'ABSPATH' ) || exit;

/**
 * Verified links — the platform's trust layer.
 *
 * Whoever owns a site can prove it: they enter it here and place a link back
 * to their SK profile there. Only both together carry weight. The entry here
 * says "this account claims this URL", the link there says "this URL belongs
 * to this account". Either direction alone would be worthless: anyone can put
 * a link to someone else's profile, and a claim without a counter-check isn't
 * one.
 *
 * Deliberately in the core and not in the shop import: the confirmation is a
 * statement about the user, not about a catalog import. It carries the badge
 * on the profile, and the import only reads it. If it lived in the import
 * module, no one who isn't a merchant would find it — and the trust layer
 * could never grow.
 *
 * What it proves: control over a URL. Nothing more. A throwaway domain is
 * registered quickly, so a badge is no substitute for a human check where
 * money is involved.
 */
final class VerifiedLinks {

    /** User meta: list of claimed URLs. */
    const META = '_sk_verified_links';

    /** User meta: this user's secret token. */
    const META_TOKEN = '_sk_verify_token';

    /**
     * User meta: how long the confirmation is valid, as a timestamp.
     *
     * Derived from the list, but flat — the list itself is serialized and
     * can't be sorted meaningfully in SQL. The vendor list orders by this.
     */
    const META_UNTIL = '_sk_verified_until';

    /** links back */
    const OK = 1;
    /** reachable, but no proof found */
    const MISSING = 0;
    /** fetch failed — says nothing about the proof */
    const UNREACHABLE = -2;

    /** How long a confirmation stays valid. Domains change owners. */
    const MAX_AGE = 90 * DAY_IN_SECONDS;

    /** Seconds per fetch. */
    const TIMEOUT = 8;

    /** Search at most this much of the document. */
    const MAX_BODY = 512000;

    /** No one needs more URLs, and it caps the fetches. */
    const MAX_LINKS = 5;

    /**
     * The target a link must point to.
     *
     * The shop page, where there is one — it's the account's public page.
     * Otherwise the author archive page.
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
     * The secret token for places where no rel="me" survives.
     *
     * GitHub, for instance, strips the rel attribute when rendering Markdown,
     * so a README can't carry the link at all. An unguessable string of text
     * works everywhere — and because it can't be guessed, no one can have it
     * on their page by accident either.
     */
    public static function token( int $user_id ): string {
        $token = (string) get_user_meta( $user_id, self::META_TOKEN, true );

        if ( $token === '' ) {
            $token = 'sk-verify-' . wp_generate_password( 24, false, false );
            update_user_meta( $user_id, self::META_TOKEN, $token );
        }

        return $token;
    }

    /** The snippet for the <head> of one's own page. */
    public static function snippet( int $user_id ): string {
        return '<link rel="me" href="' . esc_url( self::target_url( $user_id ) ) . '">';
    }

    /**
     * All entries of a user.
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
     * Only the valid ones: confirmed and not too old.
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
     * The confirmed hosts, each once.
     *
     * Two URLs on the same domain count as one domain — otherwise it would
     * show up twice in the display.
     *
     * @return string[]
     */
    public static function confirmed_hosts( int $user_id ): array {
        return array_values( array_unique( wp_list_pluck( self::confirmed( $user_id ), 'host' ) ) );
    }

    /** Does this user carry the badge? */
    public static function is_verified( int $user_id ): bool {
        return ! empty( self::confirmed( $user_id ) );
    }

    /**
     * Is this URL covered by a confirmation?
     *
     * The comparison is by host, not the exact path: whoever confirmed
     * example.com has claimed the domain, not a single subpage.
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
     * Add a URL. Does not check it yet.
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
                    /* translators: %d: maximum number of URLs. */
                    __( 'Mehr als %d Adressen gehen nicht. Entferne zuerst eine.', 'sk-core' ),
                    self::MAX_LINKS
                )
            );
        }

        $rows[] = [ 'url' => $url, 'status' => self::MISSING, 'checked' => 0, 'confirmed' => 0 ];

        self::save( $user_id, $rows );

        return true;
    }

    /** Remove a URL again. */
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
     * Check a URL and record the result.
     *
     * @return int One of the three states.
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

        /**
         * A URL was confirmed.
         *
         * The shop import hooks its approval to this — the core itself
         * knows nothing about merchants, hence a signal instead of a direct
         * call.
         *
         * @param int    $user_id
         * @param string $url
         */
        if ( $ergebnis === self::OK ) {
            do_action( 'sk_link_verified', $user_id, $url );
        }

        return $ergebnis;
    }

    /**
     * The actual fetch.
     *
     * Two kinds of proof are accepted: a `rel="me"` pointing to the user's
     * own profile page — the clean approach for a personal website — and the
     * secret token somewhere in the document, for places like GitHub that
     * strip rel when rendering.
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

        // Kept separate from MISSING: a timeout doesn't mean the proof is
        // missing — it means this server couldn't reach the page.
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
     * Does the document contain a rel="me" pointing to exactly this URL?
     *
     * Deliberately not just searching the source for the domain: a badge
     * hinges on this, and a random mention isn't a claim.
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

            // rel may carry multiple values: rel="me noopener".
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
     * Make URLs comparable: scheme, www, and a trailing slash shouldn't make
     * a difference.
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

    /** Host of a URL, without www. */
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
     * Keep the flat expiry date up to date.
     *
     * The latest expiry across all confirmed URLs is what gets stored. That
     * makes a comparison against the current time sufficient in SQL, and an
     * expired confirmation drops out on its own.
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
