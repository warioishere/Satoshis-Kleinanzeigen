<?php
namespace SK\Modules\ProductAdvertisement;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class Ajax
 *
 *
 */
class Ajax {
    /**
     * Ajax constructor.
     *
     */
    public function __construct() {
        // ajax product add to cart
        add_action( 'wp_ajax_sk_add_advertise_product_to_cart', [ $this, 'purchase_advertisement' ] );
        // ajax get product advertisement status
        add_action( 'wp_ajax_sk_get_advertisement_status', [ $this, 'get_advertisement_data' ] );
    }

    /**
     * This method will add a product to cart from product edit page
     *
     *
     * @return void
     */
    public function purchase_advertisement() {
        // Nonce check.
        if ( ! isset( $_POST['advertise_product_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['advertise_product_nonce'] ) ), 'sk_advertise_product_nonce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce', 'sk-core' ) ], 400 );
        }

        $product_id = isset( $_REQUEST['product_id'] ) ? absint( wp_unslash( $_REQUEST['product_id'] ) ) : 0;

        if ( ! $product_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid product id. Please check your input.', 'sk-core' ) ], 400 );
        }

        $purchased = Helper::purchase_advertisement( $product_id );

        if ( is_wp_error( $purchased ) ) {
            // the http status sits in the error data, get_error_code() returns a slug
            $error_data = $purchased->get_error_data();
            $status     = is_array( $error_data ) && ! empty( $error_data['status'] ) ? absint( $error_data['status'] ) : 0;

            wp_send_json_error( [ 'message' => $purchased->get_error_message() ], $status ? $status : 400 );
        }

        wp_send_json_success( $purchased );
    }

    /**
     * This method will get advertisement status for a product
     *
     *
     * @return void
     */
    public function get_advertisement_data() {
        // nonce check
        if ( ! isset( $_POST['advertise_product_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['advertise_product_nonce'] ) ), 'sk_advertise_product_nonce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce', 'sk-core' ) ], 400 );
        }

        // check permission, don't let vendor staff view this section
        if ( ! current_user_can( 'skdar' ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to use this action.', 'sk-core' ) ], 400 );
        }

        // check if product advertisement is enabled or not for vendors
        if ( ! Helper::is_per_product_advertisement_enabled() && ! Helper::is_enabled_for_vendor_subscription() ) {
            wp_send_json_error( [ 'message' => __( 'Purchasing advertisement is restricted by admin.', 'sk-core' ) ], 400 );
        }

        // now get required data from
        $product_id = isset( $_REQUEST['product_id'] ) ? absint( wp_unslash( $_REQUEST['product_id'] ) ) : 0;

        // now check for data validation
        // check if we found a valid product id
        if ( ! $product_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid product id. Please check your input.', 'sk-core' ) ], 400 );
        }

        $advertisement_data = Helper::get_advertisement_data_for_insert( $product_id, sk_get_current_user_id() );

        if ( is_wp_error( $advertisement_data ) ) {
            wp_send_json_error( [ 'message' => $advertisement_data->get_error_message() ], 400 );
        }

        // this is to get translated string, doing it from jquery was problematic due to some dynamic values
        if ( false !== $advertisement_data['can_advertise_for_free'] ) {
            $advertisement_text = sprintf(
                // translators: 1) remaining advertisement slot
                __( 'You can advertise this product for free. Expire after <strong>%1$s</strong>, Remaining slot: <strong>%2$s</strong>', 'sk-core' ),
                Helper::format_expire_after_days_text( $advertisement_data['expires_after_days'] ), Helper::get_formatted_remaining_slot_count( $advertisement_data['remaining_slot'] )
            );
        } else {
            $subscription_empty_slot_message = false !== $advertisement_data['subscription_status'] && empty( $advertisement_data['subscription_remaining_slot'] ) ?
                                                    __( 'No advertisement slot is available with your subscription. However you can purchase this advertisement.', 'sk-core' ) . ' ' : '';
            $advertisement_text = sprintf(
                // translators: 1) advertisement expires after days 2) advertisement listing price html
                __( '%4$sAdvertise this product for: <strong>%1$s</strong>, Advertisement Cost: <strong>%2$s</strong>, Remaining slot: <strong>%3$s</strong>', 'sk-core' ),
                Helper::format_expire_after_days_text( $advertisement_data['expires_after_days'] ), wc_price( $advertisement_data['listing_price'] ),
                Helper::get_formatted_remaining_slot_count( $advertisement_data['remaining_slot'] ), $subscription_empty_slot_message
            );
        }

        $data['advertisement_text'] = $advertisement_text;

        wp_send_json_success( $data );
    }
}
