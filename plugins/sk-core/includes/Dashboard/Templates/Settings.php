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

}
