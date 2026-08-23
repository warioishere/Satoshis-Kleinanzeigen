<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Wer darf einen Shop-Katalog hochladen.
 *
 * Bewusst eine Freigabe je Verkäufer statt einer offenen Funktion für alle
 * 480 Konten: Ein Katalogimport erzeugt hunderte Inserate, und wer das darf,
 * soll eine bewusste Entscheidung sein.
 */
final class Dealer {

    const META_ENABLED  = '_sk_dealer_import';
    const META_SHOP_URL = '_sk_dealer_shop_url';
    const META_LAST_RUN = '_sk_dealer_last_import';

    /**
     * Vom Betreiber geprueft.
     *
     * Bewusst getrennt von der Importfreigabe: "darf einen Katalog hochladen"
     * und "ist geprueft" sind zwei Aussagen. Die zweite ist die, an der spaeter
     * der Sofortkauf ueber sk_payments haengen soll — dort zahlt der Kaeufer
     * direkt in die Wallet des Verkaeufers, ohne Treuhand, weshalb ungepruefte
     * Verkaeufer dort nichts verloren haben. Ein gemeinsames Flag muesste man
     * dafuer spaeter wieder auseinandernehmen.
     */
    const META_VERIFIED    = '_sk_vendor_verified';
    const META_VERIFIED_AT = '_sk_vendor_verified_at';
    const META_VERIFIED_BY = '_sk_vendor_verified_by';

    public static function is_enabled( int $user_id ): bool {
        return (int) get_user_meta( $user_id, self::META_ENABLED, true ) === 1;
    }

    public static function set_enabled( int $user_id, bool $on ): void {
        update_user_meta( $user_id, self::META_ENABLED, $on ? 1 : 0 );
    }

    public static function is_verified( int $user_id ): bool {
        return (int) get_user_meta( $user_id, self::META_VERIFIED, true ) === 1;
    }

    public static function set_verified( int $user_id, bool $on, int $by = 0 ): void {
        update_user_meta( $user_id, self::META_VERIFIED, $on ? 1 : 0 );

        if ( $on ) {
            update_user_meta( $user_id, self::META_VERIFIED_AT, time() );
            update_user_meta( $user_id, self::META_VERIFIED_BY, $by ?: get_current_user_id() );
        }
    }

    /**
     * Darf dieser Verkaeufer einen Katalog hochladen?
     *
     * Beides noetig: Ein Katalogimport erzeugt hunderte Inserate unter einem
     * Namen, der fuer echte Ware steht.
     */
    public static function may_import( int $user_id ): bool {
        return self::is_enabled( $user_id ) && self::is_verified( $user_id );
    }

    public static function shop_url( int $user_id ): string {
        return (string) get_user_meta( $user_id, self::META_SHOP_URL, true );
    }

    /**
     * Alle freigeschalteten Händler.
     *
     * @return \WP_User[]
     */
    public static function all(): array {
        return get_users(
            [
                'meta_key'   => self::META_ENABLED,
                'meta_value' => 1,
                'number'     => 200,
            ]
        );
    }

}
