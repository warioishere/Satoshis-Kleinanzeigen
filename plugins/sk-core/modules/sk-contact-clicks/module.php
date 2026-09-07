<?php

namespace SK\Modules\ContactClicks;

defined( 'ABSPATH' ) || exit;

/**
 * SK Contact Clicks — measures whether a listing leads to a contact.
 *
 * Until August 2026 it was known how often a listing gets viewed (21 times
 * on average), but not whether a contact ever resulted from that. Without
 * this number, neither a boost can be sold ("buy visibility" — how much?)
 * nor a sponsor price justified, and every commission discussion rests on
 * sand.
 *
 * Measurement is deliberately done in the browser instead of via a redirect:
 * some contact targets are tel: and mailto:, which can't be redirected
 * cleanly. The links therefore stay unchanged; a beacon reports the click
 * on the side. If the reporting fails, the visitor still clicks through
 * normally.
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
        defined( 'SK_CC_VERSION' )  || define( 'SK_CC_VERSION', $this->version );
        defined( 'SK_CC_FILE' )     || define( 'SK_CC_FILE', __FILE__ );
        defined( 'SK_CC_PATH' )     || define( 'SK_CC_PATH', dirname( SK_CC_FILE ) );
        defined( 'SK_CC_INCLUDES' ) || define( 'SK_CC_INCLUDES', SK_CC_PATH . '/includes' );
        defined( 'SK_CC_URL' )      || define( 'SK_CC_URL', plugins_url( '', SK_CC_FILE ) );
    }

    private function includes() {
        require_once SK_CC_INCLUDES . '/Install.php';
        require_once SK_CC_INCLUDES . '/Tracker.php';
        require_once SK_CC_INCLUDES . '/Stats.php';
        require_once SK_CC_INCLUDES . '/AdminPage.php';
    }

    private function maybe_install() {
        if ( get_option( 'sk_contact_clicks_db_version' ) !== $this->version ) {
            Install::install();
            update_option( 'sk_contact_clicks_db_version', $this->version );
        }
    }

    private function instances() {
        new Tracker();

        add_filter( 'sk_php_dashboard_pages', function ( $pages ) {
            $pages['contact-clicks'] = new AdminPage();
            return $pages;
        } );
    }
}
