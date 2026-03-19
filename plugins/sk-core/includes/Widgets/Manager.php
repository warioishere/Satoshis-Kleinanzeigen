<?php

namespace SK\Core\Widgets;

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
        $sk_widgets = apply_filters(
            'sk_widgets', [
				'best_selling_products' => 'SK\Core\Widgets\BestSellingProducts',
				'product_category_menu' => 'SK\Core\Widgets\ProductCategoryMenu',
				'store_contact_form'    => 'SK\Core\Widgets\StoreContactForm',
				'store_location'        => 'SK\Core\Widgets\StoreLocation',
				'store_category_menu'   => 'SK\Core\Widgets\StoreCategoryMenu',
				'toprated_products'     => 'SK\Core\Widgets\TopratedProducts',

				'filter_by_attribute'   => 'SK\Core\Widgets\FilterByAttributes',
			]
        );

        foreach ( $sk_widgets as $widget_id => $widget_class ) {
            register_widget( $widget_class );
        }

        $this->container = $sk_widgets;
    }

    /**
     * Check if widget class exists
     *
     *
     * @param string $widget_id
     *
     * @return bool
     */
    public function is_exists( $widget_id ) {
        return isset( $this->container[ $widget_id ] ) && class_exists( $this->container[ $widget_id ] );
    }

    /**
     * Get widget id from widget class
     *
     *
     * @param string $widget_class
     *
     * @return bool|string Returns widget id if found, outherwise returns false
     */
    public function get_id( $widget_class ) {
        return array_search( $widget_class, $this->container, true );
    }
}
