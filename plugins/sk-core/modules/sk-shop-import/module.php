<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * SK Shop Import — Händlerkataloge als Inserate.
 *
 * Ein Partnershop exportiert in seinem eigenen WooCommerce eine CSV und lädt
 * sie hier hoch. Bewusst CSV statt REST-API: keine Zugangsdaten, keine
 * dauerhafte Verbindung, kein stiller Ausfall eines Abgleichs.
 *
 * Varianten werden zu Ausführungen des Elternprodukts zusammengefasst — im
 * Beispielexport waren 42 von 70 Zeilen Varianten, die als eigene Inserate
 * dieselbe Ware mehrfach gezeigt hätten. Preise kommen in Fiat herein, werden
 * in Sats umgerechnet und täglich am Kurs nachgeführt; der Fiat-Betrag bleibt
 * die Wahrheit.
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
        defined( 'SK_SHOP_IMPORT_VERSION' )  || define( 'SK_SHOP_IMPORT_VERSION', $this->version );
        defined( 'SK_SHOP_IMPORT_FILE' )     || define( 'SK_SHOP_IMPORT_FILE', __FILE__ );
        defined( 'SK_SHOP_IMPORT_PATH' )     || define( 'SK_SHOP_IMPORT_PATH', dirname( SK_SHOP_IMPORT_FILE ) );
        defined( 'SK_SHOP_IMPORT_INCLUDES' ) || define( 'SK_SHOP_IMPORT_INCLUDES', SK_SHOP_IMPORT_PATH . '/includes' );
        defined( 'SK_SHOP_IMPORT_URL' )      || define( 'SK_SHOP_IMPORT_URL', plugins_url( '', SK_SHOP_IMPORT_FILE ) );
    }

    private function includes() {
        foreach ( [ 'Dealer', 'Rate', 'Csv', 'Catalog', 'Shopify', 'Source', 'Settings', 'Quota', 'Storage', 'Silence', 'Job', 'Importer', 'PriceRefresh', 'Variants', 'PriceUnit', 'Display', 'DashboardPage', 'AdminPage' ] as $class ) {
            require_once SK_SHOP_IMPORT_INCLUDES . '/' . $class . '.php';
        }
    }

    private function instances() {
        new DashboardPage();
        new PriceRefresh();
        new Display();
        new Variants();
        new PriceUnit();

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 20 );

        add_filter( 'sk_php_dashboard_pages', function ( $pages ) {
            $pages['dealers'] = new AdminPage();
            return $pages;
        } );
    }

    public function enqueue(): void {
        wp_enqueue_style(
            'sk-shop-import',
            SK_SHOP_IMPORT_URL . '/assets/css/sk-shop-import.css',
            [],
            SK_SHOP_IMPORT_VERSION
        );

        wp_enqueue_script(
            'sk-shop-import',
            SK_SHOP_IMPORT_URL . '/assets/js/sk-shop-import.js',
            [],
            SK_SHOP_IMPORT_VERSION,
            true
        );
    }
}
