<?php

namespace SK\Core\Dashboard;

use SK\Core\Dashboard\Templates\Manager as TemplateManager;
use SK\Core\Traits\ChainableContainer;

class Manager {

    use ChainableContainer;

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        $this->container['templates'] = new TemplateManager();
    }
}
