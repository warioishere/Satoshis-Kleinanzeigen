<?php

namespace SK\Modules\NostrMarket;

defined( 'ABSPATH' ) || exit;

/**
 * Sends NIP-15 deletion events (Kind 5) when products are unpublished/trashed.
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

        $result = EventSender::delete( $event_id );

        if ( $result ) {
            delete_post_meta( $post_id, ProductPublisher::META_KEY );
        }

        return $result;
    }
}
