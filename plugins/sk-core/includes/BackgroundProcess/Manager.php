<?php

namespace SK\Core\BackgroundProcess;

defined( 'ABSPATH' ) || exit;

use SK\Core\Traits\ChainableContainer;
use SK\Core\Vendor\ChangeProductStatus as ChangeVendorProductStatus;

/**
 * Background Process Manager Class.
 *
 *
 * @property ChangeVendorProductStatus $change_vendor_product_status Instance of SK\Core\Vendor\ChangeProductStatus class
 */
class Manager {

    use ChainableContainer;

    /**
     * Class constructor.
     */
    public function __construct() {
        $this->init_classes();
        $this->init_hooks();
    }

    /**
     * Initialize classes to chainable container.
     *
     *
     * @return void
     */
    public function init_classes() {
        $this->container['rewrite_variable_products_author'] = new RewriteVariableProductsAuthor();
        $this->container['change_vendor_product_status']     = new ChangeVendorProductStatus();

        $this->container = apply_filters( 'sk_background_process_container', $this->container );
    }

    /**
     * Initialize hooks.
     *
     *
     * @return void
     */
    public function init_hooks() {
        add_filter( 'sk_admin_notices', [ $this, 'show_variable_products_author_updated_notice' ], 10, 1 );
    }

    /**
     * Show variable products author updated notice.
     *
     *
     * @param array $notices
     *
     * @return array $notices
     */
    public function show_variable_products_author_updated_notice( $notices ) {
        if ( empty( get_transient( 'sk_variable_products_author_updated' ) ) ) {
            return $notices;
        }

        // Remove the cache for showing the notice only once.
        delete_transient( 'sk_variable_products_author_updated' );

        $notices[] = [
            'type'        => 'success',
            'title'       => __( 'SK Variable Products Updated', 'sk-core' ),
            'description' => __( 'SK variable products author IDs regenerated successfully!', 'sk-core' ),
            'priority'    => 0,
        ];

        return $notices;
    }
}
