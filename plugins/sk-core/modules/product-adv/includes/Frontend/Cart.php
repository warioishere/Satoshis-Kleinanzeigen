<?php
namespace SK\Modules\ProductAdvertisement\Frontend;

use WC_Order;
use SK\Modules\ProductAdvertisement\Helper;
use SK\Modules\ProductAdvertisement\Manager;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class Cart
 *
 *
 */
class Cart {
    /**
     * Cart constructor.
     *
     */
    public function __construct() {
        // prevent user from adding product to cart while purchasing per product advertisement setting is disabled
        add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'prevent_purchasing_advertisement_product' ], 5, 2 );

        // prevent other products to add cart while advertisement product exists in cart
        add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'remove_other_products' ], 10, 2 );

        // check if vendor can advertise their product/cart validation
        add_action( 'woocommerce_after_checkout_validation', [ $this, 'check_vendor_can_purchase_advertisement' ], 15, 2 );

        add_action( 'woocommerce_before_calculate_totals', [ $this, 'woocommerce_custom_price_to_cart_item' ], 99, 1 );

        add_filter( 'woocommerce_get_item_data', [ $this, 'display_advertisement_meta' ], 9, 2 );

        // remove delivery time module section from checkout page
        add_action( 'woocommerce_review_order_before_payment', [ $this, 'remove_delivery_time_section_from_checkout' ], 9 );

        // remove paypal marketplace checkout validations
        add_filter( 'sk_paypal_marketplace_escape_after_checkout_validation', [ $this, 'paypal_remove_gateway_validations' ], 10, 2 );
        add_filter( 'sk_paypal_marketplace_merchant_id', [ $this, 'paypal_get_merchant_id_for_advertisement' ], 10, 2 );
        add_filter( 'sk_paypal_marketplace_purchase_unit_merchant_id', [ $this, 'paypal_purchase_unit_merchant_id' ], 10, 2 );

        // Skip Stripe Express cart validation and disbursement.
        add_filter( 'sk_stripe_express_needs_cart_validation', [ $this, 'skip_cart_validation_for_advertisement' ] );
        add_filter( 'sk_stripe_express_disburse_payment', [ $this, 'skip_payment_disbursement_for_advertisement' ], 10, 2 );

        // Skip MangoPay cart validation and disbursement.
        add_filter( 'sk_mangopay_needs_cart_validation', [ $this, 'skip_cart_validation_for_advertisement' ] );
        add_filter( 'sk_mangopay_disburse_payment', [ $this, 'skip_payment_disbursement_for_advertisement' ], 10, 2 );
    }

    /**
     * Remove PayPal Marketplace Checkout Validations
     *
     *
     * @param bool $escape
     * @param array $cart_item
     *
     * @return bool
     */
    public function paypal_remove_gateway_validations( $escape, $cart_item ) {
        if ( true === wc_string_to_bool( $escape ) ) {
            return $escape;
        } elseif ( Helper::has_product_advertisement_in_cart() ) {
            return true;
        }
        return $escape;
    }

    /**
     * Get admin partner id for advertisement product
     *
     *
     * @param string $merchant_id
     * @param int $product_id
     *
     * @return string
     */
    public function paypal_get_merchant_id_for_advertisement( $merchant_id, $product_id ) {
        // check if this is a recurring subscription product
        if ( (int) $product_id === Helper::get_advertisement_base_product() ) {
            return \SK\Modules\PayPalMarketplace\Helper::get_partner_id();
        }

        return $merchant_id;
    }

    /**
     * Get admin partner id for advertisement product
     *
     *
     * @param string $merchant_id
     * @param \WC_Abstract_Order $order
     *
     * @return string
     */
    public function paypal_purchase_unit_merchant_id( $merchant_id, $order ) {
        // check if this is a recurring subscription product
        if ( Helper::has_product_advertisement_in_order( $order ) ) {
            return \SK\Modules\PayPalMarketplace\Helper::get_partner_id();
        }

        return $merchant_id;
    }

    /**
     * This method will remove delivery time module section from checkout page
     *
     *
     * @return void
     */
    public function remove_delivery_time_section_from_checkout() {
        if ( sk_ext()->module->is_active( 'delivery_time' ) && Helper::has_product_advertisement_in_cart() ) {
            remove_action( 'woocommerce_review_order_before_payment', [ sk_ext()->module->delivery_time->dt_frontend, 'render_delivery_time_template' ], 10 );
        }
    }

    /**
     * Injects seller name on cart and other areas
     *
     *
     * @param array $item_data
     * @param array $cart_item
     *
     * @return array
     */
    public function display_advertisement_meta( $item_data, $cart_item ) {
        if ( ! isset( $cart_item['sk_product_advertisement'] ) ) {
            return $item_data;
        }

        // remove seller data for advertisement product
        remove_filter( 'woocommerce_get_item_data', 'sk_product_seller_info', 10 );

        if ( isset( $cart_item['sk_advertisement_product_id'] ) ) {
            $formatted_title = sprintf( '<a href="%1$s">%2$s</a>', esc_url( get_the_permalink( $cart_item['sk_advertisement_product_id'] ) ), get_the_title( $cart_item['sk_advertisement_product_id'] ) );
            $item_data[] = array(
                'name'  => __( 'Product Name', 'sk' ),
                'value' => $formatted_title,
            );
        }

        if ( isset( $cart_item['sk_advertisement_expire_after_days'] ) ) {
            $item_data[] = array(
                'name'  => __( 'Expires In Days', 'sk' ),
                'value' => Helper::format_expire_after_days_text( $cart_item['sk_advertisement_expire_after_days'] ),
            );
        }

        return $item_data;
    }

    /**
     * Add custom price into cart meta item.
     *
     *
     * @param \WC_Cart $cart for whole cart.
     */
    public function woocommerce_custom_price_to_cart_item( $cart ) {
        if ( ! empty( $cart->cart_contents ) ) {
            foreach ( $cart->cart_contents as $key => $value ) {
                if ( isset( $value['sk_advertisement_cost'] ) && $value['data']->get_id() === Helper::get_advertisement_base_product() ) {
                    $value['data']->set_price( $value['sk_advertisement_cost'] );
                }
            }
        }
    }

    /**
     * This method will prevent user from adding product to cart while purchasing per product advertisement setting is disabled.
     *
     *
     * @param bool $passed
     * @param int $product_id
     *
     * @return bool
     */
    public static function prevent_purchasing_advertisement_product( $passed, $product_id ) {
        // return if product_id doesn't match advertisement base product
        if ( $product_id !== Helper::get_advertisement_base_product() ) {
            return $passed;
        }

        if ( Helper::is_per_product_advertisement_enabled() ) {
            return $passed;
        }

        $message = wp_kses(
            sprintf(
            // translators: 1) Product title
                __( '<strong>Error!</strong> Could not add product <strong>%1$s</strong> to cart. Purchasing Product advertisement feature is restricted by admin.', 'sk' ),
                get_the_title( $product_id )
            ),
            [
                'strong' => [],
            ]
        );
        wc_add_notice( $message, 'error' );
        return false;
    }

    /**
     * This method will remove other products from cart if advertisement exists in cart.
     *
     *
     * @param bool $passed
     * @param int $product_id
     *
     * @return bool
     */
    public static function remove_other_products( $passed, $product_id ) {
        if ( ! Helper::has_product_advertisement_in_cart() ) {
            return $passed;
        }

        $message = wp_kses(
            sprintf(
            // translators: 1) Product title
                __( '<strong>Error!</strong> Could not add product <strong>%1$s</strong> to cart. Product advertisement can not be purchased along with other products.', 'sk' ),
                get_the_title( $product_id )
            ),
            [
                'strong' => [],
            ]
        );
        wc_add_notice( $message, 'error' );
        return false;
    }

    /**
     * Validate cart data
     *
     *
     * @param $data
     * @param $errors
     *
     * @return void
     */
    public function check_vendor_can_purchase_advertisement( $data, $errors ) {
        $product_id             = null;
        $advertisement_cost     = 0;
        $advertisement_validity = 0;

        if ( ! WC()->cart ) {
            return;
        }

        foreach ( WC()->cart->get_cart() as $item ) {
            if ( intval( $item['data']->get_id() ) !== Helper::get_advertisement_base_product() ) {
                continue;
            }

            // check if purchasing advertisement feature is enabled
            if ( ! Helper::is_per_product_advertisement_enabled() ) {
                $errors->add(
                    'advertisement-disabled',
                    wp_kses(
                        sprintf(
                        // translators: 1) and 2) Payment Gateway Title
                            __( '<strong>Error!</strong> Purchasing Product advertisement feature is restricted by admin. Kindly remove <strong>%1$s</strong> from cart.', 'sk' ),
                            get_the_title( $item['data']->get_id() )
                        ),
                        [
                            'strong' => [],
                        ]
                    )
                );
                return;
            }

            // check if we got required data
            if ( ! isset( $item['sk_advertisement_product_id'] ) ) {
                $errors->add(
                    'advertisement-disabled',
                    wp_kses(
                        sprintf(
                        // translators: 1) and 2) Payment Gateway Title
                            __( '<strong>Error!</strong> Required data to purchase this advertisement is not found. Kindly remove <strong>%1$s</strong> from cart.', 'sk' ),
                            get_the_title( $item['data']->get_id() )
                        ),
                        [
                            'strong' => [],
                        ]
                    )
                );
                return;
            }

            // get required data
            $product_id             = intval( $item['sk_advertisement_product_id'] );
            $advertisement_cost     = floatval( $item['sk_advertisement_cost'] );
            $advertisement_validity = intval( $item['sk_advertisement_expire_after_days'] );
            break;
        }

        if ( null === $product_id ) {
            return;
        }

        // get advertisement data
        $advertisement_data = Helper::get_advertisement_data_for_insert( $product_id, sk_get_current_user_id() );

        if ( is_wp_error( $advertisement_data ) ) {
            $errors->add( $advertisement_data->get_error_code(), $advertisement_data->get_error_message() );
            return;
        }

        // verify listing price is equal to advertisement cost
        if ( $advertisement_data['listing_price'] !== $advertisement_cost ) {
            $errors->add(
                'invalid-advertisement-cost',
                wp_kses(
                    sprintf(
                    // translators: 1) and 2) Payment Gateway Title
                        __( '<strong>Error!</strong> Advertisement cost does not match with listing price. Kindly remove <strong>%1$s</strong> from cart.', 'sk' ),
                        get_the_title( $product_id )
                    ),
                    [
                        'strong' => [],
                    ]
                )
            );
            return;
        }

        // verify expire after days
        if ( $advertisement_data['expires_after_days'] !== $advertisement_validity ) {
            $errors->add(
                'invalid-advertisement-validity',
                wp_kses(
                    sprintf(
                    // translators: 1) and 2) Payment Gateway Title
                        __( '<strong>Error!</strong> Advertisement validity does not match with listing expire after days. Kindly remove <strong>%1$s</strong> from cart.', 'sk' ),
                        get_the_title( $product_id )
                    ),
                    [
                        'strong' => [],
                    ]
                )
            );
            return;
        }
    }

    /**
     * Skips the cart validation for Stripe Express and MangoPay.
     *
     *
     * @param bool $needs_validation
     *
     * @return bool
     */
    public function skip_cart_validation_for_advertisement( $needs_validation ) {
        if ( ! $needs_validation || ! Helper::has_product_advertisement_in_cart() ) {
            return $needs_validation;
        }

        return false;
    }

    /**
     * Skips payment disbursement to vendors for Stripe Express and MangoPay.
     *
     *
     * @param bool $disburse
     * @param WC_Order $order
     *
     * @return bool
     */
    public function skip_payment_disbursement_for_advertisement( $disburse, $order ) {
        if ( ! $disburse || ! Helper::has_product_advertisement_in_order( $order ) ) {
            return $disburse;
        }

        return false;
    }
}
