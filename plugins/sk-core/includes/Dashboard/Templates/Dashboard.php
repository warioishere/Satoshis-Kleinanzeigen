<?php

namespace SK\Core\Dashboard\Templates;

use SK\Core\Utilities\ReportUtil;

/**
 * SK Template Dashboard Class
 *
 * @author weDves
 */
class Dashboard {
    /**
     * @var int $user_id current user id
     */
    protected $user_id;

    /**
     * @var array $order_count
     */
    protected $orders_count;

    /**
     * Load autometically when class inistantiate
     * hooked up all actions and filters
     *
     */
    public function __construct() {
        $this->user_id = sk_get_current_user_id();

        add_action( 'sk_dashboard_content_inside_before', [ $this, 'show_seller_dashboard_notice' ], 10 );

        if ( ! ReportUtil::is_analytics_enabled() ) {
            add_action( 'sk_dashboard_left_widgets', [ $this, 'get_big_counter_widgets' ], 10 );
            add_action( 'sk_dashboard_left_widgets', [ $this, 'get_orders_widgets' ], 15 );
            add_action( 'sk_dashboard_left_widgets', [ $this, 'get_products_widgets' ], 20 );
            // Sales chart widget removed — not used
        }
    }

    /**
     * Get Seller Dashboard Notice
     *
     *
     * @return void
     */
    public function show_seller_dashboard_notice() {
        if ( ! sk_is_seller_enabled( $this->user_id ) ) {
            sk_seller_not_enabled_notice();
        }
    }

    /**
     * Get big counter widget in dashboard
     *
     *
     * @return void
     */
    public function get_big_counter_widgets() {
        if ( ! apply_filters( 'sk_dashboard_widget_applicable', true, 'reports' ) ) {
            return;
        }

        if ( ! current_user_can( 'sk_view_sales_overview' ) ) {
            return;
        }

        if ( ! is_array( $this->orders_count ) ) {
            $this->orders_count = $this->get_orders_count();
        }

        sk_get_template_part(
            'dashboard/big-counter-widget', '', [
                'pageviews'      => $this->get_pageviews(),
                'orders_count'   => $this->orders_count,
                'earning'        => $this->get_earning(),
                'seller_balance' => $this->get_seller_balance(),
            ]
        );
    }

    /**
     * Get order widget in Dashboard
     *
     *
     * @return void
     */
    public function get_orders_widgets() {
        if ( ! apply_filters( 'sk_dashboard_widget_applicable', true, 'orders' ) ) {
            return;
        }

        if ( ! current_user_can( 'sk_view_order_report' ) ) {
            return;
        }

        if ( ! is_array( $this->orders_count ) ) {
            $this->orders_count = $this->get_orders_count();
        }

        $order_data = [
            [
                'value' => $this->orders_count->{'wc-completed'},
                'color' => '#73a724',
                'label' => __( 'Completed', 'sk-core' ),
            ],
            [
                'value' => $this->orders_count->{'wc-pending'},
                'color' => '#999',
                'label' => __( 'Pending', 'sk-core' ),
            ],
            [
                'value' => $this->orders_count->{'wc-processing'},
                'color' => '#21759b',
                'label' => __( 'Processing', 'sk-core' ),
            ],
            [
                'value' => $this->orders_count->{'wc-cancelled'},
                'color' => '#d54e21',
                'label' => __( 'Cancelled', 'sk-core' ),
            ],
            [
                'value' => $this->orders_count->{'wc-refunded'},
                'color' => '#e6db55',
                'label' => __( 'Refunded', 'sk-core' ),
            ],
            [
                'value' => $this->orders_count->{'wc-on-hold'},
                'color' => '#f0ad4e',
                'label' => __( 'On Hold', 'sk-core' ),
            ],
        ];

        $nonce          = wp_create_nonce( 'seller-order-filter-nonce' );
        $order_url      = sk_get_navigation_url( 'orders' );
        $completed_url  = add_query_arg(
            [
                'order_status'              => 'wc-completed',
                'seller_order_filter_nonce' => $nonce,
            ],
            $order_url
        );
        $pending_url    = add_query_arg(
            [
                'order_status'              => 'wc-pending',
                'seller_order_filter_nonce' => $nonce,
            ],
            $order_url
        );
        $processing_url = add_query_arg(
            [
                'order_status'              => 'wc-processing',
                'seller_order_filter_nonce' => $nonce,
            ],
            $order_url
        );
        $cancelled_url  = add_query_arg(
            [
                'order_status'              => 'wc-cancelled',
                'seller_order_filter_nonce' => $nonce,
            ],
            $order_url
        );
        $refunded_url   = add_query_arg(
            [
                'order_status'              => 'wc-refunded',
                'seller_order_filter_nonce' => $nonce,
            ],
            $order_url
        );
        $on_hold_url    = add_query_arg(
            [
                'order_status'              => 'wc-on-hold',
                'seller_order_filter_nonce' => $nonce,
            ],
            $order_url
        );

        sk_get_template_part(
            'dashboard/orders-widget', '', [
                'order_data'     => $order_data,
                'orders_count'   => $this->orders_count,
                'orders_url'     => $order_url,
                'completed_url'  => $completed_url,
                'pending_url'    => $pending_url,
                'processing_url' => $processing_url,
                'cancelled_url'  => $cancelled_url,
                'refunded_url'   => $refunded_url,
                'on_hold_url'    => $on_hold_url,
            ]
        );
    }

    /**
     * Get product widgets in dashboard
     *
     *
     * @return void
     */
    public function get_products_widgets() {
        if ( ! apply_filters( 'sk_dashboard_widget_applicable', true, 'products' ) ) {
            return;
        }

        if ( ! current_user_can( 'sk_view_product_status_report' ) ) {
            return;
        }
        $nonce       = wp_create_nonce( 'product_listing_filter' );
        $product_url = sk_get_navigation_url( 'products' );
        $online_url  = add_query_arg(
            [
                'post_status'                   => 'publish',
                '_product_listing_filter_nonce' => $nonce,
            ], $product_url
        );
        $draft_url   = add_query_arg(
            [
                'post_status'                   => 'draft',
                '_product_listing_filter_nonce' => $nonce,
            ], $product_url
        );
        $pending_url = add_query_arg(
            [
                'post_status'                   => 'pending',
                '_product_listing_filter_nonce' => $nonce,
            ], $product_url
        );
        sk_get_template_part(
            'dashboard/products-widget', '', [
                'post_counts'  => $this->get_post_counts(),
                'products_url' => $product_url,
                'online_url'   => $online_url,
                'draft_url'    => $draft_url,
                'pending_url'  => $pending_url,
            ]
        );
    }

    /**
     * Get sales report chart widget in dashboard
     *
     *
     * @return void
     */
    /**
     * Get orders Count
     *
     *
     * @return array
     */
    public function get_orders_count() {
        return sk_count_orders( $this->user_id );
    }

    /**
     * Get Post Count
     *
     *
     * @return array
     */
    public function get_post_counts() {
        return sk_count_posts( 'product', $this->user_id );
    }

    /**
     * Get Comments Count
     *
     *
     * @return array
     */
    public function get_comment_counts() {
        return sk_count_comments( 'product', $this->user_id );
    }

    /**
     * Get Pageview Count
     *
     *
     * @return integer
     */
    public function get_pageviews() {
        return (int) sk_author_pageviews( $this->user_id );
    }

    /**
     * Get Author Sales Count
     *
     *
     * @return integer
     */
    public function get_earning() {
        return sk_author_total_sales( $this->user_id );
    }

    /**
     * Get Seller Balance
     *
     *
     * @return integer
     */
    public function get_seller_balance() {
        return sk_get_seller_balance( $this->user_id );
    }
}
