<?php

namespace SK\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Auto-Assign Free Pack + Ensure Free When Others Expire.
 *
 * Assigns new vendors the Free Pack (0€ order) on onboarding.
 * Re-activates the Free Pack when no other active pack exists
 * (e.g. when a purchased subscription expires).
 *
 * Triggered on: onboarding, login, throttled page-load.
 * No cron, no periodic renewals.
 */
final class FreePack {

    const FREE_PACK_PRODUCT_ID = 1206;
    const PAGECHECK_COOLDOWN   = 900; // 15 minutes
    const LOCK_META            = '_freepack_assign_lock_until';
    const LOCK_TTL             = 300; // 5 minutes
    const META_LAST_PAGECHECK  = '_freepack_last_pagecheck';

    public static function init(): void {
        // Silent emails for auto-created free orders.
        add_filter( 'woocommerce_email_enabled_new_order',                 [ __CLASS__, 'silent_emails' ], 10, 2 );
        add_filter( 'woocommerce_email_enabled_customer_processing_order', [ __CLASS__, 'silent_emails' ], 10, 2 );
        add_filter( 'woocommerce_email_enabled_customer_completed_order',  [ __CLASS__, 'silent_emails' ], 10, 2 );
        add_filter( 'woocommerce_email_enabled_customer_invoice',          [ __CLASS__, 'silent_emails' ], 10, 2 );

        // Onboarding.
        add_action( 'user_register',        [ __CLASS__, 'on_user_register' ], 20 );
        add_action( 'sk_new_seller_created', [ __CLASS__, 'on_new_seller' ], 10 );

        // Login check.
        add_action( 'wp_login', [ __CLASS__, 'on_login' ], 10, 2 );

        // A pack just expired or was cancelled — hand out the free pack right away
        // instead of leaving the vendor without one until their next visit.
        add_action( 'sk_subscription_pack_deleted', [ __CLASS__, 'on_pack_deleted' ], 10, 1 );

        // Throttled page-load checks.
        add_action( 'wp',         [ __CLASS__, 'on_pageload_front' ], 1 );
        add_action( 'admin_init', [ __CLASS__, 'on_pageload_admin' ] );
    }

    public static function silent_emails( $enabled, $order ) {
        if ( ! $enabled ) {
            return false;
        }
        if ( empty( $order ) || ! is_object( $order ) ) {
            return $enabled;
        }

        try {
            if ( (int) $order->get_meta( '_freepack_silent' ) === 1 ) {
                return false;
            }
        } catch ( \Throwable $e ) {
            return $enabled;
        }

        return $enabled;
    }

    public static function on_user_register( int $user_id ): void {
        self::maybe_assign_onboarding( $user_id );
    }

    public static function on_new_seller( $user_id ): void {
        self::maybe_assign_onboarding( (int) $user_id );
    }

    public static function on_pack_deleted( $user_id ): void {
        $uid = (int) $user_id;
        if ( ! $uid || ! self::is_vendor( $uid ) ) {
            return;
        }
        self::ensure_free_if_no_active_other( $uid );
    }

    public static function on_login( $user_login, $user ): void {
        $uid = (int) $user->ID;
        if ( ! $uid || ! self::is_vendor( $uid ) ) {
            return;
        }
        self::ensure_free_if_no_active_other( $uid );
    }

    public static function on_pageload_front(): void {
        if ( is_admin() || ! is_user_logged_in() ) {
            return;
        }

        $uid = get_current_user_id();
        if ( ! $uid || ! self::is_vendor( $uid ) ) {
            return;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if ( strpos( $uri, '/dashboard' ) === false ) {
            return;
        }

        if ( self::is_throttled( $uid ) ) {
            return;
        }

        self::ensure_free_if_no_active_other( $uid );
    }

    public static function on_pageload_admin(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $uid = get_current_user_id();
        if ( ! $uid || ! self::is_vendor( $uid ) ) {
            return;
        }

        if ( self::is_throttled( $uid ) ) {
            return;
        }

        self::ensure_free_if_no_active_other( $uid );
    }

    private static function maybe_assign_onboarding( int $user_id ): void {
        if ( ! $user_id || ! self::is_vendor( $user_id ) ) {
            return;
        }

        $existing_pack_id = (int) get_user_meta( $user_id, 'product_package_id', true );
        if ( $existing_pack_id > 0 ) {
            return;
        }

        self::assign_free_pack_via_order( $user_id );
    }

    private static function ensure_free_if_no_active_other( int $user_id ): void {
        $status = self::get_pack_status( $user_id );

        if ( $status['pack_id'] === 0 ) {
            self::assign_free_pack_via_order( $user_id );
            return;
        }

        if ( $status['has_active'] ) {
            return;
        }

        self::assign_free_pack_via_order( $user_id );
    }

    private static function get_pack_status( int $user_id ): array {
        $pack_id = (int) get_user_meta( $user_id, 'product_package_id', true );
        $raw     = get_user_meta( $user_id, 'product_pack_enddate', true );

        $end_ts = null;
        if ( $raw === 'unlimited' ) {
            $end_ts = PHP_INT_MAX;
        } elseif ( is_numeric( $raw ) ) {
            $end_ts = (int) $raw;
            if ( $end_ts > 20000000000 ) {
                $end_ts = (int) floor( $end_ts / 1000 );
            }
        } elseif ( ! empty( $raw ) ) {
            $parsed = strtotime( $raw );
            if ( $parsed !== false ) {
                $end_ts = $parsed;
            }
        }

        return [
            'pack_id'    => $pack_id,
            'end_ts'     => $end_ts,
            'has_active' => ( $pack_id > 0 && $end_ts && $end_ts > time() ),
        ];
    }

    private static function is_vendor( int $user_id ): bool {
        if ( function_exists( 'sk_is_user_seller' ) ) {
            return sk_is_user_seller( $user_id );
        }
        $u = get_user_by( 'id', $user_id );
        return $u ? user_can( $u, 'skdar' ) : false;
    }

    private static function is_throttled( int $user_id ): bool {
        $last = (int) get_user_meta( $user_id, self::META_LAST_PAGECHECK, true );
        if ( $last && ( time() - $last ) < self::PAGECHECK_COOLDOWN ) {
            return true;
        }
        update_user_meta( $user_id, self::META_LAST_PAGECHECK, time() );
        return false;
    }

    private static function acquire_lock( int $user_id ): bool {
        $until = (int) get_user_meta( $user_id, self::LOCK_META, true );
        if ( $until && $until > time() ) {
            return false;
        }
        update_user_meta( $user_id, self::LOCK_META, time() + self::LOCK_TTL );
        return true;
    }

    private static function release_lock( int $user_id ): void {
        delete_user_meta( $user_id, self::LOCK_META );
    }

    private static function assign_free_pack_via_order( int $user_id ): bool {
        if ( ! class_exists( 'WC_Order' ) || ! function_exists( 'wc_create_order' ) ) {
            return false;
        }
        if ( ! self::acquire_lock( $user_id ) ) {
            return false;
        }

        try {
            $status = self::get_pack_status( $user_id );
            if ( $status['has_active'] ) {
                self::release_lock( $user_id );
                return false;
            }

            $product = wc_get_product( self::FREE_PACK_PRODUCT_ID );
            if ( ! $product || ! $product->exists() ) {
                self::release_lock( $user_id );
                return false;
            }

            $order = wc_create_order( [ 'customer_id' => $user_id ] );
            $order->add_product( $product, 1 );

            foreach ( $order->get_items() as $item ) {
                if ( (int) $item->get_product_id() === self::FREE_PACK_PRODUCT_ID ) {
                    $item->set_subtotal( 0 );
                    $item->set_total( 0 );
                    $item->save();
                }
            }

            $order->calculate_totals();
            $order->update_meta_data( '_freepack_silent', 1 );
            $order->save();
            $order->payment_complete();
            $order->add_order_note( 'Free pack auto-assigned.' );

            self::apply_pack_to_user( $user_id, self::FREE_PACK_PRODUCT_ID, (int) $order->get_id() );
            self::release_lock( $user_id );
            return true;

        } catch ( \Exception $e ) {
            self::release_lock( $user_id );
            return false;
        }
    }

    private static function apply_pack_to_user( int $user_id, int $product_id, int $order_id ): void {
        $valid_days = (int) get_post_meta( $product_id, '_pack_validity', true );
        $start_ts   = time();
        $end_ts     = $valid_days > 0 ? $start_ts + ( $valid_days * DAY_IN_SECONDS ) : PHP_INT_MAX;

        update_user_meta( $user_id, 'product_id', $product_id );
        update_user_meta( $user_id, 'product_package_id', $product_id );
        update_user_meta( $user_id, 'product_order_id', $order_id );
        update_user_meta( $user_id, 'product_no_with_pack', 0 );
        update_user_meta( $user_id, 'product_pack_startdate', gmdate( 'Y-m-d H:i:s', $start_ts ) );
        update_user_meta(
            $user_id,
            'product_pack_enddate',
            $end_ts === PHP_INT_MAX ? 'unlimited' : gmdate( 'Y-m-d H:i:s', $end_ts )
        );
        update_user_meta( $user_id, 'can_post_product', 1 );
        update_user_meta( $user_id, '_customer_recurring_subscription', false );

        $selling = get_user_meta( $user_id, 'sk_enable_selling', true );
        if ( ! $selling ) {
            update_user_meta( $user_id, 'sk_enable_selling', 'yes' );
        }

        do_action( 'sk_vendor_subscription_applied_programmatically', $user_id, $product_id, $order_id );
    }
}
