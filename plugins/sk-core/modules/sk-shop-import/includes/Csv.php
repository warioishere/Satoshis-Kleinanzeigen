<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * CSV einlesen und Spalten erraten.
 *
 * Bewusst ein eigener Leser statt WC_Product_CSV_Importer: Jener ist auf den
 * Import-Assistenten im WP-Admin zugeschnitten und bringt eine grosse
 * Fehleroberflaeche mit. Hier braucht es Kontrolle darueber, wem die Inserate
 * gehoeren, wie Preise nach Sats kommen und wie viele Bilder geladen werden.
 *
 * Die Spaltennamen haengen von der Sprache des exportierenden Shops ab —
 * deshalb wird geraten und die Zuordnung anschliessend angezeigt, statt sich
 * auf feste Namen zu verlassen.
 */
final class Csv {

    /** Welche Felder das Ziel kennt. */
    const FIELDS = [
        'sku'         => 'Artikelnummer',
        'name'        => 'Titel',
        'description' => 'Beschreibung',
        'short'       => 'Kurzbeschreibung',
        'price'       => 'Preis',
        'categories'  => 'Kategorien',
        'images'      => 'Bilder',
    ];

    /**
     * Spalten, die die Struktur beschreiben statt den Inhalt. Sie werden nicht
     * zugeordnet, sondern erraten — ohne sie liessen sich Varianten nicht von
     * eigenstaendigen Produkten unterscheiden.
     */
    const STRUCTURE = [ 'type', 'parent', 'published', 'id' ];

    /**
     * Uebliche Spaltennamen aus WooCommerce-Exporten, deutsch und englisch.
     */
    const GUESS = [
        'sku'         => [ 'sku', 'artikelnummer', 'artikel-nr', 'artikelnr' ],
        'name'        => [ 'name', 'titel', 'title', 'produktname', 'post_title' ],
        'description' => [ 'description', 'beschreibung', 'post_content', 'inhalt' ],
        'short'       => [ 'short description', 'kurzbeschreibung', 'post_excerpt', 'auszug' ],
        'price'       => [ 'regular price', 'regulärer preis', 'regulaerer preis', 'preis', 'price', 'normalpreis' ],
        'categories'  => [ 'categories', 'kategorien', 'kategorie', 'category' ],
        'images'      => [ 'images', 'bilder', 'bild', 'image' ],
        'type'        => [ 'type', 'typ', 'produkttyp', 'product type' ],
        'parent'      => [ 'parent', 'übergeordnetes produkt', 'uebergeordnetes produkt', 'parent product', 'übergeordnet' ],
        'published'   => [ 'published', 'veröffentlicht', 'veroeffentlicht', 'status' ],
        'id'          => [ 'id', 'produkt-id', 'post_id' ],
    ];

    /**
     * Datei einlesen.
     *
     * @return array{headers:array,rows:array,delimiter:string,count:int}|\WP_Error
     */
    public static function read( string $file, int $limit = 0 ) {
        if ( ! is_readable( $file ) ) {
            return new \WP_Error( 'sk_csv_unreadable', __( 'Die Datei lässt sich nicht lesen.', 'sk-core' ) );
        }

        $handle = fopen( $file, 'r' );
        if ( ! $handle ) {
            return new \WP_Error( 'sk_csv_open', __( 'Die Datei lässt sich nicht öffnen.', 'sk-core' ) );
        }

        $first = fgets( $handle );
        if ( $first === false ) {
            fclose( $handle );
            return new \WP_Error( 'sk_csv_empty', __( 'Die Datei ist leer.', 'sk-core' ) );
        }

        $delimiter = self::sniff_delimiter( $first );
        rewind( $handle );

        $headers = fgetcsv( $handle, 0, $delimiter );
        if ( ! $headers ) {
            fclose( $handle );
            return new \WP_Error( 'sk_csv_headers', __( 'Es liess sich keine Kopfzeile lesen.', 'sk-core' ) );
        }

        $headers = array_map( [ self::class, 'clean' ], $headers );

        $rows  = [];
        $count = 0;
        while ( ( $data = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
            // Leere Zeilen am Dateiende ueberspringen.
            if ( count( $data ) === 1 && trim( (string) $data[0] ) === '' ) {
                continue;
            }
            $count++;
            if ( $limit === 0 || count( $rows ) < $limit ) {
                $rows[] = array_map( [ self::class, 'clean' ], $data );
            }
        }
        fclose( $handle );

        return [
            'headers'   => $headers,
            'rows'      => $rows,
            'delimiter' => $delimiter,
            'count'     => $count,
        ];
    }

    /**
     * Zuordnung raten: Feld => Spaltenindex, -1 wenn nichts passt.
     *
     * @return array<string,int>
     */
    public static function guess_mapping( array $headers ): array {
        // mb_strtolower, nicht strtolower: Letzteres laesst Umlaute
        // unveraendert, wodurch "Übergeordnetes Produkt" nie auf die
        // Kleinschreibung in GUESS traf.
        $normalized = array_map(
            static fn( $h ) => mb_strtolower( trim( (string) $h ), 'UTF-8' ),
            $headers
        );

        $map = [];
        foreach ( array_merge( array_keys( self::FIELDS ), self::STRUCTURE ) as $field ) {
            $map[ $field ] = -1;
            foreach ( self::GUESS[ $field ] as $needle ) {
                $hit = array_search( $needle, $normalized, true );
                if ( $hit !== false ) {
                    $map[ $field ] = (int) $hit;
                    break;
                }
            }
        }

        return $map;
    }

    private static function sniff_delimiter( string $line ): string {
        $best  = ',';
        $count = 0;
        foreach ( [ ',', ';', "\t", '|' ] as $candidate ) {
            $n = substr_count( $line, $candidate );
            if ( $n > $count ) {
                $count = $n;
                $best  = $candidate;
            }
        }

        return $best;
    }

    /**
     * BOM entfernen und nach UTF-8 bringen.
     *
     * Exporte aus aelteren Shops kommen oft als ISO-8859-1; ohne Umwandlung
     * landen Umlaute als Fragezeichen im Inserat.
     */
    private static function clean( $value ): string {
        $value = (string) $value;
        $value = str_replace( "\xEF\xBB\xBF", '', $value );

        if ( ! mb_check_encoding( $value, 'UTF-8' ) ) {
            $value = mb_convert_encoding( $value, 'UTF-8', 'ISO-8859-1' );
        }

        /*
         * Manche Exporte tragen den Zeilenumbruch als die zwei Zeichen
         * Backslash und n statt als echten Umbruch — im Beispielexport steht
         * beides gemischt in derselben Beschreibung. Unbehandelt steht im
         * Inserat spaeter ein einzelnes "n" in der Zeile.
         */
        $value = str_replace( [ '\\r\\n', '\\n', '\\r' ], "\n", $value );

        return trim( $value );
    }
}
