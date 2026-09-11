<?php

namespace SK\Modules\Auth;

use SK\Core\Nostr\Events;
use SK\Core\Nostr\Keys;
use SK\Core\Secret;

defined( 'ABSPATH' ) || exit;

/**
 * Nostr Identity Manager — generates, stores and uses Nostr keypairs per user.
 *
 * Every vendor can have a Nostr identity (secp256k1 keypair).
 * Private keys are stored encrypted in user meta through SK\Core\Secret.
 * Public keys are stored unencrypted for fast lookup.
 */
class NostrIdentity {

    /**
     * Register hooks for profile sync.
     */
    public static function init_hooks() {
        add_action( 'sk_store_profile_saved', [ __CLASS__, 'on_store_profile_saved' ], 30, 1 );

        // The same shop change for an outside key: built here, signed in the
        // browser, sent from here.
        add_action( 'wp_ajax_sk_nostr_profile_event', [ __CLASS__, 'ajax_profile_event' ] );
        add_action( 'wp_ajax_sk_nostr_publish_profile', [ __CLASS__, 'ajax_publish_profile' ] );
    }

    /** Hands the browser the unsigned profile event of the current user. */
    public static function ajax_profile_event(): void {
        check_ajax_referer( 'sk_nostr_profile', 'nonce' );

        $user_id = get_current_user_id();

        if ( ! $user_id || self::has_identity( $user_id ) ) {
            // With a key of ours there is nothing to sign outside.
            wp_send_json_error( [ 'message' => __( 'Kein fremder Schlüssel hinterlegt.', 'sk-core' ) ] );
        }

        $event = self::unsigned_profile_event( $user_id );

        if ( '' === $event['pubkey'] ) {
            wp_send_json_error( [ 'message' => __( 'Kein bestätigter Nostr-Schlüssel.', 'sk-core' ) ] );
        }

        wp_send_json_success( [ 'event' => $event ] );
    }

    /** Takes the signed profile event back and sends it to the relays. */
    public static function ajax_publish_profile(): void {
        check_ajax_referer( 'sk_nostr_profile', 'nonce' );

        $user_id = get_current_user_id();
        $event   = json_decode( (string) wp_unslash( $_POST['event'] ?? '' ), true );

        if ( ! $user_id || ! is_array( $event ) ) {
            wp_send_json_error( [ 'message' => __( 'Kein Profil-Event.', 'sk-core' ) ] );
        }

        if ( ! self::publish_signed( $user_id, $event ) ) {
            wp_send_json_error( [ 'message' => __( 'Profil konnte nicht veröffentlicht werden.', 'sk-core' ) ] );
        }

        wp_send_json_success( [ 'id' => (string) ( $event['id'] ?? '' ) ] );
    }

    /**
     * Re-publish Kind 0 when vendor updates store settings.
     */
    public static function on_store_profile_saved( int $store_id ) {
        if ( ! self::has_identity( $store_id ) ) {
            return;
        }
        // Defer to shutdown to not slow down the settings save.
        register_shutdown_function( [ __CLASS__, 'publish_profile_deferred' ], $store_id );
    }

    /**
     * Create a new Nostr identity for a user.
     * Generates keypair, stores encrypted privkey + pubkey, publishes Kind 0 profile.
     *
     * @return string Public key (hex).
     */
    public static function create_for_user( int $user_id ): string {
        $pair    = Keys::generate();
        $privkey = $pair['priv'];
        $pubkey  = $pair['pub'];

        // Store encrypted private key.
        update_user_meta( $user_id, 'sk_nostr_private_key', self::encrypt( $privkey ) );
        // Store public key (compatible with existing nostr_public_key field).
        update_user_meta( $user_id, 'nostr_public_key', $pubkey );
        // Mark source.
        update_user_meta( $user_id, 'sk_nostr_identity_source', 'generated' );

        // Bind the new key to this site right away: a signature only, the
        // relay work runs in cron (see VendorKey).
        if ( class_exists( 'SK\Core\Trust\KeyBinding' ) ) {
            \SK\Core\Trust\KeyBinding::maintain( $user_id );
        }

        // Publish the Kind 0 profile only after the response has gone out.
        // Even with RelayPublisher's 5 s per relay, four relays can hold the
        // click on "Create" for 20 s. Same approach as the profile update
        // further up in this file.
        register_shutdown_function( [ __CLASS__, 'publish_profile_deferred' ], $user_id );

        return $pubkey;
    }

    /**
     * Delete a generated Nostr identity — removes private key, public key,
     * identity source and the npub from the vendor's store settings.
     * User can later re-link via browser extension (NIP-07) through the
     * auth-connector dashboard.
     */
    public static function delete_for_user( int $user_id ): void {
        // While the key is still here: revoke its binding on the relays and
        // drop the proofs kept for it (see VendorKey).
        $privkey = self::get_private_key( $user_id );

        if ( $privkey && class_exists( 'SK\Core\Trust\KeyBinding' ) ) {
            \SK\Core\Trust\KeyBinding::on_identity_deleted( $user_id, $privkey );
        }

        delete_user_meta( $user_id, 'sk_nostr_private_key' );
        delete_user_meta( $user_id, 'nostr_public_key' );
        delete_user_meta( $user_id, 'sk_nostr_identity_source' );

        // Wipe the npub + visibility flag from the public store profile so the
        // old key stops showing up on the vendor's shop page.
        $settings = get_user_meta( $user_id, 'sk_profile_settings', true );
        if ( is_array( $settings ) ) {
            unset( $settings['nostr'], $settings['show_nostr'] );
            update_user_meta( $user_id, 'sk_profile_settings', $settings );
        }
    }

    /**
     * Check if user has a Nostr identity (with private key we control).
     */
    public static function has_identity( int $user_id ): bool {
        return ! empty( get_user_meta( $user_id, 'sk_nostr_private_key', true ) );
    }

    /**
     * Check if user has any Nostr public key (generated or imported via extension).
     */
    public static function has_pubkey( int $user_id ): bool {
        return ! empty( get_user_meta( $user_id, 'nostr_public_key', true ) );
    }

    /**
     * Get user's public key (hex).
     */
    public static function get_public_key( int $user_id ): string {
        return get_user_meta( $user_id, 'nostr_public_key', true ) ?: '';
    }

    /**
     * Get user's private key (decrypted hex). Returns null if no generated identity.
     *
     * A key still stored under the old scheme is rewritten here on first read,
     * so the move to authenticated encryption needs no migration run and no
     * user has to enter anything again.
     */
    public static function get_private_key( int $user_id ): ?string {
        $encrypted = (string) get_user_meta( $user_id, 'sk_nostr_private_key', true );
        if ( $encrypted === '' ) {
            return null;
        }

        $privkey = self::decrypt( $encrypted );

        if ( $privkey !== '' && Secret::needs_upgrade( $encrypted, Secret::NOSTR ) ) {
            $upgraded = self::encrypt( $privkey );

            // Never write an empty value over a key we can still read.
            if ( $upgraded !== '' ) {
                update_user_meta( $user_id, 'sk_nostr_private_key', $upgraded );
            }
        }

        return $privkey;
    }

    /**
     * Get user's npub (bech32 public key).
     */
    public static function get_npub( int $user_id ): string {
        return Keys::to_npub( self::get_public_key( $user_id ) );
    }

    /**
     * Get user's nsec (bech32 private key). Only for generated identities.
     */
    public static function get_nsec( int $user_id ): string {
        return Keys::to_nsec( (string) self::get_private_key( $user_id ) );
    }

    /**
     * Build, sign and publish an event to relays with the user's key.
     *
     * @param array|null $report Filled with the per-relay verdicts, so a
     *                           caller can record where the event actually
     *                           landed instead of only whether one relay
     *                           took it.
     * @return string|null Event ID if successful.
     */
    public static function publish( int $user_id, int $kind, string $content, array $tags = [], ?array &$report = null ): ?string {
        $privkey = self::get_private_key( $user_id );
        if ( ! $privkey ) {
            return null;
        }

        $event = Events::sign( $kind, $content, $tags, $privkey );

        // Only a relay's OK for this event id counts. The previous check,
        // "false !== $result", accepted every response object, including an
        // explicit rejection, and waited the client's default 60 s for a
        // silent relay.
        $result = \SK\Core\Nostr\Relays::publish( $event, self::get_relays(), $privkey );
        $report = $result;

        if ( empty( $result['accepted'] ) ) {
            return null;
        }

        if ( 0 === $kind ) {
            self::remember_profile_time( $user_id, (int) $event['created_at'] );
        }

        return (string) $event['id'];
    }

    /**
     * When the newest profile we know of was written.
     *
     * Both sides may change a profile now, so the newer one wins — the same
     * rule every Nostr client follows. Without this, our own publication would
     * come back through the relay sync and overwrite what was just saved.
     */
    public static function remember_profile_time( int $user_id, int $created_at ): void {
        if ( $created_at > (int) get_user_meta( $user_id, 'sk_nostr_profile_at', true ) ) {
            update_user_meta( $user_id, 'sk_nostr_profile_at', $created_at );
        }
    }

    /** Is this profile event newer than what we already have? */
    public static function profile_is_newer( int $user_id, int $created_at ): bool {
        return $created_at > (int) get_user_meta( $user_id, 'sk_nostr_profile_at', true );
    }

    /**
     * Has a Nostr profile ever been applied to this account?
     *
     * Before that, everything here was built in the shop, and someone linking
     * their Nostr account would lose a finished shop to whatever their profile
     * happens to hold. So the first profile only fills what is empty. From then
     * on both sides are in step and the newer change wins.
     */
    public static function profile_seen( int $user_id ): bool {
        return '' !== (string) get_user_meta( $user_id, 'sk_nostr_profile_at', true );
    }

    /**
     * The profile event for a key we do not hold, ready to be signed.
     *
     * The shop is the source, the key stays with its owner: we build the event,
     * the browser extension signs it, and publish_signed() sends it on.
     */
    public static function unsigned_profile_event( int $user_id ): array {
        return [
            'kind'       => 0,
            'pubkey'     => \SK\Core\Trust\VendorKey::bound( $user_id ),
            'created_at' => time(),
            'tags'       => [],
            'content'    => self::profile_json( $user_id ),
        ];
    }

    /**
     * Send a profile event that was signed elsewhere.
     *
     * Nothing is taken on trust: it has to be a profile, signed by exactly the
     * key this account is bound to, with a signature that checks out. Anything
     * else would let a caller publish under someone else's name through us.
     */
    public static function publish_signed( int $user_id, array $event ): bool {
        $bound = \SK\Core\Trust\VendorKey::bound( $user_id );

        if ( '' === $bound || 0 !== (int) ( $event['kind'] ?? -1 ) ) {
            return false;
        }

        if ( strcasecmp( (string) ( $event['pubkey'] ?? '' ), $bound ) !== 0 ) {
            return false;
        }

        if ( ! Events::verify( $event ) ) {
            return false;
        }

        $result = \SK\Core\Nostr\Relays::publish( $event, self::get_relays() );

        if ( empty( $result['accepted'] ) ) {
            return false;
        }

        self::remember_profile_time( $user_id, (int) ( $event['created_at'] ?? 0 ) );

        return true;
    }

    /**
     * The Lightning address to publish, or '' when there is none.
     *
     * Our own v/<id>@<host> is minted through the wallet the vendor connected,
     * so without one it is a dead address. It used to go out for every account,
     * and NostrRelaySync wrote it straight back into the shop settings, which
     * left a pile of vendors carrying an address that can never be paid.
     */
    private static function payable_address( int $user_id ): string {
        if ( \SK\Core\Wallet\Settings::has_nwc( $user_id ) || \SK\Core\Wallet\Settings::has_lndhub( $user_id ) ) {
            return 'v/' . $user_id . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
        }

        // Their own address, wherever it is served — but not one of ours.
        $own = \SK\Core\Wallet\Settings::get_lightning_address( $user_id );

        return \SK\Core\Wallet\Settings::is_local_address( $own ) ? '' : $own;
    }

    /**
     * The shop banner as an address, or ''.
     *
     * The banner is stored as the id of an uploaded image, and a profile wants
     * a URL. Publishing the field as it is put a bare number into the Nostr
     * profile where an address belongs.
     */
    private static function banner_url( $banner ): string {
        $id = absint( $banner );

        if ( $id > 0 ) {
            $url = wp_get_attachment_url( $id );

            return is_string( $url ) ? $url : '';
        }

        // Older rows hold the address itself, written by the relay sync.
        return is_string( $banner ) && preg_match( '#^https?://#i', $banner ) ? $banner : '';
    }

    /**
     * The shop as a Nostr profile (NIP-01 kind 0 content).
     *
     * One builder for both ways out: the key we hold signs on the server, an
     * outside key signs in the browser. Only fields Nostr knows are carried —
     * what a shop has beyond that stays here.
     */
    public static function profile_content( int $user_id ): array {
        $store_info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $user_id ) : [];
        $user       = get_userdata( $user_id );
        $domain     = wp_parse_url( home_url(), PHP_URL_HOST );

        $profile = [
            'name'    => $store_info['store_name'] ?? ( $user ? $user->display_name : '' ),
            'about'   => $store_info['store_description'] ?? '',
            'picture' => get_user_meta( $user_id, 'nostr_avatar', true ) ?: '',
            'banner'  => self::banner_url( $store_info['banner'] ?? '' ),
            'website' => function_exists( 'sk_get_store_url' ) ? sk_get_store_url( $user_id ) : '',
            'lud16'   => self::payable_address( $user_id ),
            'nip05'   => ( $user ? $user->user_nicename : $user_id ) . '@' . $domain,
        ];

        // Remove empty values.
        return array_filter( $profile );
    }

    /** The profile content as the JSON that goes into the event. */
    public static function profile_json( int $user_id ): string {
        return (string) wp_json_encode( self::profile_content( $user_id ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    }

    /**
     * Publish Kind 0 profile event for user, signed with the key we hold.
     */
    public static function publish_profile( int $user_id ): ?string {
        return self::publish( $user_id, 0, self::profile_json( $user_id ) );
    }

    /**
     * Publish the profile after the response has reached the browser.
     *
     * `register_shutdown_function()` alone isn't enough under PHP-FPM: output
     * only goes out once the script, shutdown handlers included, has
     * finished. Clicking "Create" would still have waited ~85 s.
     * `fastcgi_finish_request()` closes the response beforehand, and sending
     * to the relays continues afterwards in the same process.
     */
    public static function publish_profile_deferred( int $user_id ): void {
        if ( function_exists( 'fastcgi_finish_request' ) ) {
            fastcgi_finish_request();
        }

        self::publish_profile( $user_id );
    }

    /**
     * Get configured relays.
     */
    public static function get_relays(): array {
        return \SK\Core\Nostr\Relays::list();
    }

    // ── Encryption ──

    /**
     * Private keys live in the same store as every other secret of this plugin
     * (SK\Core\Secret): AES-256-GCM under a key namespace of their own. Keys
     * written by the old unauthenticated AES-256-CBC scheme are still read and
     * are rewritten on first use, see get_private_key().
     */
    private static function encrypt( string $data ): string {
        return Secret::encrypt( $data, Secret::NOSTR );
    }

    private static function decrypt( string $data ): string {
        $plaintext = Secret::decrypt( $data, Secret::NOSTR );

        // CBC without authentication can return garbage under a wrong key
        // instead of failing, so the result has to look like a key before it is
        // handed to a signer.
        return self::is_privkey( $plaintext ) ? $plaintext : '';
    }

    /** A Nostr private key is 32 bytes as lowercase hex. */
    private static function is_privkey( string $value ): bool {
        return (bool) preg_match( '/^[0-9a-f]{64}$/', $value );
    }
}
