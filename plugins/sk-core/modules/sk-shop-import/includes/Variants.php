<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Ausführungen eines Inserats — bearbeitbar ab einem grösseren Paket.
 *
 * Bewusst keine WooCommerce-Varianten: Deren Auswahl steckt im
 * Warenkorb-Formular, und das ist im Katalogmodus abgeschaltet. Was der
 * Käufer braucht, ist die Information „gibt es in drei Ausführungen, ab
 * 169 Franken" — kaufen kann er ohnehin nicht über den Warenkorb, er meldet
 * sich beim Verkäufer.
 *
 * Der Datensatz ist derselbe, den auch der Import schreibt, samt Schlüssel
 * und eigenem Sats-Betrag je Ausführung.
 */
final class Variants {

    /** Pakete, die Ausführungen erlauben. Leer = aus der Paketgrösse abgeleitet. */
    const OPTION_PACKS = 'sk_variants_packs';

    /** Ab dieser Inseratszahl gilt ein Paket als gross genug (Delphin: 21). */
    const DEFAULT_MIN_PRODUCTS = 21;

    public function __construct() {
        add_action( 'sk_product_edit_after_pricing_fields', [ $this, 'render_field' ], 10, 2 );
        add_action( 'sk_process_product_meta', [ $this, 'save' ], 20 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 20 );
    }

    /**
     * Pakete, die Ausführungen erlauben.
     *
     * @return int[]
     */
    public static function allowed_packs(): array {
        $stored = get_option( self::OPTION_PACKS, [] );
        if ( is_array( $stored ) && ! empty( $stored ) ) {
            return array_map( 'intval', $stored );
        }

        global $wpdb;

        $ids   = $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_pack_validity'" );
        $packs = [];

        foreach ( $ids as $id ) {
            if ( (int) get_post_meta( $id, '_no_of_product', true ) >= self::DEFAULT_MIN_PRODUCTS ) {
                $packs[] = (int) $id;
            }
        }

        return $packs;
    }

    /**
     * Erlaubt dieses Paket Ausfuehrungen? Fuer die Paketkarte im Abo-Bereich.
     */
    public static function pack_allows( int $pack_id ): bool {
        return $pack_id > 0 && in_array( $pack_id, self::allowed_packs(), true );
    }

    public static function is_allowed( int $vendor_id = 0 ): bool {
        $vendor_id = $vendor_id ?: get_current_user_id();
        if ( ! $vendor_id ) {
            return false;
        }

        return self::pack_allows( (int) get_user_meta( $vendor_id, 'product_package_id', true ) );
    }

    /**
     * Name des günstigsten Pakets, das Ausführungen erlaubt — für den Hinweis.
     */
    public static function cheapest_allowed_pack(): ?array {
        $best = null;

        foreach ( self::allowed_packs() as $id ) {
            $post = get_post( $id );
            if ( ! $post || $post->post_status !== 'publish' ) {
                continue;
            }
            $price = (int) get_post_meta( $id, '_price', true );
            if ( $best === null || $price < $best['price'] ) {
                $best = [ 'id' => $id, 'name' => $post->post_title, 'price' => $price ];
            }
        }

        return $best;
    }

    /**
     * @return array<int,array{key:string,name:string,price:?float,currency:string,sats:?int}>
     */
    public static function get( int $post_id ): array {
        $variants = get_post_meta( $post_id, Importer::META_VARIANTS, true );

        return is_array( $variants ) ? $variants : [];
    }

    public function enqueue(): void {
        if ( ! function_exists( 'sk_is_seller_dashboard' ) || ! sk_is_seller_dashboard() ) {
            return;
        }

        wp_enqueue_script(
            'sk-variants',
            SK_SHOP_IMPORT_URL . '/assets/js/sk-variants.js',
            [],
            SK_SHOP_IMPORT_VERSION,
            true
        );
    }

    public function render_field( $post = null, $post_id = 0 ): void {
        $post_id  = (int) ( $post_id ?: ( $post->ID ?? 0 ) );
        $allowed  = self::is_allowed();
        $variants = $post_id ? self::get( $post_id ) : [];
        $pack     = self::cheapest_allowed_pack();

        // Einheit des Inserats — bei importierten Artikeln ist das die
        // Waehrung aus der Datei, sonst Sats.
        $currency = PriceUnit::current( $post_id );

        include SK_SHOP_IMPORT_PATH . '/templates/variants-field.php';
    }

    /**
     * Ausführungen speichern.
     */
    public function save( $post_id ): void {
        $post_id = (int) $post_id;
        if ( ! $post_id ) {
            return;
        }

        // Ohne passendes Paket wird nichts geschrieben — und Vorhandenes
        // bleibt unangetastet, statt beim Speichern still zu verschwinden.
        if ( ! self::is_allowed() ) {
            return;
        }

        if ( ! isset( $_POST['sk_variant_name'] ) || ! is_array( $_POST['sk_variant_name'] ) ) {
            return;
        }

        $names  = array_map( 'sanitize_text_field', wp_unslash( $_POST['sk_variant_name'] ) );
        $prices = isset( $_POST['sk_variant_price'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['sk_variant_price'] ) ) : [];

        // Dieselbe Einheit wie der Inseratspreis — zwei Einheiten in einem
        // Inserat waeren nicht vergleichbar und der "ab"-Preis waere Unsinn.
        $currency = PriceUnit::posted( $post_id );

        $existing = [];
        foreach ( self::get( $post_id ) as $variant ) {
            $existing[ $variant['name'] ] = $variant['key'] ?? '';
        }

        $clean = [];
        foreach ( $names as $index => $name ) {
            $name = trim( $name );
            if ( $name === '' ) {
                continue;
            }

            $amount = Importer::parse_price( (string) ( $prices[ $index ] ?? '' ) );
            $fiat   = null;
            $sats   = null;

            if ( $amount !== null && $amount > 0 ) {
                if ( $currency === 'SATS' ) {
                    $sats = (int) round( $amount );
                } else {
                    $fiat      = $amount;
                    $converted = Rate::to_sats( $fiat, $currency );
                    $sats      = is_wp_error( $converted ) ? null : (int) $converted;
                }
            }

            $clean[] = [
                // Vorhandenen Schluessel behalten, damit spaetere Verweise —
                // etwa ein Sofortkauf ueber sk_payments — nicht ins Leere gehen.
                'key'      => $existing[ $name ] ?? substr( md5( $name ), 0, 12 ),
                'name'     => $name,
                'price'    => $fiat,
                'currency' => $currency,
                'sats'     => $sats,
            ];
        }

        if ( empty( $clean ) ) {
            delete_post_meta( $post_id, Importer::META_VARIANTS );
            delete_post_meta( $post_id, Importer::META_FROM );
            return;
        }

        update_post_meta( $post_id, Importer::META_VARIANTS, $clean );
        update_post_meta( $post_id, Importer::META_FROM, count( $clean ) > 1 ? 1 : 0 );

        // Der Inseratspreis ist der guenstigste — daher "ab".
        $lowest = null;
        foreach ( $clean as $variant ) {
            if ( $variant['sats'] === null || $variant['sats'] <= 0 ) {
                continue;
            }
            if ( $lowest === null || $variant['sats'] < $lowest['sats'] ) {
                $lowest = $variant;
            }
        }

        if ( $lowest === null ) {
            return;
        }

        // Bei Fiat denselben Betrag hinterlegen wie der Import: daran haengen
        // der Klammerzusatz auf der Produktseite und die taegliche
        // Kursnachfuehrung. Ohne ihn stuende der Sats-Preis handgepflegter
        // Inserate still, waehrend importierte mitwandern.
        if ( $lowest['price'] !== null ) {
            update_post_meta( $post_id, Importer::META_FIAT, $lowest['price'] );
            update_post_meta( $post_id, Importer::META_CURRENCY, $currency );
        }

        $product = wc_get_product( $post_id );
        if ( $product ) {
            $product->set_regular_price( (string) $lowest['sats'] );
            $product->set_price( (string) $lowest['sats'] );
            $product->save();
        }
    }
}
