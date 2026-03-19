<?php

namespace SK\Modules\Subscription;

use SK\Core\Abstracts\ProductStatusChanger;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Product status changer class
 *
 */
class HelperChangerProductStatus extends ProductStatusChanger {

    /**
     * Get products to process
     *
     *
     * @return int[]
     */
    public function get_products() {
        $product_types = array_filter(
            wc_get_product_types(), function ( $type ) {
                return 'product_pack' !== $type;
            }
        );

        $status = sk_get_option( 'product_status_after_end', 'sk_product_subscription', 'draft' );

        $args = [
            'status' => 'change_status' === $this->get_task_type() ? [ 'publish', 'pending' ] : $status,
            'type'   => array_merge( array_keys( $product_types ) ),
            'author' => $this->get_vendor_id(),
            'page'   => $this->get_current_page(),
            'limit'  => $this->get_per_page(),
            'return' => 'ids',
        ];

        return wc_get_products( $args );
    }
}
