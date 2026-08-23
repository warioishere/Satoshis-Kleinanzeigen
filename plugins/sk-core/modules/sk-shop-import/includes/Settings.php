<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Zuordnung der Shop-Kategorien zu den eigenen.
 *
 * Je Verkäufer gespeichert: Zwei Shops nennen dieselbe Ware verschieden, eine
 * gemeinsame Tabelle würde sich gegenseitig überschreiben. Beim zweiten Import
 * steht die Zuordnung dadurch schon da.
 */
final class Settings {

    const META_CATEGORY_MAP = '_sk_import_category_map';
    const META_DEFAULT_CAT  = '_sk_import_default_cat';
    const META_CURRENCY     = '_sk_import_currency';

    /**
     * @return array<string,int> Shop-Kategorie (klein) => term_id
     */
    public static function category_map( int $vendor_id = 0 ): array {
        $vendor_id = $vendor_id ?: get_current_user_id();
        $map       = get_user_meta( $vendor_id, self::META_CATEGORY_MAP, true );

        return is_array( $map ) ? $map : [];
    }

    public static function save_category_map( int $vendor_id, array $map ): void {
        $clean = [];
        foreach ( $map as $name => $term_id ) {
            $name    = mb_strtolower( trim( (string) $name ), 'UTF-8' );
            $term_id = (int) $term_id;
            if ( $name !== '' && $term_id > 0 ) {
                $clean[ $name ] = $term_id;
            }
        }

        update_user_meta( $vendor_id, self::META_CATEGORY_MAP, $clean );
    }

    public static function default_category( int $vendor_id = 0 ): int {
        $vendor_id = $vendor_id ?: get_current_user_id();

        return (int) get_user_meta( $vendor_id, self::META_DEFAULT_CAT, true );
    }

    public static function save_default_category( int $vendor_id, int $term_id ): void {
        update_user_meta( $vendor_id, self::META_DEFAULT_CAT, max( 0, $term_id ) );
    }

    /**
     * Waehrung des Shops.
     *
     * Aus der Datei ist sie nicht zu holen: Ein WooCommerce-Export enthaelt
     * keine Waehrungsspalte, weil die Waehrung dort eine Shop-Einstellung ist
     * und kein Feld je Produkt. Deshalb wird abgeleitet — und die einmal
     * getroffene Wahl gemerkt, die weiss es besser als jede Heuristik.
     *
     * @return array{currency:string,reason:string}
     */
    public static function currency( int $vendor_id = 0 ): array {
        $vendor_id = $vendor_id ?: get_current_user_id();

        $saved = (string) get_user_meta( $vendor_id, self::META_CURRENCY, true );
        if ( in_array( $saved, [ 'EUR', 'CHF' ], true ) ) {
            return [ 'currency' => $saved, 'reason' => __( 'wie beim letzten Import', 'sk-core' ) ];
        }

        // Endung der Shop-Adresse.
        $host = (string) wp_parse_url( Dealer::shop_url( $vendor_id ), PHP_URL_HOST );
        if ( $host !== '' ) {
            if ( substr( $host, -3 ) === '.ch' ) {
                return [ 'currency' => 'CHF', 'reason' => __( 'aus deiner Shop-Adresse abgeleitet', 'sk-core' ) ];
            }
            if ( in_array( substr( $host, -3 ), [ '.de', '.at' ], true ) ) {
                return [ 'currency' => 'EUR', 'reason' => __( 'aus deiner Shop-Adresse abgeleitet', 'sk-core' ) ];
            }
        }

        // Standort des Verkaeufers.
        $place = mb_strtolower( (string) get_user_meta( $vendor_id, 'sk_geo_address', true ), 'UTF-8' );
        foreach ( [ 'schweiz', 'switzerland', 'suisse', 'svizzera' ] as $needle ) {
            if ( $place !== '' && strpos( $place, $needle ) !== false ) {
                return [ 'currency' => 'CHF', 'reason' => __( 'aus deinem Standort abgeleitet', 'sk-core' ) ];
            }
        }

        return [ 'currency' => 'EUR', 'reason' => __( 'Voreinstellung', 'sk-core' ) ];
    }

    public static function save_currency( int $vendor_id, string $currency ): void {
        $currency = strtoupper( $currency );

        if ( in_array( $currency, [ 'EUR', 'CHF' ], true ) ) {
            update_user_meta( $vendor_id, self::META_CURRENCY, $currency );
        }
    }
}
