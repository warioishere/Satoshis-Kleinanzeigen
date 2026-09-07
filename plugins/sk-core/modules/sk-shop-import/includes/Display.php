<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Variants and fiat price on the product page.
 */
class Display {

    public function __construct() {
        add_action( 'woocommerce_single_product_summary', [ $this, 'variants' ], 12 );
        add_filter( 'woocommerce_get_price_html', [ $this, 'price_html' ], 100, 2 );
    }

    /**
     * Variants as a list — no select field, no cart.
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

            $label = '';

            if ( $price !== null && $price > 0 ) {
                $label = self::format_fiat( (float) $price, (string) ( $variant['currency'] ?? 'EUR' ) );
            } elseif ( ! empty( $variant['sats'] ) ) {
                // Priced in Sats: there's no fiat amount then, and without
                // this branch the variant would show up with no price at all.
                $label = sprintf(
                    /* translators: %s: amount in sats */
                    __( '%s Sats', 'sk-core' ),
                    number_format_i18n( (int) $variant['sats'] )
                );
            }

            if ( $label !== '' ) {
                echo ' <span class="sk-variants__price">' . esc_html( $label ) . '</span>';
            }

            echo '</li>';
        }

        echo '</ul></div>';
    }

    /**
     * Prefer the stored fiat price.
     *
     * Without this, the converter shows the back-converted amount — 169 €
     * would then become 168.97 €, which looks like an error rather than a
     * price.
     */
    public function price_html( $html, $product ) {
        if ( ! $product instanceof \WC_Product ) {
            return $html;
        }

        // Only on the listing page itself. In tiles and sliders, this
        // suffix would otherwise trail every price and clutter the row —
        // there, the Sats amount is what counts, the fiat reference
        // belongs on the detail page.
        if ( ! is_singular( 'product' ) || $product->get_id() !== get_queried_object_id() ) {
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
