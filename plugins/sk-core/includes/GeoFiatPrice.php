<?php

namespace SK\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Shows the sats price converted to EUR/CHF underneath WooCommerce price
 * amounts, based on the visitor's browser locale.
 *
 * Absorbed from the standalone "BTC Preisumrechner (Geo-basiert)" plugin;
 * behavior unchanged.
 */
final class GeoFiatPrice {

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	public static function enqueue_assets(): void {
		$js_path = SK_CORE_DIR . '/assets/js/sk-geo-fiat-price.js';

		wp_enqueue_script(
			'btcpreis-geo',
			plugins_url( 'assets/js/sk-geo-fiat-price.js', SK_CORE_FILE ),
			[],
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : SK_CORE_VERSION,
			true
		);
	}
}
