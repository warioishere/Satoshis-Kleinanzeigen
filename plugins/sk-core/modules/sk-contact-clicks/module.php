<?php

namespace SK\Modules\ContactClicks;

defined( 'ABSPATH' ) || exit;

/**
 * SK Contact Clicks — misst, ob ein Inserat zu einem Kontakt führt.
 *
 * Bis August 2026 war bekannt, wie oft ein Inserat aufgerufen wird (im Schnitt
 * 21 mal), aber nicht, ob daraus je eine Kontaktaufnahme wurde. Ohne diese Zahl
 * lässt sich weder ein Boost verkaufen ("kauf Sichtbarkeit" — wie viel?) noch
 * ein Sponsorenpreis begründen, und jede Kommissionsdiskussion steht auf Sand.
 *
 * Gemessen wird bewusst im Browser statt über eine Weiterleitung: Die
 * Kontaktziele sind teils tel: und mailto:, die sich nicht sauber umleiten
 * lassen. Die Links bleiben deshalb unverändert; ein Beacon meldet den Klick
 * nebenher. Geht das Melden schief, klickt der Besucher trotzdem normal weiter.
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
