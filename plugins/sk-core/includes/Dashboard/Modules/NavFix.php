<?php

namespace SK\Core\Dashboard\Modules;

class NavFix {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    public function enqueue(): void {
        $path = SK_CORE_DIR . '/assets/js/dashboard/nav-text-fix.js';
        wp_enqueue_script(
            'sk-nav-text-fix',
            SK_CORE_ASSETS . '/js/dashboard/nav-text-fix.js',
            [],
            file_exists( $path ) ? (string) filemtime( $path ) : SK_CORE_VERSION,
            true
        );
    }
}
