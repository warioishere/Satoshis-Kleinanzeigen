<?php

namespace SK\Core\Vendor\SettingsApi\Settings\Pages;

use SK\Core\Vendor\SettingsApi\Abstracts\Page;

defined( 'ABSPATH' ) || exit;

class Store extends Page {

    /**
     * Group or page key.
     *
     * @var string $group Group or page key.
     */
    protected $group = 'store';

    /**
     * Render the settings page with tab, cad, fields.
     *
     *
     * @param array $groups Settings Group or page to render.
     *
     * @return array
     */
    public function render_group( array $groups ): array {
        $groups[] = [
            'id'          => $this->group,
            'label'       => __( 'Store Settings', 'sk-core' ),
            'description' => __( 'Vendor Store Settings', 'sk-core' ),
            'parent_id'   => '',
            'sub_groups'  => apply_filters( 'sk_vendor_settings_store_sub_groups', [] ),
        ];

        return $groups;
    }

    /**
     * Render the store settings page.
     *
     */
    public function render_settings( array $settings ): array {
        $general_tab   = [];
        $general_tab[] = [
            'id'        => 'general',
            'title'     => __( 'General', 'sk-core' ),
            'desc'      => '',
            'icon'      => '',
            'info'      => [],
            'type'      => 'tab',
            'parent_id' => 'store',
        ];

        $branding_card   = [];
        $branding_card[] = [
            'id'        => 'branding',
            'title'     => __( 'Branding', 'sk-core' ),
            'desc'      => __( 'Set the overall appearance of your store by setting banner image, logo and more', 'sk-core' ),
            'info'      => [],
            'icon'      => 'sk-icon-banner',
            'type'      => 'card',
            'parent_id' => 'store',
            'tab'       => 'general',
            'editable'  => false,
        ];
        $branding_card[] = [
            'id'        => 'banner',
            'title'     => __( 'Store Banner', 'sk-core' ),
            // translators: 1) store banner width, 2) store banner height.
            'desc'      => sprintf( __( 'Upload your store banner [ jpg or png, %1$d X %2$d pixels (max), 5 mb (max) ]', 'sk-core' ), sk_get_option( 'store_banner_width', 'sk_appearance', 625 ), sk_get_option( 'store_banner_height', 'sk_appearance', 300 ) ),
            'icon'      => '',
            'type'      => 'image',
            'parent_id' => 'store',
            'tab'       => 'general',
            'card'      => 'branding',
        ];
        $branding_card[] = [
            'id'        => 'gravatar',
            'title'     => __( 'Store Gravatar', 'sk-core' ),
            'desc'      => __( 'Upload your brand logo [ jpg or png, 150 X 150 pixels (max), 5mb (max) ]', 'sk-core' ),
            'icon'      => '',
            'type'      => 'image',
            'parent_id' => 'store',
            'tab'       => 'general',
            'card'      => 'branding',
        ];

        $branding_card = apply_filters( 'sk_vendor_settings_api_branding_card', $branding_card );
        array_push( $general_tab, ...$branding_card );

        $business_info_card   = [];
        $business_info_card[] = [
            'id'        => 'business_info',
            'title'     => __( 'Business Info', 'sk-core' ),
            'desc'      => __( 'Provide your business details for store visitors', 'sk-core' ),
            'info'      => [
//                [
//                    'text' => __( 'Docs', 'sk-core' ),
//                    'url'  => '#',
//                    'icon' => 'sk-icon-doc',
//                ],
//                [
//                    'text' => __( 'Video Guide', 'sk-core' ),
//                    'url'  => '#',
//                    'icon' => 'sk-icon-video',
//                ],
            ],
            'icon'      => 'sk-icon-inventory',
            'type'      => 'card',
            'parent_id' => 'store',
            'tab'       => 'general',
        ];
        $business_info_card[] = [
            'id'          => 'store_name',
            'title'       => __( 'Store Name', 'sk-core' ),
            'desc'        => '',
            'placeholder' => __( 'Insert Store Name', 'sk-core' ),
            'icon'        => '',
            'required'    => true,
            'type'        => 'text',
            'parent_id'   => 'store',
            'tab'         => 'general',
            'card'        => 'business_info',
        ];

        $business_info_card = apply_filters( 'sk_vendor_settings_api_business_info_card', $business_info_card );
        array_push( $general_tab, ...$business_info_card );

        $general_tab = apply_filters( 'sk_vendor_settings_api_general_tab', $general_tab );
        array_push( $settings, ...$general_tab );

        $store_details_tab   = [];
        $store_details_tab[] = [
            'id'        => 'store_details',
            'title'     => __( 'Store Setup', 'sk-core' ),
            'desc'      => '',
            'icon'      => '',
            'info'      => [],
            'type'      => 'tab',
            'parent_id' => 'store',
        ];

        $location_contact_card   = [];
        $location_contact_card[] = [
            'id'        => 'location_contact',
            'title'     => __( 'Store Address & Details', 'sk-core' ),
            'desc'      => __( 'Store locations, contact information and more', 'sk-core' ),
            'info'      => [
                [
                    'text' => __( 'Docs', 'sk-core' ),
                    'url'  => '',
                    'icon' => 'sk-icon-doc',
                ],
            ],
            'icon'      => 'sk-icon-location',
            'type'      => 'card',
            'parent_id' => 'store',
            'tab'       => 'store_details',
            'editable'  => false,
        ];
        $location_contact_card[] = [
            'id'        => 'phone',
            'title'     => __( 'Phone', 'sk-core' ),
            'desc'      => __( 'Enter your store phone', 'sk-core' ),
            'icon'      => 'sk-icon-phone',
            'type'      => 'text',
            'parent_id' => 'store',
            'tab'       => 'store_details',
            'card'      => 'location_contact',
        ];
        $location_contact_card[] = [
            'id'        => 'address',
            'title'     => __( 'Address', 'sk-core' ),
            'desc'      => __( 'Provide your store locations to be displayed on the site.', 'sk-core' ),
            'icon'      => 'sk-icon-location',
            'type'      => 'section',
            'parent_id' => 'store',
            'tab'       => 'store_details',
            'card'      => 'location_contact',
            'editable'  => true,
            'fields'    => [
                [
                    'id'        => 'street_1',
                    'title'     => __( 'Street', 'sk-core' ),
                    'desc'      => __( 'Street address', 'sk-core' ),
                    'icon'      => '',
                    'type'      => 'text',
                    'parent_id' => 'address',
                ],
                [
                    'id'        => 'street_2',
                    'title'     => __( 'Street Line 2', 'sk-core' ),
                    'desc'      => __( 'Street address continued', 'sk-core' ),
                    'icon'      => '',
                    'type'      => 'text',
                    'parent_id' => 'address',
                ],
                [
                    'id'        => 'city',
                    'title'     => __( 'City', 'sk-core' ),
                    'desc'      => __( 'City name', 'sk-core' ),
                    'icon'      => '',
                    'type'      => 'text',
                    'parent_id' => 'address',
                ],
                [
                    'id'        => 'zip',
                    'title'     => __( 'Zip Code', 'sk-core' ),
                    'desc'      => __( 'Zip code', 'sk-core' ),
                    'icon'      => '',
                    'type'      => 'text',
                    'parent_id' => 'address',
                ],
                [
                    'id'        => 'country',
                    'title'     => __( 'Country', 'sk-core' ),
                    'desc'      => __( 'Select your country', 'sk-core' ),
                    'icon'      => '',
                    'type'      => 'select',
                    'options'   => [ '' => __( 'Select a country&hellip;', 'sk-core' ) ] + WC()->countries->get_allowed_countries(),
                    'parent_id' => 'address',
                ],
                [
                    'id'        => 'state',
                    'title'     => __( 'State', 'sk-core' ),
                    'desc'      => __( 'State or state code', 'sk-core' ),
                    'icon'      => '',
                    'type'      => 'select',
                    'options'   => [ '' => __( 'Select a state', 'sk-core' ) ] + WC()->countries->get_allowed_country_states(),
                    'parent_id' => 'address',
                ],
            ],
        ];
        $location_contact_card[] = [
            'id'        => 'location',
            'title'     => __( 'Store Location', 'sk-core' ),
            'desc'      => __( 'Store Location GPS coordinate.', 'sk-core' ),
            'icon'      => '',
            'type'      => 'text',
            'hidden'    => true,
            'parent_id' => 'store',
            'tab'       => 'store_details',
            'card'      => 'location_contact',
        ];
        $location_contact_card[] = [
            'id'        => 'find_address',
            'title'     => __( 'Store Address', 'sk-core' ),
            'desc'      => __( 'Store Address', 'sk-core' ),
            'icon'      => '',
            'type'      => 'text',
            'parent_id' => 'store',
            'tab'       => 'store_details',
            'card'      => 'location_contact',
        ];

        $location_contact_card = apply_filters( 'sk_vendor_settings_api_location_contact_card', $location_contact_card );
        array_push( $store_details_tab, ...$location_contact_card );

        $store_details_tab = apply_filters( 'sk_vendor_settings_api_store_details_tab', $store_details_tab );
        array_push( $settings, ...$store_details_tab );

        $advanced_tab   = [];
        $advanced_tab[] = [
            'id'        => 'advanced',
            'title'     => __( 'Advanced', 'sk-core' ),
            'desc'      => '',
            'icon'      => '',
            'info'      => [],
            'type'      => 'tab',
            'parent_id' => 'store',
        ];

        $product_display_card   = [];
        $product_display_card[] = [
            'id'        => 'product_display',
            'title'     => __( 'Product Display Settings', 'sk-core' ),
            'desc'      => __( 'Configure which product sections you want to display in your store page', 'sk-core' ),
            'info'      => [
                [
                    'text' => __( 'Docs', 'sk-core' ),
                    'url'  => '',
                    'icon' => 'sk-icon-doc',
                ],
            ],
            'icon'      => 'sk-icon-products',
            'type'      => 'card',
            'parent_id' => 'store',
            'tab'       => 'advanced',
            'editable'  => false,
        ];

        $fields              = [];
        $customizer_settings = sk_get_option( 'product_sections', 'sk_appearance' );

        if ( isset( $customizer_settings['featured'] ) && 'off' === $customizer_settings['featured'] ) {
            $fields[] = [
                'id'        => 'featured',
                'title'     => __( 'Show featured products section', 'sk-core' ),
                'desc'      => __( 'Allow your Featured Products section to be displayed in your single store page', 'sk-core' ),
                'icon'      => '',
                'type'      => 'checkbox',
                'default'   => 'yes',
                'options'   => [
                    'yes' => __( 'Yes', 'sk-core' ),
                    'no'  => __( 'No', 'sk-core' ),
                ],
                'parent_id' => 'product_sections',
            ];
        }

        if ( isset( $customizer_settings['latest'] ) && 'off' === $customizer_settings['latest'] ) {
            $fields[] = [
                'id'        => 'latest',
                'title'     => __( 'Show latest products section', 'sk-core' ),
                'desc'      => __( 'Allow your Latest Products section to be displayed in your single store page', 'sk-core' ),
                'icon'      => '',
                'type'      => 'checkbox',
                'default'   => 'yes',
                'options'   => [
                    'yes' => __( 'Yes', 'sk-core' ),
                    'no'  => __( 'No', 'sk-core' ),
                ],
                'parent_id' => 'product_sections',
            ];
        }

        if ( isset( $customizer_settings['best_selling'] ) && 'off' === $customizer_settings['best_selling'] ) {
            $fields[] = [
                'id'        => 'best_selling',
                'title'     => __( 'Show best selling products section', 'sk-core' ),
                'desc'      => __( 'Allow your Best Selling Products section to be displayed in your single store page', 'sk-core' ),
                'icon'      => '',
                'type'      => 'checkbox',
                'default'   => 'yes',
                'options'   => [
                    'yes' => __( 'Yes', 'sk-core' ),
                    'no'  => __( 'No', 'sk-core' ),
                ],
                'parent_id' => 'product_sections',
            ];
        }

        if ( isset( $customizer_settings['top_rated'] ) && 'off' === $customizer_settings['top_rated'] ) {
            $fields[] = [
                'id'        => 'top_rated',
                'title'     => __( 'Show top rated products section', 'sk-core' ),
                'desc'      => __( 'Allow your Top Rated Products section to be displayed in your single store page', 'sk-core' ),
                'icon'      => '',
                'type'      => 'checkbox',
                'default'   => 'yes',
                'options'   => [
                    'yes' => __( 'Yes', 'sk-core' ),
                    'no'  => __( 'No', 'sk-core' ),
                ],
                'parent_id' => 'product_sections',
            ];
        }

        $fields                 = apply_filters( 'sk_vendor_settings_api_product_section_fields', $fields );
        $product_display_card[] = [
            'id'        => 'product_section',
            'title'     => '',
            'desc'      => '',
            'info'      => [],
            'icon'      => '',
            'type'      => 'section',
            'parent_id' => 'store',
            'tab'       => 'advance',
            'card'      => 'product_display',
            'fields'    => $fields,
        ];
        $product_display_card[] = [
            'id'        => 'show_email',
            'title'     => __( 'Show Email', 'sk-core' ),
            'desc'      => __( 'Do you want to display the store email publicly?', 'sk-core' ),
            'icon'      => '',
            'type'      => 'checkbox',
            'default'   => 'no',
            'options'   => [
                'yes' => __( 'Yes', 'sk-core' ),
                'no'  => __( 'No', 'sk-core' ),
            ],
            'parent_id' => 'store',
            'tab'       => 'advanced',
            'card'      => 'product_display',
        ];
        $product_display_card   = apply_filters( 'sk_vendor_settings_api_product_display_card', $product_display_card );
        array_push( $advanced_tab, ...$product_display_card );

        $terms_and_conditions_card   = [];
        $terms_and_conditions_card[] = [
            'id'        => 'terms_and_conditions',
            'title'     => __( 'Terms and Conditions', 'sk-core' ),
            'desc'      => __( 'Define the rules of your store page by providing a detailed break down of the Terms and Conditions ', 'sk-core' ),
            'info'      => [],
            'icon'      => 'sk-icon-policy',
            'type'      => 'card',
            'parent_id' => 'store',
            'tab'       => 'advanced',
            'editable'  => false,
        ];
        $terms_and_conditions_card[] = [
            'id'        => 'enable_tnc',
            'title'     => __( 'Display Terms & Condition', 'sk-core' ),
            'desc'      => __( 'Enable Store Terms & Condition', 'sk-core' ),
            'icon'      => '',
            'type'      => 'checkbox',
            'default'   => 'yes',
            'options'   => [
                'on'  => __( 'On', 'sk-core' ),
                'off' => __( 'Off', 'sk-core' ),
            ],
            'parent_id' => 'store',
            'tab'       => 'advanced',
            'card'      => 'terms_and_conditions',
        ];
        $terms_and_conditions_card[] = [
            'id'          => 'sk_tnc_text',
            'title'       => __( 'Terms & Condition', 'sk-core' ),
            'desc'        => __( 'Store Terms & Condition', 'sk-core' ),
            'placeholder' => __( 'Insert your store Terms & Conditions', 'sk-core' ),
            'icon'        => '',
            'type'        => 'textarea',
            'default'     => '',
            'parent_id'   => 'store',
            'tab'         => 'advanced',
            'card'        => 'terms_and_conditions',
            'editing'     => true,
        ];

        $terms_and_conditions_card = apply_filters( 'sk_vendor_settings_api_terms_and_conditions_card', $terms_and_conditions_card );
        array_push( $advanced_tab, ...$terms_and_conditions_card );

        $advanced_tab = apply_filters( 'sk_vendor_settings_api_advanced_tab', $advanced_tab );
        array_push( $settings, ...$advanced_tab );

        return $settings;
    }
}
