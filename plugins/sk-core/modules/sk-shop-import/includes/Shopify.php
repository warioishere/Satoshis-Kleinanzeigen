<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Fetch a Shopify shop's catalog via /products.json.
 *
 * The CSV path remains the general one — it's the only option for
 * WooCommerce, and the fallback when a shop has this endpoint disabled.
 * For Shopify, the second path still pays off: there, variants and images
 * are nested inside the product, while the CSV flattens them and they'd
 * have to be reassembled via the handle. This removes both column mapping
 * and guessing.
 *
 * The endpoint is public, so no credentials are needed. It only shows
 * what's published in the online store — drafts and archived items are
 * missing, which is more correct than not for a catalog import.
 *
 * The dealer enters the shop URL themselves. It is therefore user input;
 * the fetch runs via wp_safe_remote_get(), which rejects internal address
 * ranges, fetching only happens from a confirmed domain, and the page is
 * open only to enabled dealers anyway.
 */
final class Shopify {

    /** Maximum per fetch; Shopify doesn't give out more. */
    const PER_PAGE = 250;

    /** Safety brake against a catalog that keeps paging forever. */
    const MAX_PAGES = 20;

    /** Seconds per fetch. */
    const TIMEOUT = 20;

    /**
     * The catalog URL for a shop URL.
     *
     * @return string Empty if no usable URL can be derived from it.
     */
    public static function catalog_url( string $shop_url, int $page = 1 ): string {
        $shop_url = trim( $shop_url );

        if ( $shop_url === '' ) {
            return '';
        }

        // Without a scheme, wp_parse_url would read the URL as a path.
        if ( ! preg_match( '#^https?://#i', $shop_url ) ) {
            $shop_url = 'https://' . ltrim( $shop_url, '/' );
        }

        $host = (string) wp_parse_url( $shop_url, PHP_URL_HOST );

        if ( $host === '' ) {
            return '';
        }

        return add_query_arg(
            [ 'limit' => self::PER_PAGE, 'page' => max( 1, $page ) ],
            'https://' . $host . '/products.json'
        );
    }

    /**
     * Fetch the whole catalog, page by page.
     *
     * @return array<int,array>|\WP_Error Raw products, as Shopify delivers them.
     */
    public static function fetch( string $shop_url ) {
        if ( self::catalog_url( $shop_url ) === '' ) {
            return new \WP_Error( 'sk_shopify_url', __( 'Für diesen Händler ist keine gültige Shop-Adresse hinterlegt.', 'sk-core' ) );
        }

        $products = [];

        for ( $page = 1; $page <= self::MAX_PAGES; $page++ ) {
            $response = wp_safe_remote_get(
                self::catalog_url( $shop_url, $page ),
                [
                    'timeout'    => self::TIMEOUT,
                    'user-agent' => 'SatoshisKleinanzeigen Shop-Import',
                    'headers'    => [ 'Accept' => 'application/json' ],
                ]
            );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $code = (int) wp_remote_retrieve_response_code( $response );

            if ( $code !== 200 ) {
                return new \WP_Error(
                    'sk_shopify_http',
                    sprintf(
                        /* translators: %d: HTTP status code. */
                        __( 'Der Shop antwortet mit Status %d. Entweder ist es kein Shopify-Shop, oder der Katalog ist dort nicht öffentlich.', 'sk-core' ),
                        $code
                    )
                );
            }

            $data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

            if ( ! is_array( $data ) || ! isset( $data['products'] ) || ! is_array( $data['products'] ) ) {
                return new \WP_Error( 'sk_shopify_body', __( 'Die Antwort des Shops ist kein Shopify-Katalog.', 'sk-core' ) );
            }

            $batch = $data['products'];

            if ( empty( $batch ) ) {
                break;
            }

            $products = array_merge( $products, $batch );

            // A page that isn't full is the last one.
            if ( count( $batch ) < self::PER_PAGE ) {
                break;
            }
        }

        if ( empty( $products ) ) {
            return new \WP_Error( 'sk_shopify_empty', __( 'Der Shop gibt keine Produkte heraus.', 'sk-core' ) );
        }

        return $products;
    }

    /**
     * Bring raw Shopify products into the shape the importer expects.
     *
     * The same keys as Catalog::build() — this way, Importer, Job, Quota,
     * and Variants don't notice which source an item came from.
     *
     * @param array<int,array> $products
     * @return array<int,array>
     */
    public static function build( array $products ): array {
        $items = [];

        foreach ( $products as $product ) {
            if ( ! is_array( $product ) ) {
                continue;
            }

            $name = trim( (string) ( $product['title'] ?? '' ) );

            if ( $name === '' ) {
                continue;
            }

            $variants = self::variants( $product );
            $first    = is_array( $product['variants'] ?? null ) ? reset( $product['variants'] ) : [];
            $sku      = is_array( $first ) ? trim( (string) ( $first['sku'] ?? '' ) ) : '';

            $item = [
                'id'          => (string) ( $product['id'] ?? '' ),
                'sku'         => $sku,
                'name'        => $name,
                'description' => (string) ( $product['body_html'] ?? '' ),
                'short'       => '',
                'price'       => '',
                'categories'  => self::category( $product ),
                'images'      => self::images( $product ),
                'parent'      => '',
                // The endpoint gives out only published items anyway; the
                // check stays in case a shop leaves the field empty regardless.
                'draft'       => empty( $product['published_at'] ),
                'variants'    => $variants,
            ];

            if ( empty( $variants ) ) {
                // A product without a real selection carries its own price.
                $item['price'] = is_array( $first ) ? trim( (string) ( $first['price'] ?? '' ) ) : '';
            } else {
                $item['price'] = self::lowest_price( $variants );
                $item['from']  = true;
            }

            $item['key'] = $item['sku'] !== '' ? $item['sku'] : md5( $item['name'] );

            $items[] = $item;
        }

        return $items;
    }

    /**
     * A product's variants.
     *
     * Shopify creates a variant even for a product with no real selection,
     * named "Default Title". That isn't a variant, it's the product
     * itself — carrying it over would give every single listing a
     * meaningless variant with that name.
     *
     * @return array<int,array{name:string,price:string,sku:string}>
     */
    private static function variants( array $product ): array {
        $raw = is_array( $product['variants'] ?? null ) ? $product['variants'] : [];

        if ( count( $raw ) < 2 ) {
            return [];
        }

        $variants = [];

        foreach ( $raw as $variant ) {
            if ( ! is_array( $variant ) ) {
                continue;
            }

            $label = trim( (string) ( $variant['title'] ?? '' ) );

            if ( $label === '' || strcasecmp( $label, 'Default Title' ) === 0 ) {
                $label = trim( (string) ( $variant['sku'] ?? '' ) );
            }

            if ( $label === '' ) {
                continue;
            }

            $variants[] = [
                'name'  => $label,
                'price' => trim( (string) ( $variant['price'] ?? '' ) ),
                'sku'   => trim( (string) ( $variant['sku'] ?? '' ) ),
            ];
        }

        return $variants;
    }

    /**
     * Images as a comma-separated list — the format apply_images() reads.
     */
    private static function images( array $product ): string {
        $urls = [];

        foreach ( (array) ( $product['images'] ?? [] ) as $image ) {
            $src = is_array( $image ) ? trim( (string) ( $image['src'] ?? '' ) ) : '';

            if ( $src !== '' ) {
                $urls[] = $src;
            }
        }

        return implode( ',', $urls );
    }

    /**
     * Whatever comes closest to a category in Shopify.
     *
     * product_type is the field a shop uses to organize its range ("ASIC",
     * "Upgrade Kit"). If it's missing, the first tag serves as a clue —
     * the mapping to an SK category is decided by the dealer in the form
     * anyway.
     */
    private static function category( array $product ): string {
        $type = trim( (string) ( $product['product_type'] ?? '' ) );

        if ( $type !== '' ) {
            return $type;
        }

        $tags = $product['tags'] ?? [];

        if ( is_string( $tags ) ) {
            $tags = array_map( 'trim', explode( ',', $tags ) );
        }

        return is_array( $tags ) && ! empty( $tags ) ? trim( (string) reset( $tags ) ) : '';
    }

    private static function lowest_price( array $variants ): string {
        $prices = [];

        foreach ( $variants as $variant ) {
            $value = Importer::parse_price( (string) $variant['price'] );

            if ( $value !== null && $value > 0 ) {
                $prices[] = $value;
            }
        }

        return empty( $prices ) ? '' : (string) min( $prices );
    }
}
