<?php

namespace SK\Core\Product;

defined( 'ABSPATH' ) || exit;

/**
 * Variants of a listing for instant purchase.
 *
 * The buyer only sends the key of their choice, never an amount: the price
 * is looked up here from the listing. Otherwise any variant could be
 * ordered for one satoshi.
 *
 * The data comes from the import module. If it is disabled, there are no
 * variants and everything behaves as before.
 */
final class Variant {

    /**
     * @return array<int,array{key:string,name:string,price:?float,currency:string,sats:?int}>
     */
    public static function all( int $product_id ): array {
        if ( ! class_exists( \SK\Modules\ShopImport\Variants::class ) ) {
            return [];
        }

        $variants = \SK\Modules\ShopImport\Variants::get( $product_id );

        // Without its own sats amount, a variant is not orderable.
        return array_values(
            array_filter(
                $variants,
                static function ( $variant ) {
                    return ! empty( $variant['sats'] ) && ! empty( $variant['name'] );
                }
            )
        );
    }

    public static function find( int $product_id, string $key ): ?array {
        if ( $key === '' ) {
            return null;
        }

        foreach ( self::all( $product_id ) as $variant ) {
            if ( (string) ( $variant['key'] ?? '' ) === $key ) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * Price of the chosen variant, otherwise the listing price.
     */
    public static function price( \WC_Product $product, string $key ): int {
        $variant = self::find( $product->get_id(), $key );

        return $variant ? (int) $variant['sats'] : (int) $product->get_price();
    }

    /**
     * Listing title, extended with the variant — so the chat and the payment
     * card show what was actually ordered.
     */
    public static function title( \WC_Product $product, string $key ): string {
        $variant = self::find( $product->get_id(), $key );

        if ( ! $variant ) {
            return $product->get_name();
        }

        return $product->get_name() . ' — ' . $variant['name'];
    }

    /**
     * Key from the request, raw and unvalidated — find() does the validation.
     */
    public static function posted(): string {
        return isset( $_POST['variant'] ) // phpcs:ignore WordPress.Security.NonceVerification
            ? sanitize_text_field( wp_unslash( $_POST['variant'] ) ) // phpcs:ignore WordPress.Security.NonceVerification
            : '';
    }

    public static function format_sats( int $sats ): string {
        /* translators: %s: amount in sats */
        return sprintf( __( '%s Sats', 'sk-core' ), number_format_i18n( $sats ) );
    }
}
