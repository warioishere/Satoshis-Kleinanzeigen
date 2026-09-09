<?php
namespace SK\Modules\ProductAdvertisement;

use WP_Error;
use SK\Core\Cache;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class AdvertisementCache
 *
 *
 */
class AdvertisementCache {
    /**
     * AdvertisementCache constructor.
     *
     *
     * @return void
     */
    public function __construct() {
        // after advertisement status has been changed
        add_action( 'sk_after_product_advertisement_created', [ $this, 'after_product_advertisement_created' ], 10, 3 );
        add_action( 'sk_before_deleting_product_advertisement', [ $this, 'before_deleting_product_advertisement' ], 10, 1 );
        add_action( 'sk_before_batch_delete_product_advertisement', [ $this, 'batch_delete_product_advertisement' ], 10, 1 );
        add_action( 'sk_before_batch_expire_product_advertisement', [ $this, 'batch_delete_product_advertisement' ], 10, 1 );
        // after product status has been updated
        add_action( 'sk_new_product_added', [ $this, 'after_product_update' ], 20, 1 );
        add_action( 'sk_product_updated', [ $this, 'after_product_update' ], 20, 1 );
        add_action( 'woocommerce_update_product', [ $this, 'after_product_update' ], 20, 1 );
        add_action( 'wp_trash_post', [ $this, 'after_product_update' ], 20, 1 );
    }

    /**
     * This method will delete advertisement cache by ids
     *
     *
     * @param $advertisement_ids
     *
     * @return void
     */
    public function batch_delete_product_advertisement( $advertisement_ids ) {
        foreach ( $advertisement_ids as $advertisement_id ) {
            $this->before_deleting_product_advertisement( $advertisement_id );
        }
    }

    /**
     * Delete cache by advertisement id
     *
     *
     * @param int $advertisement_id
     *
     * @return void
     */
    public function before_deleting_product_advertisement( $advertisement_id ) {
        $manager = new Manager();
        $advertisement_data = $manager->get( $advertisement_id );
        if ( is_wp_error( $advertisement_data ) ) {
            static::delete();
            return;
        }

        // get seller id
        $seller_id = sk_get_vendor_by_product( $advertisement_data['product_id'], true );
        if ( ! $seller_id && ! empty( $advertisement_data['order_id'] ) ) {
            $seller_id = sk_get_seller_id_by_order( $advertisement_data['order_id'] );
        }

        // delete cache
        static::delete( $seller_id );
    }

    /**
     * Delete advertisement cache after new advertisement is created
     *
     *
     * @param int $advertisement_id
     * @param array $advertisement_data
     * @param array $args
     *
     * @return void
     */
    public function after_product_advertisement_created( $advertisement_id, $advertisement_data, $args ) {
        // clear advertisement cache
        $seller_id = sk_get_vendor_by_product( $advertisement_data['product_id'], true );
        if ( ! $seller_id && ! empty( $args['order_id'] ) ) {
            $seller_id = sk_get_seller_id_by_order( $args['order_id'] );
        }

        static::delete( $seller_id );
    }

    /**
     * Invalidate Advertisement Seller Cache
     *
     *
     * @param int|null $seller_id
     *
     * @return void
     */
    public static function delete( $seller_id = null ) {
        // delete global advertisement cache
        $cache_group = 'advertised_product';
        Cache::invalidate_group( $cache_group );

        // delete individual seller cache
        if ( is_numeric( $seller_id ) ) {
            $cache_group = "advertised_product_{$seller_id}";
            Cache::invalidate_group( $cache_group );
        }
    }

    /**
     * Delete advertisement cache after a product has been updated
     *
     *
     * @param int|\WC_Product $product
     *
     * @return void
     */
    public static function after_product_update( $product ) {
        // some hooks can return product object also, making sure we are getting id only
        if ( ! $product instanceof \WC_Product ) {
            $product = wc_get_product( $product );
        }

        if ( ! $product instanceof \WC_Product ) {
            return;
        }

        $seller_id = sk_get_vendor_by_product( $product, true );

        self::delete( $seller_id );
    }
}
