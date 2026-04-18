<?php

namespace SK\Core\Dashboard\Templates;

use SK\Core\Traits\ChainableContainer;

class Manager {

    use ChainableContainer;

    public function __construct() {
        $this->container['main']             = new Main();
        $this->container['dashboard']        = new Dashboard();
        $this->container['products']         = new Products();
        $this->container['settings']         = new Settings();
        $this->container['product_category'] = new MultiStepCategories();
    }
}
