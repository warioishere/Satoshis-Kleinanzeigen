<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Fetch a WooCommerce shop's catalog via its Store API.
 *
 * The counterpart to Shopify: the dealer gives the shop address, nothing
 * else. /wp-json/wc/store/v1/products is part of WooCommerce, needs no
 * credentials and hands out only what the shop shows publicly anyway.
 * That removes the CSV detour, where the file had to be exported by hand
 * and could never be fetched again for a later comparison.
 *
 * The CSV path stays as the fallback: older shops don't have the endpoint,
 * and some hosts switch off /wp-json altogether.
 *
 * Prices arrive in minor units with the exponent alongside — 63900 with
 * currency_minor_unit 2 is 639.00 — so they are converted here rather than
 * anywhere further down.
 */
final class Woo {

    /** Maximum the Store API accepts. */
    const PER_PAGE = 100;

    /** Safety brake against a catalog that keeps paging forever. */
    const MAX_PAGES = 30;

    /** Seconds per fetch. */
    const TIMEOUT = 20;

    /**
     * Products whose variants may cost different amounts, per fetch.
     *
     * Each of those needs one request per variant, so a catalog full of
     * them would otherwise turn one import into hundreds of requests.
     */
    const MAX_VARIANT_LOOKUPS = 60;

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
            [ 'per_page' => self::PER_PAGE, 'page' => max( 1, $page ) ],
            'https://' . $host . '/wp-json/wc/store/v1/products'
        );
    }

    /**
     * Is there a WooCommerce catalog behind this address?
     *
     * Asks for a single product, so the answer is cheap.
     */
    public static function available( string $shop_url ): bool {
        $url = self::catalog_url( $shop_url );

        if ( $url === '' ) {
            return false;
        }

        $response = wp_safe_remote_get(
            add_query_arg( 'per_page', 1, remove_query_arg( 'per_page', $url ) ),
            [ 'timeout' => self::TIMEOUT, 'headers' => [ 'Accept' => 'application/json' ] ]
        );

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return false;
        }

        return is_array( json_decode( (string) wp_remote_retrieve_body( $response ), true ) );
    }

    /**
     * Fetch the whole catalog, page by page.
     *
     * @return array<int,array>|\WP_Error Raw products, as the Store API delivers them.
     */
    public static function fetch( string $shop_url ) {
        if ( self::catalog_url( $shop_url ) === '' ) {
            return new \WP_Error( 'sk_woo_url', __( 'Für diesen Händler ist keine gültige Shop-Adresse hinterlegt.', 'sk-core' ) );
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

            if ( 200 !== $code ) {
                return new \WP_Error(
                    'sk_woo_http',
                    sprintf(
                        /* translators: %d: HTTP status code. */
                        __( 'Der Shop antwortet mit Status %d. Entweder ist es kein WooCommerce-Shop, oder die Schnittstelle ist dort abgeschaltet.', 'sk-core' ),
                        $code
                    )
                );
            }

            $batch = json_decode( (string) wp_remote_retrieve_body( $response ), true );

            if ( ! is_array( $batch ) ) {
                return new \WP_Error( 'sk_woo_body', __( 'Die Antwort des Shops ist kein WooCommerce-Katalog.', 'sk-core' ) );
            }

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
            return new \WP_Error( 'sk_woo_empty', __( 'Der Shop gibt keine Produkte heraus.', 'sk-core' ) );
        }

        return self::with_variant_prices( $shop_url, $products );
    }

    /**
     * Bring raw Store API products into the shape the importer expects.
     *
     * The same keys as Catalog::build() and Shopify::build().
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

            $name = self::text( (string) ( $product['name'] ?? '' ) );

            if ( '' === $name ) {
                continue;
            }

            $variants = self::variants( $product );
            $sku      = trim( (string) ( $product['sku'] ?? '' ) );

            $item = [
                'id'          => (string) ( $product['id'] ?? '' ),
                'sku'         => $sku,
                'name'        => $name,
                'description' => (string) ( $product['description'] ?? '' ),
                'short'       => (string) ( $product['short_description'] ?? '' ),
                'price'       => self::price( $product ),
                'categories'  => self::categories( $product ),
                'images'      => self::images( $product ),
                'parent'      => '',
                // Sold out counts as not for sale — the shop shows it, we
                // would otherwise advertise something nobody can get.
                'draft'       => isset( $product['is_in_stock'] ) && ! $product['is_in_stock'],
                'variants'    => $variants,
            ];

            if ( ! empty( $variants ) ) {
                $lowest = self::lowest_price( $variants );

                if ( '' !== $lowest ) {
                    $item['price'] = $lowest;
                    $item['from']  = true;
                }
            }

            $item['key'] = '' !== $item['sku'] ? $item['sku'] : md5( $item['name'] );

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Ask for the price of each variant, but only where they can differ.
     *
     * The list hands out variant ids without prices. price_range is set
     * exactly when they are not all the same, so everything else keeps the
     * parent's price and costs no request.
     *
     * @param array<int,array> $products
     * @return array<int,array>
     */
    private static function with_variant_prices( string $shop_url, array $products ): array {
        $host    = (string) wp_parse_url( self::catalog_url( $shop_url ), PHP_URL_HOST );
        $lookups = 0;

        foreach ( $products as $i => $product ) {
            $variations = is_array( $product['variations'] ?? null ) ? $product['variations'] : [];

            if ( count( $variations ) < 2 || empty( $product['prices']['price_range'] ) ) {
                continue;
            }

            foreach ( $variations as $k => $variation ) {
                if ( $lookups >= self::MAX_VARIANT_LOOKUPS ) {
                    break 2;
                }

                $id = (int) ( $variation['id'] ?? 0 );

                if ( $id <= 0 ) {
                    continue;
                }

                $lookups++;

                $response = wp_safe_remote_get(
                    'https://' . $host . '/wp-json/wc/store/v1/products/' . $id,
                    [ 'timeout' => self::TIMEOUT, 'headers' => [ 'Accept' => 'application/json' ] ]
                );

                if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
                    continue;
                }

                $single = json_decode( (string) wp_remote_retrieve_body( $response ), true );

                if ( is_array( $single ) ) {
                    $products[ $i ]['variations'][ $k ]['prices'] = $single['prices'] ?? [];
                    $products[ $i ]['variations'][ $k ]['sku']    = $single['sku'] ?? '';
                }
            }
        }

        return $products;
    }

    /**
     * A product's variants.
     *
     * @return array<int,array{name:string,price:string,sku:string}>
     */
    private static function variants( array $product ): array {
        $raw = is_array( $product['variations'] ?? null ) ? $product['variations'] : [];

        if ( count( $raw ) < 2 ) {
            return [];
        }

        $fallback = self::price( $product );
        $terms    = self::term_names( $product );
        $variants = [];

        foreach ( $raw as $variation ) {
            if ( ! is_array( $variation ) ) {
                continue;
            }

            $parts = [];

            foreach ( (array) ( $variation['attributes'] ?? [] ) as $attribute ) {
                if ( ! is_array( $attribute ) ) {
                    continue;
                }

                $name  = self::text( (string) ( $attribute['name'] ?? '' ) );
                $value = self::text( (string) ( $attribute['value'] ?? '' ) );

                if ( '' === $value ) {
                    continue;
                }

                // The variation carries the slug; the readable name sits on
                // the parent. Without it a yes/no option reads as "ja / nein".
                $value = $terms[ $name ][ $value ] ?? $value;

                $parts[] = '' === $name ? $value : self::label( $name ) . ': ' . $value;
            }

            $label = implode( ', ', $parts );

            if ( '' === $label ) {
                continue;
            }

            $variants[] = [
                'name'  => $label,
                'price' => isset( $variation['prices'] ) ? self::price( $variation ) : $fallback,
                'sku'   => trim( (string) ( $variation['sku'] ?? '' ) ),
            ];
        }

        return $variants;
    }

    /**
     * Readable term names per attribute, keyed by the slug a variation uses.
     *
     * @return array<string,array<string,string>>
     */
    private static function term_names( array $product ): array {
        $map = [];

        foreach ( (array) ( $product['attributes'] ?? [] ) as $attribute ) {
            if ( ! is_array( $attribute ) ) {
                continue;
            }

            $name = self::text( (string) ( $attribute['name'] ?? '' ) );

            foreach ( (array) ( $attribute['terms'] ?? [] ) as $term ) {
                if ( is_array( $term ) ) {
                    $map[ $name ][ (string) ( $term['slug'] ?? '' ) ] = self::text( (string) ( $term['name'] ?? '' ) );
                }
            }
        }

        return $map;
    }

    /**
     * Attribute names arrive as they were typed, sometimes all lowercase.
     */
    private static function label( string $name ): string {
        return function_exists( 'mb_convert_case' ) && $name === mb_strtolower( $name, 'UTF-8' )
            ? mb_convert_case( $name, MB_CASE_TITLE, 'UTF-8' )
            : $name;
    }

    /**
     * The price in the shop's own currency, in whole units.
     */
    private static function price( array $product ): string {
        $prices = is_array( $product['prices'] ?? null ) ? $product['prices'] : [];
        $raw    = trim( (string) ( $prices['price'] ?? '' ) );

        if ( '' === $raw || ! is_numeric( $raw ) ) {
            return '';
        }

        $exponent = isset( $prices['currency_minor_unit'] ) ? (int) $prices['currency_minor_unit'] : 2;

        return (string) ( (float) $raw / ( 10 ** max( 0, $exponent ) ) );
    }

    /**
     * The deepest category, the way the CSV path reads them too.
     */
    private static function categories( array $product ): string {
        $names = [];

        foreach ( (array) ( $product['categories'] ?? [] ) as $category ) {
            $name = is_array( $category ) ? self::text( (string) ( $category['name'] ?? '' ) ) : '';

            if ( '' !== $name ) {
                $names[] = $name;
            }
        }

        return empty( $names ) ? '' : (string) end( $names );
    }

    /**
     * Images as a comma-separated list — the format apply_images() reads.
     */
    private static function images( array $product ): string {
        $urls = [];

        foreach ( (array) ( $product['images'] ?? [] ) as $image ) {
            $src = is_array( $image ) ? trim( (string) ( $image['src'] ?? '' ) ) : '';

            if ( '' !== $src ) {
                $urls[] = $src;
            }
        }

        return implode( ',', $urls );
    }

    private static function lowest_price( array $variants ): string {
        $prices = [];

        foreach ( $variants as $variant ) {
            $value = Importer::parse_price( (string) $variant['price'] );

            if ( null !== $value && $value > 0 ) {
                $prices[] = $value;
            }
        }

        return empty( $prices ) ? '' : (string) min( $prices );
    }

    /**
     * The Store API returns names with HTML entities ("6.31&#8243;").
     */
    private static function text( string $value ): string {
        return trim( html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES, 'UTF-8' ) );
    }
}
