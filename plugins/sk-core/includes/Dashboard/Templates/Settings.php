<?php

namespace SK\Core\Dashboard\Templates;

use SK\Core\Utilities\VendorUtil;
use WP_Error;

/**
 * SK settings Class
 *
 * @author weDves
 */
class Settings {

    public $currentuser;
    public $profile_info;

    /**
     * Loading autometically when class initiate
     *
     *
     * @return void
     */
    public function __construct() {
        add_action( 'sk_settings_content_inside_before', [ $this, 'show_enable_seller_message' ] );
        add_action( 'sk_settings_content_area_header', [ $this, 'render_settings_header' ], 10 );
        add_action( 'sk_settings_content_area_header', [ $this, 'render_settings_help' ], 15 );
        add_action( 'sk_settings_content', [ $this, 'render_settings_content' ], 10 );
        add_filter( 'sk_payment_method_title', [ $this, 'get_method_frontend_title' ], 10, 2 );
    }

    /**
     * Show Seller Enable Error Message
     *
     *
     * @return void
     */
    public function show_enable_seller_message() {
        $user_id = get_current_user_id();

        if ( ! sk_is_seller_enabled( $user_id ) ) {
            sk_seller_not_enabled_notice();
        }
    }

    /**
     * Render Settings Header
     *
     *
     * @return void
     */
    public function render_settings_header() {
        global $wp;
        $is_store_setting = false;

        if ( isset( $wp->query_vars['settings'] ) && $wp->query_vars['settings'] === 'store' ) {
            $heading          = __( 'Settings', 'sk-core' );
            $is_store_setting = true;
        } elseif ( isset( $wp->query_vars['settings'] ) && 'payment' === substr( $wp->query_vars['settings'], 0, 7 ) ) {
            $heading = __( 'Payment Method', 'sk-core' );
            $slug    = str_replace( 'payment-manage-', '', $wp->query_vars['settings'] );
            $heading = $this->get_payment_heading( $slug, $heading );
        } else {
            $heading = apply_filters( 'sk_dashboard_settings_heading_title', __( 'Settings', 'sk-core' ), $wp->query_vars['settings'] );
        }

        sk_get_template_part(
            'settings/header', '', [
                'heading'          => $heading,
                'is_store_setting' => $is_store_setting,
            ]
        );
    }

    /**
     * Render Settings help
     *
     *
     * @return void
     */
    public function render_settings_help() {
        global $wp;

        $help_text = '';

        if ( isset( $wp->query_vars['settings'] ) && $wp->query_vars['settings'] === 'payment' ) {
            $help_text = __( 'These are the payment methods available for you. Please update your payment information below to receive your store payments seamlessly.', 'sk-core' );
        }

        if ( $help_text = apply_filters( 'sk_dashboard_settings_helper_text', $help_text, $wp->query_vars['settings'] ) ) { // phpcs:ignore
            sk_get_template_part(
                'global/sk-help', '', [
                    'help_text' => $help_text,
                ]
            );
        }
    }

    /**
     * Render Settings Content
     *
     *
     * @return void
     */
    public function render_settings_content() {
        global $wp;

        // return if we are not in settings page
        if ( ! isset( $wp->query_vars['settings'] ) ) {
            return;
        }

        // check if user have permission to view settings page
        if ( ! current_user_can( 'sk_view_store_settings_menu' ) ) {
            sk_get_template_part(
                'global/sk-error', '', [
                    'deleted' => false,
                    'message' => __( 'You have no permission to view this page', 'sk-core' ),
                ]
            );

            return;
        }

        // load store settings page content
        if ( 'store' === $wp->query_vars['settings'] ) {
            $this->load_store_content();
            // load payment settings page content
        } elseif ( 'payment' === substr( $wp->query_vars['settings'], 0, 7 ) ) {
            $this->load_payment_content( substr( $wp->query_vars['settings'], 7 ) );
        }

        do_action( 'sk_render_settings_content', $wp->query_vars );
    }

    /**
     * Load Store Content
     *
     *
     * @return void
     */
    public function load_store_content() {
        $current_user   = sk_get_current_user_id();
        $profile_info   = sk_get_store_info( $current_user );
        $default_banner = VendorUtil::get_vendor_default_banner_url();
        $default_avatar = VendorUtil::get_vendor_default_avatar_url();

        sk_get_template_part(
            'settings/store-form', '', [
                'current_user'       => $current_user,
                'profile_info'       => $profile_info,
                'default_banner_url' => $default_banner,
                'default_avatar_url' => $default_avatar,
            ]
        );
    }

    /**
     * Get sellers connected and not connected payment methods.
     *
     * @param $seller_id
     *
     * @param $active_payment_methods
     *
     * @return array
     */
    public function get_seller_payment_methods( $seller_id = '', $active_payment_methods = [] ): array {
        if ( empty( $active_payment_methods ) ) {
            $active_payment_methods = [];
        }

        // methods which are inactive in SK > Settings > Payment Options have an empty value so filter them out
        $active_payment_methods = array_filter(
            $active_payment_methods, function ( $value ) {
				return ! empty( $value );
			}
        );

        $payment_method_ids = array_keys( $active_payment_methods );

        if ( empty( $seller_id ) ) {
            $seller_id = sk_get_current_user_id();
        }

        $seller_connected_payment_method_ids = array_filter(
            $payment_method_ids,
            function ( $payment_method_id ) use ( $seller_id ) {
                return $this->is_seller_connected( $payment_method_id, $seller_id );
            }
        );

        $seller_disconnected_payment_method_ids = array_diff( $payment_method_ids, $seller_connected_payment_method_ids );
        $seller_disconnected_payment_methods    = $this->get_payment_methods( $seller_disconnected_payment_method_ids );
        $seller_connected_payment_methods       = $this->get_payment_methods( $seller_connected_payment_method_ids );

        return [
            'connected_methods'    => $seller_connected_payment_methods,
            'disconnected_methods' => $seller_disconnected_payment_methods,
            'active_methods'       => $active_payment_methods,
        ];
    }

    /**
     * Validate payment access and check active methods
     *
     *
     * @param array $active_methods
     *
     * @return bool Returns true if validation passes, false otherwise
     */
    protected function validate_payment_access( $active_methods ) {
        // Check staff permissions
        if ( ! current_user_can( 'sk_view_store_payment_menu' ) ) {
            sk_get_template_part(
                'global/sk-error',
                '',
                [
                    'deleted' => false,
                    'message' => esc_html__( 'You have no permission to view this page', 'sk-core' ),
                ]
            );

            return false;
        }

        // Check if payment methods are available
        if ( empty( $active_methods ) ) {
            sk_get_template_part(
                'global/sk-error',
                '',
                [
                    'deleted' => false,
                    'message' => esc_html__( 'No payment method is available. Please contact site admin.', 'sk-core' ),
                ]
            );

            return false;
        }

        return true;
    }

    /**
     * Load Payment Content
     *
     *
     * @param string $slug_suffix
     *
     * @return void
     */
    public function load_payment_content( $slug_suffix ) {
        $seller_id            = sk_get_current_user_id();
        $data                 = $this->get_seller_payment_methods( $seller_id );
        $connected_methods    = $data['connected_methods'];
        $disconnected_methods = $data['disconnected_methods'];
        $active_methods       = $data['active_methods'];

        // Check permissions and validate payment methods
        if ( ! $this->validate_payment_access( $active_methods ) ) {
            return;
        }

        /*
         * If we are requesting a single payment method page (to edit or for first time setup)
         * then we have the corresponding payment method key in the url.
         */
        $method_key   = str_replace( '-manage-', '', $slug_suffix );
        $is_edit_mode = false;

        /*
         * If payment method key has /edit suffix then we are trying to edit the method,
         * otherwise we are doing a initial setup for that payment method.
         */
        if ( false !== stripos( $method_key, '-edit' ) ) {
            $is_edit_mode = true;
            $method_key   = str_replace( '-edit', '', $method_key ); // removing '/edit' suffix to get payment method key
        }

        $profile_info = get_user_meta( $seller_id, 'sk_profile_settings', true );

        if ( $is_edit_mode && 'bank' === $method_key ) {
            $profile_info['is_edit_mode'] = $is_edit_mode;
        }

        // Template arguments
        $args = [
            'current_user' => $seller_id,
            'profile_info' => $profile_info,
        ];

        if ( empty( $method_key ) ) { // payment method list page arguments
            $args = array_merge(
                $args,
                [
                    'methods'        => $connected_methods,
                    'unused_methods' => $disconnected_methods,
                ]
            );

            // Show the payment method list template
            sk_get_template_part( 'settings/payment', '', $args );

            return;
        }

        // Get the single payment method for the $method_key
        $method = [];
        $args   = array_merge(
            $args,
            [
                'method'     => $method,
                'method_key' => $method_key,
            ]
        );

        if ( ! in_array( $method_key, array_keys( $active_methods ), true ) || empty( $method ) || ! isset( $method['callback'] ) || ! is_callable( $method['callback'] ) ) {
            sk_get_template_part(
                'global/sk-error',
                '',
                [
                    'deleted' => false,
                    'message' => __( 'Invalid payment method. Please contact site admin', 'sk-core' ),
                ]
            );

            return;
        }

        // todo: this connect message is coming from sk pro, need to move this to sk pro
        if ( isset( $_GET['status'] ) && isset( $_GET['message'] ) ) { // phpcs:ignore
            $connect_status = sanitize_text_field( wp_unslash( $_GET['status'] ) ); // phpcs:ignore
            $status_message = wp_kses_post( wp_unslash( $_GET['message'] ) ); // phpcs:ignore

            $args['connect_status'] = $connect_status;
            $args['status_message'] = $status_message;
        }

        // Show the single payment method page
        sk_get_template_part( 'settings/payment', 'manage', $args );
    }

    /**
     * Save settings via ajax
     *
     *
     * @return void
     */
    public function ajax_settings() {
        $uid     = get_current_user_id();
        $form_id = isset( $_POST['form_id'] ) ? sanitize_text_field( wp_unslash( $_POST['form_id'] ) ) : '(missing)'; // phpcs:ignore

        if ( ! sk_is_user_seller( $uid ) ) {
            wp_send_json_error( __( 'Are you cheating?', 'sk-core' ) );
        }

        if ( ! isset( $_POST['_wpnonce'] ) ) {
            // there can be multiple nonce action, so we are validating nonce later on
            wp_send_json_error( __( 'Are you cheating?', 'sk-core' ) );
        }

        switch ( $form_id ) { // phpcs:ignore
            case 'profile-form':
                if ( ! current_user_can( 'sk_view_store_social_menu' ) ) {
                    wp_send_json_error( __( 'Pemission denied social', 'sk-core' ) );
                }

                if ( ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'sk_profile_settings_nonce' ) ) {
                    wp_send_json_error( __( 'Are you cheating?', 'sk-core' ) );
                }

                $ajax_validate = $this->profile_validate();
                break;

            case 'store-form':
                if ( ! current_user_can( 'sk_view_store_settings_menu' ) ) {
                    wp_send_json_error( __( 'Pemission denied', 'sk-core' ) );
                }

                if ( ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'sk_store_settings_nonce' ) ) {
                    wp_send_json_error( __( 'Are you cheating?', 'sk-core' ) );
                }

                $ajax_validate = $this->store_validate();
                break;

            case 'payment-form':
                if ( ! current_user_can( 'sk_view_store_payment_menu' ) ) {
                    wp_send_json_error( __( 'Pemission denied', 'sk-core' ) );
                }

                if ( ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'sk_payment_settings_nonce' ) ) {
                    wp_send_json_error( __( 'Are you cheating?', 'sk-core' ) );
                }

                $ajax_validate = apply_filters( 'sk_bank_payment_validation_error', $this->payment_validate() );
                break;
            default:
                $ajax_validate = new WP_Error( 'form_id_not_matched', __( 'Failed to process data, invalid submission', 'sk-core' ) );
        }

        if ( is_wp_error( $ajax_validate ) ) {
            wp_send_json_error( $ajax_validate->get_error_messages() );
        }

        // we are good to go
        $this->insert_settings_info();

        $success_msg = __( 'Your information has been saved successfully', 'sk-core' );

        $data = apply_filters(
            'sk_ajax_settings_response', [
                'msg' => $success_msg,
            ]
        );

        wp_send_json_success( $data );
    }

    /**
     * Validate profile settings
     *
     * @return bool|WP_Error
     */
    private function profile_validate() {
        if ( ! isset( $_POST['sk_update_profile_settings'] ) ) {
            return false;
        }

        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'sk_profile_settings_nonce' ) ) {
            wp_die( esc_attr__( 'Are you cheating?', 'sk-core' ) );
        }

        $error = new WP_Error();

        if ( isset( $_POST['setting_category'] ) ) {
            if ( ! is_array( $_POST['setting_category'] ) || ! count( $_POST['setting_category'] ) ) {
                $error->add( 'sk_type', __( 'Store type required', 'sk-core' ) );
            }
        }

        if ( ! empty( $_POST['setting_paypal_email'] ) ) {
            $email = sanitize_email( wp_unslash( $_POST['setting_paypal_email'] ) );

            if ( empty( $email ) ) {
                $error->add( 'sk_email', __( 'Invalid email', 'sk-core' ) );
            }
        }

        if ( $error->get_error_codes() ) {
            return $error;
        }

        return true;
    }

    /**
     * Validate store settings
     *
     * @return bool|WP_Error
     */
    private function store_validate() {
        if ( ! isset( $_POST['sk_update_store_settings'] ) ) {
            return false;
        }

        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'sk_store_settings_nonce' ) ) {
            wp_die( esc_attr__( 'Are you cheating?', 'sk-core' ) );
        }

        $error = new WP_Error();

        $sk_name = isset( $_POST['sk_store_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sk_store_name'] ) ) : '';

        if ( empty( $sk_name ) ) {
            $error->add( 'sk_name', __( 'Store name required', 'sk-core' ) );
        }

        $sk_gravatar = isset( $_POST['sk_gravatar'] ) ? absint( $_POST['sk_gravatar'] ) : 0;
        if ( empty( $sk_gravatar ) ) {
            $error->add( 'sk_gravatar', __( 'Profilbild ist erforderlich', 'sk-core' ) );
        }

        if ( isset( $_POST['setting_category'] ) ) {
            if ( ! is_array( $_POST['setting_category'] ) || ! count( $_POST['setting_category'] ) ) {
                $error->add( 'sk_type', __( 'Store type required', 'sk-core' ) );
            }
        }

        if ( ! empty( $_POST['setting_paypal_email'] ) ) {
            $email = sanitize_email( wp_unslash( $_POST['setting_paypal_email'] ) );

            if ( empty( $email ) ) {
                $error->add( 'sk_email', __( 'Invalid email', 'sk-core' ) );
            }
        }

        if ( $error->get_error_codes() ) {
            return $error;
        }

        return true;
    }

    /**
     * Validate payment settings
     *
     *
     * @return bool|WP_Error
     */
    private function payment_validate() {
        if ( ! isset( $_POST['sk_update_payment_settings'] ) ) {
            return false;
        }

        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'sk_payment_settings_nonce' ) ) {
            wp_die( esc_attr__( 'Are you cheating?', 'sk-core' ) );
        }

        $error = new WP_Error();

        if ( ! empty( $_POST['settings']['paypal'] ) && isset( $_POST['settings']['paypal']['email'] ) ) {
            $email = sanitize_email( wp_unslash( $_POST['settings']['paypal']['email'] ) );

            if ( isset( $_POST['settings']['paypal']['disconnect'] ) ) {
                $_POST['settings']['paypal']['email'] = '';
            } elseif ( empty( $email ) ) {
                $error->add( 'sk_email', __( 'Invalid email', 'sk-core' ) );
            }
        }

        if ( ! empty( $_POST['settings']['skrill'] ) && isset( $_POST['settings']['skrill']['email'] ) ) {
            $email = sanitize_email( wp_unslash( $_POST['settings']['skrill']['email'] ) );

            if ( isset( $_POST['settings']['skrill']['disconnect'] ) ) {
                $_POST['settings']['skrill']['email'] = '';
            } elseif ( empty( $email ) ) {
                $error->add( 'sk_email', __( 'Invalid email', 'sk-core' ) );
            }
        }

        $is_disconnect = isset( $_POST['settings']['bank']['disconnect'] );
        if ( ! empty( $_POST['settings']['bank'] ) && ! $is_disconnect ) {
            $payment_fields = sk_bank_payment_required_fields();
            /**
             * Here we are validating the bank payment required fields,
             * if the payment field is required and the payment field from post data is given.
             * And if the filed in account type and the given value is personal or business.
             */
            foreach ( $payment_fields as $key => $payment_field ) {
                if ( ! empty( $payment_field ) && empty( $_POST['settings']['bank'][ $key ] ) ) {
                    $error->add( 'sk_bank_' . $key, $payment_field );
                } elseif ( ! empty( $payment_field ) && $key === 'ac_type' && ! in_array( $_POST['settings']['bank'][ $key ], [ 'personal', 'business' ], true ) ) {
                    $error->add( 'sk_bank_ac_type', __( 'Invalid Account Type', 'sk-core' ) );
                }
            }

            if ( empty( $_POST['settings']['bank']['declaration'] ) ) {
                $error->add( 'sk_bank_declaration', __( 'You must attest that the bank account is yours.', 'sk-core' ) );
            }
        }

        if ( $error->get_error_codes() ) {
            return $error;
        }

        return true;
    }

    /**
     * Save store settings
     *
     * @return void
     */
    public function insert_settings_info() {
        $store_id = sk_get_current_user_id();
        wp_cache_delete( $store_id, 'user_meta' );
        clean_user_cache( $store_id );
        $existing_sk_settings = get_user_meta( $store_id, 'sk_profile_settings', true );
        $prev_sk_settings     = ! empty( $existing_sk_settings ) ? $existing_sk_settings : [];


        if ( ! isset( $_POST['_wpnonce'] ) ) {
            return;
        }

        if ( wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'sk_profile_settings_nonce' ) ) {
            // update profile settings info
            $social         = isset( $_POST['settings']['social'] ) ? array_map( 'esc_url_raw', (array) wp_unslash( $_POST['settings']['social'] ) ) : [];
            $social_fields  = sk_get_social_profile_fields();
            $sk_settings = [ 'social' => [] ];

            foreach ( $social as $key => $value ) {
                if ( isset( $social_fields[ $key ] ) ) {
                    $sk_settings['social'][ $key ] = $social[ $key ];
                }
            }
        } elseif ( wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'sk_store_settings_nonce' ) ) {
            // Server-side validation: store name is required
            $sk_name = isset( $_POST['sk_store_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sk_store_name'] ) ) : '';
            if ( empty( $sk_name ) ) {
                if ( function_exists( 'sk_add_notice' ) ) {
                    sk_add_notice( __( 'Bitte gib einen Anzeigenamen für deinen Shop ein.', 'sk-core' ), 'error' );
                }
                return;
            }

            $default_locations = sk_get_option( 'location', 'sk_geolocation' );

            if ( ! is_array( $default_locations ) || empty( $default_locations ) ) {
                $default_locations = [
                    'latitude'  => '',
                    'longitude' => '',
                    'address'   => '',
                ];
            }

            $find_address     = ! empty( $_POST['find_address'] ) ? sanitize_text_field( wp_unslash( $_POST['find_address'] ) ) : $default_locations['address'];
            $default_location = $default_locations['latitude'] . ',' . $default_locations['longitude'];
            $location         = ! empty( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : $default_location;

            // Update store settings info.
            $sk_settings = [
                'store_name'               => isset( $_POST['sk_store_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sk_store_name'] ) ) : '',
                'address'                  => isset( $_POST['sk_address'] ) ? wc_clean( wp_unslash( $_POST['sk_address'] ) ) : $prev_sk_settings['address'],
                'location'                 => $location,
                'find_address'             => $find_address,
                'banner'                   => isset( $_POST['sk_banner'] ) ? absint( $_POST['sk_banner'] ) : 0,
                'phone'                    => isset( $_POST['setting_phone'] ) ? sk_sanitize_phone_number( wp_unslash( $_POST['setting_phone'] ) ) : 'no',
                'show_email'               => isset( $_POST['setting_show_email'] ) ? sanitize_text_field( wp_unslash( $_POST['setting_show_email'] ) ) : 'no',
                'gravatar'                 => isset( $_POST['sk_gravatar'] ) ? absint( $_POST['sk_gravatar'] ) : 0,
                'enable_tnc'               => isset( $_POST['sk_store_tnc_enable'] ) && 'on' === sanitize_text_field( wp_unslash( $_POST['sk_store_tnc_enable'] ) ) ? 'on' : 'off',
                'store_tnc'                => isset( $_POST['sk_store_tnc'] ) ? wp_kses_post( wp_unslash( $_POST['sk_store_tnc'] ) ) : '',
                'vendor_biography'     => isset( $_POST['vendor_biography'] ) ? wp_kses_post( wp_unslash( $_POST['vendor_biography'] ) ) : ( $prev_sk_settings['vendor_biography'] ?? '' ),
                'telegram'             => isset( $_POST['telegram'] ) ? sanitize_text_field( wp_unslash( $_POST['telegram'] ) ) : ( $prev_sk_settings['telegram'] ?? '' ),
                'show_telegram'        => isset( $_POST['show_telegram'] ) ? '1' : '',
                'twitter'              => isset( $_POST['twitter'] ) ? sanitize_text_field( wp_unslash( $_POST['twitter'] ) ) : ( $prev_sk_settings['twitter'] ?? '' ),
                'show_twitter'         => isset( $_POST['show_twitter'] ) ? '1' : '',
                'phone_number'         => isset( $_POST['phone_number'] ) ? sanitize_text_field( $_POST['phone_number'] ) : ( $prev_sk_settings['phone_number'] ?? '' ),
                'show_phone_number'    => isset( $_POST['show_phone_number'] ) ? '1' : '',
                'nostr'                => isset( $_POST['nostr'] ) ? sanitize_text_field( $_POST['nostr'] ) : ( $prev_sk_settings['nostr'] ?? '' ),
                'show_nostr'           => isset( $_POST['show_nostr'] ) ? '1' : '',
            ];

            // E-Mail Verarbeitung
            $user = get_userdata( $store_id );
            $current_email = $user instanceof \WP_User ? (string) $user->user_email : '';
            $email_raw = isset( $_POST['setting_email'] ) ? wp_unslash( $_POST['setting_email'] ) : '';
            $email_san = $email_raw !== '' ? sanitize_email( $email_raw ) : '';
            if ( $email_raw !== '' && $email_san !== '' && is_email( $email_san ) ) {
                $exists = email_exists( $email_san );
                if ( ! $exists || (int) $exists === (int) $store_id ) {
                    if ( $user instanceof \WP_User && $email_san !== $current_email ) {
                        wp_update_user( [ 'ID' => $store_id, 'user_email' => $email_san ] );
                    }
                    $sk_settings['setting_email'] = $email_san;
                }
            } elseif ( $current_email ) {
                $sk_settings['setting_email'] = $current_email;
            }

            update_user_meta( $store_id, 'sk_store_name', $sk_settings['store_name'] );

            // Sync separate geo user meta so product geolocation picks up the vendor's address.
            if ( ! empty( $location ) ) {
                $coords = explode( ',', $location );
                if ( count( $coords ) === 2 ) {
                    update_user_meta( $store_id, 'sk_geo_latitude', trim( $coords[0] ) );
                    update_user_meta( $store_id, 'sk_geo_longitude', trim( $coords[1] ) );
                }
            }
            if ( ! empty( $find_address ) ) {
                update_user_meta( $store_id, 'sk_geo_address', $find_address );
            }
        } elseif ( wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'sk_payment_settings_nonce' ) ) {

            //update payment settings info
            $sk_settings = [
                'payment' => $prev_sk_settings['payment'],
            ];

            if ( isset( $_POST['settings']['bank'] ) ) {
                $bank = wc_clean( wp_unslash( $_POST['settings']['bank'] ) );

                $sk_settings['payment']['bank'] = [
                    'ac_name'        => $bank['ac_name'],
                    'ac_number'      => $bank['ac_number'],
                    'bank_name'      => $bank['bank_name'],
                    'bank_addr'      => $bank['bank_addr'],
                    'routing_number' => $bank['routing_number'],
                    'iban'           => $bank['iban'],
                    'swift'          => $bank['swift'],
                    'ac_type'        => $bank['ac_type'],
                    'declaration'    => isset( $bank['declaration'] ) ? $bank['declaration'] : '',
                ];
            }

            if ( isset( $_POST['settings']['paypal']['email'] ) ) {
                $sk_settings['payment']['paypal'] = [
                    'email' => sanitize_email( wp_unslash( $_POST['settings']['paypal']['email'] ) ),
                ];
            }
        }

        $sk_settings = array_merge( $prev_sk_settings, $sk_settings );

        $sk_settings = apply_filters( 'sk_store_profile_settings_args', $sk_settings, $store_id );

        $save_result = update_user_meta( $store_id, 'sk_profile_settings', $sk_settings );

        do_action( 'sk_store_profile_saved', $store_id, $sk_settings, $prev_sk_settings );

        // Bust page cache so settings page shows fresh data
        $user_hash = '';
        foreach ( $_COOKIE as $k => $v ) {
            if ( str_starts_with( $k, 'wordpress_logged_in_' ) ) {
                $user_hash = md5( $v );
                break;
            }
        }
        if ( $user_hash ) {
            wp_cache_set( 'sk_dcv_' . $user_hash, time(), 'sk_page_cache', 3600 );
        }

        if ( ! defined( 'DOING_AJAX' ) ) {
            $_GET['message'] = 'profile_saved';
        }
    }

    /**
     * SK Get Category Format
     *
     *
     * @return array
     */
    public function get_sk_categories() {
        $sk_category = [
            'book'       => __( 'Book', 'sk-core' ),
            'dress'      => __( 'Dress', 'sk-core' ),
            'electronic' => __( 'Electronic', 'sk-core' ),
        ];

        return apply_filters( 'sk_category', $sk_category );
    }

    /**
     * Get proper heading for payments of vendor dashboard payment settings
     *
     *
     * @param string $slug
     * @param string $heading
     *
     * @return string
     */
    private function get_payment_heading( $slug, $heading ) {
        switch ( $slug ) {
            case 'bank':
            case 'bank-edit':
                $heading = __( 'Bank Account Settings', 'sk-core' );
                break;

            case 'paypal':
            case 'paypal-edit':
                $heading = __( 'Paypal Settings', 'sk-core' );
                break;
        }

        /**
         * To allow new payment extension give their own heading
         *
         *
         * @param string $heading previous heading
         */
        $heading = apply_filters( 'sk_payment_method_settings_title', $heading, $slug );

        return $heading;
    }

    /**
     * Check if a seller is connected to a payment method
     *
     *
     * @param $payment_method_id
     * @param $seller_id
     *
     * @return bool
     */
    public function is_seller_connected( $payment_method_id, $seller_id ) {
        $is_connected     = false;
        $store_settings   = get_user_meta( $seller_id, 'sk_profile_settings', true );
        $payment_settings = ! isset( $store_settings['payment'] ) || ! isset( $store_settings['payment'][ $payment_method_id ] ) ? [] : $store_settings['payment'][ $payment_method_id ];
        $required_fields  = []; // Holds the required fields that should be empty for connection

        switch ( $payment_method_id ) {
            case 'bank':
                $required_fields = array_keys( sk_bank_payment_required_fields() );
                break;

            case 'paypal':
                $required_fields = [ 'email' ];
                break;
        }

        /**
         * To allow modifying the required fields for a payment method.
         *
         *
         * @param array  $required_fields
         * @param string $payment_method_id
         * @param int    $seller_id
         */
        $required_fields = apply_filters( 'sk_payment_settings_required_fields', $required_fields, $payment_method_id, $seller_id );

        // Check all the required fields have values
        if ( ! empty( $payment_settings ) && is_array( $payment_settings ) && ! empty( $required_fields ) ) {
            $is_connected = true;

            foreach ( $required_fields as $required_field ) {
                if ( empty( $payment_settings[ $required_field ] ) ) {
                    $is_connected = false;
                    break;
                } elseif ( 'email' === $required_field && ! is_email( $payment_settings[ $required_field ] ) ) {
                    $is_connected = false;
                    break;
                }
            }
        }

        /**
         * Get if user with id $seller_id is connected to the payment method having $payment_method_id
         *
         *
         * @param bool   $is_connected
         * @param string $payment_method_id
         * @param int    $seller_id
         */
        return apply_filters( 'sk_is_seller_connected_to_payment_method', $is_connected, $payment_method_id, $seller_id );
    }

    /**
     * Get payment method details from the method keys
     *
     *
     * @param $method_keys
     *
     * @return array
     */
    private function get_payment_methods( $method_keys ) {
        $methods  = [];
        $gateways = WC()->payment_gateways->payment_gateways();

        foreach ( $method_keys as $method_key ) {
            $cur_method = [];

            if ( ! empty( $cur_method ) ) {
                //remove the 'SK' prefix from method title
                $method_title = $cur_method['title'];
                if ( 0 === stripos( $method_title, 'SK ' ) ) {
                    $method_title        = substr( $method_title, 6 );
                    $cur_method['title'] = $method_title;
                }

                $cur_method['description'] = '';
                if ( isset( $gateways[ $method_key ] ) ) {
                    $cur_method['description'] = $gateways[ $method_key ]->get_description();
                } elseif ( $method_key === 'bank' ) {
                    $cur_method['description'] = $gateways['bacs']->get_description();
                } elseif ( $method_key === 'paypal' ) {
                    $cur_method['description'] = $gateways['bacs']->get_description();
                }

                $methods[ $method_key ] = $cur_method;
            }
        }

        return apply_filters( 'sk_vendor_payment_methods', $methods, $gateways );
    }

    /**
     * Get Method title to show in frontend
     *
     *
     * @return string
     */
    public function get_method_frontend_title( $title, $method ) {
        if ( 0 === stripos( $title, 'SK ' ) ) {
            return substr( $title, 6 );
        }

        return $title;
    }
}
