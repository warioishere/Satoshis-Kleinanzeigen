<?php

namespace SK\Modules\Sponsors;

defined( 'ABSPATH' ) || exit;

/**
 * Click counting and redirect under /go/<slug>.
 *
 * Without solid click numbers you can't sell a sponsor slot — so this class
 * doesn't just count, it also filters: bots, prefetches, and HEAD requests
 * don't count, and repeated clicks by the same person on the same day
 * increase the click count but not the visitor count.
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
     * A sponsor's target URL, routed through the counter.
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
        // No wp_safe_redirect: the target is deliberately external.
        wp_redirect( $url, 302 );
        exit;
    }

    private function bail(): void {
        wp_safe_redirect( home_url( '/' ), 302 );
        exit;
    }

    /**
     * Does this request count as a genuine click?
     */
    private function is_countable(): bool {
        $method = strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' );
        if ( $method !== 'GET' ) {
            return false;
        }

        // Browsers sometimes prefetch links ahead of time. That's not a click.
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

        // INSERT ... ON DUPLICATE KEY uses the UNIQUE index from Install and
        // therefore needs neither a prior SELECT nor a lock.
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
     * Daily-rotating visitor hash.
     *
     * No IP is stored, and because the date is baked into the hash, the same
     * visitor can't be recognized across days. That's enough to answer "how
     * many people clicked", but not enough for profiling — which is the
     * right trade-off for this platform.
     */
    private function visitor_hash( string $day ): string {
        $ip    = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
        $agent = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );

        return md5( $ip . '|' . $agent . '|' . $day . '|' . wp_salt( 'nonce' ) );
    }
}
