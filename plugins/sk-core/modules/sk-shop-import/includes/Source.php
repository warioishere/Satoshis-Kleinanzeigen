<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Aus einer abgelegten Datei Artikel machen — gleich welcher Herkunft.
 *
 * Drei Stellen brauchen denselben Schritt: die Vorschau im Dashboard, das
 * Anlegen des Auftrags und jeder Stapel, den der Auftrag später abarbeitet.
 * Stünde die Fallunterscheidung dreimal da, liefe sie irgendwann auseinander
 * — die Vorschau zeigte dann etwas anderes als der Import tut.
 */
final class Source {

    /** Ein geholter Shopify-Katalog liegt als JSON, ein Export als CSV. */
    public static function is_json( string $path ): bool {
        return strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) ) === 'json';
    }

    /**
     * Artikel aus der Datei.
     *
     * @param string $path    Abgelegte Datei.
     * @param array  $mapping Nur für CSV; JSON braucht keine Zuordnung.
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
     * Wie viele Zeilen beziehungsweise Produkte die Quelle enthält.
     *
     * Für die Zusammenfassung im Formular: "aus 89 Zeilen wurden 26 Inserate"
     * ist die Angabe, an der jemand merkt, ob etwas verschluckt wurde.
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
     * Rohe Produkte aus einer abgelegten Katalogdatei.
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
