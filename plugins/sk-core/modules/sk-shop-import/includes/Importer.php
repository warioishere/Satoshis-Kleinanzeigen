<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Erzeugt Inserate aus CSV-Zeilen.
 */
final class Importer {

    /** Kennzeichnet ein importiertes Inserat. */
    const META_IMPORTED = '_sk_imported';

    /** Schluessel zum Wiederfinden: Verkaeufer plus Artikelnummer. */
    const META_KEY = '_sk_import_key';

    const META_SOURCE   = '_sk_import_source';
    const META_RUN      = '_sk_import_run';
    const META_FIAT     = '_sk_fiat_price';
    const META_CURRENCY = '_sk_fiat_currency';

    /** Kennzeichnet ein Haendlerinserat, zur spaeteren Trennung von Privat. */
    const META_DEALER = '_sk_dealer_listing';

    /** Ausfuehrungen eines Produkts, nur zur Anzeige. */
    const META_VARIANTS = '_sk_variants';

    /** Preis ist ein "ab"-Preis, weil er aus Varianten stammt. */
    const META_FROM = '_sk_price_from';

    /**
     * Bilder je Produkt. Mehr als eine Handvoll sieht sich ohnehin niemand an,
     * und jedes Bild ist eine Datei auf der Platte.
     */
    const IMAGES_PER_PRODUCT = 5;

    /**
     * Bilder je Durchlauf. Zweiter Deckel, damit ein Katalog mit 200 Artikeln
     * nicht tausend Dateien nachlaedt.
     */
    const DEFAULT_IMAGE_CAP = 60;

    /**
     * @param array $items Von Catalog::build() aufbereitete Inserate
     * @param array $args  vendor_id, currency, default_cat, category_map, image_cap, status
     *
     * @return array{created:int,updated:int,skipped:int,images:int,errors:array}
     */
    public static function run( array $items, array $args ): array {
        $vendor_id = (int) ( $args['vendor_id'] ?? 0 );
        $currency  = strtoupper( (string) ( $args['currency'] ?? 'EUR' ) );
        $image_cap = (int) ( $args['image_cap'] ?? self::DEFAULT_IMAGE_CAP );
        $status    = in_array( $args['status'] ?? 'publish', [ 'publish', 'draft' ], true ) ? $args['status'] : 'publish';
        $cat_map   = (array) ( $args['category_map'] ?? [] );
        $default   = (int) ( $args['default_cat'] ?? 0 );
        $source    = (string) ( $args['source'] ?? '' );
        $run_id    = (string) ( $args['run_id'] ?? uniqid( 'imp', false ) );

        $result = [ 'created' => 0, 'updated' => 0, 'skipped' => 0, 'images' => 0, 'errors' => [] ];

        if ( $vendor_id <= 0 ) {
            $result['errors'][] = __( 'Kein Verkäufer angegeben.', 'sk-core' );
            return $result;
        }

        $geo = self::vendor_location( $vendor_id );

        // Auto-Poster pausieren, sonst geht der ganze Katalog als Einzelposts
        // in Telegram, Nostr und den Community-Feed.
        Silence::start();

        foreach ( $items as $index => $item ) {
            $name = (string) ( $item['name'] ?? '' );
            if ( $name === '' ) {
                $result['skipped']++;
                continue;
            }

            $sku = (string) ( $item['sku'] ?? '' );
            // Ohne Artikelnummer waere ein zweiter Import ein Duplikat; der
            // Titel ist dann der stabilste Ersatz.
            $key = $vendor_id . ':' . ( $sku !== '' ? $sku : md5( $name ) );

            $existing = self::find_by_key( $key );

            // Was im Shop privat steht, wird hier nicht oeffentlich.
            $row_status = ! empty( $item['draft'] ) ? 'draft' : $status;

            $postarr = [
                'post_type'    => 'product',
                'post_status'  => $row_status,
                'post_title'   => $name,
                'post_content' => (string) ( $item['description'] ?? '' ),
                'post_excerpt' => (string) ( $item['short'] ?? '' ),
                'post_author'  => $vendor_id,
            ];

            if ( $existing ) {
                $postarr['ID'] = $existing;
                $post_id       = wp_update_post( $postarr, true );
            } else {
                $post_id = wp_insert_post( $postarr, true );
            }

            if ( is_wp_error( $post_id ) || ! $post_id ) {
                $result['errors'][] = sprintf(
                    /* translators: 1: row number, 2: error */
                    __( 'Zeile %1$d: %2$s', 'sk-core' ),
                    (int) $index + 2,
                    is_wp_error( $post_id ) ? $post_id->get_error_message() : __( 'unbekannter Fehler', 'sk-core' )
                );
                continue;
            }

            $post_id = (int) $post_id;
            $existing ? $result['updated']++ : $result['created']++;

            self::apply_price( $post_id, (string) ( $item['price'] ?? '' ), $currency, $result );
            self::apply_categories( $post_id, (string) ( $item['categories'] ?? '' ), $cat_map, $default );
            self::apply_variants( $post_id, (array) ( $item['variants'] ?? [] ), $currency, ! empty( $item['from'] ) );

            if ( $result['images'] < $image_cap ) {
                $budget            = min( self::IMAGES_PER_PRODUCT, $image_cap - $result['images'] );
                $result['images'] += self::apply_images( $post_id, (string) ( $item['images'] ?? '' ), $budget );
            }

            foreach ( $geo as $meta_key => $value ) {
                if ( $value !== '' ) {
                    update_post_meta( $post_id, $meta_key, $value );
                }
            }

            update_post_meta( $post_id, self::META_IMPORTED, 1 );
            update_post_meta( $post_id, self::META_DEALER, 1 );
            update_post_meta( $post_id, self::META_KEY, $key );
            update_post_meta( $post_id, self::META_RUN, $run_id );
            if ( $source !== '' ) {
                update_post_meta( $post_id, self::META_SOURCE, $source );
            }
        }

        Silence::stop();

        update_user_meta( $vendor_id, Dealer::META_LAST_RUN, time() );

        return $result;
    }

    /**
     * Ausfuehrungen ablegen.
     *
     * Heute nur Anzeige: Die Variantenauswahl steckt bei WooCommerce im
     * Warenkorb-Formular, und das ist im Katalogmodus abgeschaltet.
     *
     * Der Datensatz ist aber schon so geschnitten, dass sk_payments spaeter
     * daran andocken kann, ohne dass etwas umgebaut werden muss: Jede
     * Ausfuehrung traegt einen stabilen Schluessel (fuer die Auswahl beim
     * Sofortkauf) und ihren eigenen Sats-Betrag (fuer die Rechnung). Ohne
     * beides muesste man beim Aktivieren des Kaufs von vorn anfangen.
     */
    private static function apply_variants( int $post_id, array $variants, string $currency, bool $from ): void {
        if ( empty( $variants ) ) {
            delete_post_meta( $post_id, self::META_VARIANTS );
            delete_post_meta( $post_id, self::META_FROM );
            return;
        }

        $clean = [];
        foreach ( $variants as $variant ) {
            $label = trim( (string) ( $variant['name'] ?? '' ) );
            if ( $label === '' ) {
                continue;
            }

            $fiat = self::parse_price( (string) ( $variant['price'] ?? '' ) );
            $sats = null;

            if ( $fiat !== null && $fiat > 0 ) {
                $converted = Rate::to_sats( $fiat, $currency );
                $sats      = is_wp_error( $converted ) ? null : (int) $converted;
            }

            $sku = trim( (string) ( $variant['sku'] ?? '' ) );

            $clean[] = [
                // Artikelnummer wenn vorhanden, sonst aus dem Namen abgeleitet —
                // in beiden Faellen ueber Importe hinweg stabil.
                'key'      => $sku !== '' ? $sku : substr( md5( $label ), 0, 12 ),
                'name'     => $label,
                'price'    => $fiat,
                'currency' => $currency,
                'sats'     => $sats,
            ];
        }

        if ( empty( $clean ) ) {
            return;
        }

        update_post_meta( $post_id, self::META_VARIANTS, $clean );
        update_post_meta( $post_id, self::META_FROM, $from ? 1 : 0 );
    }

    private static function find_by_key( string $key ): int {
        $found = get_posts(
            [
                'post_type'      => 'product',
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_key'       => self::META_KEY,
                'meta_value'     => $key,
                'no_found_rows'  => true,
            ]
        );

        return $found ? (int) $found[0] : 0;
    }

    /**
     * Preis in Sats umrechnen, den Fiatbetrag aber behalten.
     *
     * Nur den Sats-Preis zu speichern hiesse, dass er mit jedem Kurssprung
     * falscher wird; mit dem Ausgangsbetrag laesst er sich jederzeit neu
     * berechnen.
     */
    private static function apply_price( int $post_id, string $raw, string $currency, array &$result ): void {
        $value = self::parse_price( $raw );
        if ( $value === null ) {
            return;
        }

        update_post_meta( $post_id, self::META_FIAT, $value );
        update_post_meta( $post_id, self::META_CURRENCY, $currency );

        $sats = Rate::to_sats( $value, $currency );
        if ( is_wp_error( $sats ) ) {
            $result['errors'][] = $sats->get_error_message();
            return;
        }

        update_post_meta( $post_id, '_regular_price', (string) $sats );
        update_post_meta( $post_id, '_price', (string) $sats );
    }

    /**
     * "1.234,56" und "1,234.56" sollen beide funktionieren.
     */
    public static function parse_price( string $raw ): ?float {
        $raw = trim( preg_replace( '/[^\d.,\-]/', '', $raw ) ?? '' );
        if ( $raw === '' ) {
            return null;
        }

        $last_comma = strrpos( $raw, ',' );
        $last_dot   = strrpos( $raw, '.' );

        if ( $last_comma !== false && ( $last_dot === false || $last_comma > $last_dot ) ) {
            // Deutsches Format: Punkt ist Tausendertrenner.
            $raw = str_replace( '.', '', $raw );
            $raw = str_replace( ',', '.', $raw );
        } else {
            $raw = str_replace( ',', '', $raw );
        }

        return is_numeric( $raw ) ? (float) $raw : null;
    }

    private static function apply_categories( int $post_id, string $raw, array $map, int $default ): void {
        $ids = [];

        foreach ( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) as $name ) {
            // WooCommerce exportiert Unterkategorien als "Eltern > Kind".
            // array_pop braucht eine Variable, kein Funktionsergebnis.
            $parts = explode( '>', $name );
            $leaf  = trim( (string) end( $parts ) );
            $key  = strtolower( $leaf );

            if ( isset( $map[ $key ] ) && (int) $map[ $key ] > 0 ) {
                $ids[] = (int) $map[ $key ];
            }
        }

        if ( empty( $ids ) && $default > 0 ) {
            $ids[] = $default;
        }

        if ( ! empty( $ids ) ) {
            wp_set_object_terms( $post_id, array_unique( $ids ), 'product_cat' );
        }
    }

    /**
     * Bilder laden. Das erste wird Beitragsbild, weitere in die Galerie.
     */
    private static function apply_images( int $post_id, string $raw, int $budget ): int {
        if ( $raw === '' || $budget <= 0 ) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $urls    = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
        $loaded  = 0;
        $gallery = [];

        foreach ( $urls as $url ) {
            if ( $loaded >= $budget ) {
                break;
            }
            if ( ! wp_http_validate_url( $url ) ) {
                continue;
            }

            $attachment_id = media_sideload_image( $url, $post_id, null, 'id' );
            if ( is_wp_error( $attachment_id ) ) {
                continue;
            }

            $loaded++;
            if ( ! has_post_thumbnail( $post_id ) ) {
                set_post_thumbnail( $post_id, $attachment_id );
            } else {
                $gallery[] = (int) $attachment_id;
            }
        }

        if ( ! empty( $gallery ) ) {
            update_post_meta( $post_id, '_product_image_gallery', implode( ',', $gallery ) );
        }

        return $loaded;
    }

    /**
     * Standort des Verkaeufers auf das Inserat uebernehmen — ohne Koordinaten
     * taucht es in der Umkreissuche nicht auf.
     */
    private static function vendor_location( int $vendor_id ): array {
        return [
            'sk_geo_latitude'  => (string) get_user_meta( $vendor_id, 'sk_geo_latitude', true ),
            'sk_geo_longitude' => (string) get_user_meta( $vendor_id, 'sk_geo_longitude', true ),
            'sk_geo_address'   => (string) get_user_meta( $vendor_id, 'sk_geo_address', true ),
            'sk_geo_public'    => '1',
        ];
    }
}
