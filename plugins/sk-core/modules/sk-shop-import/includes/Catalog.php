<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Turns CSV rows into a list of listings.
 *
 * A WooCommerce export contains three kinds of rows: standalone products
 * (simple), parent products (variable), and their variations (variation). If
 * all of them were imported, the same item would end up listed multiple
 * times in the marketplace — in the sample export, 42 of 70 rows were
 * variations.
 *
 * Variations therefore don't become their own listings but are attached to
 * their parent product as variants. A variable product has no price of its
 * own; it gets the lowest variant price instead.
 */
final class Catalog {

    /**
     * @return array<int,array> Listings with keys
     *                          sku,name,description,short,price,categories,
     *                          images,variants,draft
     */
    public static function build( array $headers, array $rows, array $mapping ): array {
        $get = static function ( array $row, string $field ) use ( $mapping ): string {
            $i = (int) ( $mapping[ $field ] ?? -1 );
            return $i >= 0 && isset( $row[ $i ] ) ? trim( (string) $row[ $i ] ) : '';
        };

        $parents    = [];
        $variations = [];

        foreach ( $rows as $row ) {
            $name = $get( $row, 'name' );
            if ( $name === '' ) {
                continue;
            }

            $type = mb_strtolower( $get( $row, 'type' ), 'UTF-8' );
            $item = [
                'id'          => $get( $row, 'id' ),
                'sku'         => $get( $row, 'sku' ),
                'name'        => $name,
                'description' => $get( $row, 'description' ),
                'short'       => $get( $row, 'short' ),
                'price'       => $get( $row, 'price' ),
                'categories'  => $get( $row, 'categories' ),
                'images'      => $get( $row, 'images' ),
                'parent'      => $get( $row, 'parent' ),
                // In the export, 1 means published and -1 means private.
                'draft'       => $get( $row, 'published' ) !== '' && $get( $row, 'published' ) !== '1',
            ];

            // Catch both "variation" and "variation, virtual".
            if ( strpos( $type, 'variation' ) !== false ) {
                $variations[] = $item;
                continue;
            }

            $parents[] = $item;
        }

        // Match variations to their parents. The export refers to them via
        // the SKU or via "id:<ID>".
        foreach ( $parents as &$parent ) {
            $parent['variants'] = [];

            foreach ( $variations as $variation ) {
                if ( ! self::belongs_to( $variation, $parent ) ) {
                    continue;
                }

                $parent['variants'][] = [
                    'name'  => self::variant_label( $variation, $parent ),
                    'price' => $variation['price'],
                    'sku'   => $variation['sku'],
                ];
            }

            // A variable product doesn't carry its own price.
            if ( $parent['price'] === '' && ! empty( $parent['variants'] ) ) {
                $parent['price'] = self::lowest_price( $parent['variants'] );
                $parent['from']  = true;
            }

            // The same key the importer uses to look this item back up —
            // so the checkbox and the import are guaranteed to mean the same thing.
            $parent['key'] = $parent['sku'] !== '' ? $parent['sku'] : md5( $parent['name'] );
        }
        unset( $parent );

        return $parents;
    }

    private static function belongs_to( array $variation, array $parent ): bool {
        $ref = $variation['parent'];
        if ( $ref === '' ) {
            return false;
        }

        if ( $parent['sku'] !== '' && $ref === $parent['sku'] ) {
            return true;
        }

        return $parent['id'] !== '' && $ref === 'id:' . $parent['id'];
    }

    /**
     * "Bitbox 02 - Bitcoin only" becomes "Bitcoin only".
     */
    private static function variant_label( array $variation, array $parent ): string {
        $label = $variation['name'];

        if ( $parent['name'] !== '' && strpos( $label, $parent['name'] ) === 0 ) {
            $label = trim( substr( $label, strlen( $parent['name'] ) ), " -–—\t" );
        }

        return $label !== '' ? $label : $variation['sku'];
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

    /**
     * Category names that occur in the export — the basis for mapping.
     *
     * @return string[]
     */
    public static function categories( array $items ): array {
        $found = [];

        foreach ( $items as $item ) {
            foreach ( array_filter( array_map( 'trim', explode( ',', (string) $item['categories'] ) ) ) as $name ) {
                $found[ $name ] = true;
            }
        }

        $names = array_keys( $found );
        sort( $names );

        return $names;
    }
}
