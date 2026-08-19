<?php

namespace SK\Modules\ReportAbuse;

class AdminSettings {

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        add_filter( 'sk_settings_sections', [ $this, 'add_settings_section' ] );
        add_filter( 'sk_settings_fields', [ $this, 'add_settings_fields' ] );
    }

    /**
     * Add admin settings section
     *
     *
     * @param array $sections
     *
     * @return array
     */
    public function add_settings_section( $sections ) {
        $sections['sk_report_abuse'] = [
            'id'                   => 'sk_report_abuse',
            'title'                => __( 'Product Report Abuse', 'sk-core' ),
            'icon_url'             => SK_REPORT_ABUSE_ASSETS . '/images/report.svg',
            'description'          => __( 'Configure Product Abusal Reports', 'sk-core' ),
            'document_link'        => 'https://sk.co/docs/wordpress/modules/sk-report-abuse/',
            'settings_title'       => __( 'Product Report Abuse Settings', 'sk-core' ),
            'settings_description' => __( 'Configure your marketplace to ensure safety and honesty by allowing customers to report fraudulent products.', 'sk-core' ),
        ];

        return $sections;
    }

    /**
     * Add admin settings fields
     *
     *
     * @param array $settings_fields
     *
     * @return array
     */
    public function add_settings_fields( $settings_fields ) {
        $settings_fields['sk_report_abuse'] = [
            'reported_by_logged_in_users_only' => [
                'name'    => 'reported_by_logged_in_users_only',
                'label'   => __( 'Reported by', 'sk-core' ),
                'desc'    => __( 'Only logged-in users can report', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'off',
                'tooltip' => __( 'Restrict Product Abuse feature for logged-In users only', 'sk-core' ),
            ],

            'abuse_reasons' => [
                'name'    => 'abuse_reasons',
                'label'   => __( 'Reasons for Abuse Report', 'sk-core' ),
                'type'    => 'repeatable',
                'desc'    => __( 'Add multiple customized reasons.', 'sk-core' ),
                'tooltip' => __( 'Add multiple customized reasons.', 'sk-core' ),
            ],
        ];

        return $settings_fields;
    }
}
