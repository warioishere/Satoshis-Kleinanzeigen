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
     * Zwei Wege dorthin. Der eine geht ohne Zutun des Betreibers: wer eine
     * Adresse per Ruecklink bestaetigt hat, darf importieren — was er
     * einstellt, begrenzt ohnehin sein Paket. Der andere bleibt der bisherige
     * Weg von Hand; er wird gebraucht, weil sich nicht jede Seite von diesem
     * Server aus abrufen laesst (siehe VerifiedLinks).
     *
     * Ausdruecklich NICHT dasselbe wie is_verified(): eine bestaetigte Domain
     * beweist Kontrolle ueber eine Domain, nicht dass jemand ein redlicher
     * Haendler ist. Am Haekchen "geprueft" haengt spaeter der Sofortkauf, bei
     * dem Geld ohne Treuhand fliesst — das bleibt eine Entscheidung.
     */
    public static function may_import( int $user_id ): bool {
        if ( \SK\Core\Verification\VerifiedLinks::is_verified( $user_id ) ) {
            return true;
        }

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
    /**
     * Alle Händler — freigeschaltete und selbst bestätigte.
     *
     * Wer seine Domain bestätigt hat, darf importieren, ohne dass der
     * Betreiber ein Häkchen setzt. Stünde er nicht in dieser Liste, sähe es
     * im Admin aus, als sei nichts geschehen — und niemand käme darauf, ihn
     * auch noch zu prüfen.
     *
     * @return \WP_User[]
     */
    public static function all(): array {
        $freigeschaltet = get_users(
            [
                'meta_key'   => self::META_ENABLED,
                'meta_value' => 1,
                'number'     => 200,
            ]
        );

        $bestaetigt = get_users(
            [
                'meta_key'     => \SK\Core\Verification\VerifiedLinks::META_UNTIL,
                'meta_value'   => time(),
                'meta_compare' => '>',
                'meta_type'    => 'NUMERIC',
                'number'       => 200,
            ]
        );

        $alle = [];

        foreach ( array_merge( $freigeschaltet, $bestaetigt ) as $user ) {
            $alle[ $user->ID ] = $user;
        }

        return array_values( $alle );
    }

}
