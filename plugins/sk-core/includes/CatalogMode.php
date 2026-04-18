<?php

namespace SK\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Globaler Katalog-Modus — versteckt plattformweit den "In den Warenkorb"-Button
 * und optional den Preis. Settings unter Admin → SK → Verkaufsoptionen.
 */
final class CatalogMode {

    public static function init(): void {
        if ( self::hide_cart() ) {
            add_filter( 'woocommerce_is_purchasable',          '__return_false', 99 );
            add_filter( 'woocommerce_product_is_visible',      [ __CLASS__, 'keep_visible' ], 10, 2 );
            remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
        }

        if ( self::hide_price() ) {
            add_filter( 'woocommerce_get_price_html', '__return_empty_string', 99 );
        }
    }

    public static function hide_cart(): bool {
        return 'on' === sk_get_option( 'catalog_mode_hide_add_to_cart_button', 'sk_selling', 'off' );
    }

    public static function hide_price(): bool {
        return self::hide_cart()
            && 'on' === sk_get_option( 'catalog_mode_hide_product_price', 'sk_selling', 'off' );
    }

    /**
     * woocommerce_is_purchasable=false macht Produkte auch unsichtbar (out-of-stock
     * check). Wir wollen sie sichtbar halten, nur den Button wegnehmen.
     */
    public static function keep_visible( $visible, $product_id ) {
        return true;
    }
}
