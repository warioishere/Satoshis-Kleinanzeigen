<?php

namespace SK\Core;

use WP_Error;

/**
 * Vendor Registration
 *
 */
class Registration {

    public function __construct() {
        // validate registration
        add_filter( 'woocommerce_process_registration_errors', [ $this, 'validate_registration' ] );
        add_filter( 'woocommerce_registration_errors', [ $this, 'validate_registration' ] );

        // after registration
        add_filter( 'woocommerce_new_customer_data', [ $this, 'set_new_vendor_names' ] );
        add_action( 'woocommerce_created_customer', [ $this, 'save_vendor_info' ], 10, 2 );
    }

    /**
     * Validate vendor registration
     *
     * @param \WP_Error $error
     *
     * @return \WP_Error
     */
    public function validate_registration( $error ) {
        if ( is_checkout() ) {
            return $error;
        }

        if ( defined( 'WP_CLI' ) || defined( 'REST_REQUEST' ) ) {
            return $error;
        }

        if ( ! $this->validate_nonce() ) {
            return new WP_Error( 'nonce_verification_failed', __( 'Nonce verification failed', 'sk-core' ) );
        }

        $allowed_roles = apply_filters( 'sk_register_user_role', [ 'customer', 'seller' ] );

        // is the role name allowed or user is trying to manipulate?
        if ( empty( $_POST['role'] ) || ( ! in_array( $_POST['role'], $allowed_roles, true ) ) ) {
            return new WP_Error( 'role-error', __( 'Cheating, eh?', 'sk-core' ) );
        }

        $role            = sanitize_text_field( wp_unslash( $_POST['role'] ) );
        $shop_url        = isset( $_POST['shopurl'] ) ? sanitize_text_field( wp_unslash( $_POST['shopurl'] ) ) : '';
        $required_fields = apply_filters(
            'sk_seller_registration_required_fields', [
                'fname'    => __( 'Please enter your first name.', 'sk-core' ),
                'lname'    => __( 'Please enter your last name.', 'sk-core' ),
                'phone'    => __( 'Please enter your phone number.', 'sk-core' ),
                'shopname' => __( 'Please provide a shop name.', 'sk-core' ),
                'shopurl'  => __( 'Please provide a unique shop URL.', 'sk-core' ),
            ]
        );

        if ( $role === 'seller' ) {
            foreach ( $required_fields as $field => $msg ) {
                $field_value = isset( $_POST[ $field ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) ) : '';
                if ( empty( $field_value ) ) {
                    return new WP_Error( "$field-error", $msg );
                }
            }

            // Check if the shop URL already not in use.
            if ( ! empty( get_user_by( 'slug', $shop_url ) ) ) {
                return new WP_Error( 'shop-url-error', __( 'Shop URL is not available', 'sk-core' ) );
            }
        }

        return $error;
    }

    /**
     * Inject first and last name to WooCommerce for new vendor registraion
     *
     * @param array $data
     *
     * @return array
     */
    public function set_new_vendor_names( $data ) {
		if ( ! $this->validate_nonce() ) {
            return $data;
        }

        $allowed_roles = apply_filters( 'sk_register_user_role', [ 'customer', 'seller' ] );
        $role          = ( isset( $_POST['role'] ) && in_array( $_POST['role'], $allowed_roles, true ) ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : 'customer';

        $data['role'] = $role;

        if ( $role !== 'seller' ) {
            return $data;
        }

        $data['first_name']    = isset( $_POST['fname'] ) ? sanitize_text_field( wp_unslash( $_POST['fname'] ) ) : '';
        $data['last_name']     = isset( $_POST['lname'] ) ? sanitize_text_field( wp_unslash( $_POST['lname'] ) ) : '';
        $data['user_nicename'] = isset( $_POST['shopurl'] ) ? sanitize_user( wp_unslash( $_POST['shopurl'] ) ) : '';

        return $data;
    }

    /**
     * Adds default sk store settings when a new vendor registers
     *
     * @param int   $user_id
     * @param array $data
     *
     * @return void
     */
    public function save_vendor_info( $user_id, $data ) {

        if ( ! $this->validate_nonce() ) {
            return;
        }

        if ( ! isset( $data['role'] ) || $data['role'] !== 'seller' ) {
            return;
        }

        $social_profiles = [];

        foreach ( sk_get_social_profile_fields() as $key => $item ) {
            $social_profiles[ $key ] = '';
        }

        $sk_settings = [
            'store_name'   => isset( $_POST['shopname'] ) ? sanitize_text_field( wp_unslash( $_POST['shopname'] ) ) : '',
            'social'       => $social_profiles,
            'phone'        => isset( $_POST['phone'] ) ? sk_sanitize_phone_number( wp_unslash( $_POST['phone'] ) ) : '',
            'show_email'   => 'no',
            'location'     => '',
            'find_address' => '',
            'sk_category'  => '',
            'banner'       => 0,
        ];

        update_user_meta( $user_id, 'sk_profile_settings', $sk_settings );
        sk_set_store_name( $user_id, $sk_settings['store_name'] );

        do_action( 'sk_new_seller_created', $user_id, $sk_settings );
    }

    /**
     * Validate nonce for seller registration.
     * This function checks the nonce value to ensure the request is valid and secure.
     * If the "sk_register_nonce_check" filter returns false, the validation is bypassed,
     * third-party developers to override the nonce check if necessary.
     *
     * @return bool True if nonce is valid or validation is bypassed, false otherwise.
     */
	protected function validate_nonce() {
		if ( apply_filters( 'sk_register_nonce_check', true ) ) {
			$nonce_value = isset( $_POST['_wpnonce'] ) ? sanitize_key( $_POST['_wpnonce'] ) : '';
			$nonce_value = isset( $_POST['woocommerce-register-nonce'] ) ? sanitize_key( $_POST['woocommerce-register-nonce'] ) : $nonce_value;

			return ! empty( $nonce_value ) && wp_verify_nonce( $nonce_value, 'woocommerce-register' );
		}

		// Bypass validation if the filter returns false
		return true;
	}
}
