<?php

namespace SK\Core\Dashboard\Modules;

/**
 * Nav text fix (Store count wording) and settings/shipping redirect.
 * Ported from kadence-child/functions.php.
 */
class NavFix {

    public function __construct() {
        add_action( 'wp_footer',        [ $this, 'output_nav_text_fix' ] );
        add_action( 'template_redirect', [ $this, 'redirect_shipping' ] );
    }

    public function output_nav_text_fix(): void {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var el = document.querySelector('p.store-count');
            if (el && el.textContent.includes('Shop Insgesamt Anzeigen')) {
                el.textContent = el.textContent.replace('Shop Insgesamt Anzeigen', 'Anbieter insgesamt');
            }
        });
        </script>
        <?php
    }

    public function redirect_shipping(): void {
        if ( trailingslashit( $_SERVER['REQUEST_URI'] ) === '/dashboard/settings/shipping/' ) {
            wp_redirect( home_url( '/dashboard/settings/regular-shipping/' ), 301 );
            exit;
        }
    }
}
