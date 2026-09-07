<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Read a CSV file and guess its columns.
 *
 * Deliberately a custom reader instead of WC_Product_CSV_Importer: that one
 * is tailored to the WP-admin import wizard and brings a large error
 * surface with it. Here we need control over who owns the listings, how
 * prices get converted to Sats, and how many images get loaded.
 *
 * Column names depend on the language of the exporting shop — so they're
 * guessed and the mapping is then shown, rather than relying on fixed
 * names.
 */
final class Csv {

    /** Which fields the target knows. */
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
     * Columns that describe the structure rather than the content. They
     * aren't mapped but guessed — without them, variations couldn't be told
     * apart from standalone products.
     */
    const STRUCTURE = [ 'type', 'parent', 'published', 'id' ];

    /**
     * Common column names from WooCommerce exports, German and English.
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
     * Read the file.
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
            // Skip empty rows at the end of the file.
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
     * Guess the mapping: field => column index, -1 if nothing matches.
     *
     * @return array<string,int>
     */
    public static function guess_mapping( array $headers ): array {
        // mb_strtolower, not strtolower: the latter leaves umlauts
        // unchanged, which meant "Übergeordnetes Produkt" would never match
        // the lowercase entries in GUESS.
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
     * Strip the BOM and convert to UTF-8.
     *
     * Exports from older shops often arrive as ISO-8859-1; without
     * conversion, umlauts end up as question marks in the listing.
     */
    private static function clean( $value ): string {
        $value = (string) $value;
        $value = str_replace( "\xEF\xBB\xBF", '', $value );

        if ( ! mb_check_encoding( $value, 'UTF-8' ) ) {
            $value = mb_convert_encoding( $value, 'UTF-8', 'ISO-8859-1' );
        }

        /*
         * Some exports carry line breaks as the two characters backslash and
         * n instead of an actual newline — in the sample export, both forms
         * appear mixed within the same description. Left unhandled, a
         * stray "n" would later show up in the listing's text.
         */
        $value = str_replace( [ '\\r\\n', '\\n', '\\r' ], "\n", $value );

        return trim( $value );
    }
}
