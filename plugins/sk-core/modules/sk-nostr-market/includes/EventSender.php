<?php

namespace SK\Modules\NostrMarket;

use SK\Core\Nostr\Events;
use SK\Core\Nostr\Keys;
use swentel\nostr\Event\Event;

defined( 'ABSPATH' ) || exit;

/**
 * Nostr Event sender — signs and publishes events to relays.
 * Shared by ProductPublisher and ProductDeleter.
 */
class EventSender {

    /**
     * Create, sign, and send a Nostr event.
     *
     * @param int    $kind    Event kind (30402 listing, 5 deletion, 4 DM).
     * @param string $content Event content.
     * @param array  $tags    Array of tag arrays.
     * @return string|null     Event ID on success, null on failure.
     */
    public static function send( int $kind, string $content, array $tags ): ?string {
        $privkey = self::get_privkey();
        if ( ! $privkey ) {
            error_log( '[SK Nostr Market] No private key configured.' );
            return null;
        }

        $relays = self::get_relays();
        if ( empty( $relays ) ) {
            error_log( '[SK Nostr Market] No relays configured.' );
            return null;
        }

        if ( ! class_exists( '\swentel\nostr\Event\Event' ) ) {
            error_log( '[SK Nostr Market] Nostr PHP library not found.' );
            return null;
        }

        try {
            $event = Events::to_object( Events::sign( $kind, $content, $tags, $privkey ) );

            return self::publish_event( $event, $relays, $report, $privkey );

        } catch ( \Throwable $e ) {
            error_log( '[SK Nostr Market] Event error: ' . $e->getMessage() );
            return null;
        }
    }

    /**
     * Hand a signed event to the relays; the id if at least one accepted.
     *
     * @param string[]   $relays
     * @param array|null $report Filled with the per-relay verdicts.
     */
    private static function publish_event( Event $event, array $relays, ?array &$report = null, ?string $auth_privkey = null ): ?string {
        $result = \SK\Core\Nostr\Relays::publish( $event, $relays, $auth_privkey );
        $report = $result;

        return empty( $result['accepted'] ) ? null : $event->getId();
    }

    /**
     * Hand an already-signed event to the relays.
     *
     * For listings the vendor signed themselves in the browser: we never get
     * to see the key, only the finished event. It's distributed through the
     * same loop and the same success check as everything else.
     *
     * Id and signature are verified here, not left to the relay. The input
     * comes from a browser; without the check an arbitrary id could be
     * written into the product meta.
     *
     * @param array      $signed_event
     * @param array|null $report Filled with the per-relay verdicts.
     * @return string|null Event id, if a relay accepted it.
     */
    public static function send_signed( array $signed_event, ?array &$report = null ): ?string {
        $event = self::event_from_array( $signed_event );

        if ( null === $event ) {
            $report = [ 'accepted' => [], 'rejected' => [] ];

            return null;
        }

        $relays = self::get_relays();

        if ( empty( $relays ) ) {
            error_log( '[SK Nostr Market] No relays configured.' );
            $report = [ 'accepted' => [], 'rejected' => [] ];

            return null;
        }

        return self::publish_event( $event, $relays, $report );
    }

    /**
     * Build an Event from raw data as a browser sends it.
     *
     * The id has to match the content and the signature has to match the
     * pubkey. The library checks both: the id is the SHA-256 of the
     * canonical form, the signature is Schnorr over the id.
     *
     * @param array $raw
     */
    private static function event_from_array( array $raw ): ?Event {
        $raw['content'] = isset( $raw['content'] ) && is_string( $raw['content'] ) ? $raw['content'] : '';
        $raw['tags']    = isset( $raw['tags'] ) && is_array( $raw['tags'] ) ? $raw['tags'] : [];

        if ( ! Events::verify( $raw ) ) {
            error_log( '[SK Nostr Market] Signed event ' . ( is_string( $raw['id'] ?? null ) ? $raw['id'] : '?' ) . ' failed verification.' );
            return null;
        }

        try {
            return Events::to_object( $raw );
        } catch ( \Throwable $e ) {
            error_log( '[SK Nostr Market] Signed event unusable: ' . $e->getMessage() );
            return null;
        }
    }

    /**
     * Send a Kind 5 deletion event referencing another event.
     */
    public static function delete( string $event_id ): bool {
        $result = self::send( 5, '', [ [ 'e', $event_id ] ] );
        return $result !== null;
    }

    /**
     * Get the Nostr private key (reuses Auto Poster's key), always as hex.
     *
     * The key may be stored as an nsec — on Live it is. Sign::signEvent()
     * converts that itself, but Key::getPublicKey() does not: there an nsec
     * threw a ValueError. So it's normalized centrally here once, so every
     * caller gets hex.
     */
    public static function get_privkey(): ?string {
        $key = Keys::marketplace_privkey();

        return '' === $key ? null : $key;
    }

    /**
     * Get the Nostr public key derived from the private key.
     */
    public static function get_pubkey(): ?string {
        $key = Keys::marketplace_pubkey();

        return '' === $key ? null : $key;
    }

    /**
     * Relay URLs: the one list from the Nostr settings.
     */
    public static function get_relays(): array {
        if ( class_exists( 'SK\Modules\Auth\NostrIdentity' ) ) {
            return \SK\Modules\Auth\NostrIdentity::get_relays();
        }

        return [];
    }
}
