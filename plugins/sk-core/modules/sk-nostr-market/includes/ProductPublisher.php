<?php

namespace SK\Modules\NostrMarket;

defined( 'ABSPATH' ) || exit;

/**
 * Publishes WooCommerce products as NIP-99 Classified Listings (Kind 30402).
 *
 * NIP-99 spec: https://github.com/nostr-protocol/nips/blob/master/99.md
 *
 * Previously NIP-15 (Kind 30018). That's struck through in the NIP list and
 * marked "unrecommended: too complicated, try 99 instead".
 */
class ProductPublisher {

    const META_KEY = '_sk_nostr_market_event_id';

    /** Which relays accepted the listing, and why the others did not. */
    const RELAY_META = '_sk_nostr_market_relays';

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

        /*
         * Only with the vendor's own key, never with the marketplace's.
         *
         * The marketplace key is the SK account on Nostr — the same one the
         * Auto Poster uses to write to the SK feed. Publishing listings under
         * it meant: they appeared under our name, buyers replied to us
         * instead of the vendor, and the routing had to be guessed from the
         * text.
         *
         * Anyone without a key is offered the choice when checking the
         * option. Until then, the listing doesn't go out.
         */
        if ( ! class_exists( 'SK\Modules\Auth\NostrIdentity' ) || ! \SK\Modules\Auth\NostrIdentity::has_identity( $vendor_id ) ) {
            return null;
        }

        $report   = null;
        $event_id = \SK\Modules\Auth\NostrIdentity::publish( $vendor_id, 30402, $data['content'], $data['tags'], $report );

        self::store_relay_report( $post_id, $report );

        if ( $event_id ) {
            update_post_meta( $post_id, self::META_KEY, $event_id );
            delete_post_meta( $post_id, '_sk_nostr_market_pending_sign' );
        }

        return $event_id;
    }

    /**
     * Record which relays took the listing and which did not.
     *
     * One accepting relay was enough to report success, so a listing that
     * reached one of five relays looked the same as one that reached all of
     * them — and a listing missing from the relay a client happens to read
     * is invisible without anything looking wrong here.
     *
     * @param array|null $report As returned by RelayPublisher::publish().
     */
    public static function store_relay_report( int $post_id, ?array $report ): void {
        if ( ! is_array( $report ) ) {
            return;
        }

        update_post_meta( $post_id, self::RELAY_META, [
            'time'     => time(),
            'accepted' => array_values( (array) ( $report['accepted'] ?? [] ) ),
            'rejected' => (array) ( $report['rejected'] ?? [] ),
        ] );
    }

    /**
     * The stored relay verdicts for a listing.
     *
     * @return array{time:int,accepted:string[],rejected:array<string,string>}|null
     */
    public static function relay_report( int $post_id ): ?array {
        $report = get_post_meta( $post_id, self::RELAY_META, true );

        return is_array( $report ) ? $report : null;
    }

    /**
     * Build the event data for a product.
     *
     * The single place where an event is built — publish() and the
     * self-signing path (NIP-07) both use this. There used to be two copies
     * that had already drifted apart: only one wrote the image dimensions,
     * so the same listing produced a different event depending on who
     * signed it.
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

        $title       = self::one_line( $product->get_name() );
        $description = self::clean_text( $product->get_description() ?: $product->get_short_description() );
        $description = mb_substr( $description, 0, 2000 );
        $permalink   = get_permalink( $post_id );

        // Location from vendor profile: address fields first, map search as fallback.
        $city     = $store_info['address']['city'] ?? '';
        $country  = $store_info['address']['country'] ?? '';
        $location = implode( ', ', array_filter( [ $city, $country ] ) );
        if ( empty( $location ) && ! empty( $store_info['find_address'] ) ) {
            $location = $store_info['find_address'];
        }

        // Per NIP-99, the summary is a short line, not a paragraph.
        $summary = mb_substr( self::one_line( $product->get_short_description() ?: $description ), 0, 200 );

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

        foreach ( self::geohash_tags( $post_id, $vendor_id ) as $geohash_tag ) {
            $tags[] = $geohash_tag;
        }

        // WooCommerce reports null when stock tracking is off — that's not a sellout.
        $stock  = $product->get_stock_quantity();
        $tags[] = [ 'status', ( $stock !== null && $stock <= 0 ) ? 'sold' : 'active' ];

        // Images: featured (with dimensions, as NIP-99 specifies) + gallery.
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
     * Prepare text for the event.
     *
     * wp_strip_all_tags() alone isn't enough: it removes markup but leaves
     * HTML entities in place. Events therefore literally contained
     * "Truck &amp; Logistics", because an event is plain text and nobody
     * resolves that anymore. Non-breaking spaces are collapsed to normal
     * ones for the same reason.
     *
     * Paragraphs are preserved; only spaces and tabs are collapsed.
     */
    private static function clean_text( string $text ): string {
        $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $text = str_replace( "\xC2\xA0", ' ', $text );
        $text = wp_strip_all_tags( $text );
        $text = preg_replace( '/[ \t]+/u', ' ', $text );
        $text = preg_replace( "/\r\n?/", "\n", $text );

        return trim( (string) $text );
    }

    /**
     * The same, but on one line — for title and summary.
     */
    private static function one_line( string $text ): string {
        $text = self::clean_text( $text );
        $text = preg_replace( '/\s*\n+\s*/u', ' ', $text );

        return trim( (string) $text );
    }

    /**
     * The price tag per NIP-99: [ 'price', amount, currency ].
     *
     * The configured currency only changes the label, not the amount —
     * anyone who picked "BTC" would thereby offer 25000 sats as 25000 BTC.
     * Sats and BTC can be converted without a rate, so it's converted here.
     * For everything else, the shop's currency applies: a wrong label is
     * worse than a mismatched one.
     *
     * @param \WC_Product $product
     * @return array
     */
    private static function price_tag( $product ): array {
        $betrag = (float) $product->get_price();

        $shop    = function_exists( 'get_woocommerce_currency' ) ? strtolower( get_woocommerce_currency() ) : 'sat';
        $gewollt = strtolower( (string) sk_get_option( 'sk_nostr_market_currency', 'sk_nostr_market', 'sat' ) );

        if ( 'sat' === $shop && 'btc' === $gewollt ) {
            return [ 'price', self::btc_string( $betrag / 100000000 ), self::currency_code( 'btc' ) ];
        }

        if ( 'btc' === $shop && 'sat' === $gewollt ) {
            return [ 'price', (string) (int) round( $betrag * 100000000 ), self::currency_code( 'sat' ) ];
        }

        if ( $shop === $gewollt ) {
            return [ 'price', 'sat' === $shop ? (string) (int) round( $betrag ) : self::btc_string( $betrag ), self::currency_code( $gewollt ) ];
        }

        // No conversion possible: label it however the shop calculates.
        return [ 'price', (string) $betrag, self::currency_code( $shop ) ];
    }

    /**
     * Currency label for the price tag.
     *
     * NIP-99 asks for an ISO 4217 code, and marketplace clients match on the
     * uppercase form: Shopstr reads "SATS", so a lowercase "sat" left the
     * listing without a price the client could use. Sats have no ISO code,
     * "SATS" is what the ecosystem settled on.
     */
    private static function currency_code( string $currency ): string {
        $currency = strtolower( $currency );

        if ( 'sat' === $currency || 'sats' === $currency ) {
            return 'SATS';
        }

        return strtoupper( $currency );
    }

    /**
     * Geohash tags for the coordinates of the listing.
     *
     * The location tag carries the place name and only matches a client
     * searching for that exact string. Clients that search an area filter on
     * the g tag instead, so the coordinates go out as a geohash — and every
     * shorter prefix with it, because a query for a coarse cell cannot know
     * the finer one below it.
     *
     * Six characters are roughly a kilometre. The coordinates come from the
     * geocoded place name, so they point at the town, not at a front door.
     *
     * @return array[] Tags, empty when there is no public location.
     */
    private static function geohash_tags( int $post_id, int $vendor_id ): array {
        $lat    = get_post_meta( $post_id, 'sk_geo_latitude', true );
        $lng    = get_post_meta( $post_id, 'sk_geo_longitude', true );
        $public = get_post_meta( $post_id, 'sk_geo_public', true );

        if ( '' === $lat || '' === $lng ) {
            $lat    = get_user_meta( $vendor_id, 'sk_geo_latitude', true );
            $lng    = get_user_meta( $vendor_id, 'sk_geo_longitude', true );
            $public = get_user_meta( $vendor_id, 'sk_geo_public', true );
        }

        if ( '' === $lat || '' === $lng || empty( $public ) ) {
            return [];
        }

        // 0/0 is the placeholder a failed geocode leaves behind, not a spot
        // in the Atlantic. Sending it would put the listing on null island.
        if ( 0.0 === (float) $lat && 0.0 === (float) $lng ) {
            return [];
        }

        $geohash = self::geohash( (float) $lat, (float) $lng, 6 );
        $tags    = [];

        for ( $len = strlen( $geohash ); $len > 0; $len-- ) {
            $tags[] = [ 'g', substr( $geohash, 0, $len ) ];
        }

        return $tags;
    }

    /**
     * Encode coordinates as a geohash of the given length.
     *
     * Interleaved binary search: each bit halves the longitude range, then
     * the latitude range, and every five bits become one base32 character.
     *
     * A value sitting exactly on a division goes into the upper half, which
     * is what the common implementations do — geohash(0, 0) is "s000…", not
     * "7zzz…". It only matters on the boundaries, but a client computing the
     * cell for a search area has to land on the same side.
     */
    private static function geohash( float $lat, float $lng, int $precision ): string {
        $base32    = '0123456789bcdefghjkmnpqrstuvwxyz';
        $lat_range = [ -90.0, 90.0 ];
        $lng_range = [ -180.0, 180.0 ];

        $hash = '';
        $bit  = 0;
        $char = 0;
        $even = true;

        while ( strlen( $hash ) < $precision ) {
            if ( $even ) {
                $mid = ( $lng_range[0] + $lng_range[1] ) / 2;

                if ( $lng >= $mid ) {
                    $char |= 16 >> $bit;
                    $lng_range[0] = $mid;
                } else {
                    $lng_range[1] = $mid;
                }
            } else {
                $mid = ( $lat_range[0] + $lat_range[1] ) / 2;

                if ( $lat >= $mid ) {
                    $char |= 16 >> $bit;
                    $lat_range[0] = $mid;
                } else {
                    $lat_range[1] = $mid;
                }
            }

            $even = ! $even;

            if ( $bit < 4 ) {
                $bit++;
            } else {
                $hash .= $base32[ $char ];
                $bit   = 0;
                $char  = 0;
            }
        }

        return $hash;
    }

    /**
     * BTC without exponential notation and without trailing zeros.
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
