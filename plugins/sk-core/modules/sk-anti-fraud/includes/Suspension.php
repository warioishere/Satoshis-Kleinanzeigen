<?php

namespace SK\Modules\AntiFraud;

defined( 'ABSPATH' ) || exit;

/**
 * Suspending and un-suspending vendors.
 *
 * Suspending drafts every published listing. The IDs are recorded so that
 * un-suspending only republishes what the suspension actually took offline —
 * listings the vendor had drafted themselves must stay drafted.
 */
final class Suspension {

    /** User meta: vendor is suspended. */
    const META_SUSPENDED = 'sk_auto_suspended';

    /** User meta: why. */
    const META_REASON = 'sk_auto_suspended_reason';

    /** User meta: when. */
    const META_SINCE = 'sk_auto_suspended_at';

    /** User meta: product IDs this suspension drafted. */
    const META_DRAFTED = 'sk_auto_suspended_products';

    public static function is_suspended( int $user_id ): bool {
        return (bool) get_user_meta( $user_id, self::META_SUSPENDED, true );
    }

    /**
     * Draft all published listings of a vendor and mark them suspended.
     *
     * @return int Number of listings taken offline.
     */
    public static function suspend( int $user_id, string $reason = '' ): int {
        $product_ids = get_posts( [
            'author'         => $user_id,
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        $drafted = [];

        foreach ( $product_ids as $product_id ) {
            $result = wp_update_post( [ 'ID' => (int) $product_id, 'post_status' => 'draft' ], true );

            if ( ! is_wp_error( $result ) ) {
                $drafted[] = (int) $product_id;
            }
        }

        update_user_meta( $user_id, self::META_SUSPENDED, 1 );
        update_user_meta( $user_id, self::META_REASON, $reason );
        update_user_meta( $user_id, self::META_SINCE, current_time( 'mysql' ) );
        update_user_meta( $user_id, self::META_DRAFTED, $drafted );

        // Close the store front.
        $store_info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $user_id ) : [];

        if ( is_array( $store_info ) ) {
            $store_info['store_open_close'] = 'close';
            update_user_meta( $user_id, 'sk_profile_settings', $store_info );
        }

        return count( $drafted );
    }

    /**
     * Reverse a suspension: republish exactly the listings it drafted.
     *
     * @return int Number of listings put back online.
     */
    public static function unsuspend( int $user_id ): int {
        $drafted   = (array) get_user_meta( $user_id, self::META_DRAFTED, true );
        $restored  = 0;

        foreach ( $drafted as $product_id ) {
            $product_id = (int) $product_id;

            // Only touch what is still a draft — the vendor may have changed
            // or deleted it in the meantime.
            if ( 'draft' !== get_post_status( $product_id ) ) {
                continue;
            }

            $result = wp_update_post( [ 'ID' => $product_id, 'post_status' => 'publish' ], true );

            if ( ! is_wp_error( $result ) ) {
                $restored++;
            }
        }

        delete_user_meta( $user_id, self::META_SUSPENDED );
        delete_user_meta( $user_id, self::META_REASON );
        delete_user_meta( $user_id, self::META_SINCE );
        delete_user_meta( $user_id, self::META_DRAFTED );

        // Reopen the store front.
        $store_info = function_exists( 'sk_get_store_info' ) ? sk_get_store_info( $user_id ) : [];

        if ( is_array( $store_info ) ) {
            $store_info['store_open_close'] = 'open';
            update_user_meta( $user_id, 'sk_profile_settings', $store_info );
        }

        return $restored;
    }

    /**
     * All currently suspended vendors, newest first.
     *
     * @return array[] { user, reason, since, products }
     */
    public static function get_suspended(): array {
        $users = get_users( [
            'meta_key'   => self::META_SUSPENDED,
            'meta_value' => 1,
            'fields'     => 'all',
        ] );

        $rows = [];

        foreach ( $users as $user ) {
            $ids      = (array) get_user_meta( $user->ID, self::META_DRAFTED, true );
            $products = [];

            foreach ( $ids as $product_id ) {
                $post = get_post( (int) $product_id );

                if ( $post ) {
                    $products[] = $post;
                }
            }

            $rows[] = [
                'user'     => $user,
                'reason'   => (string) get_user_meta( $user->ID, self::META_REASON, true ),
                'since'    => (string) get_user_meta( $user->ID, self::META_SINCE, true ),
                'products' => $products,
            ];
        }

        usort( $rows, static function ( $a, $b ) {
            return strcmp( $b['since'], $a['since'] );
        } );

        return $rows;
    }
}
