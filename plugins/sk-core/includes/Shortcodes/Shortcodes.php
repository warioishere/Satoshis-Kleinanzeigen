<?php

namespace SK\Core\Shortcodes;

use SK\Core\Shortcodes\BestSellingProduct;
use SK\Core\Shortcodes\Dashboard;
use SK\Core\Shortcodes\MyOrders;
use SK\Core\Shortcodes\Stores;
use SK\Core\Shortcodes\TopRatedProduct;
use SK\Core\Shortcodes\VendorRegistration;

class Shortcodes {

    private $shortcodes = [];

    /**
     *  Register SK shortcodes
     *
     *
     * @return void
     */
    public function __construct() {
        $this->shortcodes = apply_filters(
            'sk_shortcodes', [
				'sk-dashboard'            => new Dashboard(),
				'sk-best-selling-product' => new BestSellingProduct(),
				'sk-top-rated-product'    => new TopRatedProduct(),
				'sk-my-orders'            => new MyOrders(),
				'sk-stores'               => new Stores(),
				'sk-vendor-registration'  => new VendorRegistration(),
				'sk-customer-migration'   => new CustomerMigration(),
			]
        );

    }

    /**
     * Get registered shortcode classes
     *
     *
     * @return array
     */
    public function get_shortcodes() {
        return $this->shortcodes;
    }
}
