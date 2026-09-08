<?php

namespace SK\Core\Trust;

defined( 'ABSPATH' ) || exit;

/**
 * Which Nostr key a vendor may be shown under.
 *
 * A key only counts once its holder has proven control of it towards this
 * site. Nothing is typed in for that: the proof comes from what happens
 * anyway — a Nostr login (the signed NIP-98 request, which is what puts
 * the key on the user in the first place), a key this site generated and
 * holds for the vendor, or a one-time signature the browser extension
 * gives silently in the vendor's dashboard. An npub merely typed into the
 * store settings is a claim, not a key, until that signature exists.
 *
 * The durable proof is a kind 30078 event (NIP-78 application data) signed
 * by the key: d = this site's host, r = the store URL. It is stored on the
 * user and published to the relays, so anyone can check the binding on a
 * relay without trusting this site.
 */
class VendorKey {

    /** JSON of the signed binding event. */
    const BINDING_META = 'sk_nostr_binding';

    /** The bound key alone, for uniqueness lookups. */
    const BOUND_META = 'sk_nostr_bound_pubkey';

    /** JSON of the last verified NIP-98 login event. */
    const LOGIN_PROOF_META = 'sk_nostr_login_proof';

    /** Relays that accepted the binding, and how often publishing was tried. */
    const RELAYS_META   = 'sk_nostr_binding_relays';
    const ATTEMPTS_META = 'sk_nostr_binding_attempts';

    const BINDING_KIND = 30078;

    const PUBLISH_HOOK = 'sk_trust_publish_binding';
    const AJAX_ACTION  = 'sk_trust_bind';
    const NONCE        = 'sk_trust_bind';

    /** How far a binding's created_at may sit from now when it is handed in. */
    const MAX_SKEW = 10 * MINUTE_IN_SECONDS;

    const MAX_PUBLISH_ATTEMPTS = 3;

    /** @var array<int, string> */
    private static array $bound_cache = [];

    public static function init(): void {
        add_action( self::PUBLISH_HOOK, [ __CLASS__, 'publish' ] );
        add_action( 'wp_ajax_' . self::AJAX_ACTION, [ __CLASS__, 'ajax_bind' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'maybe_enqueue' ], 20 );
    }

    /** The site this binding is for — the d tag of the event. */
    public static function site(): string {
        return strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
    }

    // ── Reading ──────────────────────────────────────────────────────────

    /**
     * The key the vendor claims, proven or not: the login/generated key on
     * the user, otherwise the npub typed into the store settings.
     */
    public static function candidate( int $vendor_id ): string {
        $hex = strtolower( (string) get_user_meta( $vendor_id, 'nostr_public_key', true ) );

        if ( self::is_hex_key( $hex ) ) {
            return $hex;
        }

        $settings = get_user_meta( $vendor_id, 'sk_profile_settings', true );
        $npub     = is_array( $settings ) ? trim( preg_replace( '/^nostr:/i', '', (string) ( $settings['nostr'] ?? '' ) ) ) : '';

        if ( 0 === strpos( $npub, 'npub1' ) && class_exists( '\swentel\nostr\Key\Key' ) ) {
            try {
                $hex = strtolower( (string) ( new \swentel\nostr\Key\Key() )->convertToHex( $npub ) );

                if ( self::is_hex_key( $hex ) ) {
                    return $hex;
                }
            } catch ( \Throwable $e ) {
                // Not a usable npub.
            }
        }

        return '';
    }

    /**
     * A private key this site holds for the vendor: a generated identity,
     * or the marketplace key for the platform account. Hex, or null.
     */
    public static function held_private_key( int $vendor_id ): ?string {
        if ( sk_module_active( 'sk_auth' ) && class_exists( 'SK\Modules\Auth\NostrIdentity' ) ) {
            $priv = \SK\Modules\Auth\NostrIdentity::get_private_key( $vendor_id );

            if ( is_string( $priv ) && self::is_hex_key( strtolower( $priv ) ) ) {
                return strtolower( $priv );
            }
        }

        if ( sk_module_active( 'sk_nostr_market' )
            && class_exists( 'SK\Modules\NostrMarket\Bridge\ChatBridge' )
            && \SK\Modules\NostrMarket\Bridge\ChatBridge::is_platform_account( $vendor_id )
            && class_exists( 'SK\Modules\NostrMarket\EventSender' ) ) {
            $priv = \SK\Modules\NostrMarket\EventSender::get_privkey();

            if ( is_string( $priv ) && self::is_hex_key( strtolower( $priv ) ) ) {
                return strtolower( $priv );
            }
        }

        return null;
    }

    /**
     * The key that counts for this vendor, lowercase hex, or '' when no
     * proven key exists. Never returns a merely claimed key.
     */
    public static function bound( int $vendor_id ): string {
        if ( $vendor_id <= 0 ) {
            return '';
        }

        if ( isset( self::$bound_cache[ $vendor_id ] ) ) {
            return self::$bound_cache[ $vendor_id ];
        }

        self::$bound_cache[ $vendor_id ] = self::resolve( $vendor_id );

        return self::$bound_cache[ $vendor_id ];
    }

    private static function resolve( int $vendor_id ): string {
        $held = self::held_private_key( $vendor_id );

        if ( null !== $held ) {
            $pub = self::pubkey_of( $held );

            if ( '' !== $pub ) {
                self::ensure_self_binding( $vendor_id, $held, $pub );

                return $pub;
            }
        }

        // The key on the user is written only by a verified Nostr login,
        // by generating an identity, or by an administrator — never from
        // an unsigned claim (NostrLogin refuses to change it otherwise).
        $login_key = strtolower( (string) get_user_meta( $vendor_id, 'nostr_public_key', true ) );

        if ( self::is_hex_key( $login_key ) ) {
            return $login_key;
        }

        // A typed npub counts once its holder has signed the binding.
        $candidate = self::candidate( $vendor_id );
        $binding   = self::binding( $vendor_id );

        if ( '' !== $candidate && $binding && $binding['pubkey'] === $candidate ) {
            return $candidate;
        }

        return '';
    }

    /**
     * How the bound key is proven, for the trust page and the admin view.
     *
     * @return array{type: string, pubkey: string, event: ?array, relays: string[]}|null
     */
    public static function proof( int $vendor_id ): ?array {
        $pub = self::bound( $vendor_id );

        if ( '' === $pub ) {
            return null;
        }

        $binding = self::binding( $vendor_id );
        $relays  = get_user_meta( $vendor_id, self::RELAYS_META, true );

        if ( $binding && $binding['pubkey'] === $pub ) {
            return [
                'type'   => null !== self::held_private_key( $vendor_id ) ? 'held' : 'binding',
                'pubkey' => $pub,
                'event'  => $binding,
                'relays' => is_array( $relays ) ? $relays : [],
            ];
        }

        $proof = self::login_proof( $vendor_id );

        if ( $proof && $proof['pubkey'] === $pub ) {
            return [
                'type'   => 'login',
                'pubkey' => $pub,
                'event'  => $proof,
                'relays' => [],
            ];
        }

        return [
            'type'   => 'held',
            'pubkey' => $pub,
            'event'  => null,
            'relays' => [],
        ];
    }

    /** The stored binding event as an array, if it is one for this site. */
    public static function binding( int $vendor_id ): ?array {
        $raw = get_user_meta( $vendor_id, self::BINDING_META, true );

        if ( ! is_string( $raw ) || '' === $raw ) {
            return null;
        }

        $event = json_decode( $raw, true );

        if ( ! is_array( $event ) || self::BINDING_KIND !== (int) ( $event['kind'] ?? 0 ) ) {
            return null;
        }

        if ( self::tag( $event, 'd' ) !== self::site() ) {
            return null;
        }

        $event['pubkey'] = strtolower( (string) ( $event['pubkey'] ?? '' ) );

        return self::is_hex_key( $event['pubkey'] ) ? $event : null;
    }

    /** The stored login event as an array. */
    public static function login_proof( int $vendor_id ): ?array {
        $raw = get_user_meta( $vendor_id, self::LOGIN_PROOF_META, true );

        if ( ! is_string( $raw ) || '' === $raw ) {
            return null;
        }

        $event = json_decode( $raw, true );

        if ( ! is_array( $event ) || 27235 !== (int) ( $event['kind'] ?? 0 ) ) {
            return null;
        }

        $event['pubkey'] = strtolower( (string) ( $event['pubkey'] ?? '' ) );

        return self::is_hex_key( $event['pubkey'] ) ? $event : null;
    }

    /**
     * Whether the vendor's claimed key still lacks a proof the browser
     * could supply. Decides if the binding script is loaded.
     */
    public static function needs_binding( int $vendor_id ): bool {
        if ( null !== self::held_private_key( $vendor_id ) ) {
            return false;
        }

        $candidate = self::candidate( $vendor_id );

        if ( '' === $candidate ) {
            return false;
        }

        return '' === self::bound( $vendor_id );
    }

    // ── The binding event ────────────────────────────────────────────────

    /**
     * The unsigned event the key holder signs. The browser fills in
     * created_at; the server side checks the tags, not the exact template.
     */
    public static function template( int $vendor_id, string $pubkey ): array {
        $store_url = self::store_url( $vendor_id );

        $tags = [
            [ 'd', self::site() ],
            [ 'r', $store_url ],
        ];

        $marketplace = self::marketplace_pubkey();

        if ( '' !== $marketplace ) {
            $tags[] = [ 'p', $marketplace ];
        }

        return [
            'kind'       => self::BINDING_KIND,
            'pubkey'     => $pubkey,
            'created_at' => time(),
            'tags'       => $tags,
            'content'    => sprintf( 'Dieser Schlüssel gehört zum Shop %s auf %s.', $store_url, self::site() ),
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
                return 'Kein vollständiges Event.';
            }
        }

        if ( self::BINDING_KIND !== (int) ( $event['kind'] ?? 0 ) ) {
            return 'Falscher Event-Typ.';
        }

        if ( ! isset( $event['created_at'] ) || ! is_int( $event['created_at'] ) || abs( time() - $event['created_at'] ) > self::MAX_SKEW ) {
            return 'Zeitstempel außerhalb des Fensters.';
        }

        $event['tags'] = isset( $event['tags'] ) && is_array( $event['tags'] ) ? $event['tags'] : [];

        if ( self::tag( $event, 'd' ) !== self::site() ) {
            return 'Das Event gilt nicht für diese Seite.';
        }

        if ( self::normalize_url( self::tag( $event, 'r' ) ) !== self::normalize_url( self::store_url( $vendor_id ) ) ) {
            return 'Das Event nennt einen anderen Shop.';
        }

        if ( strtolower( $event['pubkey'] ) !== strtolower( $expected_pubkey ) ) {
            return 'Der Schlüssel der Erweiterung ist nicht der Schlüssel des Shops.';
        }

        if ( ! class_exists( '\swentel\nostr\Event\Event' ) ) {
            return 'Signaturprüfung nicht verfügbar.';
        }

        try {
            $valid = ( new \swentel\nostr\Event\Event() )->verify( (object) $event );
        } catch ( \Throwable $e ) {
            $valid = false;
        }

        if ( ! $valid ) {
            return 'Signatur ungültig.';
        }

        $holder = self::holder_of( strtolower( $event['pubkey'] ) );

        if ( $holder && $holder !== $vendor_id ) {
            return 'Dieser Schlüssel ist bereits an ein anderes Konto gebunden.';
        }

        return '';
    }

    /** Store a verified binding and queue its publication. */
    public static function store( int $vendor_id, array $event ): void {
        $event['pubkey'] = strtolower( $event['pubkey'] );

        update_user_meta( $vendor_id, self::BINDING_META, self::encode( $event ) );
        update_user_meta( $vendor_id, self::BOUND_META, $event['pubkey'] );
        delete_user_meta( $vendor_id, self::RELAYS_META );
        delete_user_meta( $vendor_id, self::ATTEMPTS_META );

        unset( self::$bound_cache[ $vendor_id ] );

        self::queue_publish( $vendor_id, 10 );
    }

    /**
     * Keys this site holds are bound by this site: sign the binding once
     * and publish it, so the proof exists on the relays like any other.
     */
    private static function ensure_self_binding( int $vendor_id, string $privkey, string $pubkey ): void {
        $binding = self::binding( $vendor_id );

        if ( $binding && $binding['pubkey'] === $pubkey ) {
            return;
        }

        if ( ! class_exists( '\swentel\nostr\Sign\Sign' ) ) {
            return;
        }

        try {
            $template = self::template( $vendor_id, $pubkey );

            $event = new \swentel\nostr\Event\Event();
            $event->setKind( $template['kind'] );
            $event->setCreatedAt( $template['created_at'] );
            $event->setContent( $template['content'] );
            $event->setTags( $template['tags'] );

            ( new \swentel\nostr\Sign\Sign() )->signEvent( $event, $privkey );

            self::store( $vendor_id, $event->toArray() );
        } catch ( \Throwable $e ) {
            error_log( '[SK Trust] self-binding for user ' . $vendor_id . ' failed: ' . $e->getMessage() );
        }
    }

    /**
     * Remember the signed login request. It proves control of the key
     * towards this site and lets a login count as a binding until the
     * durable one exists.
     *
     * @param string $event_json The verified NIP-98 event, as received.
     */
    public static function record_login_proof( int $user_id, string $event_json ): void {
        $event = json_decode( $event_json, true );

        if ( ! is_array( $event ) || 27235 !== (int) ( $event['kind'] ?? 0 ) || ! self::is_hex_key( strtolower( (string) ( $event['pubkey'] ?? '' ) ) ) ) {
            return;
        }

        update_user_meta( $user_id, self::LOGIN_PROOF_META, self::encode( $event ) );

        unset( self::$bound_cache[ $user_id ] );
    }

    /**
     * An event as it is stored on the user. The meta API strips slashes
     * from what it is given, which turns a "ü" in the JSON into
     * "u00fc" and breaks the event's id; hence unescaped output, slashed
     * once for the API.
     */
    private static function encode( array $event ): string {
        return wp_slash( wp_json_encode( $event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
    }

    // ── Publishing ───────────────────────────────────────────────────────

    private static function queue_publish( int $vendor_id, int $delay ): void {
        if ( ! wp_next_scheduled( self::PUBLISH_HOOK, [ $vendor_id ] ) ) {
            wp_schedule_single_event( time() + $delay, self::PUBLISH_HOOK, [ $vendor_id ] );
        }
    }

    /** Cron: send the binding to the site's relays. */
    public static function publish( $vendor_id ): void {
        $vendor_id = (int) $vendor_id;
        $binding   = self::binding( $vendor_id );

        if ( ! $binding
            || ! sk_module_active( 'sk_auth' )
            || ! class_exists( 'SK\Modules\Auth\RelayPublisher' )
            || ! class_exists( 'SK\Modules\Auth\NostrIdentity' ) ) {
            return;
        }

        $attempts = (int) get_user_meta( $vendor_id, self::ATTEMPTS_META, true ) + 1;
        update_user_meta( $vendor_id, self::ATTEMPTS_META, $attempts );

        try {
            $event = ( new \swentel\nostr\Event\Event() )->populate( (object) $binding );

            $result = \SK\Modules\Auth\RelayPublisher::publish(
                $event,
                \SK\Modules\Auth\NostrIdentity::get_relays(),
                self::held_private_key( $vendor_id )
            );
        } catch ( \Throwable $e ) {
            error_log( '[SK Trust] publishing binding for user ' . $vendor_id . ' failed: ' . $e->getMessage() );
            $result = [ 'accepted' => [] ];
        }

        $accepted = array_values( (array) ( $result['accepted'] ?? [] ) );

        if ( ! empty( $accepted ) ) {
            update_user_meta( $vendor_id, self::RELAYS_META, $accepted );

            return;
        }

        if ( $attempts < self::MAX_PUBLISH_ATTEMPTS ) {
            self::queue_publish( $vendor_id, HOUR_IN_SECONDS );
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

        if ( ! self::needs_binding( $user_id ) ) {
            return;
        }

        $candidate = self::candidate( $user_id );

        $file = SK_CORE_DIR . '/assets/js/sk-key-binding.js';

        wp_enqueue_script(
            'sk-key-binding',
            plugins_url( 'assets/js/sk-key-binding.js', SK_CORE_FILE ),
            [],
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
            wp_send_json_error( [ 'message' => 'Nicht angemeldet.' ] );
        }

        check_ajax_referer( self::NONCE );

        if ( function_exists( 'sk_is_same_origin_request' ) && ! sk_is_same_origin_request() ) {
            wp_send_json_error( [ 'message' => 'Anfrage von fremder Herkunft.' ] );
        }

        $user_id = get_current_user_id();

        if ( function_exists( 'sk_rate_limit' ) && ! sk_rate_limit( 'trust-bind:' . $user_id, 10 ) ) {
            wp_send_json_error( [ 'message' => 'Zu viele Anfragen.' ] );
        }

        $event = json_decode( (string) wp_unslash( $_POST['event'] ?? '' ), true );

        if ( ! is_array( $event ) ) {
            wp_send_json_error( [ 'message' => 'Kein Event.' ] );
        }

        $candidate = self::candidate( $user_id );

        if ( '' === $candidate ) {
            wp_send_json_error( [ 'message' => 'Kein Schlüssel hinterlegt.' ] );
        }

        $error = self::verify( $event, $user_id, $candidate );

        if ( '' !== $error ) {
            wp_send_json_error( [ 'message' => $error ] );
        }

        self::store( $user_id, $event );

        wp_send_json_success( [ 'pubkey' => $candidate ] );
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** The user a key is bound to or logs in as, other than by claim. */
    private static function holder_of( string $pubkey ): int {
        global $wpdb;

        $id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key IN (%s, 'nostr_public_key') AND meta_value = %s ORDER BY user_id LIMIT 1",
            self::BOUND_META,
            $pubkey
        ) );

        return $id;
    }

    private static function pubkey_of( string $privkey ): string {
        if ( ! class_exists( '\swentel\nostr\Key\Key' ) ) {
            return '';
        }

        try {
            $pub = strtolower( (string) ( new \swentel\nostr\Key\Key() )->getPublicKey( $privkey ) );
        } catch ( \Throwable $e ) {
            return '';
        }

        return self::is_hex_key( $pub ) ? $pub : '';
    }

    private static function marketplace_pubkey(): string {
        if ( sk_module_active( 'sk_nostr_market' ) && class_exists( 'SK\Modules\NostrMarket\EventSender' ) ) {
            $pub = strtolower( (string) \SK\Modules\NostrMarket\EventSender::get_pubkey() );

            return self::is_hex_key( $pub ) ? $pub : '';
        }

        return '';
    }

    private static function store_url( int $vendor_id ): string {
        return function_exists( 'sk_get_store_url' ) ? (string) sk_get_store_url( $vendor_id ) : '';
    }

    private static function normalize_url( string $url ): string {
        return strtolower( untrailingslashit( preg_replace( '#^http://#i', 'https://', trim( $url ) ) ) );
    }

    private static function tag( array $event, string $name ): string {
        foreach ( (array) ( $event['tags'] ?? [] ) as $tag ) {
            if ( is_array( $tag ) && $name === ( $tag[0] ?? '' ) ) {
                return (string) ( $tag[1] ?? '' );
            }
        }

        return '';
    }

    private static function is_hex_key( string $value ): bool {
        return (bool) preg_match( '/^[0-9a-f]{64}$/', $value );
    }
}
