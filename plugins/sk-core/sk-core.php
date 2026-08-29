<?php
/**
 * Plugin Name: SK Core
 * Plugin URI: https://satoshiskleinanzeigen.space/
 * Description: A private multivendor marketplace plugin for WordPress.
 * Version: 4.3.1
 * Author: SK
 * Author URI: https://satoshiskleinanzeigen.space/
 * Text Domain: sk-core
 * Requires Plugins: woocommerce
 * WC requires at least: 8.5.0
 * WC tested up to: 10.4.3
 * Domain Path: /languages/
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/autoload.php';
require_once __DIR__ . '/sk-core-class.php';

defined( 'SK_CORE_FILE' ) || define( 'SK_CORE_FILE', __FILE__ );

use SK\Core\DependencyManagement\Container;

global $sk_container;
$sk_container = new Container();
$sk_container->addServiceProvider( new \SK\Core\DependencyManagement\Providers\ServiceProvider() );

function sk_get_container(): Container {
    global $sk_container;
    return $sk_container;
}

function sk() {
    return SK_Core::init();
}

sk();

// Module constants
defined( 'SK_CORE_MODULE_DIR' ) || define( 'SK_CORE_MODULE_DIR', SK_CORE_DIR . '/modules' );
defined( 'SK_CORE_MODULE_URL' ) || define( 'SK_CORE_MODULE_URL', plugins_url( 'modules', __FILE__ ) );
defined( 'SK_CORE_TEMPLATE_DIR' ) || define( 'SK_CORE_TEMPLATE_DIR', SK_CORE_DIR . '/templates' );

// Bootstrap extended features
function sk_ext() {
    static $instance = null;
    if ( null === $instance ) {
        $instance = new \SK\Core\Bootstrap();
    }
    return $instance;
}
sk_ext();

// Auto-assign free vendor pack.
\SK\Core\FreePack::init();

// Globaler Katalog-Modus (hide add-to-cart + optional price).
add_action( 'init', [ \SK\Core\CatalogMode::class, 'init' ], 20 );
add_action( 'init', [ \SK\Core\Antispam::class, 'init' ], 20 );

// Seiten-Cache leeren wenn Inhalte gelöscht oder versteckt werden.
\SK\Core\PageCache::init();

// Buy Now — direct BTCPay checkout for subscriptions & boosts.
\SK\Core\BuyNow::init();

// Vendor avatar + name on product cards.
\SK\Core\ProductVendorInfo::init();

// "Rezension(en)" → "Kommentar(e)" auf Produktseiten (Tab, Form, Headings).
// Rezension = feminin (die), Kommentar = maskulin (der) — Artikel mit umtauschen,
// sonst Grammatik-Fehler à la "die erste Kommentar".
add_filter( 'gettext', function ( $translation, $text, $domain ) {
    if ( $domain !== 'woocommerce' ) {
        return $translation;
    }
    // strtr matched longest substring first → längere Phrasen vor kurzen.
    $map = [
        'Schreibe die erste Rezension für' => 'Schreibe den ersten Kommentar zu',
        'die erste Rezension'              => 'den ersten Kommentar',
        'eine Rezension'                   => 'einen Kommentar',
        'Deine Rezension'                  => 'Dein Kommentar',
        'Rezensionen'                      => 'Kommentare',
        'Rezension'                        => 'Kommentar',
    ];
    return strtr( $translation, $map );
}, 20, 3 );
add_filter( 'ngettext', function ( $translation, $single, $plural, $number, $domain ) {
    if ( $domain !== 'woocommerce' ) {
        return $translation;
    }
    return strtr( $translation, [
        'Rezensionen' => 'Kommentare',
        'Rezension'   => 'Kommentar',
    ] );
}, 20, 5 );
