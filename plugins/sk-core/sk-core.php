<?php
/**
 * Plugin Name: SK Core
 * Plugin URI: https://satoshiskleinanzeigen.space/
 * Description: A private multivendor marketplace plugin for WordPress.
 * Version: 4.3.20
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

// Global catalog mode (hide add-to-cart + optional price).
add_action( 'init', [ \SK\Core\CatalogMode::class, 'init' ], 20 );
add_action( 'init', [ \SK\Core\Antispam::class, 'init' ], 20 );

// Clear the page cache when content is deleted or hidden.
\SK\Core\PageCache::init();

// Buy Now — direct BTCPay checkout for subscriptions & boosts.
\SK\Core\BuyNow::init();

// Wallet connections of every user (NWC, LNDHub, onchain, Lightning address)
// and the LNURL-pay endpoint that mints invoices from them.
new \SK\Core\Wallet\Settings();
new \SK\Core\Wallet\LnurlPayEndpoint();

// Public questions on a listing, with the vendor answering from the dashboard.
\SK\Core\Product\QuestionsTab::init();

// Product description excerpt on shop/category loops.
\SK\Core\ProductDescriptionExcerpt::init();

// Product category search box shortcode ([woo_kategorie_finder]).
\SK\Core\CategoryFinder::init();

// Geo-based fiat price under sats prices (EUR/CHF from browser locale).
\SK\Core\GeoFiatPrice::init();

// Vendor avatar + name on product cards.
\SK\Core\ProductVendorInfo::init();

// "Rezension(en)" → "Bewertung(en)" on product pages (tab, form, headings).
// These carry a star rating, so the plainer word fits — and it is feminine
// like Rezension, so no article has to be rewritten along with it. Questions
// live in their own tab, see SK\Core\Product\QuestionsTab.
$sk_review_wording = [
    'Rezensionen' => 'Bewertungen',
    'Rezension'   => 'Bewertung',
];

add_filter( 'gettext', function ( $translation, $text, $domain ) use ( $sk_review_wording ) {
    // strtr matches the longest substring first → plural before singular.
    return 'woocommerce' === $domain ? strtr( $translation, $sk_review_wording ) : $translation;
}, 20, 3 );

add_filter( 'ngettext', function ( $translation, $single, $plural, $number, $domain ) use ( $sk_review_wording ) {
    return 'woocommerce' === $domain ? strtr( $translation, $sk_review_wording ) : $translation;
}, 20, 5 );
