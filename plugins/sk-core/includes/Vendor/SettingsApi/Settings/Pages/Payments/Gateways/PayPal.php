<?php

namespace SK\Core\Vendor\SettingsApi\Settings\Pages\Payments\Gateways;

use SK\Core\Vendor\SettingsApi\Abstracts\Gateways;

defined( 'ABSPATH' ) || exit;

/**
 * Payment processor PayPal.
 *
 */
class PayPal extends Gateways {

    /**
     * Render the settings for PayPal.
     *
     *
     * @param array $settings Settings to render.
     *
     * @return array
     */
    public function render_settings( array $settings ): array {
        $settings[] = [
            'id'        => 'paypal_card',
            'title'     => __( 'Paypal', 'sk-core' ),
            'desc'      => __( 'Paypal settings.', 'sk-core' ),
            'info'      => [],
            'icon'      => '',
            'type'      => 'card',
            'parent_id' => 'payment',
            'tab'       => 'general',
            'editable'  => true,
        ];
        $settings[] = [
            'id'        => 'paypal',
            'title'     => __( 'PayPal', 'sk-core' ),
            'desc'      => __( 'Paypal settings', 'sk-core' ),
            'icon'      => '',
            'type'      => 'section',
            'parent_id' => 'payment',
            'tab'       => 'general',
            'editable'  => true,
            'card'      => 'paypal_card',
            'fields'    => [
                [
                    'id'        => 'email',
                    'title'     => __( 'Email', 'sk-core' ),
                    'desc'      => __( 'Enter your paypal email address', 'sk-core' ),
                    'icon'      => '',
                    'type'      => 'email',
                    'parent_id' => 'paypal',
                ],
            ],
        ];

        return $settings;
    }
}
