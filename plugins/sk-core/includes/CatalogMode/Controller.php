<?php

namespace SK\Core\CatalogMode;

use SK\Core\Traits\ChainableContainer;
use SK\Core\CatalogMode\Admin\Settings as AdminSettings;
use SK\Core\CatalogMode\Dashboard\ProductBulkEdit;
use SK\Core\CatalogMode\Dashboard\Products;
use SK\Core\CatalogMode\Dashboard\Settings as VendorSettings;

/**
 * Class Controller
 *
 * This class will include all the related files required for Catalog Mode feature and will work as an entry point for
 * all the hooks.
 *
 *
 */
class Controller {

    use ChainableContainer;

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        $this->set_controllers();
    }

    /**
     * This method will load all the required files
     *
     *
     * @return void
     */
    private function set_controllers() {
        // load admin stuff
        $this->container['admin_settings'] = new AdminSettings();
        // load frontend stuff
        if ( ! is_admin() ) {
            $this->container['products']  = new Products();
            $this->container['bulk_edit'] = new ProductBulkEdit();
        }

        // ajax hooks won't work with inline_edit
        $this->container['vendor_settings'] = new VendorSettings();
        $this->container['hooks']           = new Hooks();
    }
}
