<?php

namespace SK\Core\Blocks;

use WC_Product;

defined( 'ABSPATH' ) || exit;

class Product {

    /**
     * Constructor class.
     *
     */
    public function __construct() {
        add_filter( 'sk_rest_get_product_block_data', [ $this, 'set_block_data' ], 10, 3 );
    }

    /**
     * Set product block data for SK-pro.
     *
     *
     * @param array      $block
     * @param WC_Product $product
     * @param string     $context
     *
     * @return array
     */
    public function set_block_data( $block, $product, $context = 'view' ) {
        return $block;
    }

    /**
     * Get formatted products from IDS with name and id.
     *
     *
     * @param array $product_ids
     *
     * @return array
     */
    private function get_formatted_products( $product_ids = [] ) {
        if ( ! is_array( $product_ids ) || ! count( $product_ids ) ) {
            return [];
        }

        $products = wc_get_products(
            [
				'include' => $product_ids,
			]
        );

        $formatted_products = [];
        foreach ( $products as $product ) {
            $formatted_products[] = [
                'label' => $product->get_name(),
                'value' => $product->get_id(),
            ];
        }

        return $formatted_products;
    }
}
