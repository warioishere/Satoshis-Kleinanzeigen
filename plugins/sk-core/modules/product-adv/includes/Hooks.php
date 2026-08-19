<?php
namespace SK\Modules\ProductAdvertisement;

use SK\Modules\ProductAdvertisement\Frontend\ProductSection;
use SK\Core\Utilities\OrderUtil;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class Hooks
 *
 *
 */
class Hooks {
    /**
     * Hooks constructor.
     */
    public function __construct() {
        // make product featured
        add_action( 'sk_after_product_advertisement_created', [ $this, 'make_product_featured' ], 10, 2 );
        // remove featured products after an advertisement is expired
        add_action( 'sk_after_batch_expire_product_advertisement', [ $this, 'remove_featured_product' ], 10, 1 );

        // remove from feature product during product delete
        add_action( 'sk_before_deleting_product_advertisement', [ $this, 'remove_deleted_featured_product' ], 10, 1 );
        add_action( 'sk_before_batch_delete_product_advertisement', [ $this, 'remove_deleted_featured_product' ], 10, 1 );

        // expire advertisements daily cron hook
        add_action( 'sk_product_advertisement_daily_at_midnight_cron', [ $this, 'expire_advertisements' ] );

        // remove advertisement base product after advertisement product has been deleted
        add_action( 'delete_post', [ $this, 'delete_advertisement_base_product' ], 20 );

        //display advertised products on top
        add_action( 'posts_results', [ $this, 'display_advertised_products_on_top' ], 10, 2 );

        //render advertise product section in single store page
        add_filter( 'sk_product_sections_container', [ $this, 'render_product_section' ], 99, 1 );

        // after deleting a product, delete advertisement
        add_action( 'delete_post', [ $this, 'delete_advertisement' ], 20, 1 );

        // remove min max rules for advertisement product.
        add_filter( 'sk_validate_min_max_rules_for_product', [ $this, 'remove_min_max_for_advertisements' ], 10, 2 );

        // make product purchasable for vendors own product.
        add_filter( 'sk_vendor_own_product_purchase_restriction', [ $this, 'make_product_purchasable_for_advertisement' ], 10, 2 );

        // Add a Product Advertisement filter option to order the type filter dropdown.
        add_filter( 'sk_order_type_filter_options', [ $this, 'add_product_advertisement_filter_option' ] );
        add_filter( 'sk_order_type_filter_query_args', [ $this, 'filter_product_advertisement_orders' ], 10, 3 );

        // Boost icon styling on vendor dashboard.
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_boost_icon_css' ], 20 );
    }

    /**
     * Inject boost-icon CSS on the vendor dashboard.
     */
    public function enqueue_boost_icon_css() {
        if ( ! function_exists( 'sk_is_seller_dashboard' ) || ! sk_is_seller_dashboard() ) {
            return;
        }

        $handle       = wp_style_is( 'sk-style', 'registered' ) ? 'sk-style' : 'wp-block-library';
        $icon_default = esc_url_raw( SK_PRODUCT_ADV_ASSETS . '/images/boost.svg' );
        $icon_active  = esc_url_raw( SK_PRODUCT_ADV_ASSETS . '/images/boost-active.svg' );

        $css = "
            .sk-dashboard .adv_icon_2{
                font-size:0 !important; line-height:1; position:relative;
                width:28px; height:28px; display:inline-block; vertical-align:middle;
                background:url('{$icon_default}') no-repeat center; background-size:contain;
            }
            .sk-dashboard .adv_icon_1{ display:none !important; }
            .sk-dashboard span.sk-product-advertisement.advertised .adv_icon_2,
            .sk-dashboard span.sk-product-advertisement[data-already-advertised=\"advertised\"] .adv_icon_2{
                background:url('{$icon_active}') no-repeat center !important; background-size:contain !important;
            }
            .sk-dashboard td.product-advertisement-td{ text-align:center; }
            .sk-dashboard td.product-advertisement-td .boost-label{
                display:block; margin-top:6px; font-size:13px; font-weight:600; color:#f7931a;
            }
        ";
        wp_add_inline_style( $handle, $css );
    }

    /**
     * This method will mark advertised product as featured
     *
     *
     * @param int $advertisement_id
     * @param array $data
     *
     * @return void
     */
    public function make_product_featured( $advertisement_id, $data ) {
        if ( ! Helper::is_featured_enabled() ) {
            return;
        }

        Helper::make_product_featured( $data['product_id'] );
    }

    /**
     * Remove from featured list when advertisement is expired
     *
     *
     * @param array $ids
     *
     * @return void
     */
    public function remove_featured_product( $ids ) {
        // return if make featured is disabled
        if ( ! Helper::is_featured_enabled() ) {
            return;
        }

        $manager     = new Manager();
        $product_ids = $manager->all(
            [
                'id'       => $ids,
                'per_page' => -1,
                'return'   => 'product_ids',
            ]
        );

        foreach ( $product_ids as $product_id ) {
            Helper::make_product_featured( $product_id, false );
        }
    }

    /**
     * Remove from featured list when advertisement is deleted
     *
     *
     * @param array $ids
     *
     * @return void
     */
    public function remove_deleted_featured_product( $ids ) {
        // return if make featured is disabled
        if ( ! Helper::is_featured_enabled() ) {
            return;
        }

        // get product by ids
        $manager  = new Manager();
        $items    = $manager->all(
            [
                'id'       => $ids,
                'per_page' => -1,
                'return'   => 'all',
            ]
        );

        // if advertisement status is 1, we'll consider this product
        $eligible_products = [];
        foreach ( $items as $item ) {
            if ( intval( $item['status'] ) === 1 ) {
                $eligible_products[] = $item['product_id'];
            }
        }

        foreach ( $eligible_products as $product_id ) {
            Helper::make_product_featured( $product_id, false );
        }
    }

    /**
     * Expire advertisement daily
     *
     *
     * @return void
     */
    public function expire_advertisements() {
        $manager = new Manager();
        $manager->expire_advertisement_by_date();
    }

    /**
     * Remove advertisement base product after advertisement product has been deleted.
     *
     *
     * @param int
     *
     * @return void
     */
    public function delete_advertisement_base_product( $post_id ) {
        if (
            file_exists( SK_PRODUCT_ADVERTISEMENT_INC . 'Helper' )
            && $post_id === Helper::get_advertisement_base_product() ) {
            delete_option( Helper::get_advertisement_base_product_option_key() );
        }
    }

    /**
     * Display advertised products on top
     *
     *
     * @param array $posts
     * @param object $query query arguments
     *
     * @return array
     */
    public function display_advertised_products_on_top( $posts, $query ) {
        global $wp_query;
        if ( ! is_admin() &&
            Helper::is_catalog_priority_enabled() &&
            $query->is_main_query() &&
            (
                is_search() ||
                ( is_a( $wp_query, 'WP_Query' ) && ! empty( $wp_query->get_queried_object() ) && is_shop() ) ||
                is_product_category() ||
                ( is_a( $wp_query, 'WP_Query' ) && sk_is_store_page() )
            )
        ) {
            $non_advertised = [];
            $advertised    = [];
            // get all advertised products
            $manager = new Manager();
            $advertised_products = $manager->all(
                [
                    'status'   => 1,
                    'per_page' => -1,
                    'return'   => 'product_ids',
                ]
            );

            foreach ( $posts as $post ) {
                if ( in_array( (string) $post->ID, $advertised_products, true ) ) {
                    $advertised[] = $post;
                } else {
                    $non_advertised[] = $post;
                }
            }

            if ( sk_is_store_page() ) {
                //todo: hack applied here, our store page ordering wasn't setting query var order,
                //we are putting advertised products at top
                $posts = array_merge( $advertised, $non_advertised );
            } else {
                /* if order is ASC put featured at top, otherwise put featured at bottom */
                $posts = ( 'ASC' === strtoupper( $query->get( 'order' ) ) )
                    ? array_merge( $advertised, $non_advertised )
                    : array_merge( $non_advertised, $advertised );
            }
        }

        return $posts;
    }

    /**
     * Render product section under single product page
     *
     * @param $container
     *
     * @return array
     */
    public function render_product_section( $container ) {
        return array_merge(
            [ new ProductSection() ],
            $container
        );
    }

    /**
     * Delete advertisement data if a product has been deleted
     *
     *
     * @param $post_id
     *
     * @return void
     */
    public function delete_advertisement( $post_id ) {
        // try to get wooCommerce product from post_id
        $product = wc_get_product( $post_id );

        if ( ! $product instanceof \WC_Product ) {
            return;
        }

        $manager = new Manager();
        $manager->delete_advertisement_by_product_id( $product->get_id() );
    }

    /**
     * Remove min max rules for advertisement products.
     *
     *
     * @param bool $apply_min_max
     * @param int  $product_id
     *
     * @return bool
     */
    public function remove_min_max_for_advertisements( $apply_min_max, $product_id ) {
        // Remove from min-max rules is advertisement product.
        if ( (int) $product_id === Helper::get_advertisement_base_product() ) {
            $apply_min_max = false;
        }

        return $apply_min_max;
    }

    /**
     * Make vendors own product purchasable if
     * advertisement product.
     *
     *
     * @param bool        $is_purchasable
     * @param \WC_Product $product
     *
     * @return bool
     */
    public function make_product_purchasable_for_advertisement( $is_purchasable, $product ) {
        // Check is advertisement product.
        if ( $product->get_id() === Helper::get_advertisement_base_product() ) {
            $is_purchasable = true;
        }

        return $is_purchasable;
    }

    /**
     * Add a Product Advertisement filter option to order type filter dropdown.
     *
     *
     * @param array $filter_options Array of filter options
     *
     * @return array
     */
    public function add_product_advertisement_filter_option( $filter_options ) {
        $filter_options['product_advertisement'] = esc_html__( 'Product Advertisement', 'sk' );
        return $filter_options;
    }

    /**
     * Filter orders by Product Advertisement type.
     *
     *
     * @param array   $query_args      Original query arguments.
     * @param string  $filter_type     The selected filter type.
     * @param boolean $is_hpos_enabled HPOS status.
     *
     * @return array|null
     */
    public function filter_product_advertisement_orders( $query_args, $filter_type, $is_hpos_enabled ) {
        // Only handle the product_advertisement filter type.
        if ( 'product_advertisement' !== $filter_type ) {
            return $query_args;
        }

        // Get all order IDs that contain advertisement products.
        $order_ids     = Helper::get_advertisement_order_ids();
        $query_type    = $is_hpos_enabled ? 'id' : 'post__in';
        $adv_order_ids = ! empty( $order_ids ) ? $order_ids : [ 0 ];

        // Filter orders by the retrieved order IDs.
        $query_args[ $query_type ] = $adv_order_ids;

        return $query_args;
    }
}
