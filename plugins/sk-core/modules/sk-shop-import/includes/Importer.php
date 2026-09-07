<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Creates listings from CSV rows.
 */
final class Importer {

    /** Marks an imported listing. */
    const META_IMPORTED = '_sk_imported';

    /** Key for looking items back up: vendor plus SKU. */
    const META_KEY = '_sk_import_key';

    const META_SOURCE   = '_sk_import_source';
    const META_RUN      = '_sk_import_run';
    const META_FIAT     = '_sk_fiat_price';
    const META_CURRENCY = '_sk_fiat_currency';

    /** Marks a dealer listing, for later separation from private listings. */
    const META_DEALER = '_sk_dealer_listing';

    /** A product's variants, for display only. */
    const META_VARIANTS = '_sk_variants';

    /** Price is a "from" price because it's derived from variants. */
    const META_FROM = '_sk_price_from';

    /**
     * Images per product. Nobody looks at more than a handful anyway, and
     * every image is a file on disk.
     */
    const IMAGES_PER_PRODUCT = 5;

    /**
     * Images per run. A second cap, so that a 200-item catalog doesn't
     * load a thousand files.
     */
    const DEFAULT_IMAGE_CAP = 60;

    /**
     * @param array $items Listings prepared by Catalog::build()
     * @param array $args  vendor_id, currency, default_cat, category_map, image_cap, status
     *
     * @return array{created:int,updated:int,skipped:int,images:int,errors:array}
     */
    public static function run( array $items, array $args ): array {
        $vendor_id = (int) ( $args['vendor_id'] ?? 0 );
        $currency  = strtoupper( (string) ( $args['currency'] ?? 'EUR' ) );
        $image_cap = (int) ( $args['image_cap'] ?? self::DEFAULT_IMAGE_CAP );
        $status    = in_array( $args['status'] ?? 'publish', [ 'publish', 'draft' ], true ) ? $args['status'] : 'publish';
        $cat_map   = (array) ( $args['category_map'] ?? [] );
        $default   = (int) ( $args['default_cat'] ?? 0 );
        $source    = (string) ( $args['source'] ?? '' );
        $run_id    = (string) ( $args['run_id'] ?? uniqid( 'imp', false ) );

        $result = [ 'created' => 0, 'updated' => 0, 'skipped' => 0, 'images' => 0, 'errors' => [] ];

        if ( $vendor_id <= 0 ) {
            $result['errors'][] = __( 'Kein Verkäufer angegeben.', 'sk-core' );
            return $result;
        }

        $geo = self::vendor_location( $vendor_id );

        // No items with variants without a matching pack — otherwise the
        // import would be a detour around the editor's pack limit.
        if ( ! Variants::is_allowed( $vendor_id ) ) {
            $before = count( $items );
            $items  = array_values( array_filter( $items, static fn( $item ) => empty( $item['variants'] ) ) );
            $result['skipped'] += $before - count( $items );
        }

        // Pause the auto poster, otherwise the whole catalog would go out
        // as individual posts to Telegram, Nostr, and the community feed.
        Silence::start();

        foreach ( $items as $index => $item ) {
            $name = (string) ( $item['name'] ?? '' );
            if ( $name === '' ) {
                $result['skipped']++;
                continue;
            }

            // Without a SKU, a second import would create a duplicate; the
            // title is then the most stable fallback. Catalog assigns the
            // same key so the form's selection matches it.
            $sku = (string) ( $item['sku'] ?? '' );
            $key = $vendor_id . ':' . ( (string) ( $item['key'] ?? '' ) !== '' ? $item['key'] : ( $sku !== '' ? $sku : md5( $name ) ) );

            $existing = self::find_by_key( $key );

            // Whatever is private in the shop doesn't become public here.
            $row_status = ! empty( $item['draft'] ) ? 'draft' : $status;

            /*
             * Create via the WooCommerce API, not via wp_insert_post. A
             * plain post of type "product" gets neither the product_type
             * term nor a row in wc_product_meta_lookup — it would then
             * exist in the database but show up in no product list.
             */
            try {
                /*
                 * wp_slash before setting: WooCommerce passes the values on
                 * to wp_insert_post, which strips one level of backslashes.
                 * Without the slash it would eat every backslash in the
                 * text — "\n" would become a bare "n" in the middle of a
                 * paragraph.
                 */
                $product = new \WC_Product_Simple( $existing ?: 0 );
                $product->set_name( wp_slash( $name ) );
                $product->set_description( wp_slash( (string) ( $item['description'] ?? '' ) ) );
                $product->set_short_description( wp_slash( (string) ( $item['short'] ?? '' ) ) );
                $product->set_status( $row_status );
                $product->set_catalog_visibility( 'visible' );
                $post_id = (int) $product->save();
            } catch ( \Throwable $e ) {
                $post_id = 0;
                $error   = $e->getMessage();
            }

            if ( ! $post_id ) {
                $result['errors'][] = sprintf(
                    /* translators: 1: row number, 2: error */
                    __( 'Zeile %1$d: %2$s', 'sk-core' ),
                    (int) $index + 2,
                    $error ?? __( 'Produkt liess sich nicht anlegen', 'sk-core' )
                );
                continue;
            }

            // The product API doesn't set the vendor.
            if ( (int) get_post_field( 'post_author', $post_id ) !== $vendor_id ) {
                wp_update_post( [ 'ID' => $post_id, 'post_author' => $vendor_id ] );
            }
            $existing ? $result['updated']++ : $result['created']++;

            self::apply_price( $post_id, (string) ( $item['price'] ?? '' ), $currency, $result );
            self::apply_categories( $post_id, (string) ( $item['categories'] ?? '' ), $cat_map, $default );
            self::apply_variants( $post_id, (array) ( $item['variants'] ?? [] ), $currency, ! empty( $item['from'] ) );

            if ( $result['images'] < $image_cap ) {
                $budget            = min( self::IMAGES_PER_PRODUCT, $image_cap - $result['images'] );
                $result['images'] += self::apply_images( $post_id, (string) ( $item['images'] ?? '' ), $budget );
            }

            foreach ( $geo as $meta_key => $value ) {
                if ( $value !== '' ) {
                    update_post_meta( $post_id, $meta_key, $value );
                }
            }

            update_post_meta( $post_id, self::META_IMPORTED, 1 );
            update_post_meta( $post_id, self::META_DEALER, 1 );
            update_post_meta( $post_id, self::META_KEY, $key );
            update_post_meta( $post_id, self::META_RUN, $run_id );
            if ( $source !== '' ) {
                update_post_meta( $post_id, self::META_SOURCE, $source );
            }
        }

        Silence::stop();

        update_user_meta( $vendor_id, Dealer::META_LAST_RUN, time() );

        return $result;
    }

    /**
     * Store variants.
     *
     * Display-only today: WooCommerce's variant selection lives in the
     * cart form, and that's disabled in catalog mode.
     *
     * The data is already shaped so that sk_payments can dock onto it
     * later without needing a rebuild: every variant carries a stable key
     * (for the selection at instant checkout) and its own Sats amount
     * (for the invoice). Without both, enabling checkout would mean
     * starting over.
     */
    private static function apply_variants( int $post_id, array $variants, string $currency, bool $from ): void {
        if ( empty( $variants ) ) {
            delete_post_meta( $post_id, self::META_VARIANTS );
            delete_post_meta( $post_id, self::META_FROM );
            return;
        }

        $clean = [];
        foreach ( $variants as $variant ) {
            $label = trim( (string) ( $variant['name'] ?? '' ) );
            if ( $label === '' ) {
                continue;
            }

            $fiat = self::parse_price( (string) ( $variant['price'] ?? '' ) );
            $sats = null;

            if ( $fiat !== null && $fiat > 0 ) {
                $converted = Rate::to_sats( $fiat, $currency );
                $sats      = is_wp_error( $converted ) ? null : (int) $converted;
            }

            $sku = trim( (string) ( $variant['sku'] ?? '' ) );

            $clean[] = [
                // SKU when present, otherwise derived from the name —
                // either way, stable across imports.
                'key'      => $sku !== '' ? $sku : substr( md5( $label ), 0, 12 ),
                'name'     => $label,
                'price'    => $fiat,
                'currency' => $currency,
                'sats'     => $sats,
            ];
        }

        if ( empty( $clean ) ) {
            return;
        }

        update_post_meta( $post_id, self::META_VARIANTS, $clean );
        update_post_meta( $post_id, self::META_FROM, $from ? 1 : 0 );
    }

    private static function find_by_key( string $key ): int {
        $found = get_posts(
            [
                'post_type'      => 'product',
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_key'       => self::META_KEY,
                'meta_value'     => $key,
                'no_found_rows'  => true,
            ]
        );

        return $found ? (int) $found[0] : 0;
    }

    /**
     * Convert the price to Sats, but keep the fiat amount.
     *
     * Storing only the Sats price would mean it gets more wrong with
     * every rate change; with the original amount it can always be
     * recomputed.
     */
    private static function apply_price( int $post_id, string $raw, string $currency, array &$result ): void {
        $value = self::parse_price( $raw );
        if ( $value === null ) {
            return;
        }

        update_post_meta( $post_id, self::META_FIAT, $value );
        update_post_meta( $post_id, self::META_CURRENCY, $currency );

        $sats = Rate::to_sats( $value, $currency );
        if ( is_wp_error( $sats ) ) {
            $result['errors'][] = $sats->get_error_message();
            return;
        }

        // Via the API, so that wc_product_meta_lookup gets updated too.
        $product = wc_get_product( $post_id );
        if ( $product ) {
            $product->set_regular_price( (string) $sats );
            $product->set_price( (string) $sats );
            $product->save();
        }
    }

    /**
     * Both "1.234,56" and "1,234.56" should work.
     */
    public static function parse_price( string $raw ): ?float {
        $raw = trim( preg_replace( '/[^\d.,\-]/', '', $raw ) ?? '' );
        if ( $raw === '' ) {
            return null;
        }

        $last_comma = strrpos( $raw, ',' );
        $last_dot   = strrpos( $raw, '.' );

        if ( $last_comma !== false && ( $last_dot === false || $last_comma > $last_dot ) ) {
            // German format: the dot is a thousands separator.
            $raw = str_replace( '.', '', $raw );
            $raw = str_replace( ',', '.', $raw );
        } else {
            $raw = str_replace( ',', '', $raw );
        }

        return is_numeric( $raw ) ? (float) $raw : null;
    }

    private static function apply_categories( int $post_id, string $raw, array $map, int $default ): void {
        $ids = [];

        foreach ( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) as $name ) {
            // WooCommerce exports subcategories as "Parent > Child".
            // array_pop needs a variable, not a function result.
            $parts = explode( '>', $name );
            $leaf  = trim( (string) end( $parts ) );
            $key  = strtolower( $leaf );

            if ( isset( $map[ $key ] ) && (int) $map[ $key ] > 0 ) {
                $ids[] = (int) $map[ $key ];
            }
        }

        if ( empty( $ids ) && $default > 0 ) {
            $ids[] = $default;
        }

        if ( empty( $ids ) ) {
            return;
        }

        $product = wc_get_product( $post_id );
        if ( $product ) {
            $product->set_category_ids( array_values( array_unique( $ids ) ) );
            $product->save();
        } else {
            wp_set_object_terms( $post_id, array_unique( $ids ), 'product_cat' );
        }
    }

    /**
     * Load images. The first one becomes the featured image, the rest go
     * into the gallery.
     */
    private static function apply_images( int $post_id, string $raw, int $budget ): int {
        if ( $raw === '' || $budget <= 0 ) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $urls    = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
        $loaded  = 0;
        $gallery = [];

        foreach ( $urls as $url ) {
            if ( $loaded >= $budget ) {
                break;
            }
            if ( ! wp_http_validate_url( $url ) ) {
                continue;
            }

            $attachment_id = media_sideload_image( $url, $post_id, null, 'id' );
            if ( is_wp_error( $attachment_id ) ) {
                continue;
            }

            $loaded++;
            if ( ! has_post_thumbnail( $post_id ) ) {
                set_post_thumbnail( $post_id, $attachment_id );
            } else {
                $gallery[] = (int) $attachment_id;
            }
        }

        if ( ! empty( $gallery ) ) {
            update_post_meta( $post_id, '_product_image_gallery', implode( ',', $gallery ) );
        }

        return $loaded;
    }

    /**
     * Copy the vendor's location onto the listing — without coordinates,
     * it wouldn't show up in radius search.
     */
    private static function vendor_location( int $vendor_id ): array {
        return [
            'sk_geo_latitude'  => (string) get_user_meta( $vendor_id, 'sk_geo_latitude', true ),
            'sk_geo_longitude' => (string) get_user_meta( $vendor_id, 'sk_geo_longitude', true ),
            'sk_geo_address'   => (string) get_user_meta( $vendor_id, 'sk_geo_address', true ),
            'sk_geo_public'    => '1',
        ];
    }
}
