<?php

namespace SK\Core\Admin;

use Exception;
use SK\Core\Utilities\AdminSettings;
use WP_Error;
use SK\Core\Exceptions\SkException;
use SK\Core\Traits\AjaxResponseError;

/**
 * Admin Settings Class
 *
 *
 */
class Settings {

    use AjaxResponseError;

    /**
     * Load automatically when class initiate
     *
     */
    public function __construct() {
        add_filter( 'sk_admin_localize_script', [ $this, 'settings_localize_data' ], 10 );
        add_action( 'wp_ajax_sk_get_setting_values', [ $this, 'get_settings_value' ], 10 );
        add_action( 'wp_ajax_sk_save_settings', [ $this, 'save_settings_value' ], 10 );
        add_filter( 'sk_admin_localize_script', [ $this, 'add_admin_settings_nonce' ] );
        add_action( 'wp_ajax_sk_refresh_admin_settings_field_options', [ $this, 'refresh_admin_settings_field_options' ] );
        add_filter( 'sk_settings_general_site_options', [ $this, 'add_sk_data_clear_setting' ], 310 );
        add_filter( 'sk_get_settings_values', [ $this, 'set_vendor_latest_layout' ], 20, 2 );
    }

    /**
     * Get settings values
     *
     *
     * @return void
     */
    public function get_settings_value() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'You have no permission to get settings value', 'sk-core' ) );
        }

        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'sk_admin' ) ) {
            wp_send_json_error( __( 'Invalid nonce', 'sk-core' ) );
        }

        $settings = [];

        foreach ( $this->get_settings_sections() as $key => $section ) {
            $settings[ $section['id'] ] = apply_filters( 'sk_get_settings_values', $this->sanitize_options( get_option( $section['id'], [] ), 'read' ), $section['id'] );
        }

        $new_seller_enable_selling_statuses = isset( $settings['sk_selling']['new_seller_enable_selling'] ) ? $settings['sk_selling']['new_seller_enable_selling'] : 'automatically';

        /**
         * This is the mapper of enabled selling admin setting option for before and after of 4.0.2
         */
        if ( ! empty( $settings['sk_selling'] ) && ! in_array( $new_seller_enable_selling_statuses, $settings, true ) ) {
            $settings['sk_selling']['new_seller_enable_selling'] = sk_get_container()->get( AdminSettings::class )->get_new_seller_enable_selling_status( $settings['sk_selling']['new_seller_enable_selling'] ?? null );
        }

        wp_send_json_success( $settings );
    }

    /**
     * Save settings value
     *
     *
     * @return void
     */
    public function save_settings_value() {
        try {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                throw new SkException( 'sk_settings_unauthorized_operation', __( 'You are not authorized to perform this action.', 'sk-core' ), 401 );
            }

            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'sk_admin' ) ) {
                throw new SkException( 'sk_settings_invalid_nonce', __( 'Invalid nonce', 'sk-core' ), 403 );
            }

            if ( empty( $_POST['section'] ) ) {
                throw new SkException( 'sk_settings_error_saving', __( '`section` parameter is required.', 'sk-core' ), 400 );
            }

            $option_name = sanitize_text_field( wp_unslash( $_POST['section'] ) );
            // validate and sanitize option name to avoid any unwanted option update
            if ( ! in_array( $option_name, wp_list_pluck( $this->get_settings_sections(), 'id' ), true ) ) {
                throw new SkException( 'sk_settings_invalid_section', __( 'Invalid section name.', 'sk-core' ), 400 );
            }
            $option_value = $this->sanitize_options( wp_unslash( $_POST['settingsData'] ), 'edit' ); // phpcs:ignore
            $option_value = apply_filters( 'sk_save_settings_value', $option_value, $option_name );
            $old_options  = get_option( $option_name, [] );

            /**
             */
            do_action( 'sk_before_saving_settings', $option_name, $option_value, $old_options );

            update_option( $option_name, $option_value );

            /**
             */
            do_action( 'sk_after_saving_settings', $option_name, $option_value, $old_options );

            // only flush rewrite rules if store url has been changed
            if ( 'sk_general' === $option_name && isset( $old_options['custom_store_url'] ) && $old_options['custom_store_url'] !== $option_value['custom_store_url'] ) {
                sk()->rewrite->register_rule();
                flush_rewrite_rules();
            }

            wp_send_json_success(
                [
                    'settings' => [
                        'name'  => $option_name,
                        'value' => apply_filters( 'sk_get_settings_values', $this->sanitize_options( $option_value, 'read' ), $option_name ),
                    ],
                    'message'  => __( 'Setting has been saved successfully.', 'sk-core' ),
                ]
            );
        } catch ( Exception $e ) {
            $error_code = $e->getCode() ? $e->getCode() : 422;

            wp_send_json_error( new WP_Error( 'sk_settings_error', $e->getMessage() ), $error_code );
        }
    }

    /**
     * Sanitize callback for Settings API
     *
     * @param        $options
     * @param string $context
     *
     * @return mixed
     */
    public function sanitize_options( $options, $context = 'read' ) {
        if ( ! $options ) {
            return $options;
        }

        foreach ( $options as $option_slug => $option_value ) {
            $sanitize_callback = $this->get_sanitize_callback( $option_slug, $context );

            // If callback is set, call it
            if ( $sanitize_callback ) {
                $options[ $option_slug ] = call_user_func( $sanitize_callback, $option_value );
            }
        }

        return $options;
    }

    /**
     * Get sanitization callback for given option slug
     *
     * @param string $slug option slug
     * @param string $context
     *
     * @return mixed string or bool false
     */
    public function get_sanitize_callback( $slug = '', $context = 'read' ) {
        if ( empty( $slug ) ) {
            return false;
        }

        //settings fields are called every time. so we kept it in cache for a small amount of time. to avoid error and better performance.
        $settings_fields = get_transient( 'get_sk_settings_fields' );

        if ( ! $settings_fields ) {
            $settings_fields = $this->get_settings_fields();
            set_transient( 'get_sk_settings_fields', $settings_fields, 90 );
        }

        // Iterate over registered fields and see if we can find proper callback
        foreach ( $settings_fields as $section => $options ) {
            foreach ( $options as $option ) {
                if ( $option['name'] !== $slug ) {
                    continue;
                }

                // Return the callback name
                if ( 'read' === $context ) {
                    return isset( $option['response_sanitize_callback'] ) && is_callable( $option['response_sanitize_callback'] ) ? $option['response_sanitize_callback'] : false;
                }

                if ( 'edit' === $context ) {
                    return isset( $option['sanitize_callback'] ) && is_callable( $option['sanitize_callback'] ) ? $option['sanitize_callback'] : false;
                }
            }
        }

        return false;
    }

    /**
     * Load settings sections and fields
     *
     *
     * @param $data
     *
     * @return void
     */
    public function settings_localize_data( $data ) {
        $data['settings_sections'] = $this->get_settings_sections();

        $settings_fields = [];
        foreach ( $this->get_settings_fields() as $key => $section_fields ) {
            foreach ( $section_fields as $settings_key => $value ) {
                $settings_fields[ $key ][ $value['name'] ] = $value;
            }
        }

        $data['settings_fields'] = $settings_fields;

        return $data;
    }

    /**
     * Get Post Type array
     *
     *
     * @param string $post_type
     *
     * @return array
     */
    public function get_post_type( $post_type ) {
        $pages_array = [];
        $pages       = get_posts(
            [
                'post_type'   => $post_type,
                'numberposts' => - 1,
            ]
        );

        if ( $pages ) {
            foreach ( $pages as $page ) {
                $pages_array[ $page->ID ] = $page->post_title;
            }
        }

        return $pages_array;
    }

    /**
     * Get all settings Sections
     *
     *
     * @return array
     */
    public function get_settings_sections() {
        $sections = [
            [
                'id'                   => 'sk_general',
                'title'                => __( 'General', 'sk-core' ),
                'description'          => __( 'Site Settings and Store Options', 'sk-core' ),
                'document_link'        => '',
                'settings_title'       => __( 'General Settings', 'sk-core' ),
                'settings_description' => __( 'You can configure your general site settings and vendor store options from this settings menu. SK offers countless custom options when setting up your store to provide you with the ultimate flexibility.', 'sk-core' ),
            ],
            [
                'id'                   => 'sk_selling',
                'title'                => __( 'Selling Options', 'sk-core' ),
                'description'          => __( 'Store Settings', 'sk-core' ),
                'document_link'        => '',
                'settings_title'       => __( 'Selling Option Settings', 'sk-core' ),
                'settings_description' => __( 'You can configure vendor capabilities from this menu.', 'sk-core' ),
            ],
            [
                'id'                   => 'sk_pages',
                'title'                => __( 'Page Settings', 'sk-core' ),
                'description'          => __( 'Store Page Settings Manage', 'sk-core' ),
                'document_link'        => '',
                'settings_title'       => __( 'Site and Store Page Settings', 'sk-core' ),
                'settings_description' => __( 'You can configure and setup your necessary page settings from this menu.', 'sk-core' ),
            ],
            [
                'id'                   => 'sk_appearance',
                'title'                => __( 'Appearance', 'sk-core' ),
                'description'          => __( 'Custom Store Appearance', 'sk-core' ),
                'document_link'        => '',
                'settings_title'       => __( 'Appearance Settings', 'sk-core' ),
                'settings_description' => __( 'You can configure your store appearance settings, configure map API, Google reCaptcha and more. SK offers various store header templates to choose from.', 'sk-core' ),
            ],
            [
                'id'                   => 'sk_privacy',
                'title'                => __( 'Privacy Policy', 'sk-core' ),
                'description'          => __( 'Update Store Privacy Policies', 'sk-core' ),
                'settings_title'       => __( 'Privacy Settings', 'sk-core' ),
                'settings_description' => __( 'You can configure your site\'s privacy settings and policy.', 'sk-core' ),
            ],
        ];

        return apply_filters( 'sk_settings_sections', $sections );
    }

    /**
     * Returns all the settings fields
     *
     *
     * @return array settings fields
     */
    public function get_settings_fields() {
        $pages_array = $this->get_post_type( 'page' );

        $general_site_options = apply_filters(
            'sk_settings_general_site_options', [
                'site_options'           => [
                    'name'        => 'site_options',
                    'type'        => 'sub_section',
                    'label'       => __( 'Site Settings', 'sk-core' ),
                    'description' => __( 'Configure your site settings and control access to your site.', 'sk-core' ),
                ],
                'admin_access'           => [
                    'name'    => 'admin_access',
                    'label'   => __( 'Admin Area Access', 'sk-core' ),
                    'desc'    => __( 'Prevent vendors from accessing the wp-admin dashboard area. If HPOS feature is enabled, admin access will be blocked regardless of this setting.', 'sk-core' ),
                    'type'    => 'switcher',
                    'default' => 'on',
                ],
                'custom_store_url'       => [
                    'name'              => 'custom_store_url',
                    'label'             => __( 'Vendor Store URL', 'sk-core' ),
                    /* translators: %s: store url */
                    'desc'              => sprintf( __( 'Define the vendor store URL (%s<strong>[this-text]</strong>/[vendor-name])', 'sk-core' ), site_url( '/' ) ),
                    'default'           => 'store',
                    'type'              => 'text',
                    'sanitize_callback' => [ $this, 'sanitize_custom_store_url' ],
                ],
                'setup_wizard_logo_url'  => [
                    'name'  => 'setup_wizard_logo_url',
                    'label' => __( 'Vendor Setup Wizard Logo', 'sk-core' ),
                    'type'  => 'file',
                    'desc'  => __( 'Recommended logo size ( 270px X 90px ). If no logo is uploaded, site title is shown by default.', 'sk-core' ),
                ],
                'setup_wizard_message'   => [
                    'name'    => 'setup_wizard_message',
                    'label'   => __( 'Vendor Setup Wizard Message', 'sk-core' ),
                    'type'    => 'wpeditor',
                    'default' => __( 'Thank you for choosing The Marketplace to power your online store! This quick setup wizard will help you configure the basic settings. <strong>It’s completely optional and shouldn’t take longer than two minutes.</strong>', 'sk-core' ),
                ],
                'disable_welcome_wizard' => [
                    'name'    => 'disable_welcome_wizard',
                    'label'   => __( 'Disable Welcome Wizard', 'sk-core' ),
                    'desc'    => __( 'Disable welcome wizard for newly registered vendors', 'sk-core' ),
                    'type'    => 'switcher',
                    'default' => 'off',
                    'tooltip' => __( 'If checked, vendors will not be prompted through a guided setup process but redirected straight to the vendor dashboard.', 'sk-core' ),
                ],
            ]
        );

        $general_vendor_store_options = apply_filters(
            'sk_settings_general_vendor_store_options', [
                'vendor_store_options'               => [
                    'name'          => 'vendor_store_options',
                    'type'          => 'sub_section',
                    'label'         => __( 'Vendor Store Settings', 'sk-core' ),
                    'description'   => __( 'Configure your vendor store settings and setup your store policy for vendor.', 'sk-core' ),
                    'content_class' => 'sub-section-styles',
                ],
                'seller_enable_terms_and_conditions' => [
                    'name'    => 'seller_enable_terms_and_conditions',
                    'label'   => __( 'Store Terms and Conditions', 'sk-core' ),
                    'desc'    => __( 'Enable terms and conditions for vendor stores', 'sk-core' ),
                    'type'    => 'switcher',
                    'default' => 'off',
                ],
                'store_products_per_page'            => [
                    'name'    => 'store_products_per_page',
                    'label'   => __( 'Store Products Per Page', 'sk-core' ),
                    'desc'    => __( 'Set how many products to display per page on the vendor store page.', 'sk-core' ),
                    'type'    => 'number',
                    'default' => '12',
                ],
                'enabled_address_on_reg'             => [
                    'name'    => 'enabled_address_on_reg',
                    'label'   => __( 'Enable Address Fields', 'sk-core' ),
                    'desc'    => __( 'Add Address Fields on the Vendor Registration form', 'sk-core' ),
                    'type'    => 'switcher',
                    'default' => 'off',
                ],
            ]
        );

        $general_product_page_options = apply_filters(
            'sk_settings_general_product_page_options', [
                'product_page_options'      => [
                    'name'          => 'product_page_options',
                    'type'          => 'sub_section',
                    'label'         => __( 'Product Page Settings', 'sk-core' ),
                    'description'   => __( 'Configure single product page for vendors.', 'sk-core' ),
                    'content_class' => 'sub-section-styles',
                ],
                'enabled_more_products_tab' => [
                    'name'    => 'enabled_more_products_tab',
                    'label'   => __( 'Enable More Products Tab', 'sk-core' ),
                    'desc'    => __( 'Enable "More Products" tab on the single product page.', 'sk-core' ),
                    'type'    => 'switcher',
                    'default' => 'on',
                ],
            ]
        );

        $selling_option_fees = apply_filters(
            'sk_settings_selling_option_fees', [
                'fee-recipients' => [
                    'name'        => 'fee-recipients',
                    'label'       => __( 'Fee Recipients', 'sk-core' ),
                    'type'        => 'sub_section',
                    'description' => __( 'Define the fees that admin or vendor will recive', 'sk-core' ),
                    'content_class' => 'sub-section-styles',
                ],
                'shipping_fee_recipient' => [
                    'name'    => 'shipping_fee_recipient',
                    'label'   => __( 'Shipping Fee', 'sk-core' ),
                    'desc'    => __( 'Who will be receiving the shipping fees? Note that, tax fees for corresponding shipping method will not be included with shipping fees.', 'sk-core' ),
                    'type'    => 'radio',
                    'options' => [
                        'seller' => __( 'Vendor', 'sk-core' ),
                        'admin'  => __( 'Admin', 'sk-core' ),
                    ],
                    'default' => 'seller',
                ],
                'tax_fee_recipient'      => [
                    'name'    => 'tax_fee_recipient',
                    'label'   => __( 'Product Tax Fee', 'sk-core' ),
                    'desc'    => __( 'Who will be receiving the tax fees for products? Note that, shipping tax fees will not be included with product tax.', 'sk-core' ),
                    'type'    => 'radio',
                    'options' => [
                        'seller' => __( 'Vendor', 'sk-core' ),
                        'admin'  => __( 'Admin', 'sk-core' ),
                    ],
                    'default' => 'seller',
                ],
                'shipping_tax_fee_recipient'      => [
                    'name'    => 'shipping_tax_fee_recipient',
                    'label'   => __( 'Shipping Tax Fee', 'sk-core' ),
                    'desc'    => __( 'Who will be receiving the tax fees for shipping?', 'sk-core' ),
                    'type'    => 'radio',
                    'options' => [
                        'seller' => __( 'Vendor', 'sk-core' ),
                        'admin'  => __( 'Admin', 'sk-core' ),
                    ],
                    'default' => 'seller',
                ],
            ]
        );

        $selling_option_vendor_capability = apply_filters(
            'sk_settings_selling_option_vendor_capability', [
                'selling_capabilities'      => [
                    'name'          => 'selling_capabilities',
                    'label'         => __( 'Vendor Capabilities', 'sk-core' ),
                    'type'          => 'sub_section',
                    'description'   => __( 'Configure your multivendor site settings and vendor selling capabilities.', 'sk-core' ),
                    'content_class' => 'sub-section-styles',
                ],
                'new_seller_enable_selling' => [
                    'name'    => 'new_seller_enable_selling',
                    'label'   => __( 'Enable Selling', 'sk-core' ),
                    'desc'    => __( 'Immediately enable selling for newly registered vendors', 'sk-core' ),
                    'type'    => 'select',
                    'options' => sk_get_container()->get( AdminSettings::class )->new_seller_enable_selling_statuses(),
                    'default' => 'automatically',
                    'tooltip' => __( 'If checked, vendors will have permission to sell immediately after registration. If unchecked, newly registered vendors cannot add products until selling capability is activated manually from admin dashboard.', 'sk-core' ),
                ],
                'one_step_product_create'     => [
                    'name'    => 'one_step_product_create',
                    'label'   => __( 'One Page Product Creation', 'sk-core' ),
                    'desc'    => __( 'Add new product in single page view', 'sk-core' ),
                    'type'    => 'switcher',
                    'default' => 'on',
                    'tooltip' => __( 'If disabled, instead of a single add product page it will open a pop up window or vendor will redirect to product page when adding new product.', 'sk-core' ),
                ],
                'disable_product_popup'     => [
                    'name'    => 'disable_product_popup',
                    'label'   => __( 'Disable Product Popup', 'sk-core' ),
                    'desc'    => __( 'Disable add new product in popup view', 'sk-core' ),
                    'type'    => 'switcher',
                    'default' => 'off',
                    'show_if' => [
                        'sk_selling.one_step_product_create' => [ 'equal' => 'off' ],
                    ],
                    'tooltip' => __( 'If disabled, instead of a pop up window vendor will redirect to product page when adding new product.', 'sk-core' ),
                ],
                'order_status_change'       => [
                    'name'    => 'order_status_change',
                    'label'   => __( 'Order Status Change', 'sk-core' ),
                    'desc'    => __( 'Allow vendor to update order status', 'sk-core' ),
                    'type'    => 'switcher',
                    'default' => 'on',
                    'tooltip' => __( 'Checking this will enable sellers to change the order status. If unchecked, only admin can change the order status.', 'sk-core' ),
                ],
                'sk_any_category_selection'       => [
                    'name'    => 'sk_any_category_selection',
                    'label'   => __( 'Select any category', 'sk-core' ),
                    'desc'    => __( 'Allow vendors to select any category while creating/editing products.', 'sk-core' ),
                    'type'    => 'switcher',
                    'default' => 'off',
                ],
            ]
        );

        // Collect store banner dimensions for croppable fields.
        $store_banner_width  = sk()->is_pro_exists() ? sk_get_option( 'store_banner_width', 'sk_appearance', 625 ) : 625;
        $store_banner_height = sk()->is_pro_exists() ? sk_get_option( 'store_banner_height', 'sk_appearance', 300 ) : 300;

        $settings_fields = [
            'sk_general'    => array_merge(
                $general_site_options,
                $general_vendor_store_options,
                $general_product_page_options
            ),
            'sk_selling'    => apply_filters(
                'sk_settings_selling_options',
                array_merge(
                    $selling_option_fees,
                    $selling_option_vendor_capability
                )
            ),
            'sk_pages'      => [
                'dashboard'     => [
                    'name'        => 'dashboard',
                    'label'       => __( 'Dashboard', 'sk-core' ),
                    'desc'        => __( 'Select a page to show vendor dashboard', 'sk-core' ),
                    'type'        => 'select',
                    'options'     => $pages_array,
                    'placeholder' => __( 'Select page', 'sk-core' ),
                ],
                'my_orders'     => [
                    'name'        => 'my_orders',
                    'label'       => __( 'My Orders', 'sk-core' ),
                    'desc'        => __( 'Select a page to show my orders', 'sk-core' ),
                    'type'        => 'select',
                    'placeholder' => __( 'Select page', 'sk-core' ),
                    'options'     => $pages_array,
                ],
                'store_listing' => [
                    'name'        => 'store_listing',
                    'label'       => __( 'Store Listing', 'sk-core' ),
                    'desc'        => __( 'Select a page to show all stores', 'sk-core' ),
                    'type'        => 'select',
                    'placeholder' => __( 'Select page', 'sk-core' ),
                    'options'     => $pages_array,
                ],
                'reg_tc_page'   => [
                    'name'        => 'reg_tc_page',
                    'type'        => 'select',
                    'desc'        => __( 'Select where you want to add SK pages.', 'sk-core' ),
                    'label'       => __( 'Terms and Conditions Page', 'sk-core' ),
                    'options'     => $pages_array,
                    'tooltip'     => __( 'Select a page to display the Terms and Conditions of your store for Vendors.', 'sk-core' ),
                    'placeholder' => __( 'Select page', 'sk-core' ),
                ],
            ],
            'sk_appearance' => [
                'vendor_layout_options'      => [
                    'name'        => 'vendor_layout_options',
                    'type'        => 'sub_section',
                    'label'       => esc_html__( 'Vendor Dashboard Appearance', 'sk-core' ),
                    'description' => esc_html__( 'Configure the appearance and style of the vendor dashboard.', 'sk-core' ),
                ],
                'vendor_layout_style'        => [
                    'name'    => 'vendor_layout_style',
                    'label'   => esc_html__( 'Vendor Dashboard Style', 'sk-core' ),
                    'desc'    => esc_html__( 'Select the user interface for the vendor dashboard.', 'sk-core' ),
                    'type'    => 'radio',
                    'default' => 'legacy',
                    'options' => [
                        'latest' => esc_html__( 'New UI', 'sk-core' ),
                        'legacy' => esc_html__( 'Legacy UI', 'sk-core' ),
                    ],
                ],
                'appearance_options'         => [
                    'name'          => 'appearance_options',
                    'type'          => 'sub_section',
                    'label'         => esc_html__( 'Store Appearance', 'sk-core' ),
                    'description'   => esc_html__( 'Configure your site appearances.', 'sk-core' ),
                    'content_class' => 'sub-section-styles',
                ],
                'store_map'                  => [
                    'name'    => 'store_map',
                    'label'   => __( 'Show map on Store Page', 'sk-core' ),
                    'desc'    => __( 'Enable map of the store location in the store sidebar', 'sk-core' ),
                    'type'    => 'switcher',
                    'default' => 'on',
                ],
                'map_api_source'             => [
                    'name'               => 'map_api_source',
                    'label'              => __( 'Map API Source', 'sk-core' ),
                    'desc'               => __( 'Which map API source you want to use in your site?', 'sk-core' ),
                    'refresh_after_save' => true,
                    'type'               => 'radio',
                    'default'            => 'google_maps',
                    'options'            => [
                        'google_maps' => __( 'Google Maps', 'sk-core' ),
                        'mapbox'      => __( 'Mapbox', 'sk-core' ),
                    ],
                ],
                'gmap_api_key'               => [
                    'name'    => 'gmap_api_key',
                    'label'   => __( 'Google Map API Key', 'sk-core' ),
                    'desc'    => __( '<a href="https://developers.google.com/maps/documentation/javascript/" target="_blank" rel="noopener noreferrer">API Key</a> is needed to display map on store page', 'sk-core' ),
                    'type'    => 'text',
                    'secret_text' => true,
                    'tooltip' => __( 'Insert Google API Key (with hyperlink) to display store map.', 'sk-core' ),
                    'show_if' => [
                        'map_api_source' => [
                            'equal' => 'google_maps',
                        ],
                    ],
                ],
                'mapbox_access_token'        => [
                    'name'    => 'mapbox_access_token',
                    'label'   => __( 'Mapbox Access Token', 'sk-core' ),
                    'desc'    => __( '<a href="https://docs.mapbox.com/help/how-mapbox-works/access-tokens/" target="_blank" rel="noopener noreferrer">Access Token</a> is needed to display map on store page', 'sk-core' ),
                    'type'    => 'text',
                    'secret_text' => true,
                    'tooltip' => __( 'Insert Mapbox Access Token (with hyperlink) to display store map.', 'sk-core' ),
                    'show_if' => [
                        'map_api_source' => [
                            'equal' => 'mapbox',
                        ],
                    ],
                ],
                'contact_seller'             => [
                    'name'    => 'contact_seller',
                    'label'   => __( 'Show Contact Form on Store Page', 'sk-core' ),
                    'desc'    => __( 'Display a vendor contact form in the store sidebar', 'sk-core' ),
                    'type'    => 'switcher',
                    'default' => 'on',
                ],
                'store_header_template'      => [
                    'name'    => 'store_header_template',
                    'type'    => 'radio_image',
                    'desc'    => __( 'Select a store header for your store.', 'sk-core' ),
                    'label'   => __( 'Store Header Template', 'sk-core' ),
                    'options' => [
                        'default' => SK_CORE_ASSETS . '/images/store-header-templates/default.png',
                        'layout1' => SK_CORE_ASSETS . '/images/store-header-templates/layout1.png',
                        'layout2' => SK_CORE_ASSETS . '/images/store-header-templates/layout2.png',
                        'layout3' => SK_CORE_ASSETS . '/images/store-header-templates/layout3.png',
                    ],
                    'default' => 'default',
                ],
                'default_store_banner'       => [
                    'name'            => 'default_store_banner',
                    'label'           => esc_html__( 'Default Store Banner', 'sk-core' ),
                    'type'            => 'croppable_image',
                    'default'         => SK_CORE_ASSETS . '/images/default-store-banner.png',
                    'restore'         => true,
                    'render_width'    => 625,
                    'cropping_width'  => $store_banner_width,
                    'cropping_height' => $store_banner_height,
                ],
                'default_store_profile'      => [
                    'name'            => 'default_store_profile',
                    'label'           => esc_html__( 'Default Store Profile Picture', 'sk-core' ),
                    'type'            => 'croppable_image',
                    'default'         => SK_CORE_ASSETS . '/images/mystery-person.jpg',
                    'restore'         => true,
                    'render_width'    => 120,
                    'cropping_width'  => 384,
                    'cropping_height' => 384,
                ],
                'enable_theme_store_sidebar' => [
                    'name'    => 'enable_theme_store_sidebar',
                    'label'   => __( 'Enable Store Sidebar From Theme', 'sk-core' ),
                    'desc'    => __( 'Enable showing store sidebar from your theme.', 'sk-core' ),
                    'type'    => 'switcher',
                    'default' => 'off',
                ],
                'hide_vendor_info'           => [
                    'name'    => 'hide_vendor_info',
                    'label'   => __( 'Hide Vendor Info', 'sk-core' ),
                    'desc'    => __( 'Hide vendor contact info from single store page.', 'sk-core' ),
                    'type'    => 'multicheck',
                    'default' => [
                        'email'   => '',
                        'phone'   => '',
                        'address' => '',
                    ],
                    'options' => [
                        'email'   => __( 'Email Address', 'sk-core' ),
                        'phone'   => __( 'Phone Number', 'sk-core' ),
                        'address' => __( 'Store Address', 'sk-core' ),
                    ],
                ],
                'disable_sk_fontawesome' => [
                    'name'    => 'disable_sk_fontawesome',
                    'label'   => __( 'Disable SK FontAwesome', 'sk-core' ),
                    'desc'    => __( "If disabled then sk fontawesome library won't be loaded in frontend", 'sk-core' ),
                    'type'    => 'switcher',
                    'default' => 'off',
                ],
            ],
            'sk_privacy'    => [
                'enable_privacy' => [
                    'name'    => 'enable_privacy',
                    'label'   => __( 'Enable Privacy Policy', 'sk-core' ),
                    'type'    => 'switcher',
                    'desc'    => __( 'Enable privacy policy for vendor store contact form', 'sk-core' ),
                    'default' => 'on',
                ],
                'privacy_page'   => [
                    'name'        => 'privacy_page',
                    'label'       => __( 'Privacy Page', 'sk-core' ),
                    'type'        => 'select',
                    'desc'        => __( 'Select a page to show your privacy policy', 'sk-core' ),
                    'placeholder' => __( 'Select page', 'sk-core' ),
                    'options'     => $pages_array,
                ],
                'privacy_policy' => [
                    'name'    => 'privacy_policy',
                    'label'   => __( 'Privacy Policy', 'sk-core' ),
                    'type'    => 'wpeditor',
                    'default' => __( 'Your personal data will be used to support your experience throughout this website, to manage access to your account, and for other purposes described in our [sk_privacy_policy]', 'sk-core' ),
                    'tooltip' => __( 'Customize the Privacy Policy text that will be displayed on your store.', 'sk-core' ),
                ],
            ],
        ];

        return apply_filters( 'sk_settings_fields', $settings_fields, $this );
    }

    /**
     * Add settings after specific option
     *
     *
     * @param string $section             Name of the section
     * @param string $option              Name of the option after which we wish to add new settings
     * @param array  $additional_settings New settings/options
     * @param array  $settings_fields     Current settings
     *
     * @return array
     */
    public function add_settings_after( $settings_fields, $section, $option, $additional_settings ) {
        $section_fields = $settings_fields[ $section ];

        $after_index = array_search( $option, array_keys( $section_fields ), true );

        $settings_fields[ $section ] = array_merge(
            array_slice( $section_fields, 0, $after_index + 1 ),
            $additional_settings,
            array_slice( $section_fields, $after_index + 1 )
        );

        return $settings_fields;
    }

    /**
     * Add settings nonce to localized vars
     *
     *
     * @param array $vars
     *
     * @return array
     */
    public function add_admin_settings_nonce( $vars ) {
        $vars['admin_settings_nonce'] = wp_create_nonce( 'sk_admin_settings' );

        return $vars;
    }

    /**
     * Get refreshed options for a admin setting
     *
     *
     * @return void
     */
    public function refresh_admin_settings_field_options() {
        try {
            if ( ! check_ajax_referer( 'sk_admin_settings', false, false ) ) {
                throw new SkException(
                    'sk_ajax_unauthorized_operation',
                    __( 'You are not authorized to perform this action.', 'sk-core' ),
                    403
                );
            }

            $section   = ! empty( $_POST['section'] ) ? sanitize_text_field( wp_unslash( $_POST['section'] ) ) : null;
            $field     = ! empty( $_POST['field'] ) ? sanitize_text_field( wp_unslash( $_POST['field'] ) ) : null;

            if ( ! $section || ! $field ) {
                throw new SkException(
                    'sk_ajax_missing_params',
                    __( 'Both section and field params are required.', 'sk-core' )
                );
            }

            $tag = "sk_settings_refresh_option_{$section}_{$field}";

            if ( ! has_filter( $tag ) ) {
                throw new SkException(
                    'sk_ajax_no_filter',
                    __( 'No filter found to refresh the setting options', 'sk-core' )
                );
            }

            $options = apply_filters( $tag, [] );

            wp_send_json_success( $options );
        } catch ( Exception $e ) {
            $this->send_response_error( $e );
        }
    }

    /**
     * SK data clear setting
     *
     *
     * @return array $settings_fields
     */
    public function add_sk_data_clear_setting( $settings_fields ) {
        $settings_fields['data_clear_on_uninstall'] = [
            'name'          => 'data_clear_on_uninstall',
            'label'         => __( 'Data Clear', 'sk-core' ),
            'desc'          => __( 'Delete all data and tables related to SK and SK Pro plugin while deleting the SK plugin.', 'sk-core' ),
            'type'          => 'switcher',
            'default'       => 'off',
            'field_icon'    => __( 'Check this to remove SK related data and table from the database upon deleting the plugin. When you delete the SK lite version, it will also delete all the data related to SK Pro as well. This won\'t happen when the plugins are deactivated..', 'sk-core' ),
            'content_class' => 'data_clear',
        ];

        return $settings_fields;
    }

    /**
     * Sanitize custom store URL to prevent reserved WordPress keywords
     *
     *
     * @param string $value The custom store URL value
     *
     * @return string
     * @throws SkException
     */
    public function sanitize_custom_store_url( $value ) {
        $value = sanitize_text_field( $value );

        if ( empty( $value ) ) {
            return $value;
        }

        $reserved_slugs = sk_get_reserved_url_slugs();

        // Check if the value is in the reserved slugs list.
        if ( in_array( $value, $reserved_slugs, true ) ) {
            throw new SkException(
                'sk_reserved_slug_error',
                sprintf(
                    /* translators: %s: the reserved slug */
                    esc_html__( 'The store URL "%s" is reserved by WordPress and cannot be used. Please choose a different value like "store".', 'sk-core' ),
                    esc_html( $value )
                ),
                400
            );
        }

        return $value;
    }

    /**
     * Set the default settings for vendor layout.
     *
     *
     * @param mixed $option_name
     * @param mixed $option_value
     *
     * @return void|mixed $option_value
     */
    public function set_vendor_latest_layout( $option_value, $option_name ) {
        if ( 'sk_appearance' === $option_name && empty( $option_value['vendor_layout_style'] ) ) {
            $option_value['vendor_layout_style'] = 'legacy';
        }

        return $option_value;
    }
}
