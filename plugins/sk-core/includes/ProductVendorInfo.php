<?php

namespace SK\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Vendor info (avatar + store name) on product cards in the shop loop.
 * Originally: woo-commerce-profilpics plugin by Wario.
 */
class ProductVendorInfo {

    public static function init(): void {
        add_action( 'woocommerce_shop_loop_item_title', [ __CLASS__, 'render' ], 5 );
    }

    public static function render(): void {
        global $product;

        $vendor = sk_get_vendor_by_product( $product );
        if ( ! $vendor ) {
            return;
        }

        $vendor_id  = $vendor->get_id();
        $store_info = sk_get_store_info( $vendor_id );
        $store_url  = sk_get_store_url( $vendor_id );
        $store_name = esc_html( $store_info['store_name'] ?? $vendor->get_shop_name() );
        $avatar_url = get_avatar_url( $vendor_id, [ 'size' => 64 ] );

        if ( empty( $avatar_url ) || strpos( $avatar_url, 'gravatar.com' ) !== false ) {
            $avatar_url = plugins_url( 'assets/images/default-avatar.jpg', SK_CORE_FILE );
        }

        echo '<div class="produkt-vendor-info">';
        echo '<img class="vendor-avatar" src="' . esc_url( $avatar_url ) . '" alt="" loading="lazy">';
        echo '<a class="vendor-name" href="' . esc_url( $store_url ) . '">@' . $store_name . '</a>';

        if ( function_exists( 'sk_verified_badge' ) ) {
            echo sk_verified_badge( $vendor_id ); // phpcs:ignore WordPress.Security.EscapeOutput
        }

        echo '</div>';
    }
}
