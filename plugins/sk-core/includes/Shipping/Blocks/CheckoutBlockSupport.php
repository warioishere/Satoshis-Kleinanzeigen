<?php

namespace SK\Core\Shipping\Blocks;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

defined( 'ABSPATH' ) || exit();

/**
 *  Checkout block support for sk Shipping.
 *
 */
class CheckoutBlockSupport implements IntegrationInterface {

	/**
	 * Get name of the integration
     *
	 */
	public function get_name() {
		return 'sk_shipping';
	}

	/**
     * Initialize.
     *
     *
     * @return void
	 */
	public function initialize() {
		$asset_file = SK_CORE_DIR . '/assets/blocks/shipping/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

        wp_register_script(
            'sk-shipping-block-checkout-support',
            SK_CORE_ASSETS . '/blocks/shipping/index.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_enqueue_style(
            'sk-shipping-block-checkout-support',
            SK_CORE_ASSETS . '/blocks/shipping/index.css',
            [],
            $asset['version']
        );
	}

	/**
     * Get Script handle to enqueue.
     *
     *
     * @return array
	 */
	public function get_script_handles() {
		return ['sk-shipping-block-checkout-support'];
	}

	/**
     * Get editor script handle.
     *
     *
     * @return array
	 */
	public function get_editor_script_handles() {
		return [];
	}

	/**
     * Get script data for frontend consumption.
     *
	 */
	public function get_script_data() {
		return [];
	}
}
