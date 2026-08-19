<?php

namespace SK\Core\SettingsApi;

defined( 'ABSPATH' ) || exit;

/**
 * Store settings page.
 *
 */
class Store {

    /**
     * Constructor.
     *
     *
     * @return void
     */
    public function __construct() {
        add_filter( 'sk_vendor_settings_api_general_tab', [ $this, 'add_biography_card_api' ] );
        add_filter( 'sk_vendor_settings_api_business_info_card', [ $this, 'add_store_category_to_business_info_card' ] );
        add_filter( 'sk_vendor_settings_api_advanced_tab', [ $this, 'add_support_card_to_vendor_settings_api' ] );
    }


    /**
     * Add biography card api to vendor settings
     *
     *
     * @param array $settings array of settings.
     *
     * @return array
     */
    public function add_biography_card_api( array $settings ): array {
        $biography   = [];
        $biography[] = [
            'id'        => 'biography_card',
            'title'     => __( 'About Your Store', 'sk-core' ),
            'desc'      => __( 'Give visitors detailed information about what your store is all about', 'sk-core' ),
            'info'      => [],
            'icon'      => 'sk-icon-doc-2',
            'type'      => 'card',
            'parent_id' => 'store',
            'tab'       => 'general',
            'editable'  => true,
        ];
        $biography[] = [
            'id'          => 'vendor_biography',
            'title'       => '',
            'desc'        => '',
            'placeholder' => __( 'Write about your business, product offerings and more', 'sk-core' ),
            'info'        => [],
            'icon'        => '',
            'type'        => 'textarea',
            'parent_id'   => 'store',
            'tab'         => 'general',
            'card'        => 'biography_card',
        ];

        $biography = apply_filters( 'sk_pro_vendor_settings_api_biography_card', $biography );
        array_push( $settings, ...$biography );
        return $settings;
    }

    /**
     * Add store category to business info card.
     *
     *
     * @param array $business_info_card array of settings.
     *
     * @return array
     */
    public function add_store_category_to_business_info_card( array $business_info_card ): array {
        $category_type        = sk_get_option( 'store_category_type', 'sk_general', 'none' );
        $business_info_card[] = [
            'id'          => 'categories',
            'title'       => __( 'Category', 'sk-core' ),
            'desc'        => '',
            'info'        => [],
            'icon'        => '',
            'placeholder' => __( 'Select Your Store Categories', 'sk-core' ),
            'type'        => 'select',
            'multiple'    => 'multiple' === $category_type,
            'parent_id'   => 'store',
            'tab'         => 'general',
            'card'        => 'business_info',
            'options'     => get_terms(
                [
                    'taxonomy'   => 'store_category',
                    'hide_empty' => false,
                ]
            ),
        ];

        return $business_info_card;
    }

    /**
     * Add support card to advance tab.
     *
     *
     * @param array $advance_tab Advance tab data.
     *
     * @return array
     */
    public function add_support_card_to_vendor_settings_api( array $advance_tab ): array {
        if ( ! sk_ext()->module->is_active( 'live_chat' ) && ! sk_ext()->module->is_active( 'store_support' ) ) {
            return $advance_tab;
        }

        $support_card   = [];
        $support_card[] = [
            'id'        => 'support_card',
            'title'     => __( 'Display Support Option', 'sk-core' ),
            'desc'      => __( 'Choose where to display support button for customers to utilize', 'sk-core' ),
            'info'      => [
                [
                    'text' => __( 'Docs', 'sk-core' ),
                    'url'  => 'https://sk.co/docs/wordpress/modules/how-to-install-and-use-store-support/',
                    'icon' => 'sk-icon-doc',
                ],
            ],
            'icon'      => 'sk-icon-headphone',
            'type'      => 'card',
            'parent_id' => 'store',
            'tab'       => 'advance',
            'editable'  => true,
        ];

        $support_card = apply_filters( 'sk_pro_vendor_settings_api_support_card', $support_card );
        array_push( $advance_tab, ...$support_card );

        return $advance_tab;
    }


    /**
     * Add storewide discount to advance tab.
     *
     *
     * @param array $advance_tab Advance tab data.
     *
     * @return array
     */
}
