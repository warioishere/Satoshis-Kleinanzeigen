<?php

namespace SK\Modules\Sponsors;

defined( 'ABSPATH' ) || exit;

/**
 * Listenpreise je Stufe.
 *
 * Der Listenpreis ist das, was ein Platz kosten soll — er wird dem Sponsor im
 * Portal als Voreinstellung angeboten und im Admin als Soll angezeigt. Die
 * tatsächlich vereinbarte Rate steht am Sponsor selbst
 * (PostType::META_MONTHLY) und geht immer vor.
 *
 * Bewusst getrennt: Ein Sponsor mit Rate 0 zahlt nichts und läuft nie ab,
 * auch wenn für seine Stufe ein Listenpreis hinterlegt ist. Würde der
 * Listenpreis automatisch greifen, bekämen alle Bestandssponsoren beim
 * Einschalten der Abrechnung eine Rate und verlören mangels Guthaben sofort
 * ihren Platz.
 */
final class Pricing {

    const OPTION_TOP      = 'sk_sponsors_rate_top';
    const OPTION_STANDARD = 'sk_sponsors_rate_standard';

    const DEFAULT_TOP      = 25000;
    const DEFAULT_STANDARD = 8000;

    /**
     * Listenpreis einer Stufe in Sats.
     */
    public static function list_price( string $tier ): int {
        if ( $tier === PostType::TIER_TOP ) {
            return max( 0, (int) get_option( self::OPTION_TOP, self::DEFAULT_TOP ) );
        }

        return max( 0, (int) get_option( self::OPTION_STANDARD, self::DEFAULT_STANDARD ) );
    }

    public static function set_list_price( string $tier, int $sats ): void {
        $sats = max( 0, $sats );
        update_option( $tier === PostType::TIER_TOP ? self::OPTION_TOP : self::OPTION_STANDARD, $sats );
    }

    /**
     * Was für diesen Sponsor gilt: seine eigene Rate, sonst der Listenpreis.
     *
     * Für das Portal gedacht — dort braucht es immer einen Betrag, auch wenn
     * der Sponsor bisher nichts zahlt.
     */
    public static function effective_rate( int $sponsor_id ): int {
        $own = (int) get_post_meta( $sponsor_id, PostType::META_MONTHLY, true );
        if ( $own > 0 ) {
            return $own;
        }

        return self::list_price( PostType::get_tier( $sponsor_id ) );
    }

    /**
     * Listenpreis auf alle Sponsoren einer Stufe übertragen.
     *
     * Nur auf ausdrücklichen Knopfdruck: das macht aus Gratisplätzen
     * zahlungspflichtige.
     *
     * @return int Zahl der geänderten Sponsoren
     */
    public static function apply_to_tier( string $tier, bool $overwrite_existing = false ): int {
        $price = self::list_price( $tier );
        $count = 0;

        $sponsors = get_posts(
            [
                'post_type'      => PostType::POST_TYPE,
                'post_status'    => [ 'publish', 'draft' ],
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ]
        );

        foreach ( $sponsors as $id ) {
            if ( PostType::get_tier( (int) $id ) !== $tier ) {
                continue;
            }

            $own = (int) get_post_meta( $id, PostType::META_MONTHLY, true );
            if ( $own > 0 && ! $overwrite_existing ) {
                continue;
            }
            if ( $own === $price ) {
                continue;
            }

            update_post_meta( $id, PostType::META_MONTHLY, $price );
            $count++;
        }

        return $count;
    }
}
