<?php

namespace SK\Core\Shortcodes;

use SK\Core\Abstracts\SkShortcode;

class VendorRegistration extends SkShortcode {

    protected $shortcode = 'sk-vendor-registration';

    /**
     * Vendor regsitration form shortcode callback
     *
     * @return string
     */
    public function render_shortcode( $atts ) {
        if ( is_user_logged_in() ) {
            return esc_html__( 'You are already logged in', 'sk-core' );
        }

        sk()->scripts->load_form_validate_script();

        wp_enqueue_script( 'sk-form-validate' );
        wp_enqueue_script( 'sk-vendor-registration' );
        wp_enqueue_script( 'sk-vendor-address' );

        $data = sk_get_seller_registration_form_data();

        ob_start();
        sk_get_template_part( 'account/vendor-registration', false, [ 'data' => $data ] );
        $content = ob_get_clean();

        return apply_filters( 'sk_vendor_reg_form', $content );
    }
}
