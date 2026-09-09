<?php
namespace SK\Modules\ProductAdvertisement\Frontend;

use SK\Modules\ProductAdvertisement\Helper;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class Product
 *
 *
 */
class Product {
    /**
     * Product constructor.
     *
     */
    public function __construct() {
        // add new column under vendor dashboard's product listing page
        add_action( 'sk_product_list_table_after_status_table_header', [ $this, 'product_listing_table_column' ], 1 );
        add_action( 'sk_auction_product_list_table_after_status_table_header', [ $this, 'product_listing_table_column' ], 1 );
        add_action( 'sk_booking_product_list_table_after_status_table_header', [ $this, 'product_listing_table_column' ], 1 );

        // featured column value
        add_action( 'sk_product_list_table_after_status_table_data', [ $this, 'product_listing_table_content' ], 1, 2 );
        add_action( 'sk_auction_product_list_table_after_status_table_data', [ $this, 'product_listing_table_content' ], 1, 2 );
        add_action( 'sk_booking_product_list_table_after_status_table_data', [ $this, 'product_listing_table_content' ], 1, 2 );

        // render advertise product section under single product edit page
        add_action( 'sk_product_edit_after_options', [ $this, 'render_advertise_product_section' ], 99, 1 );

        // load frontend scripts
        add_action( 'wp_enqueue_scripts', [ $this, 'load_product_scripts' ], 10 );
    }

    /**
     *
     *
     * @param int $post_id
     *
     * @return void
     */
    public function render_advertise_product_section( $post_id ) {
        // check permission, don't let vendor staff view this section
        if ( ! current_user_can( 'skdar' ) ) {
            return;
        }

        // check if purchasing advertisement settings is enabled
        if ( ! Helper::is_per_product_advertisement_enabled() && ! Helper::is_enabled_for_vendor_subscription() ) {
            return;
        }

        $advertisement_data = Helper::get_advertisement_data_by_product( $post_id );

        if ( empty( $advertisement_data ) ) {
            return;
        }

        // load template
        sk_get_template_part(
            'product-advertisement-content', '', array_merge(
                [
                    'product_id'               => $post_id,
                    'advertise_active_color'   => '#F7931A',
                    'is_product_advertisement' => true,
                ],
                $advertisement_data
            )
        );
    }

    /**
     * This method  will print advertisement row data
     *
     *
     * @param \WP_Post $post
     * @param \WC_Product $product
     *
     * @return void
     */
    public function product_listing_table_content( $post, $product ) {
        // get advertisement data via product
        $advertisement_data = Helper::get_advertisement_data_by_product( $product->get_id() );

        $title = '';
        $class = '';
        $color  = 'slategrey';
        if ( ! empty( $advertisement_data['already_advertised'] ) ) {
            // translators: 1) advertisement expire date
            $title  = esc_attr( sprintf( __( 'Expires on: %s', 'sk-core' ), $advertisement_data['expire_date'] ) );
            $color  = '#F7931A';
            $class  = 'advertised';
        }

        $status     = esc_attr( $product->get_status() );
        $product_id = absint( $product->get_id() );

        echo <<<EOD
<td class="product-advertisement-td">
     <span class="fa-stack fa-xs tips sk-product-advertisement {$class}"
             style="cursor: pointer;"
             data-title="{$title}"
             data-already-advertised="{$class}"
             data-product-status="{$status}"
             data-product-id="{$product_id}">
         <i class="fa fa-circle fa-stack-2x adv_icon_1" style="color:{$color}"></i>
         <i class="fa fa-stack-1x fa-bullhorn fa-inverse adv_icon_2"></i>
     </span>
 </td>
EOD;
    }

    /**
     * This method will add featured column under vendor dashboard product listing page
     *
     *
     * @return void
     */
    public function product_listing_table_column() {
        $color  = '#F7931A';
        $title  = esc_attr__( 'Advertised Products', 'sk-core' );
        echo <<<EOD
<th class="product-advertisement-th">
    <span class="fa-stack fa-xs tips" data-title="{$title}">
        <i class="fa fa-circle fa-stack-2x" style="color: {$color}"></i>
        <i class="fa fa-bullhorn fa-stack-1x fa-inverse" data-fa-transform="shrink-6"></i>
    </span>
</th>

EOD;
    }

    /**
     * Load frontend scripts
     *
     *
     * @return void
     */
    public function load_product_scripts() {
        global $wp;

        // Check if not vendor dashboard.
        if ( ! sk_is_seller_dashboard() ) {
            return;
        }

        // Check if the page is not "products", "auction" or "booking" vendor dashboard product pages.
        if ( ! isset( $wp->query_vars['products'] ) && ! isset( $wp->query_vars['auction'] ) && ! isset( $wp->query_vars['booking'] ) ) {
            return;
        }

        wp_enqueue_script( 'sk-product-adv-purchase' );

        // localize scripts
        $localized_data = [
            'advertise_alert'              => esc_html__( 'Are you sure you want to advertise this product?', 'sk-core' ),
            'advertise_active'             => '#F7931A',
            'advertise_product_nonce'      => wp_create_nonce( 'sk_advertise_product_nonce' ),
            'on_error_message'             => esc_html__( 'Something went wrong.', 'sk-core' ),
            'on_success_message'           => esc_html__( 'Success.', 'sk-core' ),
            'product_not_published'        => esc_html__( 'You can not advertise this product. Products needs to be published before you can advertise.', 'sk-core' ),
            'on_load_advertisement_status' => esc_html__( 'Loading advertisement data. Please wait...', 'sk-core' ),
            'checkout_url'                 => wc_get_checkout_url(),
            'ajaxurl'                      => admin_url( 'admin-ajax.php' ),

        ];
        wp_localize_script( 'sk-product-adv-purchase', 'sk_purchase_advertisement', $localized_data );
    }
}
