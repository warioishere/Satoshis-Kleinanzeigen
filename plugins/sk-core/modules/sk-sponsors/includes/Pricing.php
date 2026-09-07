<?php

namespace SK\Modules\Sponsors;

defined( 'ABSPATH' ) || exit;

/**
 * List prices per tier.
 *
 * The list price is what a placement is supposed to cost — it's offered to
 * the sponsor in the portal as a default and shown in the admin as the
 * target. The actually agreed rate lives on the sponsor itself
 * (PostType::META_MONTHLY) and always takes precedence.
 *
 * Deliberately kept separate: a sponsor with a rate of 0 pays nothing and
 * never expires, even if a list price is set for their tier. If the list
 * price applied automatically, all existing sponsors would get a rate the
 * moment billing was turned on and would instantly lose their placement for
 * lack of balance.
 */
final class Pricing {

    const OPTION_TOP      = 'sk_sponsors_rate_top';
    const OPTION_STANDARD = 'sk_sponsors_rate_standard';

    const DEFAULT_TOP      = 25000;
    const DEFAULT_STANDARD = 8000;

    /**
     * List price of a tier in sats.
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
     * What applies to this sponsor: their own rate, otherwise the list price.
     *
     * Intended for the portal — it always needs an amount there, even if the
     * sponsor hasn't paid anything so far.
     */
    public static function effective_rate( int $sponsor_id ): int {
        $own = (int) get_post_meta( $sponsor_id, PostType::META_MONTHLY, true );
        if ( $own > 0 ) {
            return $own;
        }

        return self::list_price( PostType::get_tier( $sponsor_id ) );
    }

    /**
     * Apply the list price to all sponsors of a tier.
     *
     * Only on an explicit button click: this turns free placements into
     * paying ones.
     *
     * @return int number of changed sponsors
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
