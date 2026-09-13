<?php

namespace SK\Modules\Subscription;

defined( 'ABSPATH' ) || exit;

/**
 * One package, several terms.
 *
 * A pack product carries its own term, listing limit and price, and
 * everything downstream — assignment, expiry, renewal, cancellation —
 * reads them from the product that was bought. Terms are therefore not a
 * property of one product but a set of sibling products sharing a group.
 *
 * The subscription page shows one card per group: the shortest term is the
 * card, the siblings become the buttons on it.
 */
final class Durations {

    /** Post meta: the group a pack belongs to, e.g. "krabbe". */
    const META_GROUP = '_sk_pack_group';

    /** Post meta: the term in months. */
    const META_MONTHS = '_sk_pack_months';

    public static function group( int $pack_id ): string {
        return $pack_id > 0 ? (string) get_post_meta( $pack_id, self::META_GROUP, true ) : '';
    }

    public static function months( int $pack_id ): int {
        return $pack_id > 0 ? (int) get_post_meta( $pack_id, self::META_MONTHS, true ) : 0;
    }

    /**
     * All packs of a group, shortest term first.
     *
     * @return int[]
     */
    public static function siblings( string $group ): array {
        if ( '' === $group ) {
            return [];
        }

        $ids = get_posts(
            [
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_key'       => self::META_GROUP, // phpcs:ignore WordPress.DB.SlowDBQuery
                'meta_value'     => $group,           // phpcs:ignore WordPress.DB.SlowDBQuery
            ]
        );

        $ids = array_map( 'intval', $ids );

        usort( $ids, static fn( $a, $b ) => self::months( $a ) <=> self::months( $b ) );

        return $ids;
    }

    /**
     * Is this the pack that represents its group on the subscription page?
     *
     * A pack without a group represents itself, so packages that have only
     * one term keep working untouched.
     */
    public static function is_card( int $pack_id ): bool {
        $group = self::group( $pack_id );

        if ( '' === $group ) {
            return true;
        }

        $siblings = self::siblings( $group );

        return ! empty( $siblings ) && $siblings[0] === $pack_id;
    }

    /**
     * The terms to offer on a card: id, months, price and label.
     *
     * @return array<int, array{id:int,months:int,price:int,label:string,saving:int}>
     */
    public static function options( int $pack_id ): array {
        $siblings = self::siblings( self::group( $pack_id ) );

        if ( count( $siblings ) < 2 ) {
            return [];
        }

        $monthly = 0;
        $out     = [];

        foreach ( $siblings as $id ) {
            $months = max( 1, self::months( $id ) );
            $price  = (int) get_post_meta( $id, '_price', true );

            if ( 1 === $months ) {
                $monthly = $price;
            }

            $out[] = [
                'id'     => $id,
                'months' => $months,
                'price'  => $price,
                'label'  => 1 === $months
                    ? __( '1 Monat', 'sk-core' )
                    : sprintf( __( '%d Monate', 'sk-core' ), $months ),
                'saving' => 0,
            ];
        }

        if ( $monthly > 0 ) {
            foreach ( $out as $i => $option ) {
                $full = $monthly * $option['months'];
                $out[ $i ]['saving'] = $full > 0 ? (int) round( ( 1 - $option['price'] / $full ) * 100 ) : 0;
            }
        }

        return $out;
    }

    /**
     * Does the vendor hold this pack or one of its siblings?
     */
    public static function holds_group( int $vendor_id, int $pack_id ): bool {
        $held = (int) get_user_meta( $vendor_id, 'product_package_id', true );

        if ( $held <= 0 ) {
            return false;
        }

        if ( $held === $pack_id ) {
            return true;
        }

        $group = self::group( $pack_id );

        return '' !== $group && in_array( $held, self::siblings( $group ), true );
    }
}
