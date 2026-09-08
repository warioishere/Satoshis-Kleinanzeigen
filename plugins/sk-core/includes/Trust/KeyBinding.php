<?php

namespace SK\Core\Trust;

use SK\Core\Nostr\Events;
use SK\Core\Nostr\Keys;
use SK\Core\Nostr\Relays;

defined( 'ABSPATH' ) || exit;

/**
 * The binding event — the writing side of a vendor's key.
 *
 * The durable proof that a key belongs to a shop is a kind 30078 event
 * (NIP-78 application data) signed by the key: d = this site's host, r =
 * the store URL, p = the marketplace key. It is stored on the user and
 * published to the relays, so anyone can check the binding on a relay
 * without trusting this site.
 *
 * Three ways it comes about: a key this site holds is bound by this site
 * (maintain, from identity creation and the daily cron); a typed npub is
 * bound once the vendor's extension signs the template in their dashboard
 * (sk-key-binding.js → ajax_bind); a login leaves its signed NIP-98 event
 * as proof (record_login_proof). Deleting a held key revokes its binding.
 * Relay work always runs in cron. Reading is VendorKey's job.
 */
final class KeyBinding {

    const PUBLISH_HOOK       = 'sk_trust_publish_binding';
    const PUBLISH_EVENT_HOOK = 'sk_trust_publish_event';
    const AJAX_ACTION        = 'sk_trust_bind';
    const NONCE              = 'sk_trust_bind';

    /** How far a binding's created_at may sit from now when it is handed in. */
    const MAX_SKEW = 10 * MINUTE_IN_SECONDS;

    const MAX_PUBLISH_ATTEMPTS = 3;

    /** A binding is the template plus a signature; anything bigger is not one. */
    const MAX_EVENT_BYTES    = 4096;
    const MAX_CONTENT_LENGTH = 500;

    public static function init(): void {
        add_action( self::PUBLISH_HOOK, [ __CLASS__, 'publish' ] );
        add_action( self::PUBLISH_EVENT_HOOK, [ __CLASS__, 'publish_event' ] );
        add_action( 'wp_ajax_' . self::AJAX_ACTION, [ __CLASS__, 'ajax_bind' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'maybe_enqueue' ], 20 );
    }

    // ── The event ────────────────────────────────────────────────────────

    /**
     * The unsigned event the key holder signs. The browser fills in
     * created_at; verify() checks the tags, not the exact template.
     */
    public static function template( int $vendor_id, string $pubkey ): array {
        $store_url = self::store_url( $vendor_id );

        $tags = [
            [ 'd', VendorKey::site() ],
            [ 'r', $store_url ],
        ];

        $marketplace = self::marketplace_pubkey();

        if ( '' !== $marketplace ) {
            $tags[] = [ 'p', $marketplace ];
        }

        return [
            'kind'       => VendorKey::BINDING_KIND,
            'pubkey'     => $pubkey,
            'created_at' => time(),
            'tags'       => $tags,
            'content'    => sprintf( 'Dieser Schlüssel gehört zum Shop %s auf %s.', $store_url, VendorKey::site() ),
        ];
    }

    /**
     * Check a signed binding handed in for a vendor.
     *
     * @return string '' when it holds, otherwise the reason it does not.
     */
    public static function verify( array $event, int $vendor_id, string $expected_pubkey ): string {
        foreach ( [ 'id', 'pubkey', 'sig', 'content' ] as $field ) {
            if ( ! isset( $event[ $field ] ) || ! is_string( $event[ $field ] ) ) {
                return __( 'Kein vollständiges Event.', 'sk-core' );
            }
        }

        if ( VendorKey::BINDING_KIND !== (int) ( $event['kind'] ?? 0 ) ) {
            return __( 'Falscher Event-Typ.', 'sk-core' );
        }

        if ( ! isset( $event['created_at'] ) || ! is_int( $event['created_at'] ) || abs( time() - $event['created_at'] ) > self::MAX_SKEW ) {
            return __( 'Zeitstempel außerhalb des Fensters.', 'sk-core' );
        }

        $event['tags'] = isset( $event['tags'] ) && is_array( $event['tags'] ) ? $event['tags'] : [];

        // The event is stored, published under this site's connection and
        // shown on the trust page, so it must stay the shape of the
        // template: the three known tags, nothing else, a short content.
        if ( count( $event['tags'] ) > 3 || strlen( $event['content'] ) > self::MAX_CONTENT_LENGTH ) {
            return __( 'Das Event ist größer als die Vorlage.', 'sk-core' );
        }

        $marketplace = self::marketplace_pubkey();

        foreach ( $event['tags'] as $tag ) {
            $name = is_array( $tag ) && 2 === count( $tag ) && is_string( $tag[1] ?? null ) ? (string) ( $tag[0] ?? '' ) : '';

            if ( ! in_array( $name, [ 'd', 'r', 'p' ], true ) || ( 'p' === $name && strtolower( $tag[1] ) !== $marketplace ) ) {
                return __( 'Das Event enthält Tags, die nicht zur Vorlage gehören.', 'sk-core' );
            }
        }

        if ( Events::tag( $event, 'd' ) !== VendorKey::site() ) {
            return __( 'Das Event gilt nicht für diese Seite.', 'sk-core' );
        }

        if ( self::normalize_url( Events::tag( $event, 'r' ) ) !== self::normalize_url( self::store_url( $vendor_id ) ) ) {
            return __( 'Das Event nennt einen anderen Shop.', 'sk-core' );
        }

        if ( strtolower( $event['pubkey'] ) !== strtolower( $expected_pubkey ) ) {
            return __( 'Der Schlüssel der Erweiterung ist nicht der Schlüssel des Shops.', 'sk-core' );
        }

        if ( ! Events::verify( $event, VendorKey::BINDING_KIND, [ $expected_pubkey ] ) ) {
            return __( 'Signatur ungültig.', 'sk-core' );
        }

        $holder = VendorKey::holder_of( $event['pubkey'] );

        if ( $holder && $holder !== $vendor_id ) {
            return __( 'Dieser Schlüssel ist bereits an ein anderes Konto gebunden.', 'sk-core' );
        }

        return '';
    }

    /** Store a verified binding and queue its publication. */
    public static function store( int $vendor_id, array $event ): void {
        $event['pubkey'] = strtolower( $event['pubkey'] );

        update_user_meta( $vendor_id, VendorKey::BINDING_META, Events::encode_for_meta( $event ) );
        update_user_meta( $vendor_id, VendorKey::BOUND_META, $event['pubkey'] );
        delete_user_meta( $vendor_id, VendorKey::RELAYS_META );
        delete_user_meta( $vendor_id, VendorKey::ATTEMPTS_META );

        VendorKey::forget( $vendor_id );

        self::queue_publish( $vendor_id, 10 );
    }

    /**
     * The write side of a vendor's key, run off the request path: when an
     * identity is created and once a day for every vendor with a key.
     * Signs the binding for a key this site holds if it is missing, and
     * gives a binding that never reached a relay a fresh set of attempts.
     */
    public static function maintain( int $vendor_id ): void {
        if ( $vendor_id <= 0 ) {
            return;
        }

        $held = VendorKey::held_private_key( $vendor_id );

        if ( null !== $held ) {
            $pub = Keys::pubkey_of( $held );

            if ( '' !== $pub ) {
                self::ensure_self_binding( $vendor_id, $held, $pub );
            }
        }

        VendorKey::forget( $vendor_id );

        $binding = VendorKey::binding( $vendor_id );

        if ( $binding
            && $binding['pubkey'] === VendorKey::bound( $vendor_id )
            && empty( get_user_meta( $vendor_id, VendorKey::RELAYS_META, true ) )
            && (int) get_user_meta( $vendor_id, VendorKey::ATTEMPTS_META, true ) >= self::MAX_PUBLISH_ATTEMPTS ) {
            delete_user_meta( $vendor_id, VendorKey::ATTEMPTS_META );
            self::queue_publish( $vendor_id, 10 );
        }
    }

    /**
     * Keys this site holds are bound by this site: sign the binding once
     * and publish it, so the proof exists on the relays like any other.
     */
    private static function ensure_self_binding( int $vendor_id, string $privkey, string $pubkey ): void {
        $binding = VendorKey::binding( $vendor_id );

        if ( $binding && $binding['pubkey'] === $pubkey ) {
            return;
        }

        try {
            $template = self::template( $vendor_id, $pubkey );

            self::store( $vendor_id, Events::sign( $template['kind'], $template['content'], $template['tags'], $privkey, $template['created_at'] ) );
        } catch ( \Throwable $e ) {
            error_log( '[SK Trust] self-binding for user ' . $vendor_id . ' failed: ' . $e->getMessage() );
        }
    }

    /**
     * A generated identity is being deleted: the binding on the relays
     * would otherwise keep saying the key belongs to this shop, with no
     * one left able to replace it. So, while the key is still here, sign
     * an empty event with the same d tag — it replaces the binding on
     * every relay — and queue it; then forget everything about the key.
     */
    public static function on_identity_deleted( int $user_id, string $privkey ): void {
        if ( '' !== Keys::pubkey_of( $privkey ) ) {
            try {
                $revocation = Events::sign( VendorKey::BINDING_KIND, '', [ [ 'd', VendorKey::site() ] ], $privkey );

                wp_schedule_single_event( time() + 10, self::PUBLISH_EVENT_HOOK, [ $revocation ] );
            } catch ( \Throwable $e ) {
                error_log( '[SK Trust] revoking binding for user ' . $user_id . ' failed: ' . $e->getMessage() );
            }
        }

        foreach ( [ VendorKey::BINDING_META, VendorKey::BOUND_META, VendorKey::RELAYS_META, VendorKey::ATTEMPTS_META, VendorKey::LOGIN_PROOF_META ] as $meta ) {
            delete_user_meta( $user_id, $meta );
        }

        wp_clear_scheduled_hook( self::PUBLISH_HOOK, [ $user_id ] );

        VendorKey::forget( $user_id );
    }

    /**
     * Remember the signed login request. It proves control of the key
     * towards this site and lets a login count as a binding until the
     * durable one exists.
     *
     * @param string $event_json The verified NIP-98 event, as received.
     */
    public static function record_login_proof( int $user_id, string $event_json ): void {
        $event = Events::decode( $event_json );

        if ( ! $event || VendorKey::LOGIN_KIND !== (int) ( $event['kind'] ?? 0 ) || ! Keys::is_hex( strtolower( (string) ( $event['pubkey'] ?? '' ) ) ) ) {
            return;
        }

        update_user_meta( $user_id, VendorKey::LOGIN_PROOF_META, Events::encode_for_meta( $event ) );

        VendorKey::forget( $user_id );
    }

    // ── Publishing (cron) ────────────────────────────────────────────────

    private static function queue_publish( int $vendor_id, int $delay ): void {
        if ( ! wp_next_scheduled( self::PUBLISH_HOOK, [ $vendor_id ] ) ) {
            wp_schedule_single_event( time() + $delay, self::PUBLISH_HOOK, [ $vendor_id ] );
        }
    }

    /** Cron: send the binding to the site's relays. */
    public static function publish( $vendor_id ): void {
        $vendor_id = (int) $vendor_id;
        $binding   = VendorKey::binding( $vendor_id );

        if ( ! $binding || ! sk_module_active( 'sk_auth' ) ) {
            return;
        }

        $attempts = (int) get_user_meta( $vendor_id, VendorKey::ATTEMPTS_META, true ) + 1;
        update_user_meta( $vendor_id, VendorKey::ATTEMPTS_META, $attempts );

        try {
            $result = Relays::publish( $binding, null, VendorKey::held_private_key( $vendor_id ) );
        } catch ( \Throwable $e ) {
            error_log( '[SK Trust] publishing binding for user ' . $vendor_id . ' failed: ' . $e->getMessage() );
            $result = [ 'accepted' => [] ];
        }

        $accepted = array_values( (array) ( $result['accepted'] ?? [] ) );

        if ( ! empty( $accepted ) ) {
            update_user_meta( $vendor_id, VendorKey::RELAYS_META, $accepted );

            return;
        }

        if ( $attempts < self::MAX_PUBLISH_ATTEMPTS ) {
            self::queue_publish( $vendor_id, HOUR_IN_SECONDS );
        }
    }

    /** Cron: send one signed event, as scheduled by on_identity_deleted(). */
    public static function publish_event( $event ): void {
        if ( ! is_array( $event ) || ! sk_module_active( 'sk_auth' ) ) {
            return;
        }

        try {
            $result = Relays::publish( $event );

            if ( empty( $result['accepted'] ) ) {
                error_log( '[SK Trust] revocation ' . substr( (string) ( $event['id'] ?? '' ), 0, 12 ) . ' accepted by no relay: ' . wp_json_encode( $result['rejected'] ?? [] ) );
            }
        } catch ( \Throwable $e ) {
            error_log( '[SK Trust] publishing revocation failed: ' . $e->getMessage() );
        }
    }

    // ── The browser side ─────────────────────────────────────────────────

    /**
     * In the vendor's own dashboard, and only while their claimed key has
     * no proof: load the script that asks the extension for the key and,
     * when it is the same one, for a single signature.
     */
    public static function maybe_enqueue(): void {
        if ( ! is_user_logged_in() || ! function_exists( 'sk_is_seller_dashboard' ) || ! sk_is_seller_dashboard() ) {
            return;
        }

        $user_id = get_current_user_id();

        if ( ! VendorKey::needs_binding( $user_id ) ) {
            return;
        }

        $candidate = VendorKey::candidate( $user_id );
        $file      = SK_CORE_DIR . '/assets/js/sk-key-binding.js';

        wp_enqueue_script(
            'sk-key-binding',
            plugins_url( 'assets/js/sk-key-binding.js', SK_CORE_FILE ),
            [ \SK\Core\Nostr\Assets::HANDLE ],
            (string) ( file_exists( $file ) ? filemtime( $file ) : '1' ),
            true
        );

        wp_localize_script( 'sk-key-binding', 'skTrustBind', [
            'ajaxurl'   => admin_url( 'admin-ajax.php' ),
            'action'    => self::AJAX_ACTION,
            'nonce'     => wp_create_nonce( self::NONCE ),
            'candidate' => $candidate,
            'template'  => self::template( $user_id, $candidate ),
        ] );
    }

    public static function ajax_bind(): void {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Nicht angemeldet.', 'sk-core' ) ] );
        }

        check_ajax_referer( self::NONCE );

        if ( function_exists( 'sk_is_same_origin_request' ) && ! sk_is_same_origin_request() ) {
            wp_send_json_error( [ 'message' => __( 'Anfrage von fremder Herkunft.', 'sk-core' ) ] );
        }

        $user_id = get_current_user_id();

        if ( function_exists( 'sk_rate_limit' ) && ! sk_rate_limit( 'trust-bind:' . $user_id, 10 ) ) {
            wp_send_json_error( [ 'message' => __( 'Zu viele Anfragen.', 'sk-core' ) ] );
        }

        $raw = (string) wp_unslash( $_POST['event'] ?? '' );

        if ( strlen( $raw ) > self::MAX_EVENT_BYTES ) {
            wp_send_json_error( [ 'message' => __( 'Das Event ist größer als die Vorlage.', 'sk-core' ) ] );
        }

        $event = json_decode( $raw, true );

        if ( ! is_array( $event ) ) {
            wp_send_json_error( [ 'message' => __( 'Kein Event.', 'sk-core' ) ] );
        }

        $candidate = VendorKey::candidate( $user_id );

        if ( '' === $candidate ) {
            wp_send_json_error( [ 'message' => __( 'Kein Schlüssel hinterlegt.', 'sk-core' ) ] );
        }

        $error = self::verify( $event, $user_id, $candidate );

        if ( '' !== $error ) {
            wp_send_json_error( [ 'message' => $error ] );
        }

        self::store( $user_id, $event );

        wp_send_json_success( [ 'pubkey' => $candidate ] );
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * The marketplace key names itself in bindings only while the Nostr
     * market module, whose key it is, runs.
     */
    private static function marketplace_pubkey(): string {
        return sk_module_active( 'sk_nostr_market' ) ? Keys::marketplace_pubkey() : '';
    }

    private static function store_url( int $vendor_id ): string {
        return function_exists( 'sk_get_store_url' ) ? (string) sk_get_store_url( $vendor_id ) : '';
    }

    private static function normalize_url( string $url ): string {
        return strtolower( untrailingslashit( preg_replace( '#^http://#i', 'https://', trim( $url ) ) ) );
    }
}
