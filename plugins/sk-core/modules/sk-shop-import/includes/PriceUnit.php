<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * A listing's pricing unit — Sats or fiat.
 *
 * A shop thinks in francs, not Sats. Whoever maintains their catalog here
 * should be able to enter the price they also charge in their own store;
 * the Sats amount is then a derived value, refreshed daily against the
 * exchange rate.
 *
 * The exact same meta fields as at import time, so there's only one
 * mechanism: if a fiat amount is stored, PriceRefresh takes over. Switch
 * back to Sats and it disappears, leaving the entered amount as-is.
 */
final class PriceUnit {

    /** @var string[] */
    const UNITS = [ 'SATS', 'EUR', 'CHF' ];

    public function __construct() {
        // Before Variants (20): there, the price can be overridden again
        // by the cheapest variant.
        add_action( 'sk_process_product_meta', [ $this, 'save' ], 15 );
    }

    public static function is_allowed( int $vendor_id = 0 ): bool {
        return Variants::is_allowed( $vendor_id );
    }

    /**
     * The unit this listing is stored in.
     */
    public static function current( int $post_id ): string {
        $currency = strtoupper( (string) get_post_meta( $post_id, Importer::META_CURRENCY, true ) );

        return in_array( $currency, [ 'EUR', 'CHF' ], true ) ? $currency : 'SATS';
    }

    /**
     * The unit from the submitted form, otherwise the stored state.
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
     * What the price field should show.
     *
     * For fiat, the fiat amount — if the Sats price were shown there
     * instead, saving without any change would suddenly turn 80,546 Sats
     * into 80,546 francs.
     *
     * @return string|null null = field fills itself from meta as usual
     */
    public static function input_value( int $post_id ) {
        if ( self::current( $post_id ) === 'SATS' ) {
            return null;
        }

        $fiat = get_post_meta( $post_id, Importer::META_FIAT, true );

        return ( $fiat === '' || $fiat === false ) ? null : $fiat;
    }

    /**
     * The unit dropdown prepended to the price field.
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

        // The core just saved the typed amount as if it were Sats — here
        // it gets replaced with the converted value.
        $product = wc_get_product( $post_id );
        if ( $product ) {
            $product->set_regular_price( (string) (int) $sats );
            $product->set_price( (string) (int) $sats );
            $product->save();
        }
    }
}
