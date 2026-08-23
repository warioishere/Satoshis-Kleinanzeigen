<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Auszeichnungseinheit eines Inserats — Sats oder Fiat.
 *
 * Ein Shop denkt in Franken, nicht in Sats. Wer seinen Katalog hier pflegt,
 * soll den Preis eintragen können, den er auch im eigenen Laden verlangt; der
 * Sats-Betrag ist dann eine Ableitung und wird täglich am Kurs nachgeführt.
 *
 * Genau dieselben Metas wie beim Import, damit es nur einen Mechanismus gibt:
 * ist ein Fiatbetrag hinterlegt, greift PriceRefresh. Wird auf Sats
 * zurückgestellt, verschwindet er und der eingetragene Betrag bleibt stehen.
 */
final class PriceUnit {

    /** @var string[] */
    const UNITS = [ 'SATS', 'EUR', 'CHF' ];

    public function __construct() {
        // Vor Variants (20): dort kann der Preis aus der günstigsten
        // Ausführung noch einmal überschrieben werden.
        add_action( 'sk_process_product_meta', [ $this, 'save' ], 15 );
    }

    public static function is_allowed( int $vendor_id = 0 ): bool {
        return Variants::is_allowed( $vendor_id );
    }

    /**
     * Einheit, in der dieses Inserat gespeichert ist.
     */
    public static function current( int $post_id ): string {
        $currency = strtoupper( (string) get_post_meta( $post_id, Importer::META_CURRENCY, true ) );

        return in_array( $currency, [ 'EUR', 'CHF' ], true ) ? $currency : 'SATS';
    }

    /**
     * Einheit aus dem abgeschickten Formular, sonst der gespeicherte Stand.
     */
    public static function posted( int $post_id ): string {
        if ( isset( $_POST['sk_price_unit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
            $unit = strtoupper( sanitize_text_field( wp_unslash( $_POST['sk_price_unit'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
            if ( in_array( $unit, self::UNITS, true ) ) {
                return $unit;
            }
        }

        return self::current( $post_id );
    }

    /**
     * Was im Preisfeld stehen soll.
     *
     * Bei Fiat der Fiatbetrag — stünde dort der Sats-Preis, würde ein
     * Speichern ohne Änderung aus 80'546 Sats plötzlich 80'546 Franken machen.
     *
     * @return string|null null = Feld füllt sich wie gewohnt aus dem Meta
     */
    public static function input_value( int $post_id ) {
        if ( self::current( $post_id ) === 'SATS' ) {
            return null;
        }

        $fiat = get_post_meta( $post_id, Importer::META_FIAT, true );

        return ( $fiat === '' || $fiat === false ) ? null : $fiat;
    }

    /**
     * Vorangestellte Auswahl im Preisfeld.
     */
    public static function render_select( int $post_id ): void {
        $current = self::current( $post_id );

        include SK_SHOP_IMPORT_PATH . '/templates/price-unit-select.php';
    }

    public function save( $post_id ): void {
        $post_id = (int) $post_id;

        if ( ! $post_id || ! isset( $_POST['sk_price_unit'] ) || ! self::is_allowed() ) { // phpcs:ignore WordPress.Security.NonceVerification
            return;
        }

        $unit = self::posted( $post_id );

        if ( $unit === 'SATS' ) {
            delete_post_meta( $post_id, Importer::META_FIAT );
            delete_post_meta( $post_id, Importer::META_CURRENCY );
            return;
        }

        $fiat = Importer::parse_price( (string) wp_unslash( $_POST['_regular_price'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
        if ( $fiat === null || $fiat <= 0 ) {
            return;
        }

        update_post_meta( $post_id, Importer::META_FIAT, $fiat );
        update_post_meta( $post_id, Importer::META_CURRENCY, $unit );

        $sats = Rate::to_sats( $fiat, $unit );
        if ( is_wp_error( $sats ) ) {
            return;
        }

        // Der Kern hat den eingetippten Betrag eben als Sats gespeichert —
        // hier wird daraus der umgerechnete.
        $product = wc_get_product( $post_id );
        if ( $product ) {
            $product->set_regular_price( (string) (int) $sats );
            $product->set_price( (string) (int) $sats );
            $product->save();
        }
    }
}
