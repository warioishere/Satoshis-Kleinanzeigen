<?php

namespace SK\Core\SettingsApi;

defined( 'ABSPATH' ) || exit;

/**
 * SK Pro Vendor Settings API Manager.
 *
 */
class Manager {

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct() {
        $this->init();
    }

    /**
     * Initialize settings class instance.
     *
     *
     * @return void
     */
    public function init() {
        new Store();
    }
}
