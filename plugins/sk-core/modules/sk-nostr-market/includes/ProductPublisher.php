<?php

namespace SK\Modules\NostrMarket;

defined( 'ABSPATH' ) || exit;

/**
 * Publishes WooCommerce products as NIP-99 Classified Listings (Kind 30402).
 *
 * NIP-99 spec: https://github.com/nostr-protocol/nips/blob/master/99.md
 */
class ProductPublisher {

    const META_KEY = '_sk_nostr_market_event_id';

    /**
     * Publish a product as a NIP-99 classified listing.
     *
     * @param int $post_id WooCommerce product ID.
     * @return string|null Event ID on success.
     */
    public static function publish( int $post_id ): ?string {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'product' || $post->post_status !== 'publish' ) {
            return null;
        }

        $product = function_exists( 'wc_get_product' ) ? wc_get_product( $post_id ) : null;
        if ( ! $product ) {
            return null;
        }

        $vendor_id  = (int) $post->post_author;
        $store_info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $vendor_id ) : [];
        $store_name = $store_info['store_name'] ?? '';

        // Title.
        $title = $product->get_name();

        // Description as Markdown content.
        $description = $product->get_description() ?: $product->get_short_description();
        $description = wp_strip_all_tags( $description );
        $description = mb_substr( $description, 0, 2000 );

        // Add vendor + permalink to content.
        $permalink = get_permalink( $post_id );
        $content   = $description;
        if ( $store_name ) {
            $content .= "\n\nVerkäufer: {$store_name}";
        }
        if ( $permalink ) {
            $content .= "\n\nInserat: {$permalink}";
        }

        // Price.
        $price    = (int) $product->get_price();
        $currency = sk_get_option( 'sk_nostr_market_currency', 'sk_nostr_market', 'sat' );

        // Location from vendor profile.
        $city    = $store_info['address']['city'] ?? '';
        $country = $store_info['address']['country'] ?? '';
        $location = implode( ', ', array_filter( [ $city, $country ] ) );

        // Summary.
        $summary = mb_substr( wp_strip_all_tags( $product->get_short_description() ?: $description ), 0, 200 );

        // Build tags.
        $tags = [
            [ 'd', 'sk-' . $post_id ],
            [ 'title', $title ],
            [ 'published_at', (string) strtotime( $post->post_date_gmt ) ],
            [ 'summary', $summary ],
            [ 'price', (string) $price, $currency ],
        ];

        if ( $location ) {
            $tags[] = [ 'location', $location ];
        }

        if ( $store_name ) {
            $tags[] = [ 'e_vendor', $store_name ];
        }

        // Status.
        $stock = $product->get_stock_quantity();
        if ( $stock !== null && $stock <= 0 ) {
            $tags[] = [ 'status', 'sold' ];
        } else {
            $tags[] = [ 'status', 'active' ];
        }

        // Images: featured + gallery (max 6).
        $thumb_id = get_post_thumbnail_id( $post_id );
        if ( $thumb_id ) {
            $url = wp_get_attachment_url( $thumb_id );
            if ( $url ) {
                $meta = wp_get_attachment_metadata( $thumb_id );
                $dims = '';
                if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
                    $dims = $meta['width'] . 'x' . $meta['height'];
                }
                $tags[] = $dims ? [ 'image', $url, $dims ] : [ 'image', $url ];
            }
        }

        $gallery = get_post_meta( $post_id, '_product_image_gallery', true );
        if ( ! empty( $gallery ) ) {
            $gallery_ids = array_slice( array_filter( array_map( 'trim', explode( ',', $gallery ) ) ), 0, 5 );
            foreach ( $gallery_ids as $gid ) {
                $url = wp_get_attachment_url( $gid );
                if ( $url ) {
                    $tags[] = [ 'image', $url ];
                }
            }
        }

        // Categories as t tags.
        $categories = get_the_terms( $post_id, 'product_cat' );
        if ( $categories && ! is_wp_error( $categories ) ) {
            foreach ( array_slice( $categories, 0, 5 ) as $cat ) {
                $tags[] = [ 't', strtolower( $cat->name ) ];
            }
        }

        // Permalink as reference.
        if ( $permalink ) {
            $tags[] = [ 'r', $permalink ];
        }

        $event_id = EventSender::send( 30402, $content, $tags );

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
