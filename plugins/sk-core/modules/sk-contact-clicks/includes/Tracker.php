<?php

namespace SK\Modules\ContactClicks;

defined( 'ABSPATH' ) || exit;

/**
 * Nimmt Kontaktklicks entgegen und zählt sie.
 */
class Tracker {

    const ACTION = 'sk_contact_click';

    /** Nur diese Kanäle werden angenommen — der Rest ist Rauschen. */
    const CHANNELS = [ 'tg', 'nostr', 'mail', 'tel', 'x', 'chat', 'web' ];

    public function __construct() {
        add_action( 'wp_ajax_' . self::ACTION, [ $this, 'handle' ] );
        add_action( 'wp_ajax_nopriv_' . self::ACTION, [ $this, 'handle' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 20 );
    }

    public function enqueue(): void {
        // Nur dort laden, wo Kontaktsymbole ueberhaupt vorkommen.
        if ( ! function_exists( 'is_woocommerce' ) ) {
            return;
        }
        if ( ! is_product() && ! is_shop() && ! is_product_category() && ! is_product_tag() && ! sk_is_store_page() ) {
            return;
        }

        wp_enqueue_script(
            'sk-contact-clicks',
            SK_CC_URL . '/assets/js/contact-clicks.js',
            [],
            SK_CC_VERSION,
            true
        );

        wp_localize_script(
            'sk-contact-clicks',
            'skContactClicks',
            [
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'action'  => self::ACTION,
                'nonce'   => wp_create_nonce( self::ACTION ),
            ]
        );
    }

    public function handle(): void {
        // Beacons erwarten keine Antwort; trotzdem sauber abschliessen.
        if ( ! check_ajax_referer( self::ACTION, 'nonce', false ) ) {
            wp_send_json_error( null, 403 );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $channel    = isset( $_POST['channel'] ) ? sanitize_key( wp_unslash( $_POST['channel'] ) ) : '';
        $context    = isset( $_POST['context'] ) ? sanitize_key( wp_unslash( $_POST['context'] ) ) : '';

        if ( ! in_array( $channel, self::CHANNELS, true ) ) {
            wp_send_json_error( null, 400 );
        }

        if ( ! $this->is_countable() ) {
            wp_send_json_success( null );
        }

        $vendor_id = 0;
        if ( $product_id > 0 ) {
            $post = get_post( $product_id );
            if ( ! $post || $post->post_type !== 'product' ) {
                wp_send_json_error( null, 400 );
            }
            $vendor_id = (int) $post->post_author;

            // Der eigene Verkaeufer soll seine Zahlen nicht selbst hochtreiben.
            if ( $vendor_id === get_current_user_id() ) {
                wp_send_json_success( null );
            }
        }

        $this->count( $product_id, $vendor_id, $channel, $context );

        wp_send_json_success( null );
    }

    /**
     * Zaehlt dieser Aufruf als echter Klick?
     */
    private function is_countable(): bool {
        $agent = trim( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
        if ( $agent === '' ) {
            return false;
        }

        $needles = [
            'bot', 'crawl', 'spider', 'slurp', 'curl', 'wget', 'python-requests',
            'httpclient', 'headless', 'phantom', 'monitor', 'preview',
            'facebookexternalhit', 'embedly', 'whatsapp', 'telegrambot', 'discordbot', 'slackbot',
        ];

        $lower = strtolower( $agent );
        foreach ( $needles as $needle ) {
            if ( strpos( $lower, $needle ) !== false ) {
                return false;
            }
        }

        return true;
    }

    private function count( int $product_id, int $vendor_id, string $channel, string $context ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'sk_contact_clicks';
        $now   = current_time( 'mysql' );
        $day   = current_time( 'Y-m-d' );

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table}
                    (product_id, vendor_id, channel, context, click_day, visitor_hash, clicks, first_seen, last_seen)
                 VALUES (%d, %d, %s, %s, %s, %s, 1, %s, %s)
                 ON DUPLICATE KEY UPDATE clicks = clicks + 1, last_seen = VALUES(last_seen)",
                $product_id,
                $vendor_id,
                $channel,
                $context,
                $day,
                $this->visitor_hash( $day ),
                $now,
                $now
            )
        );
    }

    /**
     * Tagesrotierender Besucher-Hash, ohne gespeicherte IP.
     */
    private function visitor_hash( string $day ): string {
        return md5(
            (string) ( $_SERVER['REMOTE_ADDR'] ?? '' )
            . '|' . (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' )
            . '|' . $day
            . '|' . wp_salt( 'nonce' )
        );
    }
}
