<?php

namespace SK\Core\Frontend\MyAccount;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * SK Become Vendor Class.
 *
 *
 */
class BecomeAVendor {
    /**
     * Class Constructor.
     *
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Init Hooks Method.
     *
     *
     * @return void
     */
    public function init_hooks() {
        add_action( 'template_redirect', [ $this, 'become_a_seller_form_handler' ] );
        add_action( 'woocommerce_after_my_account', [ $this, 'render_become_a_vendor_section' ] );
        add_action( 'woocommerce_account_account-migration_endpoint', [ $this, 'load_customer_to_vendor_update_template' ] );

        // Remove "become a vendor" feature from older version of SK Pro.
        add_action( 'init', [ $this, 'remove_account_update_feature_from_sk_ext' ], 5 );
    }

    /**
     * Remove Account Update Feature from SK Pro.
     *
     *
     * @return void
     */
    public function remove_account_update_feature_from_sk_ext() {
        // If SK Pro plugin activated.
        if ( ! sk()->is_pro_exists() ) {
            return;
        }

        // If currently activated SK Pro version below "3.7.25".
        if ( version_compare( SK_CORE_VERSION, '3.7.25', '>=' ) ) {
            return;
        }

        // Remove actions related to SK Pro "Become A Vendor" feature.
        remove_action( 'init', [ sk_ext(), 'account_migration_endpoint' ] );
        remove_action( 'woocommerce_account_account-migration_endpoint', [ sk_ext(), 'account_migration' ] );
        remove_action( 'woocommerce_after_my_account', [ sk_ext(), 'sk_account_migration_button' ] );
    }

    /**
     * Become A Seller Form Handler.
     *
     *
     * @return void
     */
    public function become_a_seller_form_handler() {
        if ( ! isset( $_POST['sk_migration'] ) || ! isset( $_POST['sk_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['sk_nonce'] ) ), 'account_migration' ) ) {
            return;
        }

        $user   = get_userdata( get_current_user_id() );
        $errors = [];

        if ( ! $user ) {
            wc_add_notice( __( 'You need to login before applying for vendor.', 'sk-core' ), 'error' );

            return;
        }

        if ( sk_is_user_seller( $user->ID ) ) {
            wc_add_notice( __( 'You are already a vendor.', 'sk-core' ), 'error' );

            return;
        }

        $required_field_checks = apply_filters(
            'sk_customer_migration_required_fields',
            [
                'fname'    => __( 'Enter your first name.', 'sk-core' ),
                'shopname' => __( 'Enter your shop name.', 'sk-core' ),
                'phone'    => __( 'Enter your phone number.', 'sk-core' ),
            ]
        );

        foreach ( $required_field_checks as $field => $error ) {
            if ( empty( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) ) ) {
                $errors[] = $error;
                wc_add_notice( $error, 'error' );
            }
        }

        if ( $errors ) {
            return;
        }

        sk_user_update_to_seller(
            $user, [
                'fname'    => isset( $_POST['fname'] ) ? sanitize_text_field( wp_unslash( $_POST['fname'] ) ) : '',
                'lname'    => isset( $_POST['lname'] ) ? sanitize_text_field( wp_unslash( $_POST['lname'] ) ) : '',
                'shopname' => isset( $_POST['shopname'] ) ? sanitize_text_field( wp_unslash( $_POST['shopname'] ) ) : '',
                'address'  => isset( $_POST['sk_address'] ) ? wc_clean( wp_unslash( $_POST['sk_address'] ) ) : '',
                'phone'    => isset( $_POST['phone'] ) ? sk_sanitize_phone_number( wp_unslash( $_POST['phone'] ) ) : '',
                'shopurl'  => isset( $_POST['shopurl'] ) ? sanitize_text_field( wp_unslash( $_POST['shopurl'] ) ) : '',
            ]
        );

        $url = sk_get_navigation_url();

        wp_safe_redirect( apply_filters( 'sk_customer_migration_redirect', $url ) );
        exit();
    }

    /**
     * Render Become A Vendor Section.
     *
     *
     * @return void
     */
    public function render_become_a_vendor_section() {
        // If user is already a seller.
        if ( sk_is_user_seller( get_current_user_id() ) ) {
            return;
        }

        sk_get_template_part( 'account/become-a-vendor-section', '' );
    }

    /**
     * Load Customer to Vendor Update Form Template.
     *
     *
     * @return void
     */
    public function load_customer_to_vendor_update_template() {
        $user_id       = get_current_user_id();
        $error_message = '';

        if ( is_admin() ) {
            return;
        }

        if ( ! $user_id ) {
            $error_message = __( 'You need to login before applying for vendor.', 'sk-core' );
        } elseif ( $user_id && sk_is_user_seller( $user_id ) ) {
            $error_message = __( 'You are already a vendor.', 'sk-core' );
        } elseif ( $user_id && current_user_can( 'manage_options' ) ) {
            $error_message = __( 'You are an administrator. Please use sk admin settings to enable your selling capabilities.', 'sk-core' );
        }

        if ( $error_message ) {
            if ( WC()->session && function_exists( 'wc_add_notice' ) && function_exists( 'wc_print_notices' ) ) {
                wc_add_notice( $error_message, 'error' );
                // print error message
                wc_print_notices();
            }

            return;
        }

        wp_enqueue_script( 'sk-vendor-registration' );

        $data = [
            'user_id'     => $user_id,
            'first_name'  => get_user_meta( $user_id, 'first_name', true ),
            'last_name'   => get_user_meta( $user_id, 'last_name', true ),
            'shop_url'    => get_user_meta( $user_id, 'nickname', true ),
            'show_toc'    => sk_get_option( 'enable_tc_on_reg', 'sk_general', 'on' ),
            'toc_page_id' => (int) sk_get_option( 'reg_tc_page', 'sk_pages', 0 ),
            'shop_name'   => '',
            'phone'       => '',
        ];

        if ( isset( $_POST['sk_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['sk_nonce'] ) ), 'account_migration' ) ) {
            $data['first_name'] = isset( $_POST['fname'] ) ? sanitize_text_field( wp_unslash( $_POST['fname'] ) ) : $data['first_name'];
            $data['last_name']  = isset( $_POST['lname'] ) ? sanitize_text_field( wp_unslash( $_POST['lname'] ) ) : $data['last_name'];
            $data['shop_url']   = isset( $_POST['shopurl'] ) ? sanitize_text_field( wp_unslash( $_POST['shopurl'] ) ) : $data['shop_url'];
            $data['shop_name']  = isset( $_POST['shopname'] ) ? sanitize_text_field( wp_unslash( $_POST['shopname'] ) ) : $data['shop_name'];
            $data['phone']      = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : $data['phone'];
        }

        sk_get_template_part( 'account/update-customer-to-vendor', '', $data );
    }
}
