<?php

namespace SK\Modules\Sponsors;

defined( 'ABSPATH' ) || exit;

/**
 * Klickzählung und Weiterleitung unter /go/<slug>.
 *
 * Ohne belastbare Klickzahlen lässt sich ein Sponsorenplatz nicht verkaufen —
 * deshalb zählt diese Klasse nicht nur, sondern filtert auch: Bots, Prefetches
 * und HEAD-Anfragen zählen nicht, und mehrfaches Klicken derselben Person am
 * selben Tag erhöht zwar die Klick-, nicht aber die Besucherzahl.
 */
class Tracker {

    const QUERY_VAR = 'sk_sponsor_go';
    const PREFIX    = 'go';

    public function __construct() {
        add_action( 'init', [ $this, 'add_rewrite_rule' ] );
        add_filter( 'query_vars', [ $this, 'add_query_var' ] );
        add_action( 'template_redirect', [ $this, 'handle' ], 0 );
    }

    public function add_rewrite_rule(): void {
        add_rewrite_rule(
            '^' . self::PREFIX . '/([^/]+)/?$',
            'index.php?' . self::QUERY_VAR . '=$matches[1]',
            'top'
        );
    }

    public function add_query_var( $vars ): array {
        $vars[] = self::QUERY_VAR;

        return $vars;
    }

    /**
     * Ziel-URL eines Sponsors, über die Zählung geleitet.
     */
    public static function link_for( \WP_Post $sponsor ): string {
        return home_url( '/' . self::PREFIX . '/' . $sponsor->post_name . '/' );
    }

    public function handle(): void {
        $slug = get_query_var( self::QUERY_VAR );
        if ( ! $slug ) {
            return;
        }

        $sponsor = get_page_by_path( sanitize_title( $slug ), OBJECT, PostType::POST_TYPE );

        if ( ! $sponsor || $sponsor->post_status !== 'publish' ) {
            $this->bail();
        }

        $url = (string) get_post_meta( $sponsor->ID, PostType::META_URL, true );
        if ( $url === '' || ! wp_http_validate_url( $url ) ) {
            $this->bail();
        }

        if ( $this->is_countable() ) {
            $this->count( (int) $sponsor->ID );
        }

        nocache_headers();
        header( 'X-Robots-Tag: noindex, nofollow', true );
        // Kein wp_safe_redirect: das Ziel ist absichtlich extern.
        wp_redirect( $url, 302 );
        exit;
    }

    private function bail(): void {
        wp_safe_redirect( home_url( '/' ), 302 );
        exit;
    }

    /**
     * Zählt dieser Aufruf als echter Klick?
     */
    private function is_countable(): bool {
        $method = strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' );
        if ( $method !== 'GET' ) {
            return false;
        }

        // Browser holen Links teils im Voraus. Das ist kein Klick.
        if ( ! empty( $_SERVER['HTTP_PURPOSE'] ) || ! empty( $_SERVER['HTTP_X_PURPOSE'] ) || ! empty( $_SERVER['HTTP_SEC_PURPOSE'] ) ) {
            return false;
        }

        $agent = trim( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
        if ( $agent === '' ) {
            return false;
        }

        $needles = [
            'bot', 'crawl', 'spider', 'slurp', 'curl', 'wget', 'python-requests',
            'httpclient', 'headless', 'phantom', 'monitor', 'preview', 'facebookexternalhit',
            'embedly', 'quora link preview', 'whatsapp', 'telegrambot', 'discordbot', 'slackbot',
        ];

        $lower = strtolower( $agent );
        foreach ( $needles as $needle ) {
            if ( strpos( $lower, $needle ) !== false ) {
                return false;
            }
        }

        return true;
    }

    private function count( int $sponsor_id ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'sk_sponsor_clicks';
        $now   = current_time( 'mysql' );
        $day   = current_time( 'Y-m-d' );

        // INSERT ... ON DUPLICATE KEY nutzt den UNIQUE-Index aus Install und
        // braucht deshalb weder Vorab-SELECT noch Sperre.
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (sponsor_id, click_day, visitor_hash, clicks, first_seen, last_seen)
                 VALUES (%d, %s, %s, 1, %s, %s)
                 ON DUPLICATE KEY UPDATE clicks = clicks + 1, last_seen = VALUES(last_seen)",
                $sponsor_id,
                $day,
                $this->visitor_hash( $day ),
                $now,
                $now
            )
        );
    }

    /**
     * Tagesrotierender Besucher-Hash.
     *
     * Es wird keine IP gespeichert, und weil das Datum im Hash steckt, lässt
     * sich derselbe Besucher über Tage hinweg nicht wiedererkennen. Für die
     * Frage "wie viele Menschen haben geklickt" reicht das, für Profilbildung
     * nicht — was auf dieser Plattform der richtige Kompromiss ist.
     */
    private function visitor_hash( string $day ): string {
        $ip    = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
        $agent = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );

        return md5( $ip . '|' . $agent . '|' . $day . '|' . wp_salt( 'nonce' ) );
    }
}
