<?php

namespace SK\Core;

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
    }

    /**
     * Settings related hooks.
     *
     *
     * @return void
     */
    public function hooks() {
        add_filter( 'sk_get_dashboard_settings_nav', array( $this, 'load_settings_menu' ), 10 );
        add_filter( 'sk_dashboard_nav_active', array( $this, 'filter_nav_active' ), 10, 3 );
        add_filter( 'sk_dashboard_settings_heading_title', array( $this, 'load_settings_header' ), 10, 2 );
        add_filter( 'sk_dashboard_settings_helper_text', array( $this, 'load_settings_helper_text' ), 10, 2 );

        add_action( 'sk_settings_content_area_header', array( $this, 'render_shipping_status_message' ), 25 );
        add_action( 'sk_render_settings_content', array( $this, 'load_settings_content' ), 10 );

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
        add_filter( 'sk_get_dashboard_nav_template_dependency', [ $this, 'nav_template_dependency' ] );
    }

    /**
     * Filter Nav Active
     *
     *
     * @return string
     */
    public function filter_nav_active( $active_menu, $request, $active ) {
        if ( 'settings/regular-shipping' === $active_menu ) {
            return 'settings/shipping';
        }

        return $active_menu;
    }


    /**
     * Load Settings Menu
     *
     *
     * @param  array $sub_settins
     *
     * @return array
     */
    public function load_settings_menu( $sub_settins ) {
        $sk_shipping_option = get_option( 'woocommerce_sk_product_shipping_settings' );
        $enable_shipping       = ( isset( $sk_shipping_option['enabled'] ) ) ? $sk_shipping_option['enabled'] : 'yes';
        $disable_woo_shipping  = get_option( 'woocommerce_ship_to_countries' );

        if ( $disable_woo_shipping !== 'disabled' ) {
            $sub_settins['shipping'] = array(
                'title'       => __( 'Shipping', 'sk' ),
                'icon'        => '<i class="fas fa-truck"></i>',
                'url'         => sk_get_navigation_url( 'settings/shipping' ),
                'pos'         => 70,
                'permission'  => 'sk_view_store_shipping_menu',
            );
        }

        $sub_settins['social'] = array(
            'title'       => __( 'Social Profile', 'sk' ),
            'icon'        => '<i class="fas fa-share-alt-square"></i>',
            'url'         => sk_get_navigation_url( 'settings/social' ),
            'pos'         => 90,
            'permission'  => 'sk_view_store_social_menu',
        );

        if ( sk_get_option( 'store_seo', 'sk_general', 'on' ) === 'on' ) {
            $sub_settins['seo'] = array(
                'title'         => __( 'Store SEO', 'sk' ),
                'icon'          => '<i class="fas fa-globe"></i>',
                'url'           => sk_get_navigation_url( 'settings/seo' ),
                'pos'           => 110,
                'permission'    => 'sk_view_store_seo_menu',
            );
        }

        return $sub_settins;
    }

    public function nav_template_dependency( array $dependencies ): array {

        $dependencies['settings/seo'] = [
            [
                'slug' => 'settings/seo',
                'name' => '',
            ],
        ];

        return $dependencies;
    }

    /**
     * Load Settings Template
     *
     *
     * @param  string $template
     * @param  string $query_vars
     *
     * @return void
     */
    public function load_settings_template( $template, $query_vars ) {
        if ( $query_vars === 'social' ) {
            sk_get_template_part( 'settings/store' );
            return;
        }

        if ( $query_vars === 'shipping' ) {
            sk_get_template_part( 'settings/store' );
            return;
        }

        if ( $query_vars === 'seo' ) {
            sk_get_template_part( 'settings/store' );
            return;
        }
    }

    /**
     * Load Settings Header
     *
     *
     * @param  string $header
     * @param  string $query_vars
     *
     * @return string
     */
    public function load_settings_header( $header, $query_vars ) {
        if ( $query_vars === 'social' ) {
            $header = __( 'Social Profiles', 'sk' );
        }

        if ( $query_vars === 'shipping' ) {
            $settings_url = sk_get_navigation_url( 'settings/shipping' ) . '/settings';
            $header = sprintf( '%s <span style="position:absolute; right:0px;"><a href="%s" class="sk-btn sk-btn-default"><i class="fas fa-cog"></i> %s</a></span>', __( 'Shipping Settings', 'sk' ), $settings_url, __( 'Click here to add Shipping Policies', 'sk' ) );
        }

        if ( $query_vars === 'seo' ) {
            $header = __( 'Store SEO', 'sk' );
        }

        return $header;
    }

    /**
     * Load Settings page helper
     *
     *
     * @param  string $help_text
     * @param  string $query_vars
     *
     * @return string
     */
    public function load_settings_helper_text( $help_text, $query_vars ) {
        $sk_shipping_option = get_option( 'woocommerce_sk_product_shipping_settings' );
        $enable_shipping       = ( isset( $sk_shipping_option['enabled'] ) ) ? $sk_shipping_option['enabled'] : 'yes';

        if ( $query_vars === 'social' ) {
            $help_text = __( 'Social profiles help you to gain more trust. Consider adding your social profile links for better user interaction.', 'sk' );
        }

        if ( $query_vars === 'shipping' ) {
            $help_text = sprintf(
                '<p>%s</p>',
                esc_html__( 'A shipping zone is a geographic region where a certain set of shipping methods are offered. We will match a customer to a single zone using their shipping address and present the shipping methods within that zone to them.', 'sk' ),
            );

            if ( 'yes' === $enable_shipping ) {
                $help_text .= sprintf(
                    '<p>%s <a href="%s">%s</a></p>',
                    __( 'If you want to use the previous shipping system then', 'sk' ),
                    esc_url( sk_get_navigation_url( 'settings/regular-shipping' ) ),
                    __( 'Click Here', 'sk' )
                );
            }
        }

        if ( $query_vars === 'regular-shipping' && $enable_shipping === 'yes' ) {
            $help_text = sprintf(
                '<p>%s</p><p>%s</p><p>%s <a href="%s">%s</a></p>',
                __( 'This page contains your store-wide shipping settings, costs, shipping and refund policy.', 'sk' ),
                __( 'You can enable/disable shipping for your products. Also you can override these shipping costs while creating or editing a product.', 'sk' ),
                __( 'If you want to configure zone wise shipping then', 'sk' ),
                esc_url( sk_get_navigation_url( 'settings/shipping' ) ),
                __( 'Click Here', 'sk' )
            );
        }

        return $help_text;
    }

    /**
     * Load Settings Content
     *
     *
     * @param  array $query_vars
     *
     * @return void
     */
    public function load_settings_content( $query_vars ) {
        if ( isset( $query_vars['settings'] ) && $query_vars['settings'] === 'social' ) {
            if ( ! current_user_can( 'sk_view_store_social_menu' ) ) {
                sk_get_template_part(
                    'global/sk-error',
                    '',
                    array(
                        'deleted' => false,
                        'message' => __( 'You have no permission to view this page', 'sk' ),
                    )
                );
            } else {
                $this->load_social_content();
            }
        }

        if ( isset( $query_vars['settings'] ) && $query_vars['settings'] === 'shipping' ) {
            if ( ! current_user_can( 'sk_view_store_shipping_menu' ) ) {
                sk_get_template_part(
                    'global/sk-error',
                    '',
                    array(
                        'deleted' => false,
                        'message' => __( 'You have no permission to view this page', 'sk' ),
                    )
                );
            } else {
                $disable_woo_shipping = get_option( 'woocommerce_ship_to_countries' );

                if ( 'disabled' === $disable_woo_shipping ) {
                    sk_get_template_part(
                        'global/sk-error',
                        '',
                        array(
                            'deleted' => false,
                            'message' => __( 'Shipping functionality is currentlly disabled by site owner', 'sk' ),
                        )
                    );
                } else {

                    /**
                     * To allow overriding dashboard/settings/shipping add these filter
                     *
                     *
                     * @param string Load Shipping Page Content
                     */
                    echo apply_filters( 'sk_load_settings_content_shipping', $this->load_shipping_content() );
                }
            }
        }

        if ( isset( $query_vars['settings'] ) && $query_vars['settings'] === 'regular-shipping' ) {
            if ( ! current_user_can( 'sk_view_store_shipping_menu' ) ) {
                sk_get_template_part(
                    'global/sk-error',
                    '',
                    array(
                        'deleted' => false,
                        'message' => __( 'You have no permission to view this page', 'sk' ),
                    )
                );
            } else {
                $disable_woo_shipping  = get_option( 'woocommerce_ship_to_countries' );
                $sk_shipping_option = get_option( 'woocommerce_sk_product_shipping_settings' );
                $enable_shipping       = ( isset( $sk_shipping_option['enabled'] ) ) ? $sk_shipping_option['enabled'] : 'yes';

                if ( 'disabled' === $disable_woo_shipping || 'no' === $enable_shipping ) {
                    sk_get_template_part(
                        'global/sk-error',
                        '',
                        array(
                            'deleted' => false,
                            'message' => __( 'Shipping functionality is currentlly disabled by site owner', 'sk' ),
                        )
                    );
                } else {
                    sk_get_template_part(
                        'settings/shipping',
                        '',
                        array( 'pro' => true )
                    );
                }
            }
        }

        if ( isset( $query_vars['settings'] ) && $query_vars['settings'] === 'seo' ) {
            if ( ! current_user_can( 'sk_view_store_seo_menu' ) ) {
                sk_get_template_part(
                    'global/sk-error',
                    '',
                    array(
                        'deleted' => false,
                        'message' => __( 'You have no permission to view this page', 'sk' ),
                    )
                );
            } else {
                $this->load_seo_content();
            }
        }
    }

    /**
     * Load Social Page Content
     *
     *
     * @return void
     */
    public function load_social_content() {
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
     * Load Shipping Page Content
     *
     *
     * @return void
     */
    public function load_shipping_content() {
        sk_get_template_part( 'settings/shipping', '', array( 'pro' => true ) );
    }

    /**
     * Render Shipping status message
     *
     *
     * @return void
     */
    public function render_shipping_status_message() {
        $data = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $data['message'] ) && $data['message'] === 'shipping_saved' ) {
            sk_get_template_part(
                'global/sk-message',
                '',
                array(
                    'message' => __( 'Shipping options saved successfully', 'sk' ),
                )
            );
        }
    }

    /**
     * Load SEO Content
     *
     *
     * @return void
     */
    public function load_seo_content() {
        sk_get_template_part( 'settings/seo', '', array( 'pro' => true ) );
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
            'description'       => esc_html__( 'Vendor biography.', 'sk' ),
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
