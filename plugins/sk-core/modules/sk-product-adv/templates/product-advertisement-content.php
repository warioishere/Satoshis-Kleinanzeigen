<?php

use SK\Modules\ProductAdvertisement\Helper;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 *
 * @var int $product_id
 * @var int $vendor_id
 * @var bool $already_advertised
 * @var bool $can_advertise_for_free
 * @var string $expire_date
 * @var float $listing_price
 * @var bool|\SK\Modules\Subscription\SubscriptionPack $subscription_status
 * @var int $remaining_slot if subscription exists, this will get remaining slot form package, otherwise from global settings
 * @var int $subscription_remaining_slot
 * @var int $expires_after_days if subscription exists, this will get remaining slot form package, otherwise from global settings
 * @var int $subscription_expires_after_days
 * @var string $post_status
 * @var string $advertise_active_color
 */
?>

<div class="sk-edit-row sk-proudct-advertisement sk-clearfix">
    <div class="sk-section-heading">
        <h2>
        <span class="fa-stack fa-xs tips">
            <i class="fa fa-circle fa-stack-2x" style="color: <?php echo esc_html( $advertise_active_color ); ?>; font-size: 2em;"></i>
            <i class="fa fa-bullhorn fa-stack-1x fa-inverse" data-fa-transform="shrink-6"></i>
        </span>
            <?php esc_html_e( 'Advertise Product', 'sk-core' ); ?>
        </h2>
        <p><?php esc_html_e( 'Manage Advertisement for this product', 'sk-core' ); ?></p>
        <div class="sk-clearfix"></div>
    </div>

    <div class="sk-section-content">
        <?php
        /**
         * Logic flow:
         * 1. Product not published → Show "publish first" message
         * 2. Product already advertised → Show expiry date
         * 3. No slots available (remaining_slot = 0 or false) → Show appropriate message
         * 4. Slots available → Show free or paid advertising option
         */

        // Case 1: Product is not published
        if ( 'publish' !== $post_status && true !== $already_advertised ) :
            ?>
            <p>
                <?php esc_html_e( 'You can not advertise this product. Product needs to be published before you can advertise.', 'sk-core' ); ?>
            </p>

            <?php
            // Case 2: Product is already being advertised
        elseif ( true === $already_advertised ) :
            ?>
            <label for="sk_advertise_single_product">
                <input type="checkbox" id="sk_advertise_single_product" name="sk_advertise_single_product" value="on" checked="checked" disabled="disabled" />
                <?php
                // translators: %s: expiration date
                printf( __( 'Product advertisement is currently ongoing. Advertisement will end on: <strong>%s</strong>', 'sk-core' ), $expire_date );
                ?>
            </label>

            <?php
            // Case 3: No advertisement slots available (either from subscription or global)
        elseif ( $remaining_slot === 0 || $remaining_slot === false ) :
            ?>
            <p>
                <?php
                if ( false !== $subscription_status && 0 === $subscription_remaining_slot ) {
                    esc_html_e( 'Your subscription plan does not include product advertisement slots. Please upgrade your subscription or contact the admin for more information.', 'sk-core' );
                } else {
                    esc_html_e('No advertisement slots are currently available. Please contact the site administrator to request additional slots or check back later.', 'sk-core');
                }
                ?>
            </p>

            <?php
            // Case 4: Slots available (unlimited or limited) and can advertise for free
        elseif ( $can_advertise_for_free && ( $remaining_slot > 0 || $remaining_slot === -1 ) ) :
            ?>
            <label for="sk_advertise_single_product">
                <input type="checkbox"
                        id="sk_advertise_single_product"
                        name="sk_advertise_single_product"
                        value="off"
                        data-product-id="<?php echo esc_attr( $product_id ); ?>" />
                <?php
                printf(
                // translators: 1) expiration period, 2) remaining slots
                    __( 'You can advertise this product for free. Expire after <strong>%1$s</strong>, Remaining slot: <strong>%2$s</strong>', 'sk-core' ),
                    Helper::format_expire_after_days_text( $expires_after_days ),
                    Helper::get_formatted_remaining_slot_count( $remaining_slot )
                );
                ?>
            </label>

            <?php
            // Case 5: Slots available but must purchase
        elseif ( $remaining_slot > 0 || $remaining_slot === -1 ) :
            ?>
            <label for="sk_advertise_single_product">
                <input type="checkbox"
                        id="sk_advertise_single_product"
                        name="sk_advertise_single_product"
                        value="off"
                        data-product-id="<?php echo esc_attr( $product_id ); ?>" />
                <?php
                printf(
                // translators: 1) expiration period, 2) cost, 3) remaining slots
                    __( 'Advertise this product for: <strong>%1$s</strong>, Advertisement Cost: <strong>%2$s</strong>, Remaining slot: <strong>%3$s</strong>', 'sk-core' ),
                    Helper::format_expire_after_days_text( $expires_after_days ),
                    wc_price( $listing_price ),
                    Helper::get_formatted_remaining_slot_count( $remaining_slot )
                );
                ?>
            </label>

            <?php
            // Case 6: Fallback - this should be unreachable, but kept for safety
        else :
            ?>
            <p class="sk-error">
                <?php esc_html_e( 'There was an error determining your advertisement eligibility. Please refresh the page or contact support.', 'sk-core' ); ?>
            </p>
        <?php endif; ?>

        <div class="sk-clearfix"></div>
    </div>
</div>

