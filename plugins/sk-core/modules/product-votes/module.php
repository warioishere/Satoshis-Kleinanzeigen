<?php

namespace SK\Modules\ProductVotes;

defined( 'ABSPATH' ) || exit;

/**
 * SK Product Votes — Hot/Cold Voting on Vendor-Produkten.
 *
 * Nur qualifizierte Accounts dürfen voten; Aggregat erst ab Threshold sichtbar.
 */
final class Module {

	public $version;

	public function __construct() {
		$this->version = function_exists( 'sk_assets_version' )
			? sk_assets_version( __DIR__ . '/assets' )
			: '1.0.0';
		$this->define_constants();
		$this->includes();
		$this->maybe_install();
		$this->instances();
	}

	private function define_constants() {
		define( 'SK_PV_VERSION', $this->version );
		define( 'SK_PV_FILE', __FILE__ );
		define( 'SK_PV_PATH', dirname( SK_PV_FILE ) );
		define( 'SK_PV_INCLUDES', SK_PV_PATH . '/includes' );
		define( 'SK_PV_URL', plugins_url( '', SK_PV_FILE ) );
	}

	private function includes() {
		require_once SK_PV_INCLUDES . '/Install.php';
		require_once SK_PV_INCLUDES . '/LoginTracker.php';
		require_once SK_PV_INCLUDES . '/Voting.php';
		require_once SK_PV_INCLUDES . '/Display.php';
		require_once SK_PV_INCLUDES . '/Ajax.php';
	}

	private function maybe_install() {
		if ( get_option( 'sk_product_votes_db_version' ) !== $this->version ) {
			Install::install();
			update_option( 'sk_product_votes_db_version', $this->version );
		}
	}

	private function instances() {
		new LoginTracker();
		new Display();
		new Ajax();
	}
}
