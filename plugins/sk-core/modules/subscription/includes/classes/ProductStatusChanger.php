<?php

namespace SK\Modules\Subscription;

use SK\Core\Traits\Singleton;
use SK\Modules\Subscription\Helper;
use SK\Modules\Subscription\HelperChangerProductStatus;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

class ProductStatusChanger {

    use Singleton;

    /**
     * Boot method
     *
     */
    protected function boot() {
        $this->hooks();
    }

    /**
     * Init hooks
     *
     */
    protected function hooks() {
        add_filter( 'sk_bulk_product_statuses', [ $this, 'product_statuses' ] );
        add_action( 'sk_bulk_product_status_change', [ $this, 'publish_products' ], 10, 2 );
        add_action( 'sk_product_listing_filter_from_end', [ $this, 'product_filter_form' ] );
        add_filter( 'sk_pre_product_listing_args', [ $this, 'filter_products' ], 15, 2 );
        add_filter( 'sk_rest_pre_product_listing_args', [ $this, 'filter_products_for_api' ], 15, 2 );
        add_action( 'sk_vendor_purchased_subscription', [ $this, 'change_product_status' ] );
        add_filter( 'sk_background_process_container', [ $this, 'init_change_product_status_bg_class' ] );
        add_action( 'dps_after_bulk_publish_product_single', 'sk_trigger_product_create_email', 10, 1 );
    }

    /**
     * Add product status filter
     *
     *
     * @param array $statuses
     *
     * @return array
     */
    public function product_statuses( $statuses ) {
        if ( $this->maybe_hide_the_form() ) {
            return $statuses;
        }

        $statuses['publish'] = __( 'Publish Products', 'sk' );

        return $statuses;
    }

    /**
     * Publish products
     *
     *
     * @param string $action
     * @param array  $product_ids
     *
     * @return void
     */
    public function publish_products( $action, $product_ids ) {
        if ( 'publish' !== $action || empty( $product_ids ) ) {
            return;
        }

        $vendor_id          = sk_get_current_user_id();
        $remaining_products = Helper::get_vendor_remaining_products( $vendor_id );
        $new_status         = sk_get_new_post_status( $vendor_id );
        if ( ! $remaining_products ) {
            return;
        }

        foreach ( $product_ids as $product_id ) {
            $product_id = absint( $product_id );

            // only ever touch the current vendor's own products
            if ( ! $product_id || ! sk_is_product_author( $product_id ) ) {
                continue;
            }

            $product = wc_get_product( $product_id );

            if ( ! $product || $product->get_status() === $new_status || 'publish' === $product->get_status() ) {
                continue;
            }

            if ( true === $remaining_products || $remaining_products > 0 ) {
                $product->set_status( $new_status );
                $product->delete_meta_data( '_sk_product_status' );
                $product->save();
                $remaining_products = true === $remaining_products ? $remaining_products : $remaining_products - 1;
                do_action( 'dps_after_bulk_publish_product_single', $product, $new_status );
            } else {
                break;
            }
        }
    }

    /**
     * Product filtering form
     *
     *
     * @return void
     */
    public function product_filter_form() {
        if ( $this->maybe_hide_the_form() ) {
            return;
        }

        $selected = ! empty( $_REQUEST['filter_by_other'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['filter_by_other'] ) ) : ''; // phpcs:ignore
        $filters  = apply_filters(
            'sk_get_other_product_filters',
            [
                'featured'     => esc_html__( 'Featured', 'sk' ),
                'top_rated'    => esc_html__( 'Top Rated', 'sk' ),
                'best_selling' => esc_html__( 'Best Selling', 'sk' ),
                'low_stock'    => esc_html__( 'Low on Stock', 'sk' ),
                'out_of_stock' => esc_html__( 'Out of Stock', 'sk' ),
            ]
        );
        ?>
        <div class="sk-form-group">
            <select name="filter_by_other" class="sk-form-control">
                <option selected="selected" value="-1"><?php esc_attr_e( '- Select Filter -', 'sk' ); ?></option>
                <?php foreach ( $filters as $key => $filter ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected, $key ); ?>>
                        <?php echo esc_attr( $filter ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    }

    /**
     * Filter best selling products
     *
     *
     * @param array $args
     *
     * @return array
     */
    public function filter_products( $args ) {
        if ( ! isset( $_GET['_product_listing_filter_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_product_listing_filter_nonce'] ) ), 'product_listing_filter' ) ) {
            return $args;
        }

        if ( ! isset( $_GET['filter_by_other'] ) ) {
            return $args;
        }

        $filter_by_other = sanitize_text_field( wp_unslash( $_GET['filter_by_other'] ) );

        return Helper::filter_products_by_filter_by_other_helper( $args, $filter_by_other );
    }

    /**
     * Prepares filter_by_other data to filter products for Product V2 api.
     *
     *
     * @param array           $args
     * @param WP_REST_Request $request
     *
     * @return array $args
     */
    public function filter_products_for_api( $args, $request ) {
        if ( ! $request->get_param( 'filter_by_other' ) ) {
            return $args;
        }

        $filter_by_other = $request->get_param( 'filter_by_other' );

        return Helper::filter_products_by_filter_by_other_helper( $args, $filter_by_other );
    }

    /**
     * Maybe hide the form fields when vendor has reached the product uploading limit
     *
     *
     * @return boolean
     */
    public function maybe_hide_the_form() {
        if ( ! Helper::get_vendor_remaining_products( sk_get_current_user_id() ) ) {
            return true;
        }

        return false;
    }

    /**
     * Change product status on subscription purchased
     *
     *
     * @param int $vendor_id
     *
     * @return void
     */
    public function change_product_status( $vendor_id ) {
        if ( ! Helper::get_vendor_remaining_products( $vendor_id ) ) {
            Helper::apply_product_status_after_end( $vendor_id );
        }

        if ( Helper::vendor_can_publish_unlimited_products( $vendor_id ) ) {
            Helper::make_product_publish( $vendor_id );
        }

        // delete user meta after vendor purchased a subscription
        delete_user_meta( $vendor_id, 'sk_vendor_subscription_cancel_email' );
    }

    /**
     * Instantiate subscription product status changer background class
     *
     *
     * @param array $bg_classes
     *
     * @return array
     */
    public function init_change_product_status_bg_class( $bg_classes ) {
        if ( ! class_exists( 'SK\Core\Abstracts\ProductStatusChanger' ) ) {
            return $bg_classes;
        }
        if ( ! class_exists( HelperChangerProductStatus::class ) ) {
            require_once DPS_PATH . '/includes/classes/HelperChangerProductStatus.php';
        }

        $bg_classes['subscription_product_status_changer'] = new HelperChangerProductStatus();

        return $bg_classes;
    }
}

ProductStatusChanger::instance();
