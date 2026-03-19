<?php

namespace SK\Core\Blocks;

/**
 * SK Block For Products.
 *
 */
class ProductBlock {

    /**
     * Get Product configurations.
     *
     *
     * @return array
     */
    public function get_configurations() {
        $can_create_tags = sk_get_option( 'product_vendors_can_create_tags', 'sk_selling' );

        return apply_filters(
            'sk_get_product_block_configurations',
            [
                'disable_popup' => 'on' === sk_get_option( 'disable_product_popup', 'sk_selling', 'off' ),
                'statuses' => sk_get_available_post_status(),
                'visibility_options' => sk_get_product_visibility_options(),
                'manage_stocks' => 'yes' === get_option( 'woocommerce_manage_stock' ),
                'stock_statuses' => [
                    'instock'    => __( 'In Stock', 'sk-core' ),
                    'outofstock' => __( 'Out of Stock', 'sk-core' ),
                ],
                'backorders' => [
                    'no'     => __( 'Do not allow', 'sk-core' ),
                    'notify' => __( 'Allow, but notify customer', 'sk-core' ),
                    'yes'    => __( 'Allow', 'sk-core' ),
                ],
                'tags' => [
                    'can_create'  => $can_create_tags,
                    'placeholder' => 'on' === $can_create_tags ? __( 'Select tags/Add tags', 'sk-core' ) : __( 'Select product tags', 'sk-core' ),
                    'max_limit'   => apply_filters( 'sk_product_tags_select_max_length', -1 ),
                ],
                'category' => [
                    'default' => get_term( get_option( 'default_product_cat' ) ),
                ],
                'can_export' => false,
                'can_import' => false,
            ]
        );
    }
}
