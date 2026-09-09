<?php

namespace SK\Modules\ProductAdvertisement;

use SK\Modules\ProductAdvertisement\Helper;
use WC_Product;
use Exception;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Product Advertisement Module product block.
 *
 */
class BlockData {

    /**
     * Block section name.
     *
     *
     * @var string
     */
    public $section;

    /**
     * Constructor class.
     *
     */
    public function __construct() {
        $this->section = 'product_advertising';

        add_filter( 'sk_rest_get_product_block_data', [ $this, 'get_product_block_data' ], 10, 3 );
        add_action( 'sk_rest_insert_product_object', [ $this, 'set_product_block_data' ], 10, 3 );
    }

    /**
     * Get order-min-max product data
     *
     *
     * @param array      $block
     * @param WC_Product $product
     * @param string     $context
     *
     * @return array
     */
    public function get_product_block_data( array $block, $product, string $context ) {
        if ( ! $product instanceof WC_Product ) {
            return $block;
        }

        // Get advertisement data.
        $advertisement_data = Helper::get_advertisement_data_by_product( $product->get_id() );

        // Modify some column for frontend printing
        $advertisement_data['listing_price']      = wc_price( $advertisement_data['listing_price'] );
        $advertisement_data['remaining_slot']     = Helper::get_formatted_remaining_slot_count( $advertisement_data['remaining_slot'] );
        $advertisement_data['expires_after_days'] = Helper::format_expire_after_days_text( $advertisement_data['expires_after_days'] );

        // Process for block.
        $block[ $this->section ]['advertisement_data']             = $advertisement_data;
        $block[ $this->section ]['sk_advertise_single_product'] = false;

        return $block;
    }

    /**
     * Save purchase advertisement data after REST-API insert or update.
     *
     *
     * @todo Handle this from an API or AJAX request.
     *
     * @param WC_Product      $product  Inserted object.
     * @param WP_REST_Request $request  Request object.
     * @param boolean         $creating True when creating object, false when updating.
     *
     * @return void
     * @throws Exception
     */
    public function set_product_block_data( $product, $request, $creating = true ) {
        if ( ! wc_string_to_bool( $request['sk_advertise_single_product'] ) ) {
            return;
        }

        if ( ! $product instanceof WC_Product ) {
            return;
        }

        $purchased = Helper::purchase_advertisement( $product->get_id() );

        if ( is_wp_error( $purchased ) ) {
            throw new Exception( $purchased->get_error_message(), $purchased->get_error_code() );
        }
    }
}
