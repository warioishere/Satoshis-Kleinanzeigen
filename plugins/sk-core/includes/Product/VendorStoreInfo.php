<?php

namespace SK\Core\Product;

/**
 * Vendor information handler class
 */
class VendorStoreInfo {
    /**
     * Class constructor
     *
     */
    public function __construct() {
        $show_vendor_info = sk_get_option( 'show_vendor_info', 'sk_general', 'off' );

        if ( 'on' === $show_vendor_info ) {
            add_action( 'woocommerce_product_meta_end', [ $this, 'add_vendor_info_on_product_single_page' ] );
        }

        add_filter( 'sk_settings_fields', array( $this, 'admin_settings_for_vendor_info' ), 10, 2 );
    }

    /**
     * Display seller info on product single page
     *
     *
     * @return void
     */
    public function add_vendor_info_on_product_single_page() {
        global $product;

        $vendor = sk_get_vendor_by_product( $product );
        if ( ! $vendor ) {
            return;
        }
        $store_info   = $vendor->get_shop_info();
        $store_rating = $vendor->get_rating();

        sk_get_template_part(
            'vendor-store-info',
            '',
            [
                'vendor'       => $vendor,
                'store_info'   => $store_info,
                'store_rating' => $store_rating,
            ]
        );
    }

    /**
     * Add setting fields for seller information
     *
     * @param array $settings_fields
     *
     * @param object $sk_settings
     *
     * @return array
     */
    public function admin_settings_for_vendor_info( $settings_fields, $sk_settings ) {
        $vendor_info = [
            'show_vendor_info' => [
                'name'              => 'show_vendor_info',
                'label'             => __( 'Show Vendor Info', 'sk-core' ),
                'desc'              => __( 'Show vendor information on single product page', 'sk-core' ),
                'type'              => 'switcher',
                'default'           => 'off',
                'class'             => 'show_vendor_info',
                'sanitize_callback' => 'sanitize_text_field',
                'is_lite'           => true,
            ],
        ];

        return $sk_settings->add_settings_after(
            $settings_fields,
            'sk_general',
            'product_page_options',
            $vendor_info
        );
    }
}

