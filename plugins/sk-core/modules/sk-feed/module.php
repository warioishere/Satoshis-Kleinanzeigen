<?php

namespace SK\Modules\Feed;

defined( 'ABSPATH' ) || exit;

/**
 * SK Feed — Vendor Social Feed.
 *
 * Vendors post updates from their dashboard.
 * Public feed page + per-store feed tab.
 * Likes, comments, zaps, report/moderation.
 */
final class Module {

	public $version = '1.0.0';

	public function __construct() {
		$this->define_constants();
		$this->includes();
		$this->maybe_install();
		$this->instances();
	}

	private function define_constants() {
		define( 'SK_FEED_VERSION', $this->version );
		define( 'SK_FEED_FILE', __FILE__ );
		define( 'SK_FEED_PATH', dirname( SK_FEED_FILE ) );
		define( 'SK_FEED_INCLUDES', SK_FEED_PATH . '/includes' );
		define( 'SK_FEED_URL', plugins_url( '', SK_FEED_FILE ) );
		define( 'SK_FEED_TEMPLATES', SK_FEED_PATH . '/templates' );
	}

	private function includes() {
		require_once SK_FEED_INCLUDES . '/Install.php';
		require_once SK_FEED_INCLUDES . '/PostType.php';
		require_once SK_FEED_INCLUDES . '/Likes.php';
		require_once SK_FEED_INCLUDES . '/Reports.php';
		require_once SK_FEED_INCLUDES . '/Ajax.php';
		require_once SK_FEED_INCLUDES . '/Scripts.php';
		require_once SK_FEED_INCLUDES . '/Dashboard.php';
		require_once SK_FEED_INCLUDES . '/StoreTab.php';
		require_once SK_FEED_INCLUDES . '/FeedPage.php';
		require_once SK_FEED_INCLUDES . '/AutoPost.php';
		require_once SK_FEED_INCLUDES . '/FollowIntegration.php';
	}

	private function maybe_install() {
		if ( get_option( 'sk_feed_db_version' ) !== $this->version ) {
			Install::create_tables();
		}
	}

	private function instances() {
		new PostType();
		new Ajax();
		new Scripts();
		new Dashboard();
		new StoreTab();
		new FeedPage();
		new AutoPost();
		new FollowIntegration();
	}

	/**
	 * Run on module activation.
	 */
	public static function activate() {
		Install::create_tables();
		flush_rewrite_rules( true );
	}
}
