<?php

namespace SK\Core\Vendor\SettingsApi\Settings\Pages\Payments;

use SK\Core\Vendor\SettingsApi\Abstracts\Page;

defined( 'ABSPATH' ) || exit;

/**
 * Payment Settings API Page.
 *
 */
class Payments extends Page {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();

        add_filter( 'sk_vendor_rest_settings_element_value_population', [ $this, 'set_active_payment_methods_status' ], 10, 3 );
    }

    /**
     * Group or page key.
     *
     * @var string $group Group or page key.
     */
    protected $group = 'payment';

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
            'label'       => __( 'Payment Settings', 'sk-core' ),
            'description' => __( 'Vendor Payment Settings', 'sk-core' ),
            'parent_id'   => '',
            'sub_groups'  => apply_filters( 'sk_vendor_settings_payment_sub_groups', [] ),
        ];
        return $groups;
    }

    /**
     * Render the payment settings page.
     *
     */
    public function render_settings( array $settings ): array {
        $settings[] = [
            'id'        => 'general',
            'title'     => __( 'General', 'sk-core' ),
            'desc'      => __( 'The general Payment settings.', 'sk-core' ),
            'icon'      => 'sk-icon-paypal',
            'info'      => [],
            'type'      => 'tab',
            'parent_id' => 'payment',
        ];

        return apply_filters( 'sk_vendor_rest_payment_settings', $settings );
    }

    /**
     * Set the active payment processor status.
     *
     *
     * @param array $settings Settings Element.
     *
     * @return array
     */
    public function set_active_payment_methods_status( array $settings, array $settings_values, string $parent_id ) {
        return $settings;
    }
}
