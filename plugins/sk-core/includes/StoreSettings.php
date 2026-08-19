<?php

namespace SK\Core;

use SK\Core\Dashboard\DashboardRegistry;
use SK\Core\Dashboard\Templates\Settings as SkSettings;
use SK\Core\Vendor\Vendor;

/**
 * SK Store Settings class
 *
 *
 */
class StoreSettings extends SkSettings {

    /**
     * Load automatically when class initiate
     *
     *
     * @uses actions hook
     * @uses filter hook
     *
     * @return void
     */
    public function __construct() {
        $this->currentuser = sk_get_current_user_id();

        // Settings hooks.
        $this->hooks();
        $this->register_tabs();
    }

    /**
     * Settings related hooks (non-tab ones).
     */
    public function hooks() {
        add_filter( 'tiny_mce_before_init', array( $this, 'biography_editor_dark_style' ), 10, 2 );

        // Add vendor biography to REST API.
        add_action( 'sk_update_vendor', [ $this, 'save_rest_biography_data' ], 10, 2 );
        add_filter( 'sk_rest_api_store_update_params', [ $this, 'update_store_rest_params' ] );
        add_filter( 'sk_rest_store_additional_fields', [ $this, 'add_store_biography_response' ], 10, 2 );
        add_filter( 'sk_vendor_create_data', [ $this, 'add_rest_biography_data' ], 10, 2 );

        // Calculate store progress after vendor creation by admin
        add_action( 'sk_new_vendor', array( $this, 'save_store_data' ) );

        //Calculate store progress after customer migrated to vendor
        add_action( 'sk_new_seller_created', array( $this, 'save_store_data' ), 10, 2 );
    }

    /**
     * Register all built-in Settings tabs with the DashboardRegistry.
     * Each entry has `parent => 'settings'` which the Registry uses to
     * wire the sidebar sub-menu and heading/helper/content renderers.
     */
    public function register_tabs(): void {
        DashboardRegistry::register_config( [
            'slug'       => 'settings-social',
            'parent'     => 'settings',
            'url_key'    => 'social',
            'title'      => __( 'Social Profile', 'sk-core' ),
            'icon'       => '<i class="fas fa-share-alt-square"></i>',
            'pos'        => 90,
            'permission' => 'sk_view_store_social_menu',
            'heading'    => __( 'Social Profiles', 'sk-core' ),
            'helper'     => __( 'Social profiles help you to gain more trust. Consider adding your social profile links for better user interaction.', 'sk-core' ),
            'template'   => [ $this, 'load_social_content' ],
        ] );
    }

    /**
     * Load Social Page Content — callable template for settings-social tab.
     */
    public function load_social_content( $query_vars = [] ) {
        $social_fields = sk_get_social_profile_fields();

        sk_get_template_part(
            'settings/social',
            '',
            array(
                'pro'           => true,
                'social_fields' => $social_fields,
                'current_user'  => $this->currentuser,
                'profile_info'  => sk_get_store_info( $this->currentuser ),
            )
        );
    }

    /**
     * Apply dark theme to the vendor biography TinyMCE editor content area.
     */
    public function biography_editor_dark_style( $init, $editor_id ) {
        if ( 'vendor_biography' !== $editor_id ) {
            return $init;
        }
        $init['content_style'] = 'body { background: #252d38 !important; color: #e8ecf0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 14px; padding: 8px 12px; margin: 0; } a { color: #F7931A; }';
        return $init;
    }

    /**
     * Save biography data
     *
     *
     * @return void
     */
    public function save_biography_data( $vendor_id, $sk_settings = [] ) {
        if ( ! isset( $_POST['vendor_biography'] ) ) {
            return;
        }

        $sk_settings['vendor_biography'] = wp_kses_post( $_POST['vendor_biography'] );
        $sk_settings = apply_filters( 'sk_vendor_biography_args', $sk_settings, $vendor_id );
        do_action( 'sk_vendor_biography_before_update', $sk_settings, $vendor_id );
        update_user_meta( $vendor_id, 'sk_profile_settings', $sk_settings );
        do_action( 'sk_vendor_biography_after_update', $sk_settings, $vendor_id );
    }

    /**
     * Save vendor biography REST data.
     *
     *
     * @param int   $vendor_id The ID of the vendor.
     * @param array $data      The data to be saved.
     *
     * @return void
     */
    public function save_rest_biography_data( $vendor_id, $data ) {
        if ( ! isset( $data['vendor_biography'] ) ) {
            return;
        }

        $this->update_biography( $vendor_id, $data['vendor_biography'] );
    }

    /**
     * Update biography data.
     *
     *
     * @param int    $vendor_id The ID of the vendor.
     * @param string $biography The biography data to be saved.
     *
     * @return void
     */
    protected function update_biography( $vendor_id, $biography ) {
        $data = [
            'vendor_biography' => wp_kses_post( $biography ),
        ];

        wp_cache_delete( $vendor_id, 'user_meta' );
        clean_user_cache( $vendor_id );
        $store_info         = get_user_meta( $vendor_id, 'sk_profile_settings', true );
        if ( ! is_array( $store_info ) ) $store_info = [];
        $updated_store_info = wp_parse_args( $data, $store_info );
        $updated_store_info = apply_filters( 'sk_vendor_biography_args', $updated_store_info, $vendor_id );

        do_action( 'sk_vendor_biography_before_update', $updated_store_info, $vendor_id );

        update_user_meta( $vendor_id, 'sk_profile_settings', $updated_store_info );

        do_action( 'sk_vendor_biography_after_update', $updated_store_info, $vendor_id );
    }

    /**
     * Update store REST params
     *
     *
     * @param array $params
     *
     * @return array
     */
    public function update_store_rest_params( $params ) {
        $params['vendor_biography'] = [
            'description'       => esc_html__( 'Vendor biography.', 'sk-core' ),
            'type'              => 'string',
            'sanitize_callback' => 'wp_kses_post',
        ];

        return $params;
    }

    /**
     * Add store biography response
     *
     *
     * @param array  $additional_fields
     * @param Vendor $store
     *
     * @return array
     */
    public function add_store_biography_response( $additional_fields, $store ) {
        $store_info = $store->get_shop_info();
        $additional_fields['vendor_biography'] = $store_info['vendor_biography'] ?? '';

        return $additional_fields;
    }

    /**
     * Add store biography data for REST API.
     *
     *
     * @param array $store_data
     * @param array $request_data
     *
     * @return array
     */
    public function add_rest_biography_data( $store_data, $request_data ) {
        $store_data['vendor_biography'] = $request_data['vendor_biography'] ?? '';

        return $store_data;
    }
}
