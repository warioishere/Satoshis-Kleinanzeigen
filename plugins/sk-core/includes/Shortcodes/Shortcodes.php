<?php

namespace SK\Core\Shortcodes;

use SK\Core\Shortcodes\BestSellingProduct;
use SK\Core\Shortcodes\Dashboard;
use SK\Core\Shortcodes\Stores;
use SK\Core\Shortcodes\TopRatedProduct;

class Shortcodes {

    private $shortcodes = [];

    public function __construct() {
        $this->shortcodes = apply_filters(
            'sk_shortcodes', [
                'sk-dashboard'            => new Dashboard(),
                'sk-best-selling-product' => new BestSellingProduct(),
                'sk-top-rated-product'    => new TopRatedProduct(),
                'sk-stores'               => new Stores(),
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
