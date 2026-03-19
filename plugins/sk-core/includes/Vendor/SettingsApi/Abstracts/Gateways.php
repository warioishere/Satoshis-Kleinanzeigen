<?php

namespace SK\Core\Vendor\SettingsApi\Abstracts;

defined( 'ABSPATH' ) || exit;

/**
 * Vendor Settings Payment processor.
 *
 */
abstract class Gateways {

    /**
     * Hook Order for setting rendering.
     *
     * @var int
     */
    protected $hook_order = 10;


    /**
     * Settings Group Key for setting rendering.
     *
     * @var string
     */
    protected $group = '';

    /**
     * Constructor function.
     */
    public function __construct() {
        add_filter( 'sk_vendor_rest_payment_settings', [ $this, 'render_settings' ], $this->hook_order );
    }

    /**
     * Render the settings page with tab, cad, fields.
     *
     *
     * @param array $settings Settings to render.
     *
     * @return array
     */
    abstract public function render_settings( array $settings ): array;
}
