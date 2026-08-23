<?php

namespace SK\Modules\Sponsors;

defined( 'ABSPATH' ) || exit;

/**
 * SK Sponsors — Sponsorenflächen der Startseite als eigenes Modul.
 *
 * Ersetzt die Konstruktion aus Beiträgen der Kategorie "sponsoren" plus den
 * Fremd-Plugins wp-post-image-carousel (Ziel-URL in _wppic_image_link) und
 * wp-post-rank (Reihenfolge in _post_rank) durch einen eigenen Post-Type.
 *
 * Neu gegenüber der alten Lösung ist die Klickmessung: Sponsorenlinks zeigen
 * auf /go/<slug> und werden dort gezählt, bevor weitergeleitet wird. Ohne
 * diese Zahlen lässt sich ein Platz nicht verkaufen.
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
        defined( 'SK_SPONSORS_VERSION' )  || define( 'SK_SPONSORS_VERSION', $this->version );
        defined( 'SK_SPONSORS_FILE' )     || define( 'SK_SPONSORS_FILE', __FILE__ );
        defined( 'SK_SPONSORS_PATH' )     || define( 'SK_SPONSORS_PATH', dirname( SK_SPONSORS_FILE ) );
        defined( 'SK_SPONSORS_INCLUDES' ) || define( 'SK_SPONSORS_INCLUDES', SK_SPONSORS_PATH . '/includes' );
        defined( 'SK_SPONSORS_URL' )      || define( 'SK_SPONSORS_URL', plugins_url( '', SK_SPONSORS_FILE ) );
    }

    private function includes() {
        require_once SK_SPONSORS_INCLUDES . '/Install.php';
        require_once SK_SPONSORS_INCLUDES . '/PostType.php';
        require_once SK_SPONSORS_INCLUDES . '/Stats.php';
        require_once SK_SPONSORS_INCLUDES . '/Billing.php';
        require_once SK_SPONSORS_INCLUDES . '/Backlink.php';
        require_once SK_SPONSORS_INCLUDES . '/TopUp.php';
        require_once SK_SPONSORS_INCLUDES . '/Portal.php';
        require_once SK_SPONSORS_INCLUDES . '/Notifier.php';
        require_once SK_SPONSORS_INCLUDES . '/Tracker.php';
        require_once SK_SPONSORS_INCLUDES . '/Shortcode.php';
        require_once SK_SPONSORS_INCLUDES . '/Migration.php';
        require_once SK_SPONSORS_INCLUDES . '/AdminPage.php';
    }

    private function maybe_install() {
        if ( get_option( 'sk_sponsors_db_version' ) !== $this->version ) {
            Install::install();
            update_option( 'sk_sponsors_db_version', $this->version );
            // Die /go/-Regel existiert erst nach einem Flush.
            update_option( 'sk_rewrite_rules_needs_flashing', 'yes' );
        }
    }

    private function instances() {
        new PostType();
        new Tracker();
        new Billing();
        new TopUp();
        new Portal();
        new Notifier();
        new Shortcode();

        add_filter( 'sk_php_dashboard_pages', function ( $pages ) {
            $pages['sponsors'] = new AdminPage();
            return $pages;
        } );
    }
}
