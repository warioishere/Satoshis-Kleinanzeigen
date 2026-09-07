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

        // Settings always load.
        new MarketplaceSettings();

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
                    'title'   => get_the_title( (int) $post_id ),
                    'content' => $event_data['content'],
                    'tags'    => $event_data['tags'],
                ];
            }
        }

        if ( empty( $pending_data ) ) {
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

        // Signieren im Browser, fuer Anbieter mit eigener Nostr-Erweiterung.
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_signing_js' ] );

        // Auswahl der Identitaet im Inseratsformular.
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_identity_js' ] );

        // Nachrichten, die nur der Anbieter selbst oeffnen kann.
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_inbox_js' ] );
        add_action( 'wp_ajax_sk_nostr_pending_wraps', [ $this, 'ajax_pending_wraps' ] );
        add_action( 'wp_ajax_sk_nostr_deliver_decrypted', [ $this, 'ajax_deliver_decrypted' ] );
        add_action( 'wp_ajax_sk_nostr_drop_wrap', [ $this, 'ajax_drop_wrap' ] );
        add_action( 'wp_ajax_sk_nostr_pending_replies', [ $this, 'ajax_pending_replies' ] );
        add_action( 'wp_ajax_sk_nostr_deliver_sealed', [ $this, 'ajax_deliver_sealed' ] );
        add_action( 'wp_ajax_sk_nostr_drop_reply', [ $this, 'ajax_drop_reply' ] );
        add_action( 'wp_ajax_sk_nostr_market_fallback_sign', [ $this, 'ajax_fallback_sign' ] );
        add_action( 'wp_ajax_sk_nostr_market_cancel_sign', [ $this, 'ajax_cancel_sign' ] );
        add_action( 'wp_footer', [ $this, 'render_sign_modal' ] );

        // Knopf "Erneut posten" auf der Inseratsseite im Adminbereich.
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
     * Muss dieser Anbieter sein Inserat selbst signieren?
     *
     * Genau dann, wenn er einen eigenen Nostr-Schluessel mitbringt, wir ihn
     * aber nicht haben — also bei Anmeldung ueber eine Nostr-Erweiterung. Sein
     * privater Schluessel verlaesst dabei nie seinen Browser, also koennen wir
     * in seinem Namen nichts signieren und muessen ihn fragen.
     *
     * Wer sich seine Identitaet beim Onboarding erzeugen liess, faellt nicht
     * darunter: dessen Schluessel liegt verschluesselt bei uns, da signiert der
     * Server ohne Rueckfrage.
     *
     * Frueher hing das an einer Einstellung, die nirgends geschrieben wurde —
     * die Pruefung war damit immer falsch und der ganze Weg tot.
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
     * Das Skript fuer die Identitaetsauswahl im Inseratsformular.
     *
     * Nur fuer Anbieter ohne eigenen Schluessel — alle anderen sehen den
     * Kasten gar nicht, dann braucht es auch das Skript nicht.
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
            // Dieselbe Aktion wie im Onboarding, also derselbe Nonce.
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
     * AJAX: was fuer den angemeldeten Anbieter zu oeffnen ist.
     *
     * Die Ereignisse sind verschluesselt und lagen ohnehin offen auf den
     * Relays — hier wird nichts preisgegeben, was nicht schon oeffentlich war.
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
     * AJAX: eine im Browser geoeffnete Nachricht entgegennehmen.
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
     * AJAX: eine vorgemerkte Nachricht verwerfen, die sich nicht oeffnen liess.
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
     * Das Wartefenster fuer die Unterschrift.
     *
     * Steht nur im Dokument, wenn wirklich etwas wartet — das JavaScript wird
     * unter derselben Bedingung geladen. Sichtbar wird es erst durch das
     * Skript, damit es ohne JavaScript nicht als toter Kasten stehenbleibt.
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
     * AJAX: der Anbieter bricht ab.
     *
     * Die Wartemarke faellt, sonst wuerde bei jedem Seitenaufruf erneut
     * gefragt. Das Inserat bleibt auf der Plattform, nur auf Nostr geht es
     * nicht — beim naechsten Speichern wird wieder gefragt.
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
     * AJAX: ein vom Anbieter signiertes Ereignis entgegennehmen und verteilen.
     *
     * Signiert wurde es im Browser ueber window.nostr.signEvent() (NIP-07).
     */
    public function ajax_publish_signed_event(): void {
        check_ajax_referer( 'sk_nostr_market_sign', 'nonce' );

        $post_id      = absint( $_POST['post_id'] ?? 0 );
        $signed_event = json_decode( (string) wp_unslash( $_POST['signed_event'] ?? '' ), true );

        if ( ! $post_id || empty( $signed_event ) || ! is_array( $signed_event ) ) {
            wp_send_json_error( [ 'message' => 'Fehlende Parameter.' ] );
        }

        /*
         * Ohne diese Pruefung konnte ein angemeldeter Nutzer eine fremde
         * Inseratsnummer schicken und dort die Ereigniskennung ueberschreiben.
         * Der Abgleich des Schluessels allein genuegt nicht — er sagt nur, wer
         * signiert hat, nicht wem das Inserat gehoert.
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

        $event_id = EventSender::send_signed( $signed_event );

        if ( ! $event_id ) {
            wp_send_json_error( [ 'message' => 'Kein Relay hat das Event akzeptiert.' ] );
        }

        update_post_meta( $post_id, ProductPublisher::META_KEY, $event_id );
        update_post_meta( $post_id, '_sk_nostr_market_self_signed', '1' );
        delete_post_meta( $post_id, '_sk_nostr_market_pending_sign' );

        wp_send_json_success( [ 'event_id' => $event_id ] );
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
     * AJAX: keine Erweiterung da oder Signatur abgelehnt.
     *
     * Frueher ging das Inserat dann unter dem Schluessel des Marktplatzes
     * raus. Das faellt weg: ein Inserat traegt den Namen seines Anbieters,
     * oder es geht nicht auf Nostr. Bleibt, die Vormerkung zu loeschen, sonst
     * wuerde bei jedem Seitenaufruf erneut gefragt.
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
     * Soll dieses Inserat auf Nostr?
     *
     * Entschieden wird das am Inserat selbst, unter "Weitere Optionen".
     * Frueher stand darueber noch ein Schalter im Anbieterprofil; wer den
     * uebersah, verstand nicht, warum das Kaestchen am Inserat nichts tat.
     *
     * Vorgabe ist aus: Veroeffentlichen in ein fremdes Netz ist nichts, was
     * ungefragt passieren sollte.
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
            // Diese Pruefung fehlte: ein Entwurf, der veroeffentlicht wurde,
            // ging auf Nostr, auch wenn das Kaestchen am Inserat leer war.
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
         * Das Kaestchen wird auf Prioritaet 5 gespeichert, also vor diesem
         * Aufruf — der Stand hier ist der neue.
         *
         * Doppelte Posts kann es dabei nicht geben: veroeffentlicht wird nur,
         * wenn noch kein Ereignis vermerkt ist, und beim Abschalten wird das
         * bestehende geloescht und der Vermerk entfernt. Wer aus- und wieder
         * einschaltet, bekommt ein neues Ereignis unter derselben Kennung
         * ('d'), das die Clients ersetzen statt danebenzulegen.
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

        self::queue( $post_id, 'delete' );
    }

    /**
     * Kasten auf der Inseratsseite im Adminbereich.
     *
     * Gleiche Bedienung wie beim Telegram-Reposter nebenan: ein Knopf, der
     * sofort sendet, statt auf den naechsten Speichervorgang zu warten.
     */
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
            echo '<p style="color:#666">' . esc_html__( 'Bereits auf Nostr. Der Knopf ersetzt den bestehenden Beitrag.', 'sk-core' ) . '</p>';
        } elseif ( ! $gewollt ) {
            echo '<p style="color:#666">' . esc_html__( 'Am Inserat ist Nostr abgewählt. Der Knopf postet trotzdem, einmalig.', 'sk-core' ) . '</p>';
        } else {
            echo '<p style="color:#666">' . esc_html__( 'Noch nicht auf Nostr.', 'sk-core' ) . '</p>';
        }
    }

    /**
     * Sofort posten, ohne auf einen Speichervorgang zu warten.
     *
     * Ein bestehender Beitrag wird vorher geloescht. Bei Kind 30402 ersetzt
     * ihn ein neuer mit derselben Kennung ohnehin; das Loeschen ist fuer
     * Clients gedacht, die ersetzbare Ereignisse nicht sauber behandeln.
     */
    public function handle_repost(): void {
        $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_die( esc_html__( 'Keine Berechtigung.', 'sk-core' ) );
        }

        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'sk_nostr_repost_' . $post_id ) ) {
            wp_die( esc_html__( 'Sicherheitsprüfung fehlgeschlagen.', 'sk-core' ) );
        }

        if ( ProductPublisher::has_event( $post_id ) ) {
            ProductDeleter::delete( $post_id );
        }

        $event_id = ProductPublisher::publish( $post_id );

        wp_safe_redirect( add_query_arg(
            'sk_nostr_repost',
            $event_id ? '1' : '0',
            get_edit_post_link( $post_id, 'url' )
        ) );
        exit;
    }

    public function repost_notice(): void {
        if ( ! isset( $_GET['sk_nostr_repost'] ) ) {
            return;
        }

        $ok = '1' === $_GET['sk_nostr_repost'];

        printf(
            '<div class="notice %s is-dismissible"><p>%s</p></div>',
            $ok ? 'notice-success' : 'notice-error',
            esc_html( $ok
                ? __( 'Nostr: Inserat gesendet.', 'sk-core' )
                : __( 'Nostr: Senden fehlgeschlagen. Grund steht im Fehlerprotokoll.', 'sk-core' ) )
        );
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
                    /*
                     * Bringt der Anbieter seinen Schluessel selbst mit, koennen
                     * wir hier nicht signieren — er liegt in seinem Browser.
                     * Statt unter unserem Namen zu veroeffentlichen, wird das
                     * Inserat vorgemerkt; beim naechsten Seitenaufruf fragt die
                     * Erweiterung nach seiner Unterschrift.
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
                    ProductDeleter::delete( $item['post_id'] );
                    break;
            }
        }

        self::$shutdown_queue = [];
    }
}
