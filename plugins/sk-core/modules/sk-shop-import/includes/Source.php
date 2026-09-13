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

    /** A fetched catalog is stored as JSON, an export as CSV. */
    public static function is_json( string $path ): bool {
        return strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) ) === 'json';
    }

    /**
     * Fetch a shop's catalog, whichever system it runs on.
     *
     * Both systems hand out their catalog publicly, only under different
     * addresses, so the dealer does not have to say which one they use —
     * we simply ask both. WooCommerce first: its answer is the one that
     * also tells a wrong address apart from a switched-off endpoint.
     *
     * @return array{source:string,products:array}|\WP_Error
     */
    public static function fetch( string $shop_url ) {
        $woo = Woo::fetch( $shop_url );

        if ( ! is_wp_error( $woo ) ) {
            return [ 'source' => 'woo', 'products' => $woo ];
        }

        $shopify = Shopify::fetch( $shop_url );

        if ( ! is_wp_error( $shopify ) ) {
            return [ 'source' => 'shopify', 'products' => $shopify ];
        }

        return new \WP_Error(
            'sk_source_fetch',
            __( 'Unter dieser Adresse ist kein Katalog zu holen. Weder WooCommerce noch Shopify antworten dort mit Produkten. Läuft dein Shop auf etwas anderem, oder ist die Schnittstelle abgeschaltet, nimm den Weg über die CSV-Datei.', 'sk-core' )
        );
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

            if ( is_wp_error( $products ) ) {
                return $products;
            }

            return 'woo' === self::system( $path ) ? Woo::build( $products ) : Shopify::build( $products );
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
     * Which shop system the stored catalog came from.
     *
     * Files written before WooCommerce could be fetched carry no marker;
     * back then only Shopify was possible.
     */
    private static function system( string $path ): string {
        $data = is_readable( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null;

        return is_array( $data ) && 'woo' === ( $data['source'] ?? '' ) ? 'woo' : 'shopify';
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
