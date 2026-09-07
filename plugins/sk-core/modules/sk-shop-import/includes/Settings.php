<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Mapping of shop categories to our own.
 *
 * Stored per vendor: two shops call the same item something different, and
 * a shared table would keep overwriting itself. This way, the mapping is
 * already there by the second import.
 */
final class Settings {

    const META_CATEGORY_MAP = '_sk_import_category_map';
    const META_DEFAULT_CAT  = '_sk_import_default_cat';
    const META_CURRENCY     = '_sk_import_currency';

    /**
     * @return array<string,int> Shop category (lowercase) => term_id
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
     * The shop's currency.
     *
     * It can't be pulled from the file: a WooCommerce export contains no
     * currency column, because there the currency is a shop setting, not
     * a per-product field. So it's inferred — and once a choice has been
     * made, it's remembered, since that knows better than any heuristic.
     *
     * @return array{currency:string,reason:string}
     */
    public static function currency( int $vendor_id = 0 ): array {
        $vendor_id = $vendor_id ?: get_current_user_id();

        $saved = (string) get_user_meta( $vendor_id, self::META_CURRENCY, true );
        if ( in_array( $saved, [ 'EUR', 'CHF' ], true ) ) {
            return [ 'currency' => $saved, 'reason' => __( 'wie beim letzten Import', 'sk-core' ) ];
        }

        /*
         * Suffix of the shop URL the dealer entered when fetching. This
         * used to be a field the operator had to maintain by hand per
         * dealer — it did nothing more than this suggestion, which the
         * dealer can override in the form anyway.
         */
        $host = (string) wp_parse_url(
            (string) get_user_meta( $vendor_id, DashboardPage::META_FETCH_URL, true ),
            PHP_URL_HOST
        );
        if ( $host !== '' ) {
            if ( substr( $host, -3 ) === '.ch' ) {
                return [ 'currency' => 'CHF', 'reason' => __( 'aus deiner Shop-Adresse abgeleitet', 'sk-core' ) ];
            }
            if ( in_array( substr( $host, -3 ), [ '.de', '.at' ], true ) ) {
                return [ 'currency' => 'EUR', 'reason' => __( 'aus deiner Shop-Adresse abgeleitet', 'sk-core' ) ];
            }
        }

        // Vendor's location.
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
