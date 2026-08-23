<?php

namespace SK\Modules\Donations;

defined( 'ABSPATH' ) || exit;

/**
 * SK Donations — Spenden mit sichtbarer Kostendeckung.
 *
 * Ausgangslage (August 2026): Die Seite /spenden wurde in 90 Tagen 35 mal
 * aufgerufen, bei 10.637 Seitenaufrufen insgesamt; gespendet wurde dreimal,
 * alles am selben Tag im September 2025. Ein weiterer Link auf diese Seite
 * würde daran nichts ändern.
 *
 * Deshalb ist die Bitte hier kein Link, sondern ein Element: Es nennt den
 * Monatsbedarf, den aktuellen Stand und bietet die Beträge direkt an. Bezahlt
 * wird über dieselbe Strecke wie Abos, Boosts und Sponsorenguthaben —
 * WooCommerce plus BTCPay —, damit jede Spende erfasst wird und der Balken
 * sich von selbst bewegt.
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
