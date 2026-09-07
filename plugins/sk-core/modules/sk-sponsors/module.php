<?php

namespace SK\Modules\Sponsors;

defined( 'ABSPATH' ) || exit;

/**
 * SK Sponsors — homepage sponsor slots as their own module.
 *
 * Replaces the old construction of posts in the "sponsoren" category plus
 * the third-party plugins wp-post-image-carousel (target URL in
 * _wppic_image_link) and wp-post-rank (order in _post_rank) with a
 * dedicated post type.
 *
 * New compared to the old solution is click tracking: sponsor links point
 * to /go/<slug> and are counted there before redirecting. Without these
 * numbers a slot can't be sold.
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
        require_once SK_SPONSORS_INCLUDES . '/Pricing.php';
        require_once SK_SPONSORS_INCLUDES . '/Billing.php';
        require_once SK_SPONSORS_INCLUDES . '/Backlink.php';
        require_once SK_SPONSORS_INCLUDES . '/TopUp.php';
        require_once SK_SPONSORS_INCLUDES . '/Portal.php';
        require_once SK_SPONSORS_INCLUDES . '/Notifier.php';
        require_once SK_SPONSORS_INCLUDES . '/Tracker.php';
        require_once SK_SPONSORS_INCLUDES . '/Shortcode.php';
        require_once SK_SPONSORS_INCLUDES . '/Carousel.php';
        require_once SK_SPONSORS_INCLUDES . '/Migration.php';
        require_once SK_SPONSORS_INCLUDES . '/AdminPage.php';
    }

    private function maybe_install() {
        if ( get_option( 'sk_sponsors_db_version' ) !== $this->version ) {
            Install::install();
            update_option( 'sk_sponsors_db_version', $this->version );
            // The /go/ rule only exists after a flush.
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
        new Carousel();

        add_filter( 'sk_php_dashboard_pages', function ( $pages ) {
            $pages['sponsors'] = new AdminPage();
            return $pages;
        } );
    }
}
