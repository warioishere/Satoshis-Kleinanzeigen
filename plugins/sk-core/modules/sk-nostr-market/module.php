<?php

namespace SK\Modules\NostrMarket;

defined( 'ABSPATH' ) || exit;

/**
 * SK Nostr Market — NIP-99 Classified Listings + DM Bridge.
 *
 * Publishes WooCommerce products as NIP-99 classified listings (Kind 30402)
 * on Nostr relays. Visible on Amethyst, Shopstr, Coracle, Plebeian Market,
 * and any NIP-99-compatible Nostr client.
 *
 * DM Bridge: Nostr users can contact vendors via DM, messages are bridged
 * to VendorChat. Vendor replies are sent back as Nostr DMs.
 *
 * Event types:
 *   Kind 30402 — Classified listing (product)
 *   Kind 5     — Deletion
 *   Kind 4     — NIP-04 DMs (bridge)
 *
 * NIP-99 spec: https://github.com/nostr-protocol/nips/blob/master/99.md
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

        // Save per-product Nostr checkbox.
        add_action( 'sk_product_updated', [ $this, 'save_product_nostr_meta' ], 5 );
        add_action( 'sk_new_product_added', [ $this, 'save_product_nostr_meta' ], 5 );

        // Enqueue NIP-07 signing JS on vendor dashboard.
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_signing_js' ], 30 );
    }

    /**
     * Enqueue signing JS on vendor product pages.
     * Checks for products pending self-signing and provides unsigned event data.
     */
    public function enqueue_signing_js(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $vendor_id = get_current_user_id();
        if ( ! self::vendor_wants_self_sign( $vendor_id ) ) {
            return;
        }

        // Find products pending self-signing.
        global $wpdb;
        $pending = $wpdb->get_col( $wpdb->prepare(
            "SELECT pm.post_id FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_sk_nostr_market_pending_sign' AND pm.meta_value = '1'
             AND p.post_author = %d AND p.post_status = 'publish'
             LIMIT 5",
            $vendor_id
        ) );

        if ( empty( $pending ) ) {
            return;
        }

        // Build unsigned event data for each pending product.
        $pending_data = [];
        foreach ( $pending as $post_id ) {
            $event_data = ProductPublisher::build_event_data( (int) $post_id );
            if ( $event_data ) {
                $pending_data[] = [
                    'post_id' => (int) $post_id,
                    'content' => $event_data['content'],
                    'tags'    => $event_data['tags'],
                ];
            }
        }

        if ( empty( $pending_data ) ) {
            return;
        }

        wp_enqueue_script(
            'sk-nostr-sign',
            plugins_url( 'assets/js/nostr-sign.js', SK_NOSTR_MARKET_PATH . '/module.php' ),
            [ 'jquery' ],
            SK_NOSTR_MARKET_VERSION,
            true
        );

        wp_localize_script( 'sk-nostr-sign', 'skNostrMarket', [
            'ajaxurl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'sk_nostr_market_sign' ),
            'pendingSign' => $pending_data,
        ] );
    }

    private function define_constants() {
        define( 'SK_NOSTR_MARKET_VERSION', $this->version );
        define( 'SK_NOSTR_MARKET_PATH', dirname( __FILE__ ) );
        define( 'SK_NOSTR_MARKET_INCLUDES', SK_NOSTR_MARKET_PATH . '/includes' );
    }

    private function includes() {
        require_once SK_NOSTR_MARKET_INCLUDES . '/EventSender.php';
        require_once SK_NOSTR_MARKET_INCLUDES . '/ProductPublisher.php';
        require_once SK_NOSTR_MARKET_INCLUDES . '/ProductDeleter.php';
        require_once SK_NOSTR_MARKET_INCLUDES . '/MarketplaceSettings.php';
        require_once SK_NOSTR_MARKET_INCLUDES . '/Bridge/NostrDMListener.php';
        require_once SK_NOSTR_MARKET_INCLUDES . '/Bridge/ChatBridge.php';
    }

    private function register_hooks() {
        // Save vendor Nostr Market preferences.
        add_action( 'sk_store_profile_saved', [ $this, 'save_vendor_settings' ], 20, 1 );

        // AJAX: Vendor signs event with NIP-07 extension.
        add_action( 'wp_ajax_sk_nostr_market_publish_signed', [ $this, 'ajax_publish_signed_event' ] );

        // Publish on new product.
        add_action( 'sk_new_product_added', [ $this, 'on_product_published' ], 10 );

        // Publish on draft/pending → publish transition.
        add_action( 'transition_post_status', [ $this, 'on_status_transition' ], 10, 3 );

        // Update on product edit.
        add_action( 'sk_product_updated', [ $this, 'on_product_updated' ], 10 );

        // Delete on trash/delete.
        add_action( 'wp_trash_post', [ $this, 'on_product_deleted' ] );
        add_action( 'before_delete_post', [ $this, 'on_product_deleted' ] );

        // Nostr DM Bridge: poll incoming DMs + forward vendor replies.
        Bridge\NostrDMListener::init();
        Bridge\ChatBridge::init();

        // Process queue after response is sent.
        register_shutdown_function( [ __CLASS__, 'process_queue' ] );
    }

    /**
     * Save vendor Nostr Market preferences from store settings form.
     */
    public function save_vendor_settings( int $store_id ): void {
        $settings = get_user_meta( $store_id, 'sk_profile_settings', true );
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }

        $settings['nostr_market_enabled']   = isset( $_POST['nostr_market_enabled'] ) ? sanitize_text_field( $_POST['nostr_market_enabled'] ) : '0';
        $settings['nostr_market_self_sign'] = isset( $_POST['nostr_market_self_sign'] ) ? sanitize_text_field( $_POST['nostr_market_self_sign'] ) : '0';

        update_user_meta( $store_id, 'sk_profile_settings', $settings );
    }

    /**
     * Check if a vendor has Nostr Market posting enabled.
     */
    public static function vendor_wants_nostr( int $vendor_id ): bool {
        $settings = get_user_meta( $vendor_id, 'sk_profile_settings', true );
        return is_array( $settings ) && ! empty( $settings['nostr_market_enabled'] ) && $settings['nostr_market_enabled'] === '1';
    }

    /**
     * Check if a vendor wants to self-sign with their Nostr key.
     */
    public static function vendor_wants_self_sign( int $vendor_id ): bool {
        $settings = get_user_meta( $vendor_id, 'sk_profile_settings', true );
        if ( ! is_array( $settings ) || empty( $settings['nostr_market_self_sign'] ) || $settings['nostr_market_self_sign'] !== '1' ) {
            return false;
        }
        // Must also have a Nostr pubkey.
        return ! empty( get_user_meta( $vendor_id, 'nostr_public_key', true ) );
    }

    /**
     * AJAX: Receive a vendor-signed NIP-99 event and publish it to relays.
     * The event was signed client-side via window.nostr.signEvent() (NIP-07).
     */
    public function ajax_publish_signed_event(): void {
        check_ajax_referer( 'sk_nostr_market_sign', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Nicht eingeloggt.' ] );
        }

        $signed_event = json_decode( wp_unslash( $_POST['signed_event'] ?? '' ), true );
        $post_id      = absint( $_POST['post_id'] ?? 0 );

        if ( empty( $signed_event ) || ! $post_id ) {
            wp_send_json_error( [ 'message' => 'Fehlende Parameter.' ] );
        }

        // Verify the event pubkey matches the vendor's stored pubkey.
        $vendor_pubkey = get_user_meta( get_current_user_id(), 'nostr_public_key', true );
        if ( empty( $vendor_pubkey ) || ( $signed_event['pubkey'] ?? '' ) !== $vendor_pubkey ) {
            wp_send_json_error( [ 'message' => 'Pubkey stimmt nicht überein.' ] );
        }

        // Publish the pre-signed event to relays.
        $relays = EventSender::get_relays();
        $sent   = false;

        foreach ( $relays as $relay_url ) {
            try {
                $msg   = new \swentel\nostr\Message\EventMessage( (object) $signed_event );
                $relay = new \swentel\nostr\Relay\Relay( $relay_url );
                if ( method_exists( $relay, 'setTimeout' ) ) {
                    $relay->setTimeout( 3 );
                }
                $relay->setMessage( $msg );
                $result = $relay->send();
                if ( $result !== false ) {
                    $sent = true;
                }
            } catch ( \Exception $e ) {
                error_log( "[SK Nostr Market] Relay error: " . $e->getMessage() );
            }
        }

        if ( $sent ) {
            $event_id = $signed_event['id'] ?? '';
            if ( $event_id ) {
                update_post_meta( $post_id, ProductPublisher::META_KEY, $event_id );
                update_post_meta( $post_id, '_sk_nostr_market_self_signed', '1' );
            }
            wp_send_json_success( [ 'event_id' => $event_id ] );
        } else {
            wp_send_json_error( [ 'message' => 'Kein Relay hat das Event akzeptiert.' ] );
        }
    }

    /**
     * Save per-product Nostr checkbox from product edit form.
     */
    public function save_product_nostr_meta( $post_id ): void {
        if ( isset( $_POST['_sk_nostr_market_post'] ) ) {
            update_post_meta( (int) $post_id, '_sk_nostr_market_post', sanitize_text_field( $_POST['_sk_nostr_market_post'] ) );
        }
    }

    /**
     * Check if a product should be posted to Nostr (per-product checkbox).
     */
    public static function product_wants_nostr( int $post_id ): bool {
        $meta = get_post_meta( $post_id, '_sk_nostr_market_post', true );
        // Default true if meta not set yet (new product from vendor with Nostr enabled).
        return $meta === '' || $meta === '1';
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

        $vendor_id = (int) $post->post_author;

        // Skip if vendor doesn't want Nostr posting.
        if ( ! self::vendor_wants_nostr( $vendor_id ) ) {
            return;
        }

        // Skip if this specific product has Nostr unchecked.
        if ( ! self::product_wants_nostr( $post_id ) ) {
            return;
        }

        // If vendor wants self-signing, skip server-side publishing.
        // The JS on the product page will handle it via NIP-07.
        if ( self::vendor_wants_self_sign( $vendor_id ) ) {
            // Mark as pending self-sign — JS will pick it up.
            update_post_meta( $post_id, '_sk_nostr_market_pending_sign', '1' );
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
