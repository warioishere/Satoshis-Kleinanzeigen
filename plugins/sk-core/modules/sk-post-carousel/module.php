<?php

namespace SK\Modules\PostCarousel;

defined( 'ABSPATH' ) || exit;

/**
 * Post Image Carousel — [post_image_carousel] shortcode over post featured
 * images, with an optional per-post external link for the "sponsoren"
 * category.
 *
 * Absorbed from the standalone `wp-post-image-carousel` plugin: only the
 * plugin-lifecycle parts (the WordPress "Settings" admin page) are replaced
 * by the module system — the shortcode, meta box and permalink filters are
 * unchanged.
 */
final class Module {

    public $version;

    public function __construct() {
        $this->version = sk_assets_version( __DIR__ . '/assets' );

        $this->define_constants();
        $this->includes();
        $this->instances();
    }

    private function define_constants() {
        define( 'SK_POST_CAROUSEL_VERSION', $this->version );
        define( 'SK_POST_CAROUSEL_FILE', __FILE__ );
        define( 'SK_POST_CAROUSEL_PATH', dirname( SK_POST_CAROUSEL_FILE ) );
        define( 'SK_POST_CAROUSEL_INCLUDES', SK_POST_CAROUSEL_PATH . '/includes' );
        define( 'SK_POST_CAROUSEL_URL', plugins_url( '', SK_POST_CAROUSEL_FILE ) );
    }

    private function includes() {
        require_once SK_POST_CAROUSEL_INCLUDES . '/Settings.php';
        require_once SK_POST_CAROUSEL_INCLUDES . '/Carousel.php';
    }

    private function instances() {
        new Settings();
        new Carousel();
    }
}
