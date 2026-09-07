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

    public $version;

    private static $shutdown_queue = [];

    public function __construct() {
        $this->version = sk_assets_version( __DIR__ . '/assets' );
        $this->define_constants();
        $this->includes();

        // Bridge chats that already exist keep rendering correctly while the
        // module is switched off; this only affects how they are displayed.
        add_filter( 'pre_get_avatar_data', [ Bridge\ChatBridge::class, 'avatar_data' ], 10, 2 );

        if ( sk_get_option( 'sk_nostr_market_enabled', 'sk_nostr_market', 'off' ) !== 'on' ) {
            return;
        }

        $this->register_hooks();

        // Save per-product Nostr checkbox.
        add_action( 'sk_product_updated', [ $this, 'save_product_nostr_meta' ], 5 );
        add_action( 'sk_new_product_added', [ $this, 'save_product_nostr_meta' ], 5 );

        // NIP-07 self-signing removed — server-side signing via NostrIdentity handles this now.
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

        // Withdrawals wait in user meta: their product may be gone already.
        $pending_delete = [];
        $vendor_pubkey  = strtolower( (string) get_user_meta( $vendor_id, 'nostr_public_key', true ) );

        foreach ( ProductDeleter::pending_for( $vendor_id ) as $entry ) {
            $pending_delete[] = [
                'post_id'  => (int) $entry['post_id'],
                'event_id' => (string) $entry['event_id'],
                'title'    => (string) ( $entry['title'] ?? '' ),
                'tags'     => ProductDeleter::tags( (string) $entry['event_id'], $vendor_pubkey, 'sk-' . (int) $entry['post_id'] ),
            ];
        }

        if ( empty( $pending ) && empty( $pending_delete ) ) {
            return;
        }

        // Build unsigned event data for each pending product.
        $pending_data = [];
        foreach ( $pending as $post_id ) {
            $event_data = ProductPublisher::build_event_data( (int) $post_id );
            if ( $event_data ) {
                $pending_data[] = [
                    'post_id' => (int) $post_id,
                    'title'   => get_the_title( (int) $post_id ),
                    'content' => $event_data['content'],
                    'tags'    => $event_data['tags'],
                ];
            }
        }

        if ( empty( $pending_data ) && empty( $pending_delete ) ) {
            return;
        }

        wp_enqueue_style(
            'sk-nostr-sign',
            plugins_url( 'assets/css/nostr-sign.css', SK_NOSTR_MARKET_PATH . '/module.php' ),
            [],
            SK_NOSTR_MARKET_VERSION
        );

        wp_enqueue_script(
            'sk-nostr-sign',
            plugins_url( 'assets/js/nostr-sign.js', SK_NOSTR_MARKET_PATH . '/module.php' ),
            [ 'jquery' ],
            SK_NOSTR_MARKET_VERSION,
            true
        );

        wp_localize_script( 'sk-nostr-sign', 'skNostrMarket', [
            'ajaxurl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'sk_nostr_market_sign' ),
            'pendingSign'   => $pending_data,
            'pendingDelete' => $pending_delete,
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
        require_once SK_NOSTR_MARKET_INCLUDES . '/Bridge/SeenEvents.php';
        require_once SK_NOSTR_MARKET_INCLUDES . '/Bridge/NostrDMListener.php';
        require_once SK_NOSTR_MARKET_INCLUDES . '/Bridge/ChatBridge.php';
    }

    private function register_hooks() {
        // The relay list is what we tell others about our mailboxes; when it
        // changes, the announcement has to follow or senders keep writing to
        // relays we no longer read.
        add_action( 'update_option_nostr_login_relays', [ Bridge\ChatBridge::class, 'announce_dm_relays' ] );
        add_action( 'update_option_sk_nostr', [ Bridge\ChatBridge::class, 'announce_dm_relays' ] );

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

        // Browser signing, for vendors with their own Nostr extension.
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_signing_js' ] );

        // Identity selection in the listing form.
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_identity_js' ] );

        // Messages only the vendor themselves can open.
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_inbox_js' ] );
        add_action( 'wp_ajax_sk_nostr_pending_wraps', [ $this, 'ajax_pending_wraps' ] );
        add_action( 'wp_ajax_sk_nostr_deliver_decrypted', [ $this, 'ajax_deliver_decrypted' ] );
        add_action( 'wp_ajax_sk_nostr_drop_wrap', [ $this, 'ajax_drop_wrap' ] );
        add_action( 'wp_ajax_sk_nostr_pending_replies', [ $this, 'ajax_pending_replies' ] );
        add_action( 'wp_ajax_sk_nostr_deliver_sealed', [ $this, 'ajax_deliver_sealed' ] );
        add_action( 'wp_ajax_sk_nostr_drop_reply', [ $this, 'ajax_drop_reply' ] );
        add_action( 'wp_ajax_sk_nostr_market_fallback_sign', [ $this, 'ajax_fallback_sign' ] );
        add_action( 'wp_ajax_sk_nostr_market_cancel_sign', [ $this, 'ajax_cancel_sign' ] );
        add_action( 'wp_ajax_sk_nostr_market_publish_signed_delete', [ $this, 'ajax_publish_signed_delete' ] );
        add_action( 'wp_ajax_sk_nostr_market_cancel_delete', [ $this, 'ajax_cancel_delete' ] );
        add_action( 'wp_footer', [ $this, 'render_sign_modal' ] );

        // "Repost" button on the listing edit screen in the admin area.
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_css' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_repost_box' ] );
        add_action( 'admin_post_sk_nostr_repost', [ $this, 'handle_repost' ] );
        add_action( 'admin_notices', [ $this, 'repost_notice' ] );

        // Nostr DM Bridge: poll incoming DMs + forward vendor replies.
        Bridge\NostrDMListener::init();
        Bridge\ChatBridge::init();

        // Process queue after response is sent.
        register_shutdown_function( [ __CLASS__, 'process_queue' ] );
    }

    /**
     * Does this vendor have to sign their listing themselves?
     *
     * Exactly when they bring their own Nostr key but we don't hold it —
     * i.e. when logging in via a Nostr extension. Their private key never
     * leaves their browser in that case, so we can't sign anything on their
     * behalf and have to ask them.
     *
     * Anyone who had their identity generated during onboarding is excluded:
     * their key sits with us encrypted, so the server signs without asking.
     *
     * This used to hinge on a setting that was never written anywhere — the
     * check was therefore always false and the whole path dead.
     */
    public static function vendor_wants_self_sign( int $vendor_id ): bool {
        if ( empty( get_user_meta( $vendor_id, 'nostr_public_key', true ) ) ) {
            return false;
        }

        if ( ! class_exists( 'SK\Modules\Auth\NostrIdentity' ) ) {
            return false;
        }

        return ! \SK\Modules\Auth\NostrIdentity::has_identity( $vendor_id );
    }

    /**
     * The script for identity selection in the listing form.
     *
     * Only for vendors without their own key — everyone else never sees the
     * box, so they don't need the script either.
     */
    public function enqueue_identity_js(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();

        if ( ! empty( get_user_meta( $user_id, 'nostr_public_key', true ) ) ) {
            return;
        }

        if ( ! function_exists( 'sk_is_user_seller' ) || ! sk_is_user_seller( $user_id ) ) {
            return;
        }

        wp_enqueue_script(
            'sk-nostr-identity',
            plugins_url( 'assets/js/nostr-identity.js', SK_NOSTR_MARKET_PATH . '/module.php' ),
            [ 'jquery' ],
            SK_NOSTR_MARKET_VERSION,
            true
        );

        wp_localize_script( 'sk-nostr-identity', 'skNostrIdentity', [
            'ajaxurl'     => admin_url( 'admin-ajax.php' ),
            // Same action as in onboarding, so the same nonce.
            'nonce'       => wp_create_nonce( 'uob_ajax_nonce' ),
            'i18nWorking' => __( 'Wird erstellt…', 'sk-core' ),
            'i18nRetry'   => __( 'Erneut versuchen', 'sk-core' ),
            'i18nCopy'    => __( 'Kopieren', 'sk-core' ),
            'i18nCopied'  => __( 'Kopiert', 'sk-core' ),
        ] );
    }

    /**
     * The script that opens queued messages in the browser and seals queued
     * replies there.
     *
     * Only for vendors whose key we do not hold and who have something
     * waiting. Nobody else gets to see it.
     */
    public function enqueue_inbox_js(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();

        if ( empty( Bridge\NostrDMListener::pending_for( $user_id ) ) && empty( Bridge\ChatBridge::pending_replies_for( $user_id ) ) ) {
            return;
        }

        wp_enqueue_script(
            'sk-nostr-inbox',
            plugins_url( 'assets/js/nostr-inbox.js', SK_NOSTR_MARKET_PATH . '/module.php' ),
            [ 'jquery' ],
            SK_NOSTR_MARKET_VERSION,
            true
        );

        wp_localize_script( 'sk-nostr-inbox', 'skNostrInbox', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'sk_nostr_inbox' ),
        ] );
    }

    /**
     * AJAX: what needs opening for the logged-in vendor.
     *
     * The events are encrypted and sat openly on the relays anyway — nothing
     * is disclosed here that wasn't already public.
     */
    public function ajax_pending_wraps(): void {
        check_ajax_referer( 'sk_nostr_inbox', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Nicht angemeldet.' ] );
        }

        wp_send_json_success( [
            'wraps' => array_values( Bridge\NostrDMListener::pending_for( get_current_user_id() ) ),
        ] );
    }

    /**
     * AJAX: accept a message opened in the browser.
     */
    public function ajax_deliver_decrypted(): void {
        check_ajax_referer( 'sk_nostr_inbox', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Nicht angemeldet.' ] );
        }

        $event_id = sanitize_text_field( (string) wp_unslash( $_POST['event_id'] ?? '' ) );
        $seal     = json_decode( (string) wp_unslash( $_POST['seal'] ?? '' ), true );
        $text     = (string) wp_unslash( $_POST['text'] ?? '' );

        $ok = is_array( $seal ) && Bridge\NostrDMListener::deliver_decrypted( get_current_user_id(), $event_id, $seal, $text );

        if ( ! $ok ) {
            wp_send_json_error( [ 'message' => 'Nachricht nicht zustellbar.' ] );
        }

        wp_send_json_success();
    }

    /**
     * AJAX: replies the vendor has to seal in the browser.
     */
    public function ajax_pending_replies(): void {
        check_ajax_referer( 'sk_nostr_inbox', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Nicht angemeldet.' ] );
        }

        wp_send_json_success( [
            'replies' => Bridge\ChatBridge::pending_replies_for( get_current_user_id() ),
        ] );
    }

    /**
     * AJAX: accept a seal signed in the browser, wrap it and send it.
     */
    public function ajax_deliver_sealed(): void {
        check_ajax_referer( 'sk_nostr_inbox', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Nicht angemeldet.' ] );
        }

        $reply_id = sanitize_text_field( (string) wp_unslash( $_POST['reply_id'] ?? '' ) );
        $seal     = json_decode( (string) wp_unslash( $_POST['seal'] ?? '' ), true );

        $ok = is_array( $seal ) && Bridge\ChatBridge::deliver_sealed( get_current_user_id(), $reply_id, $seal );

        if ( ! $ok ) {
            wp_send_json_error( [ 'message' => 'Antwort nicht gesendet.' ] );
        }

        wp_send_json_success();
    }

    /**
     * AJAX: discard a queued reply that could not be sealed.
     */
    public function ajax_drop_reply(): void {
        check_ajax_referer( 'sk_nostr_inbox', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Nicht angemeldet.' ] );
        }

        Bridge\ChatBridge::forget_reply(
            get_current_user_id(),
            sanitize_text_field( (string) wp_unslash( $_POST['reply_id'] ?? '' ) )
        );

        wp_send_json_success();
    }

    /**
     * AJAX: discard a queued message that could not be opened.
     */
    public function ajax_drop_wrap(): void {
        check_ajax_referer( 'sk_nostr_inbox', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Nicht angemeldet.' ] );
        }

        Bridge\NostrDMListener::forget_pending(
            get_current_user_id(),
            sanitize_text_field( (string) wp_unslash( $_POST['event_id'] ?? '' ) )
        );

        wp_send_json_success();
    }

    /**
     * The waiting modal for the signature.
     *
     * Only present in the document when something is actually waiting — the
     * JavaScript is loaded under the same condition. It's only made visible
     * by the script, so it doesn't sit there as a dead box without
     * JavaScript.
     */
    public function render_sign_modal(): void {
        if ( ! wp_script_is( 'sk-nostr-sign', 'enqueued' ) ) {
            return;
        }
        ?>
        <div id="sk-nostr-sign-modal" class="sk-nostr-sign-modal" data-state="waiting" style="display:none;">
            <div class="sk-nostr-sign-backdrop"></div>
            <div class="sk-nostr-sign-box">
                <div class="sk-nostr-sign-icon"><i class="fas fa-circle-notch"></i></div>
                <h3 class="sk-nostr-sign-heading"><?php esc_html_e( 'Warten auf deine Signatur', 'sk-core' ); ?></h3>
                <p>
                    <span class="sk-nostr-sign-title"></span>
                    <span class="sk-nostr-sign-text"><?php esc_html_e( 'Deine Nostr-Erweiterung fragt gleich nach deiner Unterschrift. Danach geht dein Inserat unter deinem eigenen Schlüssel ins Nostr-Netz.', 'sk-core' ); ?></span>
                </p>
                <div class="sk-nostr-sign-actions">
                    <button type="button" class="sk-nostr-sign-retry" style="display:none;"><?php esc_html_e( 'Erneut versuchen', 'sk-core' ); ?></button>
                    <button type="button" class="sk-nostr-sign-cancel"><?php esc_html_e( 'Abbrechen', 'sk-core' ); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: the vendor cancels.
     *
     * The pending marker is cleared, otherwise it would ask again on every
     * page load. The listing stays on the platform, it just doesn't go to
     * Nostr — the next save asks again.
     */
    public function ajax_cancel_sign(): void {
        check_ajax_referer( 'sk_nostr_market_sign', 'nonce' );

        $post_id = absint( $_POST['post_id'] ?? 0 );

        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( [ 'message' => 'Keine Berechtigung für dieses Inserat.' ] );
        }

        delete_post_meta( $post_id, '_sk_nostr_market_pending_sign' );

        wp_send_json_success();
    }

    /**
     * AJAX: accept an event signed by the vendor and distribute it.
     *
     * It was signed in the browser via window.nostr.signEvent() (NIP-07).
     */
    public function ajax_publish_signed_event(): void {
        check_ajax_referer( 'sk_nostr_market_sign', 'nonce' );

        $post_id      = absint( $_POST['post_id'] ?? 0 );
        $signed_event = json_decode( (string) wp_unslash( $_POST['signed_event'] ?? '' ), true );

        if ( ! $post_id || empty( $signed_event ) || ! is_array( $signed_event ) ) {
            wp_send_json_error( [ 'message' => 'Fehlende Parameter.' ] );
        }

        /*
         * Without this check, a logged-in user could send someone else's
         * listing id and overwrite the event id stored there. Matching the
         * key alone isn't enough — it only says who signed, not who owns the
         * listing.
         */
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( [ 'message' => 'Keine Berechtigung für dieses Inserat.' ] );
        }

        $vendor_pubkey = get_user_meta( get_current_user_id(), 'nostr_public_key', true );

        if ( empty( $vendor_pubkey ) || ( $signed_event['pubkey'] ?? '' ) !== $vendor_pubkey ) {
            wp_send_json_error( [ 'message' => 'Pubkey stimmt nicht überein.' ] );
        }

        // Only the listing itself, under its own d tag. The event id is stored
        // on the product and used later for the deletion event, so it has to
        // belong to this product.
        if ( 30402 !== ( $signed_event['kind'] ?? 0 ) || ! self::has_d_tag( $signed_event, 'sk-' . $post_id ) ) {
            wp_send_json_error( [ 'message' => 'Das Ereignis gehört nicht zu diesem Inserat.' ] );
        }

        $report   = null;
        $event_id = EventSender::send_signed( $signed_event, $report );

        // Recorded before the verdict: a listing no relay took is exactly the
        // case where the reasons are worth having.
        ProductPublisher::store_relay_report( $post_id, $report );

        if ( ! $event_id ) {
            wp_send_json_error( [ 'message' => 'Kein Relay hat das Event akzeptiert.' ] );
        }

        update_post_meta( $post_id, ProductPublisher::META_KEY, $event_id );
        update_post_meta( $post_id, '_sk_nostr_market_self_signed', '1' );
        delete_post_meta( $post_id, '_sk_nostr_market_pending_sign' );

        wp_send_json_success( [ 'event_id' => $event_id ] );
    }

    /**
     * AJAX: a Kind 5 the vendor signed in the browser to withdraw a listing.
     *
     * The event must reference a withdrawal from the vendor's own queue; the
     * queue entry, not the browser, says which event id may be withdrawn.
     */
    public function ajax_publish_signed_delete(): void {
        check_ajax_referer( 'sk_nostr_market_sign', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Nicht angemeldet.' ] );
        }

        $vendor_id    = get_current_user_id();
        $signed_event = json_decode( (string) wp_unslash( $_POST['signed_event'] ?? '' ), true );

        if ( empty( $signed_event ) || ! is_array( $signed_event ) ) {
            wp_send_json_error( [ 'message' => 'Fehlende Parameter.' ] );
        }

        $vendor_pubkey = get_user_meta( $vendor_id, 'nostr_public_key', true );

        if ( empty( $vendor_pubkey ) || ( $signed_event['pubkey'] ?? '' ) !== $vendor_pubkey ) {
            wp_send_json_error( [ 'message' => 'Pubkey stimmt nicht überein.' ] );
        }

        $target = '';

        foreach ( (array) ( $signed_event['tags'] ?? [] ) as $tag ) {
            if ( is_array( $tag ) && 'e' === ( $tag[0] ?? '' ) && is_string( $tag[1] ?? null ) ) {
                $target = $tag[1];
                break;
            }
        }

        if ( 5 !== ( $signed_event['kind'] ?? 0 ) || '' === $target || null === ProductDeleter::pending_entry( $vendor_id, $target ) ) {
            wp_send_json_error( [ 'message' => 'Das Ereignis gehört zu keinem offenen Rückzug.' ] );
        }

        if ( ! EventSender::send_signed( $signed_event ) ) {
            wp_send_json_error( [ 'message' => 'Kein Relay hat das Event akzeptiert.' ] );
        }

        ProductDeleter::forget_pending( $vendor_id, $target );

        wp_send_json_success();
    }

    /**
     * AJAX: the vendor declines to sign a withdrawal.
     */
    public function ajax_cancel_delete(): void {
        check_ajax_referer( 'sk_nostr_market_sign', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Nicht angemeldet.' ] );
        }

        ProductDeleter::forget_pending(
            get_current_user_id(),
            sanitize_text_field( (string) wp_unslash( $_POST['event_id'] ?? '' ) )
        );

        wp_send_json_success();
    }

    /**
     * Does the event carry the 'd' tag of exactly this product?
     */
    private static function has_d_tag( array $event, string $d ): bool {
        foreach ( (array) ( $event['tags'] ?? [] ) as $tag ) {
            if ( is_array( $tag ) && 'd' === ( $tag[0] ?? '' ) && $d === ( $tag[1] ?? '' ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * AJAX: no extension available, or the signature was declined.
     *
     * This used to make the listing go out under the marketplace's key.
     * That's gone: a listing carries its vendor's name, or it doesn't go to
     * Nostr at all. What remains is clearing the pending marker, otherwise
     * it would ask again on every page load.
     */
    public function ajax_fallback_sign(): void {
        $this->ajax_cancel_sign();
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
     * Should this listing go to Nostr?
     *
     * Decided on the listing itself, under "More options". There used to
     * also be a switch in the vendor profile above it; anyone who missed
     * that didn't understand why the checkbox on the listing did nothing.
     *
     * Default is off: publishing to an outside network is nothing that
     * should happen without being asked.
     */
    public static function product_wants_nostr( int $post_id ): bool {
        return get_post_meta( $post_id, '_sk_nostr_market_post', true ) === '1';
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

        // Skip if this specific product has Nostr unchecked.
        if ( ! self::product_wants_nostr( $post_id ) ) {
            return;
        }

        self::queue( $post_id, 'publish' );
    }

    public function on_status_transition( $new_status, $old_status, $post ) {
        if ( ! $post || $post->post_type !== 'product' ) {
            return;
        }

        if ( $new_status === 'publish' && in_array( $old_status, [ 'draft', 'pending', 'auto-draft' ], true ) ) {
            // This check was missing: a draft that got published went to
            // Nostr even when the checkbox on the listing was unchecked.
            if ( ! self::product_wants_nostr( $post->ID ) ) {
                return;
            }

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

        $gewollt   = self::product_wants_nostr( $post_id );
        $vorhanden = ProductPublisher::has_event( $post_id );

        /*
         * The checkbox is saved at priority 5, i.e. before this call — the
         * state read here is the new one.
         *
         * There can be no duplicate posts here: publishing only happens when
         * no event is recorded yet, and turning it off deletes the existing
         * one and removes the record. Anyone who turns it off and back on
         * gets a new event under the same id ('d'), which clients replace
         * rather than duplicate.
         */
        if ( $gewollt && ! $vorhanden ) {
            self::queue( $post_id, 'publish' );
        } elseif ( $gewollt ) {
            self::queue( $post_id, 'update' );
        } elseif ( $vorhanden ) {
            self::queue( $post_id, 'delete' );
        }
    }

    public function on_product_deleted( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'product' ) {
            return;
        }

        // Capture what the deletion needs now. The queue runs at shutdown,
        // and on before_delete_post the meta has been dropped with the post
        // by then — a permanent delete never sent a Kind 5.
        $event_id = (string) get_post_meta( (int) $post_id, ProductPublisher::META_KEY, true );

        if ( '' === $event_id ) {
            return;
        }

        self::queue( (int) $post_id, 'delete', [
            'event_id'  => $event_id,
            'vendor_id' => (int) $post->post_author,
            'title'     => (string) $post->post_title,
        ] );
    }

    /**
     * Box on the listing edit screen in the admin area.
     *
     * Same behavior as the Telegram reposter next to it: a button that sends
     * immediately instead of waiting for the next save.
     */
    /**
     * Styles for the Nostr box, on the product screen only.
     */
    public function enqueue_admin_css( $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        if ( ! $screen || 'product' !== $screen->post_type ) {
            return;
        }

        wp_enqueue_style(
            'sk-nostr-admin',
            plugins_url( 'assets/css/nostr-admin.css', SK_NOSTR_MARKET_PATH . '/module.php' ),
            [],
            SK_NOSTR_MARKET_VERSION
        );
    }

    public function add_repost_box(): void {
        add_meta_box(
            'sk_nostr_repost_box',
            __( 'Nostr', 'sk-core' ),
            [ $this, 'render_repost_box' ],
            'product',
            'side',
            'default'
        );
    }

    /**
     * @param \WP_Post $post
     */
    public function render_repost_box( $post ): void {
        if ( ! $post instanceof \WP_Post || 'product' !== $post->post_type ) {
            return;
        }

        $vorhanden = ProductPublisher::has_event( (int) $post->ID );
        $gewollt   = self::product_wants_nostr( (int) $post->ID );

        $url = wp_nonce_url(
            admin_url( 'admin-post.php?action=sk_nostr_repost&post_id=' . (int) $post->ID ),
            'sk_nostr_repost_' . (int) $post->ID
        );

        echo '<p><a href="' . esc_url( $url ) . '" class="button button-primary">'
            . esc_html__( 'Jetzt auf Nostr posten', 'sk-core' ) . '</a></p>';

        if ( $vorhanden ) {
            echo '<p class="sk-nostr-box-note">' . esc_html__( 'Bereits auf Nostr. Der Knopf ersetzt den bestehenden Beitrag.', 'sk-core' ) . '</p>';
        } elseif ( ! $gewollt ) {
            echo '<p class="sk-nostr-box-note">' . esc_html__( 'Am Inserat ist Nostr abgewählt. Der Knopf postet trotzdem, einmalig.', 'sk-core' ) . '</p>';
        } else {
            echo '<p class="sk-nostr-box-note">' . esc_html__( 'Noch nicht auf Nostr.', 'sk-core' ) . '</p>';
        }

        self::render_relay_report( (int) $post->ID );
    }

    /**
     * Which relays hold the listing — and which refused it.
     *
     * A listing sitting on one of five relays is invisible to a client that
     * reads the other four, and until now nothing here said so.
     */
    private static function render_relay_report( int $post_id ): void {
        $report = ProductPublisher::relay_report( $post_id );

        if ( null === $report ) {
            return;
        }

        $accepted = (array) ( $report['accepted'] ?? [] );
        $rejected = (array) ( $report['rejected'] ?? [] );

        echo '<p class="sk-nostr-relays-title"><strong>' . esc_html__( 'Relays', 'sk-core' ) . '</strong>';

        if ( ! empty( $report['time'] ) ) {
            echo ' <span class="sk-nostr-relays-time">'
                . esc_html( wp_date( 'd.m.Y H:i', (int) $report['time'] ) ) . '</span>';
        }

        echo '</p><ul class="sk-nostr-relays">';

        foreach ( $accepted as $url ) {
            echo '<li class="sk-nostr-relay-ok">✓ ' . esc_html( self::relay_label( (string) $url ) ) . '</li>';
        }

        foreach ( $rejected as $url => $reason ) {
            echo '<li class="sk-nostr-relay-failed">✕ ' . esc_html( self::relay_label( (string) $url ) )
                . ' <span class="sk-nostr-relay-reason">— ' . esc_html( (string) $reason ) . '</span></li>';
        }

        if ( empty( $accepted ) ) {
            echo '<li class="sk-nostr-relay-failed">' . esc_html__( 'Kein Relay hat das Inserat angenommen.', 'sk-core' ) . '</li>';
        }

        echo '</ul>';
    }

    /**
     * Relay URL without the scheme — the box is narrow.
     */
    private static function relay_label( string $url ): string {
        return (string) preg_replace( '#^wss?://#', '', untrailingslashit( $url ) );
    }

    /**
     * Post immediately, without waiting for a save.
     *
     * An existing event is deleted first. For kind 30402 a new one with the
     * same id would replace it anyway; the delete is meant for clients that
     * don't handle replaceable events cleanly.
     */
    public function handle_repost(): void {
        $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( esc_html__( 'Keine Berechtigung.', 'sk-core' ) );
        }

        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'sk_nostr_repost_' . $post_id ) ) {
            wp_die( esc_html__( 'Sicherheitsprüfung fehlgeschlagen.', 'sk-core' ) );
        }

        // A vendor with their own extension signs in the browser; the server
        // cannot post for them. Mark the listing and tell the admin so.
        if ( self::vendor_wants_self_sign( (int) get_post_field( 'post_author', $post_id ) ) ) {
            update_post_meta( $post_id, '_sk_nostr_market_pending_sign', '1' );
            $status = 'pending';
        } else {
            if ( ProductPublisher::has_event( $post_id ) ) {
                ProductDeleter::delete( $post_id );
            }

            $status = ProductPublisher::publish( $post_id ) ? '1' : '0';
        }

        wp_safe_redirect( add_query_arg( 'sk_nostr_repost', $status, get_edit_post_link( $post_id, 'url' ) ) );
        exit;
    }

    public function repost_notice(): void {
        if ( ! isset( $_GET['sk_nostr_repost'] ) ) {
            return;
        }

        $status = (string) $_GET['sk_nostr_repost'];

        if ( 'pending' === $status ) {
            $class = 'notice-info';
            $text  = __( 'Nostr: Der Anbieter signiert selbst. Das Inserat wird beim nächsten Besuch seiner Erweiterung vorgelegt.', 'sk-core' );
        } elseif ( '1' === $status ) {
            $class = 'notice-success';
            $text  = __( 'Nostr: Inserat gesendet.', 'sk-core' );
        } else {
            $class = 'notice-error';
            $text  = __( 'Nostr: Senden fehlgeschlagen. Grund steht im Fehlerprotokoll.', 'sk-core' );
        }

        printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr( $class ), esc_html( $text ) );
    }

    /**
     * @param array $extra Data captured now for the shutdown handler.
     */
    private static function queue( int $post_id, string $action, array $extra = [] ) {
        // Deduplicate.
        foreach ( self::$shutdown_queue as $item ) {
            if ( $item['post_id'] === $post_id && $item['action'] === $action ) {
                return;
            }
        }

        self::$shutdown_queue[] = $extra + [
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
                    /*
                     * If the vendor brings their own key, we can't sign here —
                     * it sits in their browser. Instead of publishing under
                     * our name, the listing is queued; on the next page load
                     * the extension asks for their signature.
                     */
                    $autor = (int) get_post_field( 'post_author', $item['post_id'] );

                    if ( self::vendor_wants_self_sign( $autor ) ) {
                        update_post_meta( $item['post_id'], '_sk_nostr_market_pending_sign', '1' );
                        break;
                    }

                    // For updates, delete old event first (replaceable events handle this,
                    // but explicit delete is cleaner for clients that don't support replaceable).
                    if ( $item['action'] === 'update' ) {
                        ProductDeleter::delete( $item['post_id'] );
                    }
                    ProductPublisher::publish( $item['post_id'] );
                    break;

                case 'delete':
                    ProductDeleter::delete( $item['post_id'], (string) ( $item['event_id'] ?? '' ), (int) ( $item['vendor_id'] ?? 0 ), (string) ( $item['title'] ?? '' ) );
                    break;
            }
        }

        self::$shutdown_queue = [];
    }
}
