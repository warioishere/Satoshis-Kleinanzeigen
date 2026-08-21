<?php

namespace SK\Core;

/**
 * Digital Porduct class
 *
 *
 */
class DigitalProduct {

    /**
     * Load autometically when class initiate
     *
     *
     * @uses actions
     * @uses filters
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Init hooks and filters
     *
     * @return void
     */
    public function init_hooks() {
        add_filter( 'sk_settings_general_site_options', [ $this, 'add_admin_setting_digital_mode' ], 9 );
    }

    /**
     * Add vendor store options in general settings
     *
     *
     * @param array $settings_fields
     *
     * @return array $settings_fields
     */
    public function add_admin_setting_digital_mode( $settings_fields ) {
        $settings_fields['global_digital_mode'] = array(
            'name'    => 'global_digital_mode',
            'label'   => __( 'Selling Product Types', 'sk-core' ),
            'desc'    => __( 'Select a type for vendors what type of product they can sell only', 'sk-core' ),
            'type'    => 'radio',
            'default' => 'sell_both',
            'tooltip' => __( 'Select the type of products vendor can sell.', 'sk-core' ),
            'options' => apply_filters(
                'sk_digital_product_types',
                [
                    'sell_physical' => __( 'Physical', 'sk-core' ),
                    'sell_digital'  => __( 'Digital', 'sk-core' ),
                    'sell_both'     => __( 'Both', 'sk-core' ),
                ]
            ),
            'is_lite' => false,
        );

        return $settings_fields;
    }

    /**
     * Get sk selling product type
     *
     *
     * @return string
     */
    public function get_selling_product_type() {
        return sk_get_option( 'global_digital_mode', 'sk_general', 'sell_both' );
    }

}
