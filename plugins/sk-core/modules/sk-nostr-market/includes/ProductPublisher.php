<?php

namespace SK\Modules\NostrMarket;

defined( 'ABSPATH' ) || exit;

/**
 * Publishes WooCommerce products as NIP-15 events (Kind 30018).
 */
class ProductPublisher {

    const META_KEY = '_sk_nostr_market_event_id';

    /**
     * Publish a product as a NIP-15 marketplace event.
     *
     * @param int $post_id WooCommerce product ID.
     * @return string|null Event ID on success.
     */
    public static function publish( int $post_id ): ?string {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'product' || $post->post_status !== 'publish' ) {
            return null;
        }

        $vendor_id = (int) $post->post_author;

        // Ensure the vendor has a stall.
        $stall_event_id = StallManager::ensure_stall( $vendor_id );
        if ( ! $stall_event_id ) {
            return null;
        }

        $product = function_exists( 'wc_get_product' ) ? wc_get_product( $post_id ) : null;
        if ( ! $product ) {
            return null;
        }

        // Build product data.
        $title       = $product->get_name();
        $description = wp_strip_all_tags( $product->get_description() ?: $product->get_short_description() );
        $price       = (int) $product->get_price(); // In Sats.
        $currency    = sk_get_option( 'sk_nostr_market_currency', 'sk_nostr_market', 'sat' );

        // Images: featured + gallery.
        $images = [];
        $thumb_id = get_post_thumbnail_id( $post_id );
        if ( $thumb_id ) {
            $url = wp_get_attachment_url( $thumb_id );
            if ( $url ) {
                $images[] = $url;
            }
        }
        $gallery = get_post_meta( $post_id, '_product_image_gallery', true );
        if ( ! empty( $gallery ) ) {
            foreach ( array_slice( array_filter( array_map( 'trim', explode( ',', $gallery ) ) ), 0, 5 ) as $gid ) {
                $url = wp_get_attachment_url( $gid );
                if ( $url ) {
                    $images[] = $url;
                }
            }
        }

        // Quantity.
        $stock = $product->get_stock_quantity();
        $qty   = $stock !== null ? $stock : null; // null = unlimited.

        // Specs.
        $specs = [];
        $store_info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $vendor_id ) : [];
        $location = $store_info['address']['city'] ?? '';
        if ( $location ) {
            $specs[] = [ 'location', $location ];
        }

        // Categories as tags.
        $categories = get_the_terms( $post_id, 'product_cat' );
        $t_tags     = [];
        if ( $categories && ! is_wp_error( $categories ) ) {
            foreach ( array_slice( $categories, 0, 5 ) as $cat ) {
                $t_tags[] = [ 't', strtolower( $cat->name ) ];
            }
        }

        $product_id = 'product-' . $post_id;
        $stall_id   = 'vendor-' . $vendor_id;

        $content = wp_json_encode( [
            'id'       => $product_id,
            'stall_id' => $stall_id,
            'name'     => $title,
            'description' => mb_substr( $description, 0, 1000 ),
            'images'   => $images,
            'currency' => $currency,
            'price'    => $price,
            'quantity' => $qty,
            'specs'    => ! empty( $specs ) ? $specs : null,
            'shipping' => [
                [ 'id' => 'pickup', 'cost' => 0 ],
                [ 'id' => 'shipping', 'cost' => 0 ],
            ],
        ] );

        // Build tags.
        $pubkey = EventSender::get_pubkey();
        $tags   = array_merge(
            [
                [ 'd', $product_id ],
                [ 'a', '30017:' . ( $pubkey ?: '' ) . ':' . $stall_id ],
            ],
            $t_tags
        );

        // Add permalink as reference.
        $permalink = get_permalink( $post_id );
        if ( $permalink ) {
            $tags[] = [ 'r', $permalink ];
        }

        $event_id = EventSender::send( 30018, $content, $tags );

        if ( $event_id ) {
            update_post_meta( $post_id, self::META_KEY, $event_id );
        }

        return $event_id;
    }

    /**
     * Check if a product already has a marketplace event.
     */
    public static function has_event( int $post_id ): bool {
        return ! empty( get_post_meta( $post_id, self::META_KEY, true ) );
    }
}
