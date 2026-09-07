<?php

namespace SK\Modules\NostrMarket;

defined( 'ABSPATH' ) || exit;

/**
 * Sends deletion events (Kind 5) when products are unpublished/trashed.
 * For NIP-99 addressable events, includes both 'e' and 'a' tags.
 *
 * Only the key that published a listing can withdraw it. Vendors whose key
 * we hold get the deletion signed here; vendors who only gave us a public
 * key sign it in the browser, so their withdrawals are queued in user meta
 * (see queue_for_browser()) — the product itself may already be gone by
 * then.
 */
class ProductDeleter {

    /** User meta: withdrawals waiting for the vendor's signature. */
    const PENDING_META = '_sk_nostr_pending_deletes';

    /** Cap for that queue. */
    const PENDING_MAX = 30;

    /**
     * Delete a product's marketplace event from Nostr.
     *
     * Event id and vendor can be passed in when the caller captured them
     * before the post was removed: on before_delete_post the meta is gone by
     * the time the shutdown queue runs.
     *
     * @param int    $post_id   Product ID.
     * @param string $event_id  Event to withdraw; read from meta if empty.
     * @param int    $vendor_id Author; read from the post if 0.
     * @param string $title     Listing title for the signing prompt; read
     *                          from the post if empty.
     * @return bool True if the deletion was sent or queued for signing.
     */
    public static function delete( int $post_id, string $event_id = '', int $vendor_id = 0, string $title = '' ): bool {
        if ( '' === $event_id ) {
            $event_id = (string) get_post_meta( $post_id, ProductPublisher::META_KEY, true );
        }

        if ( '' === $event_id ) {
            return false;
        }

        if ( ! $vendor_id ) {
            $vendor_id = (int) get_post_field( 'post_author', $post_id );
        }

        if ( ! $vendor_id || ! class_exists( 'SK\Modules\Auth\NostrIdentity' ) ) {
            error_log( '[SK Nostr Market] Inserat ' . $post_id . ' laesst sich nicht zurueckziehen: kein Anbieter.' );
            return false;
        }

        $d_tag = 'sk-' . $post_id;

        if ( \SK\Modules\Auth\NostrIdentity::has_identity( $vendor_id ) ) {
            $pubkey = \SK\Modules\Auth\NostrIdentity::get_public_key( $vendor_id );
            $result = \SK\Modules\Auth\NostrIdentity::publish( $vendor_id, 5, '', self::tags( $event_id, $pubkey, $d_tag ) );

            if ( null === $result ) {
                return false;
            }

            self::clear_meta( $post_id );

            return true;
        }

        if ( Module::vendor_wants_self_sign( $vendor_id ) ) {
            self::queue_for_browser( $vendor_id, $post_id, $event_id, $title );
            self::clear_meta( $post_id );

            return true;
        }

        error_log( '[SK Nostr Market] Inserat ' . $post_id . ' laesst sich nicht zurueckziehen: kein Schluessel des Anbieters.' );

        return false;
    }

    /**
     * Tags of a Kind 5 for an addressable listing: the event and its address.
     */
    public static function tags( string $event_id, string $pubkey, string $d_tag ): array {
        $tags = [ [ 'e', $event_id ] ];

        if ( '' !== $pubkey ) {
            $tags[] = [ 'a', '30402:' . $pubkey . ':' . $d_tag ];
        }

        return $tags;
    }

    private static function clear_meta( int $post_id ): void {
        // No-ops when the post is already gone.
        delete_post_meta( $post_id, ProductPublisher::META_KEY );
        delete_post_meta( $post_id, '_sk_nostr_market_self_signed' );
        delete_post_meta( $post_id, '_sk_nostr_market_pending_sign' );
    }

    // ── Withdrawals a vendor signs in the browser ──────────────────────────

    /**
     * Queue a withdrawal for a vendor whose key lives in the extension.
     *
     * The title is stored with it so the prompt can still name the listing
     * after the product has been deleted.
     */
    private static function queue_for_browser( int $vendor_id, int $post_id, string $event_id, string $title = '' ): void {
        $offen = self::pending_for( $vendor_id );

        foreach ( $offen as $e ) {
            if ( ( $e['event_id'] ?? '' ) === $event_id ) {
                return;
            }
        }

        $offen[] = [
            'post_id'  => $post_id,
            'event_id' => $event_id,
            'title'    => '' !== $title ? $title : (string) get_the_title( $post_id ),
            'time'     => time(),
        ];

        if ( count( $offen ) > self::PENDING_MAX ) {
            $offen = array_slice( $offen, -self::PENDING_MAX );
        }

        update_user_meta( $vendor_id, self::PENDING_META, $offen );
    }

    /**
     * Withdrawals waiting for this vendor's signature.
     */
    public static function pending_for( int $vendor_id ): array {
        $offen = get_user_meta( $vendor_id, self::PENDING_META, true );

        return is_array( $offen ) ? array_values( $offen ) : [];
    }

    /**
     * The queued withdrawal for an event id, or null.
     */
    public static function pending_entry( int $vendor_id, string $event_id ): ?array {
        foreach ( self::pending_for( $vendor_id ) as $e ) {
            if ( ( $e['event_id'] ?? '' ) === $event_id ) {
                return $e;
            }
        }

        return null;
    }

    public static function forget_pending( int $vendor_id, string $event_id ): void {
        $rest = array_values( array_filter( self::pending_for( $vendor_id ), static function ( $e ) use ( $event_id ) {
            return ( $e['event_id'] ?? '' ) !== $event_id;
        } ) );

        if ( empty( $rest ) ) {
            delete_user_meta( $vendor_id, self::PENDING_META );
        } else {
            update_user_meta( $vendor_id, self::PENDING_META, $rest );
        }
    }
}
