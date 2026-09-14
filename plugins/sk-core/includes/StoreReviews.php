<?php

namespace SK\Core;

defined( 'ABSPATH' ) || exit;

/**
 * A vendor's reply to a review about their shop.
 *
 * Reviews about a vendor are posts of type sk_store_reviews carrying the
 * vendor in the store_id meta. The reply lives as meta on that same post —
 * no second post type, and it disappears with the review it belongs to.
 *
 * A vendor may answer and change their answer. They may not delete the
 * review, and there is deliberately no way to: a shop that can remove what
 * it does not like turns the rating into decoration.
 */
final class StoreReviews {

    const TYPE     = 'sk_store_reviews';
    const REPLY    = '_sk_store_review_reply';
    const REPLY_AT = '_sk_store_review_reply_at';

    /** Longest a reply may be. */
    const MAX_LENGTH = 1000;

    /**
     * Reviews written about this vendor, newest first.
     *
     * @return \WP_Post[]
     */
    public static function for_vendor( int $vendor_id ): array {
        if ( ! $vendor_id ) {
            return [];
        }

        return get_posts( [
            'post_type'      => self::TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => 'store_id', // phpcs:ignore WordPress.DB.SlowDBQuery
            'meta_value'     => $vendor_id, // phpcs:ignore WordPress.DB.SlowDBQuery
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ] );
    }

    /** Reviews this vendor has not answered yet. */
    public static function count_open( int $vendor_id ): int {
        $open = 0;

        foreach ( self::for_vendor( $vendor_id ) as $review ) {
            if ( '' === self::reply_of( (int) $review->ID ) ) {
                $open++;
            }
        }

        return $open;
    }

    public static function reply_of( int $review_id ): string {
        return (string) get_post_meta( $review_id, self::REPLY, true );
    }

    public static function replied_at( int $review_id ): int {
        return (int) get_post_meta( $review_id, self::REPLY_AT, true );
    }

    /**
     * Write or change the reply. An empty text removes it again.
     *
     * @return true|\WP_Error
     */
    public static function reply( int $review_id, int $vendor_id, string $text ) {
        $review = get_post( $review_id );

        if ( ! $review || self::TYPE !== $review->post_type ) {
            return new \WP_Error( 'sk_sr_missing', __( 'Diese Bewertung gibt es nicht.', 'sk-core' ) );
        }

        if ( ! $vendor_id || (int) get_post_meta( $review_id, 'store_id', true ) !== $vendor_id ) {
            return new \WP_Error( 'sk_sr_foreign', __( 'Diese Bewertung gilt nicht deinem Shop.', 'sk-core' ) );
        }

        $text = mb_substr( trim( wp_strip_all_tags( $text ) ), 0, self::MAX_LENGTH );

        if ( '' === $text ) {
            delete_post_meta( $review_id, self::REPLY );
            delete_post_meta( $review_id, self::REPLY_AT );

            return true;
        }

        update_post_meta( $review_id, self::REPLY, $text );
        update_post_meta( $review_id, self::REPLY_AT, time() );

        return true;
    }

    /** The rating a review carries, 1 to 5. */
    public static function rating( int $review_id ): int {
        return max( 0, min( 5, (int) get_post_meta( $review_id, 'rating', true ) ) );
    }
}
