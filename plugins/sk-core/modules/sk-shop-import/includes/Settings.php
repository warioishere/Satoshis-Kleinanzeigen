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
}
