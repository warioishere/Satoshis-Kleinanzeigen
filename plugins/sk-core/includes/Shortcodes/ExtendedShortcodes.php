<?php

namespace SK\Core\Shortcodes;

// don't call the file directly
use SK\Core\Traits\ChainableContainer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ExtendedShortcodes {

    use ChainableContainer;

    /**
     * Shortcodes container
     *
     */
    public function __construct() {
        $this->set_controllers();
    }

    /**
     * Set controllers
     *
     *
     * @return void
     */
    private function set_controllers() {}
}
