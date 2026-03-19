<?php

namespace SK\Core\Vendor\SettingsApi;

use SK\Core\Vendor\SettingsApi\Settings\Pages\Payments\Gateways\Bank;
use SK\Core\Vendor\SettingsApi\Settings\Pages\Payments\Gateways\PayPal;
use SK\Core\Vendor\SettingsApi\Settings\Pages\Payments\Payments;
use SK\Core\Vendor\SettingsApi\Settings\Pages\Store;

defined( 'ABSPATH' ) || exit;

/**
 * Vendor Settings API Manager.
 *
 */
class Manager {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->init();
    }

    /**
     * Initialize the class instance.
     *
     *
     * @return void
     */
    private function init() {
        new Store();
        new Payments();
        new PayPal();
        new Bank();
    }
}
