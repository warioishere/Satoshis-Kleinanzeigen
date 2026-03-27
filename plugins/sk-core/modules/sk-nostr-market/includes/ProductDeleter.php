<?php

namespace SK\Modules\NostrMarket;

defined( 'ABSPATH' ) || exit;

/**
 * Sends deletion events (Kind 5) when products are unpublished/trashed.
 * For NIP-99 addressable events, includes both 'e' and 'a' tags.
 */
class ProductDeleter {

    /**
     * Delete a product's marketplace event from Nostr.
     *
     * @param int $post_id Product ID.
     * @return bool True if delete event was sent.
     */
    public static function delete( int $post_id ): bool {
        $event_id = get_post_meta( $post_id, ProductPublisher::META_KEY, true );
        if ( empty( $event_id ) ) {
            return false;
        }

        $vendor_id = (int) get_post_field( 'post_author', $post_id );
        $d_tag     = 'sk-' . $post_id;

        // Determine pubkey (vendor's own or marketplace).
        $pubkey = '';
        if ( $vendor_id && class_exists( 'SK\Modules\Auth\NostrIdentity' ) && \SK\Modules\Auth\NostrIdentity::has_identity( $vendor_id ) ) {
            $pubkey = \SK\Modules\Auth\NostrIdentity::get_public_key( $vendor_id );
        } else {
            $pubkey = EventSender::get_pubkey();
        }

        // For addressable events (Kind 30402), include both 'e' and 'a' tags.
        $tags = [
            [ 'e', $event_id ],
        ];
        if ( $pubkey ) {
            $tags[] = [ 'a', '30402:' . $pubkey . ':' . $d_tag ];
        }

        // Sign with vendor's key if available.
        if ( $vendor_id && class_exists( 'SK\Modules\Auth\NostrIdentity' ) && \SK\Modules\Auth\NostrIdentity::has_identity( $vendor_id ) ) {
            $result = \SK\Modules\Auth\NostrIdentity::publish( $vendor_id, 5, '', $tags );
        } else {
            $result = EventSender::send( 5, '', $tags );
        }

        if ( $result !== null ) {
            delete_post_meta( $post_id, ProductPublisher::META_KEY );
            delete_post_meta( $post_id, '_sk_nostr_market_self_signed' );
            delete_post_meta( $post_id, '_sk_nostr_market_pending_sign' );
            return true;
        }

        return false;
    }
}
