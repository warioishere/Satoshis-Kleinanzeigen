<?php

class SK_Follow_Store_Follow_Button {

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        add_action( 'sk_seller_listing_footer_content', array( $this, 'add_follow_button' ) );
        add_action( 'sk_after_store_tabs', array( $this, 'add_follow_button_after_store_tabs' ), 99 );
    }

    /**
     * Add follow store button
     *
     *
     * @param WP_User $vendor
     * @param array   $button_classes
     *
     * @return void
     */
    public function add_follow_button( $vendor, $button_classes = array() ) {
        $args = sk_follow_store_get_button_args( $vendor, $button_classes );

        sk_follow_store_get_template( 'follow-button', $args );
    }

    /**
     * Add follow button in single store tabs
     *
     *
     * @param void $vendor_id
     *
     * @return void
     */
    public function add_follow_button_after_store_tabs( $vendor_id ) {
        $vendor = sk()->vendor->get( $vendor_id );

        ob_start();
        $this->add_follow_button( $vendor->data, array( 'sk-btn-sm' ) );
        $button = ob_get_clean();

        $args = array(
            'button' => $button,
        );

        sk_follow_store_get_template( 'follow-button-after-store-tabs', $args );
    }
}
