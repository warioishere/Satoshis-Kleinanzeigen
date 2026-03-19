<?php
namespace SK\Modules\ProductAdvertisement\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class VendorSubscription
 *
 *
 */
class VendorSubscription {
    /**
     * VendorSubscription constructor.
     *
     */
    public function __construct() {
        // add advertisement fields under Vendor Subscription Product type
        add_action( 'dps_subscription_product_fields_after_pack_validity', [ $this, 'subscription_product_fields' ] );
        // save advertisement fields
        add_action( 'dps_process_subcription_product_meta', [ $this, 'save_subscription_product_fields' ], 10, 1 );
    }

    /**
     * This function will render fields under product edit page's general tab
     *
     *
     * @return void
     */
    public function subscription_product_fields() {
        woocommerce_wp_text_input(
            array(
                'id'                => '_sk_advertisement_slot_count',
                'label'             => __( 'Advertisement Slot', 'sk' ),
                'placeholder'       => __( 'Enter -1 for unlimited product advertisement, 0 for no advertisement.', 'sk' ),
                'description'       => __( 'Enter no of advertisement slot for this package. Enter -1 for unlimited advertisement, 0 for no advertisement slots.', 'sk' ),
                'desc_tip'          => true,
                'type'              => 'number',
                'custom_attributes' => array(
                    'step' => 'any',
                    'min'  => '-1',
                ),
            )
        );

        woocommerce_wp_text_input(
            array(
                'id'                => '_sk_advertisement_validity',
                'label'             => __( 'Expire After Days', 'sk' ),
                'placeholder'       => __( 'Enter -1 for no advertisement expiration.', 'sk' ),
                'description'       => __( 'Enter how many days product will be featured, enter -1 if you don\'t want to set any expiration period.', 'sk' ),
                'desc_tip'          => true,
                'type'              => 'number',
                'custom_attributes' => array(
                    'step' => 'any',
                    'min'  => '-1',
                ),
            )
        );
    }

    /**
     * This method will save fields data added to general tab
     *
     *
     * @param int $post_id
     *
     * @return void
     */
    public function save_subscription_product_fields( $post_id ) {
        if ( ! isset( $_POST['dps_product_pack'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['dps_product_pack'] ) ), 'dps_product_fields_nonce' ) ) {
            return;
        }

        if ( isset( $_POST['_sk_advertisement_slot_count'] ) ) {
            // Save the value as is, including 0
            $advertise_product_count = intval( wp_unslash( $_POST['_sk_advertisement_slot_count'] ) );
            update_post_meta( $post_id, '_sk_advertisement_slot_count', $advertise_product_count );
        }

        if ( isset( $_POST['_sk_advertisement_validity'] ) ) {
            $advertisement_validity = intval( wp_unslash( $_POST['_sk_advertisement_validity'] ) );
            update_post_meta( $post_id, '_sk_advertisement_validity', $advertisement_validity );
        }
    }
}
