<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Ausführungen und Fiat-Preis auf der Produktseite.
 */
class Display {

    public function __construct() {
        add_action( 'woocommerce_single_product_summary', [ $this, 'variants' ], 12 );
        add_filter( 'woocommerce_get_price_html', [ $this, 'price_html' ], 100, 2 );
    }

    /**
     * Ausführungen als Liste — ohne Auswahlfeld, ohne Warenkorb.
     */
    public function variants(): void {
        global $product;

        if ( ! $product instanceof \WC_Product ) {
            return;
        }

        $variants = get_post_meta( $product->get_id(), Importer::META_VARIANTS, true );
        if ( ! is_array( $variants ) || empty( $variants ) ) {
            return;
        }

        echo '<div class="sk-variants"><strong>' . esc_html__( 'Ausführungen', 'sk-core' ) . '</strong><ul class="sk-variants__list">';

        foreach ( $variants as $variant ) {
            $name  = (string) ( $variant['name'] ?? '' );
            $price = $variant['price'] ?? null;

            echo '<li><span class="sk-variants__name">' . esc_html( $name ) . '</span>';

            if ( $price !== null && $price > 0 ) {
                echo ' <span class="sk-variants__price">'
                    . esc_html( self::format_fiat( (float) $price, (string) ( $variant['currency'] ?? 'EUR' ) ) )
                    . '</span>';
            }

            echo '</li>';
        }

        echo '</ul></div>';
    }

    /**
     * Hinterlegten Fiat-Preis bevorzugen.
     *
     * Ohne das zeigt der Umrechner den zurückgerechneten Betrag — aus 169 €
     * wird dann 168,97 €, was wie ein Fehler aussieht statt wie ein Preis.
     */
    public function price_html( $html, $product ) {
        if ( ! $product instanceof \WC_Product ) {
            return $html;
        }

        $fiat = get_post_meta( $product->get_id(), Importer::META_FIAT, true );
        if ( $fiat === '' || (float) $fiat <= 0 ) {
            return $html;
        }

        $currency = (string) get_post_meta( $product->get_id(), Importer::META_CURRENCY, true );
        $from     = (int) get_post_meta( $product->get_id(), Importer::META_FROM, true ) === 1;

        $label = self::format_fiat( (float) $fiat, $currency );

        if ( $from ) {
            /* translators: %s: price */
            $label = sprintf( __( 'ab %s', 'sk-core' ), $label );
        }

        return $html . ' <span class="sk-fiat-price">(' . esc_html( $label ) . ')</span>';
    }

    public static function format_fiat( float $amount, string $currency ): string {
        $symbol = strtoupper( $currency ) === 'CHF' ? 'CHF' : '€';

        $formatted = number_format_i18n( $amount, fmod( $amount, 1 ) === 0.0 ? 0 : 2 );

        return $symbol === '€' ? $formatted . ' €' : $symbol . ' ' . $formatted;
    }
}
