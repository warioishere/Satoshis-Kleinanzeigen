<?php

namespace SK\Core\Dashboard\Modules;

/**
 * Nav text fix (Store count wording).
 * Ported from kadence-child/functions.php.
 */
class NavFix {

    public function __construct() {
        add_action( 'wp_footer', [ $this, 'output_nav_text_fix' ] );
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
}
