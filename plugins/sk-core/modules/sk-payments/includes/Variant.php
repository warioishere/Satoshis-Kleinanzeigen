<?php

namespace SK\Modules\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Ausführungen eines Inserats beim Sofortkauf.
 *
 * Der Käufer schickt nur den Schlüssel seiner Wahl, nie einen Betrag: Der
 * Preis wird hier aus dem Inserat nachgeschlagen. Sonst liesse sich jede
 * Ausführung für einen Satoshi bestellen.
 *
 * Die Daten kommen aus dem Import-Modul. Ist es abgeschaltet, gibt es keine
 * Ausführungen und alles verhält sich wie zuvor.
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

        // Ohne eigenen Sats-Betrag ist eine Ausfuehrung nicht bestellbar.
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
     * Preis der gewählten Ausführung, sonst der Inseratspreis.
     */
    public static function price( \WC_Product $product, string $key ): int {
        $variant = self::find( $product->get_id(), $key );

        return $variant ? (int) $variant['sats'] : (int) $product->get_price();
    }

    /**
     * Bezeichnung des Inserats, um die Ausführung ergänzt — damit im Chat und
     * auf der Zahlungskarte steht, was tatsächlich bestellt wurde.
     */
    public static function title( \WC_Product $product, string $key ): string {
        $variant = self::find( $product->get_id(), $key );

        if ( ! $variant ) {
            return $product->get_name();
        }

        return $product->get_name() . ' — ' . $variant['name'];
    }

    /**
     * Schlüssel aus der Anfrage, roh und ungeprüft — die Prüfung macht find().
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
