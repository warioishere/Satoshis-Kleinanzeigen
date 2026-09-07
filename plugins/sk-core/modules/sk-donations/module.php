<?php

namespace SK\Modules\Donations;

defined( 'ABSPATH' ) || exit;

/**
 * SK Donations — donations with visible cost coverage.
 *
 * Starting point (August 2026): the /spenden page was viewed 35 times in
 * 90 days, out of 10,637 page views total; donations happened three times,
 * all on the same day in September 2025. Another link to this page
 * wouldn't change that.
 *
 * That's why the ask here isn't a link but an element: it states the
 * monthly need, the current total, and offers the amounts directly.
 * Payment goes through the same path as subscriptions, boosts and sponsor
 * credit — WooCommerce plus BTCPay — so every donation is captured and
 * the bar moves on its own.
 */
final class Module {

    public $version;

    public function __construct() {
        $this->version = function_exists( 'sk_assets_version' )
            ? sk_assets_version( __DIR__ . '/assets' )
            : '1.0.0';

        $this->define_constants();
        $this->includes();
        $this->instances();
    }

    private function define_constants() {
        defined( 'SK_DONATIONS_VERSION' )  || define( 'SK_DONATIONS_VERSION', $this->version );
        defined( 'SK_DONATIONS_FILE' )     || define( 'SK_DONATIONS_FILE', __FILE__ );
        defined( 'SK_DONATIONS_PATH' )     || define( 'SK_DONATIONS_PATH', dirname( SK_DONATIONS_FILE ) );
        defined( 'SK_DONATIONS_INCLUDES' ) || define( 'SK_DONATIONS_INCLUDES', SK_DONATIONS_PATH . '/includes' );
        defined( 'SK_DONATIONS_URL' )      || define( 'SK_DONATIONS_URL', plugins_url( '', SK_DONATIONS_FILE ) );
    }

    private function includes() {
        require_once SK_DONATIONS_INCLUDES . '/BtcPay.php';
        require_once SK_DONATIONS_INCLUDES . '/Donations.php';
        require_once SK_DONATIONS_INCLUDES . '/Shortcode.php';
        require_once SK_DONATIONS_INCLUDES . '/Placement.php';
        require_once SK_DONATIONS_INCLUDES . '/AdminPage.php';
    }

    private function instances() {
        new Donations();
        new Shortcode();
        new Placement();

        add_filter( 'sk_php_dashboard_pages', function ( $pages ) {
            $pages['donations'] = new AdminPage();
            return $pages;
        } );
    }
}
