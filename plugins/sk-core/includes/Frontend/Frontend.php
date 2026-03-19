<?php

namespace SK\Core\Frontend;

use SK\Core\Frontend\MyAccount\BecomeAVendor;
use SK\Core\Traits\ChainableContainer;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Frontend Manager
 *
 *
 * @property BecomeAVendor $become_a_vendor Instance of BecomeAVendor class
 */
class Frontend {

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
    private function set_controllers() {
        $this->container['become_a_vendor'] = new BecomeAVendor();
    }
}
