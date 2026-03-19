<?php

namespace SK\Core\Shortcodes;

use SK\Core\Abstracts\SkShortcode;

// don't call the file directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CustomerMigration extends SkShortcode {
    /**
     * Shortcode name.
     *
     *
     * @var string Shortcode name
     */
    protected $shortcode = 'sk-customer-migration';

    /**
     * Render [sk-customer-migration] shortcode
     *
     *
     * @param array $atts
     *
     * @return string
     */
    public function render_shortcode( $atts ) {
        ob_start();
        sk_get_container()->get( 'frontend_manager' )->become_a_vendor->load_customer_to_vendor_update_template();
        wp_enqueue_script( 'sk-vendor-registration' );
        return ob_get_clean();
    }
}
