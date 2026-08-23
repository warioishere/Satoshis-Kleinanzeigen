<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Prüft vor dem Import, ob das Paket reicht.
 *
 * Händler sind bewusst NICHT vom Kontingent ausgenommen: Wer 28 Artikel
 * hochladen will, sieht damit unmittelbar, wofür ein grösseres Paket gut ist.
 * Das ist der wirksamste Verkaufsanlass, den der Import mitbringt.
 */
final class Quota {

    /**
     * Wie viele Inserate darf dieser Verkäufer noch anlegen?
     *
     * @return int|null null = unbegrenzt oder nicht ermittelbar
     */
    public static function remaining( int $vendor_id ): ?int {
        if ( ! class_exists( '\SK\Modules\Subscription\Helper' ) ) {
            return null;
        }

        $remaining = \SK\Modules\Subscription\Helper::get_vendor_remaining_products( $vendor_id );

        if ( $remaining === '' || $remaining === null || $remaining === false ) {
            return null;
        }

        // Unbegrenzte Pakete melden je nach Konfiguration einen Text.
        if ( ! is_numeric( $remaining ) ) {
            return null;
        }

        return max( 0, (int) $remaining );
    }

    /**
     * Reicht das Kontingent für so viele Inserate?
     *
     * @return array{ok:bool,remaining:?int,needed:int,missing:int}
     */
    public static function check( int $vendor_id, int $needed ): array {
        $remaining = self::remaining( $vendor_id );

        if ( $remaining === null ) {
            return [ 'ok' => true, 'remaining' => null, 'needed' => $needed, 'missing' => 0 ];
        }

        $missing = max( 0, $needed - $remaining );

        return [
            'ok'        => $missing === 0,
            'remaining' => $remaining,
            'needed'    => $needed,
            'missing'   => $missing,
        ];
    }

    /**
     * Pakete, die für so viele Inserate reichen — günstigstes zuerst.
     *
     * @return array<int,array{id:int,name:string,price:int,products:int,days:int}>
     */
    public static function packs_for( int $needed ): array {
        global $wpdb;

        $ids = $wpdb->get_col(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_pack_validity'"
        );

        $packs = [];
        foreach ( $ids as $id ) {
            $post = get_post( (int) $id );
            if ( ! $post || $post->post_status !== 'publish' ) {
                continue;
            }

            $products = (int) get_post_meta( $id, '_no_of_product', true );
            if ( $products < $needed ) {
                continue;
            }

            $packs[] = [
                'id'       => (int) $id,
                'name'     => $post->post_title,
                'price'    => (int) get_post_meta( $id, '_price', true ),
                'products' => $products,
                'days'     => (int) get_post_meta( $id, '_pack_validity', true ),
            ];
        }

        usort( $packs, static fn( $a, $b ) => $a['price'] <=> $b['price'] );

        return $packs;
    }

    /**
     * Bleiben Inserate nach Ablauf des Pakets online?
     *
     * Steuert den Hinweis im Upgrade-Dialog — und der muss stimmen, sonst ist
     * es ein Versprechen, das die Plattform nicht hält.
     */
    public static function listings_stay_online(): bool {
        $option = get_option( 'sk_product_subscription', [] );

        return ( $option['product_status_after_end'] ?? '' ) === 'publish';
    }
}
