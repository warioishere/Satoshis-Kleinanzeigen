<?php

namespace SK\Core\Dashboard\Templates;

/**
 * Multi step category ui class.
 *
 */
class MultiStepCategories {

    /**
     * Class constructor.
     *
     */
    public function __construct() {
        add_action( 'wp_footer', [ $this, 'load_add_category_modal' ], 10 );
    }

    /**
     * Returns new category select ui html elements.
     *
     *
     * @return void
     */
    public function load_add_category_modal() {
        /**
         * Checking if sk dashboard or add product page or product edit page or product list.
         * Because without those page we don't need to load category modal.
         */
        global $wp;
        if ( ( sk_is_seller_dashboard() && isset( $wp->query_vars['products'] ) )
            || ( isset( $wp->query_vars['products'], $_GET['product_id'] ) ) // phpcs:ignore
            || ( isset( $wp->query_vars['new-product'] ) )
        ) {
            sk_get_template_part( 'products/sk-category-ui', '', [] );
        }
    }
}
