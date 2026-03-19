<?php

namespace SK\Core\Vendor\SettingsApi\Settings\Pages\Payments\Gateways;


use SK\Core\Vendor\SettingsApi\Abstracts\Gateways;

defined( 'ABSPATH' ) || exit;

/**
 * Payment processor Bank.
 *
 */
class Bank extends Gateways {

    /**
     * Render the settings page for bank.
     *
     *
     * @param array $settings Settings to render.
     *
     * @return array
     */
    public function render_settings( array $settings ): array {
        $settings[] = [
            'id'        => 'bank_card',
            'title'     => __( 'Bank', 'sk-core' ),
            'desc'      => __( 'Bank settings.', 'sk-core' ),
            'info'      => [],
            'icon'      => '',
            'type'      => 'card',
            'parent_id' => 'payment',
            'tab'       => 'general',
            'editable'  => true,
        ];
        $settings[] = [
            'id'        => 'bank',
            'title'     => __( 'Bank', 'sk-core' ),
            'desc'      => __( 'Bank settings', 'sk-core' ),
            'icon'      => '',
            'type'      => 'section',
            'parent_id' => 'payment',
            'tab'       => 'general',
            'editable'  => true,
            'card'      => 'bank_card',
            'fields'    => [
                [
                    'id'        => 'ac_name',
                    'title'     => __( 'Account Name', 'sk-core' ),
                    'desc'      => __( 'Enter your bank account name.', 'sk-core' ),
                    'icon'      => '',
                    'type'      => 'text',
                    'parent_id' => 'bank',
                ],
                [
                    'id'        => 'ac_number',
                    'title'     => __( 'Account Number', 'sk-core' ),
                    'desc'      => __( 'Enter your bank account number.', 'sk-core' ),
                    'icon'      => '',
                    'type'      => 'text',
                    'parent_id' => 'bank',
                ],
                [
                    'id'        => 'bank_name',
                    'title'     => __( 'Bank Name', 'sk-core' ),
                    'desc'      => __( 'Enter your bank name.', 'sk-core' ),
                    'icon'      => '',
                    'type'      => 'text',
                    'parent_id' => 'bank',
                ],
                [
                    'id'        => 'bank_addr',
                    'title'     => __( 'Bank Address', 'sk-core' ),
                    'desc'      => __( 'Enter your bank address.', 'sk-core' ),
                    'icon'      => '',
                    'type'      => 'textarea',
                    'parent_id' => 'bank',
                ],
                [
                    'id'        => 'routing_number',
                    'title'     => __( 'Routing Number', 'sk-core' ),
                    'desc'      => __( 'Enter your bank routing number.', 'sk-core' ),
                    'icon'      => '',
                    'type'      => 'text',
                    'parent_id' => 'bank',
                ],
                [
                    'id'        => 'iban',
                    'title'     => __( 'IBAN', 'sk-core' ),
                    'desc'      => __( 'Enter your IBAN number.', 'sk-core' ),
                    'icon'      => '',
                    'type'      => 'text',
                    'parent_id' => 'bank',
                ],
                [
                    'id'        => 'swift',
                    'title'     => __( 'Swift Code', 'sk-core' ),
                    'desc'      => __( 'Enter your banks Swift Code.', 'sk-core' ),
                    'icon'      => '',
                    'type'      => 'text',
                    'parent_id' => 'bank',
                ],
            ],
        ];

        return $settings;
    }
}
