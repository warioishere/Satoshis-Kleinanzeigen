<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Macht aus CSV-Zeilen eine Liste von Inseraten.
 *
 * Ein WooCommerce-Export enthaelt drei Sorten Zeilen: eigenstaendige Produkte
 * (simple), Elternprodukte (variable) und deren Varianten (variation). Wuerde
 * man alle importieren, stuende dieselbe Ware mehrfach im Marktplatz — im
 * Beispielexport waren 42 von 70 Zeilen Varianten.
 *
 * Varianten werden deshalb nicht zu eigenen Inseraten, sondern als Ausfuehrungen
 * an ihr Elternprodukt gehaengt. Ein variables Produkt hat selbst keinen Preis;
 * seiner wird der guenstigste Variantenpreis.
 */
final class Catalog {

    /**
     * @return array<int,array> Inserate mit Schluesseln
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
                // Im Export steht 1 fuer veroeffentlicht und -1 fuer privat.
                'draft'       => $get( $row, 'published' ) !== '' && $get( $row, 'published' ) !== '1',
            ];

            // "variation" und "variation, virtual" beide erfassen.
            if ( strpos( $type, 'variation' ) !== false ) {
                $variations[] = $item;
                continue;
            }

            $parents[] = $item;
        }

        // Varianten ihren Eltern zuordnen. Der Export verweist ueber die
        // Artikelnummer oder ueber "id:<ID>".
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

            // Ein variables Produkt traegt keinen eigenen Preis.
            if ( $parent['price'] === '' && ! empty( $parent['variants'] ) ) {
                $parent['price'] = self::lowest_price( $parent['variants'] );
                $parent['from']  = true;
            }
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
     * Aus "Bitbox 02 - Bitcoin only" wird "Bitcoin only".
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
     * Kategorienamen, die im Export vorkommen — Grundlage der Zuordnung.
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
