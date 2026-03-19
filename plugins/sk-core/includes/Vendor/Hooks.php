<?php

namespace SK\Core\Vendor;

class Hooks {

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        // Init Vendor Cache Class
        new VendorCache();

        // init Vendor Settings Manager
        new SettingsApi\Manager();

    }
}
