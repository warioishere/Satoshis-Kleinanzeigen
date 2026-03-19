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
        add_action( 'sk_admin_setup_wizard_step_store_after', [ $this, 'admin_wizard_store_setup_field' ] );
        add_action( 'sk_admin_setup_wizard_save_step_store', [ $this, 'after_admin_wizard_store_field_save' ] );
        add_filter( 'sk_get_dashboard_settings_nav', [ $this, 'remove_shipping_settings_menu' ], 99 );
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
            'label'   => __( 'Selling Product Types', 'sk' ),
            'desc'    => __( 'Select a type for vendors what type of product they can sell only', 'sk' ),
            'type'    => 'radio',
            'default' => 'sell_both',
            'tooltip' => __( 'Select the type of products vendor can sell.', 'sk' ),
            'options' => apply_filters(
                'sk_digital_product_types',
                [
                    'sell_physical' => __( 'Physical', 'sk' ),
                    'sell_digital'  => __( 'Digital', 'sk' ),
                    'sell_both'     => __( 'Both', 'sk' ),
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

    /**
     * Add store digitial product option template
     *
     *
     * @return void
     */
    public function admin_wizard_store_setup_field( $wizard ) {
        $args = array(
            'pro'          => true,
            'label'        => __( 'Selling Product Types', 'sk' ),
            'digital_mode' => $this->get_selling_product_type(),
            'plans' => apply_filters(
                'sk_digital_product_types',
                [
                    'sell_physical' => __( 'Physical', 'sk' ),
                    'sell_digital'  => __( 'Digital', 'sk' ),
                    'sell_both'     => __( 'Both', 'sk' ),
                ]
            ),
        );

        sk_get_template_part( 'settings/seller-wizard-digital-product-settings', '', $args );
    }

    /**
     * Set store categories after wizard settings is saved
     *
     *
     * @param \SK\Core\Vendor\SetupWizard $wizard
     *
     * @return void
     */
    public function after_admin_wizard_store_field_save( $wizard ) {
        check_admin_referer( 'sk-setup' );

        $get_postdata  = wp_unslash( $_POST ); // phpcs:ignore
        $sk_general = get_option( 'sk_general', array() );

        $sk_general['global_digital_mode'] = ! empty( $get_postdata['sk_digital_product'] ) ? sanitize_text_field( $get_postdata['sk_digital_product'] ) : 'sell_both';

        update_option( 'sk_general', $sk_general );
    }

    /**
     * Remove shipping menu when digital mode only
     *
     *
     * @param  array $sub_settins
     *
     * @return array
     */
    public function remove_shipping_settings_menu( $sub_settins ) {
        if ( 'sell_digital' === $this->get_selling_product_type() ) {
            unset( $sub_settins['shipping'] );
        }

        return $sub_settins;
    }
}
