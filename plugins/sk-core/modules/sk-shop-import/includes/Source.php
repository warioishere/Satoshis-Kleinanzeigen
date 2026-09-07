<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Turn a stored file into items — regardless of its origin.
 *
 * Three places need the same step: the dashboard preview, creating the
 * job, and every batch the job later processes. If the case distinction
 * lived in three places, it would eventually drift apart — the preview
 * would then show something different from what the import actually does.
 */
final class Source {

    /** A fetched Shopify catalog is stored as JSON, an export as CSV. */
    public static function is_json( string $path ): bool {
        return strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) ) === 'json';
    }

    /**
     * Items from the file.
     *
     * @param string $path    Stored file.
     * @param array  $mapping Only for CSV; JSON needs no mapping.
     * @return array<int,array>|\WP_Error
     */
    public static function items( string $path, array $mapping = [] ) {
        if ( self::is_json( $path ) ) {
            $products = self::products( $path );

            return is_wp_error( $products ) ? $products : Shopify::build( $products );
        }

        $csv = Csv::read( $path );

        if ( is_wp_error( $csv ) ) {
            return $csv;
        }

        return Catalog::build( $csv['headers'], $csv['rows'], $mapping );
    }

    /**
     * How many rows or products the source contains.
     *
     * For the summary in the form: "89 rows became 26 listings" is the
     * figure that lets someone notice if something got swallowed.
     */
    public static function count( string $path ): int {
        if ( self::is_json( $path ) ) {
            $products = self::products( $path );

            return is_wp_error( $products ) ? 0 : count( $products );
        }

        $csv = Csv::read( $path );

        return is_wp_error( $csv ) ? 0 : (int) $csv['count'];
    }

    /**
     * Raw products from a stored catalog file.
     *
     * @return array<int,array>|\WP_Error
     */
    private static function products( string $path ) {
        if ( ! is_readable( $path ) ) {
            return new \WP_Error( 'sk_source_unreadable', __( 'Die Datei lässt sich nicht lesen.', 'sk-core' ) );
        }

        $data = json_decode( (string) file_get_contents( $path ), true );

        if ( ! is_array( $data ) || ! isset( $data['products'] ) || ! is_array( $data['products'] ) ) {
            return new \WP_Error( 'sk_source_json', __( 'Die abgelegte Katalogdatei ist unbrauchbar. Bitte den Katalog erneut holen.', 'sk-core' ) );
        }

        return $data['products'];
    }
}
