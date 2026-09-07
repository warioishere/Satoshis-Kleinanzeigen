<?php

namespace SK\Modules\NostrMarket;

defined( 'ABSPATH' ) || exit;

/**
 * Publishes WooCommerce products as NIP-99 Classified Listings (Kind 30402).
 *
 * NIP-99 spec: https://github.com/nostr-protocol/nips/blob/master/99.md
 *
 * Frueher NIP-15 (Kind 30018). Das ist in der NIP-Liste durchgestrichen und
 * als "unrecommended: too complicated, try 99 instead" gekennzeichnet.
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
        $data = self::build_event_data( $post_id );

        if ( null === $data ) {
            return null;
        }

        $vendor_id = (int) get_post_field( 'post_author', $post_id );

        // Prefer vendor's own Nostr key; fall back to marketplace key.
        if ( class_exists( 'SK\Modules\Auth\NostrIdentity' ) && \SK\Modules\Auth\NostrIdentity::has_identity( $vendor_id ) ) {
            $event_id = \SK\Modules\Auth\NostrIdentity::publish( $vendor_id, 30402, $data['content'], $data['tags'] );
        } else {
            $event_id = EventSender::send( 30402, $data['content'], $data['tags'] );
        }

        if ( $event_id ) {
            update_post_meta( $post_id, self::META_KEY, $event_id );
            delete_post_meta( $post_id, '_sk_nostr_market_pending_sign' );
        }

        return $event_id;
    }

    /**
     * Build the event data for a product.
     *
     * Einzige Stelle, an der ein Ereignis entsteht — publish() und der
     * selbstsignierende Weg (NIP-07) nutzen beide diese. Vorher gab es zwei
     * Kopien, die bereits auseinandergelaufen waren: nur eine schrieb die
     * Bildmasse mit, dasselbe Inserat ergab also je nach Signierer ein
     * anderes Ereignis.
     *
     * @param int $post_id Product ID.
     * @return array|null  { content: string, tags: array } or null.
     */
    public static function build_event_data( int $post_id ): ?array {
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
        $store_info = is_array( $store_info ) ? $store_info : [];
        $store_name = $store_info['store_name'] ?? '';

        $title       = $product->get_name();
        $description = wp_strip_all_tags( $product->get_description() ?: $product->get_short_description() );
        $description = mb_substr( $description, 0, 2000 );
        $permalink   = get_permalink( $post_id );

        // Location from vendor profile: address fields first, map search as fallback.
        $city     = $store_info['address']['city'] ?? '';
        $country  = $store_info['address']['country'] ?? '';
        $location = implode( ', ', array_filter( [ $city, $country ] ) );
        if ( empty( $location ) && ! empty( $store_info['find_address'] ) ) {
            $location = $store_info['find_address'];
        }

        $summary = mb_substr( wp_strip_all_tags( $product->get_short_description() ?: $description ), 0, 200 );

        $content = $description;
        if ( $store_name ) {
            $content .= "\n\nAnbieter: {$store_name}";
        }
        if ( $permalink ) {
            $content .= "\n\nInserat: {$permalink}";
        }

        $tags = [
            [ 'd', 'sk-' . $post_id ],
            [ 'title', $title ],
            [ 'published_at', (string) strtotime( $post->post_date_gmt ) ],
            [ 'summary', $summary ],
            self::price_tag( $product ),
        ];

        if ( $location ) {
            $tags[] = [ 'location', $location ];
        }

        // Ohne Bestandsfuehrung meldet WooCommerce null — das ist kein Ausverkauf.
        $stock  = $product->get_stock_quantity();
        $tags[] = [ 'status', ( $stock !== null && $stock <= 0 ) ? 'sold' : 'active' ];

        // Images: featured (with dimensions, wie NIP-99 sie vorsieht) + gallery.
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
                $tags[] = [ 't', strtolower( html_entity_decode( $cat->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ];
            }
        }

        if ( $permalink ) {
            $tags[] = [ 'r', $permalink ];
        }

        return [
            'content' => $content,
            'tags'    => $tags,
        ];
    }

    /**
     * Die Preis-Markierung nach NIP-99: [ 'price', Betrag, Waehrung ].
     *
     * Die eingestellte Waehrung aendert nur das Etikett, nicht den Betrag —
     * wer "BTC" waehlte, bot damit 25000 Sats als 25000 BTC an. Zwischen Sats
     * und BTC laesst sich ohne Kurs rechnen, deshalb wird hier umgerechnet.
     * Fuer alles andere gilt die Waehrung des Shops: ein falsches Etikett ist
     * schlimmer als ein unpassendes.
     *
     * @param \WC_Product $product
     * @return array
     */
    private static function price_tag( $product ): array {
        $betrag = (float) $product->get_price();

        $shop    = function_exists( 'get_woocommerce_currency' ) ? strtolower( get_woocommerce_currency() ) : 'sat';
        $gewollt = strtolower( (string) sk_get_option( 'sk_nostr_market_currency', 'sk_nostr_market', 'sat' ) );

        if ( 'sat' === $shop && 'btc' === $gewollt ) {
            return [ 'price', self::btc_string( $betrag / 100000000 ), 'btc' ];
        }

        if ( 'btc' === $shop && 'sat' === $gewollt ) {
            return [ 'price', (string) (int) round( $betrag * 100000000 ), 'sat' ];
        }

        if ( $shop === $gewollt ) {
            return [ 'price', 'sat' === $shop ? (string) (int) round( $betrag ) : self::btc_string( $betrag ), $gewollt ];
        }

        // Keine Umrechnung moeglich: so auszeichnen, wie der Shop rechnet.
        return [ 'price', (string) $betrag, $shop ];
    }

    /**
     * BTC ohne Exponentialschreibweise und ohne ueberfluessige Nullen.
     */
    private static function btc_string( float $betrag ): string {
        $s = number_format( $betrag, 8, '.', '' );
        $s = rtrim( rtrim( $s, '0' ), '.' );

        return '' === $s ? '0' : $s;
    }

    /**
     * Check if a product already has a marketplace event.
     */
    public static function has_event( int $post_id ): bool {
        return ! empty( get_post_meta( $post_id, self::META_KEY, true ) );
    }
}
