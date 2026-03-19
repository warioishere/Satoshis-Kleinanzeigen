<?php

namespace SK\Core\Shortcodes;

use SK\Core\Abstracts\SkShortcode;

class MyOrders extends SkShortcode {

    protected $shortcode = 'sk-my-orders';

    /**
     * Render my orders page
     *
     * @return string
     */
    public function render_shortcode( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '';
        }

        ob_start();

        sk_get_template_part( 'my-orders' );

        return ob_get_clean();
    }
}
