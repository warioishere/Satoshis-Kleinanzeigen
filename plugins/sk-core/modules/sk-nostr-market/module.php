<?php

namespace SK\Modules\NostrMarket;

defined( 'ABSPATH' ) || exit;

/**
 * SK Nostr Market — NIP-15 Marketplace Integration.
 *
 * Publishes WooCommerce products as structured NIP-15 events on Nostr relays.
 * Products become visible on Plebeian Market, LNbits NostrMarket,
 * and any NIP-15-compatible Nostr client.
 *
 * Event types:
 *   Kind 30017 — Stall (one per vendor)
 *   Kind 30018 — Product listing
 *   Kind 5     — Deletion
 *
 * NIP-15 spec: https://github.com/nostr-protocol/nips/blob/master/15.md
 */
final class Module {

    public $version = '1.0.0';

    private static $shutdown_queue = [];

    public function __construct() {
        $this->define_constants();
        $this->includes();

        // Settings always load.
        new MarketplaceSettings();

        if ( sk_get_option( 'sk_nostr_market_enabled', 'sk_nostr_market', 'off' ) !== 'on' ) {
            return;
        }

        $this->register_hooks();
    }

    private function define_constants() {
        define( 'SK_NOSTR_MARKET_VERSION', $this->version );
        define( 'SK_NOSTR_MARKET_PATH', dirname( __FILE__ ) );
        define( 'SK_NOSTR_MARKET_INCLUDES', SK_NOSTR_MARKET_PATH . '/includes' );
    }

    private function includes() {
        require_once SK_NOSTR_MARKET_INCLUDES . '/EventSender.php';
        require_once SK_NOSTR_MARKET_INCLUDES . '/StallManager.php';
        require_once SK_NOSTR_MARKET_INCLUDES . '/ProductPublisher.php';
        require_once SK_NOSTR_MARKET_INCLUDES . '/ProductDeleter.php';
        require_once SK_NOSTR_MARKET_INCLUDES . '/MarketplaceSettings.php';
    }

    private function register_hooks() {
        // Publish on new product.
        add_action( 'sk_new_product_added', [ $this, 'on_product_published' ], 10 );

        // Publish on draft/pending → publish transition.
        add_action( 'transition_post_status', [ $this, 'on_status_transition' ], 10, 3 );

        // Update on product edit.
        add_action( 'sk_product_updated', [ $this, 'on_product_updated' ], 10 );

        // Delete on trash/delete.
        add_action( 'wp_trash_post', [ $this, 'on_product_deleted' ] );
        add_action( 'before_delete_post', [ $this, 'on_product_deleted' ] );

        // Process queue after response is sent.
        register_shutdown_function( [ __CLASS__, 'process_queue' ] );
    }

    public function on_product_published( $post_id ) {
        $post_id = (int) $post_id;
        $post    = get_post( $post_id );

        if ( ! $post || $post->post_type !== 'product' || $post->post_status !== 'publish' ) {
            return;
        }

        // Skip if already published.
        if ( ProductPublisher::has_event( $post_id ) ) {
            return;
        }

        self::queue( $post_id, 'publish' );
    }

    public function on_status_transition( $new_status, $old_status, $post ) {
        if ( ! $post || $post->post_type !== 'product' ) {
            return;
        }

        if ( $new_status === 'publish' && in_array( $old_status, [ 'draft', 'pending', 'auto-draft' ], true ) ) {
            if ( ! ProductPublisher::has_event( $post->ID ) ) {
                self::queue( $post->ID, 'publish' );
            }
        }
    }

    public function on_product_updated( $post_id ) {
        $post_id = (int) $post_id;
        $post    = get_post( $post_id );

        if ( ! $post || $post->post_type !== 'product' || $post->post_status !== 'publish' ) {
            return;
        }

        // Only update if already published to Nostr.
        if ( ProductPublisher::has_event( $post_id ) ) {
            self::queue( $post_id, 'update' );
        }
    }

    public function on_product_deleted( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'product' ) {
            return;
        }

        self::queue( $post_id, 'delete' );
    }

    private static function queue( int $post_id, string $action ) {
        // Deduplicate.
        foreach ( self::$shutdown_queue as $item ) {
            if ( $item['post_id'] === $post_id && $item['action'] === $action ) {
                return;
            }
        }

        self::$shutdown_queue[] = [
            'post_id' => $post_id,
            'action'  => $action,
        ];
    }

    /**
     * Process the queue after the response has been sent to the browser.
     */
    public static function process_queue() {
        if ( empty( self::$shutdown_queue ) ) {
            return;
        }

        // Flush response to browser first.
        if ( function_exists( 'fastcgi_finish_request' ) ) {
            fastcgi_finish_request();
        }

        foreach ( self::$shutdown_queue as $item ) {
            switch ( $item['action'] ) {
                case 'publish':
                case 'update':
                    // For updates, delete old event first (replaceable events handle this,
                    // but explicit delete is cleaner for clients that don't support replaceable).
                    if ( $item['action'] === 'update' ) {
                        ProductDeleter::delete( $item['post_id'] );
                    }
                    ProductPublisher::publish( $item['post_id'] );
                    break;

                case 'delete':
                    ProductDeleter::delete( $item['post_id'] );
                    break;
            }
        }

        self::$shutdown_queue = [];
    }
}
