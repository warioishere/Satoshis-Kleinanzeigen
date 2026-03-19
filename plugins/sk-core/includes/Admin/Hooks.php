<?php

namespace SK\Core\Admin;

use WP_Post;

// don't call the file directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin Hooks
 *
 *
 */
class Hooks {

    /**
     * Load autometically when class initiate
     *
     */
    public function __construct() {
        // Load all actions
        add_action( 'pending_to_publish', [ $this, 'send_notification_on_product_publish' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_seller_meta_box' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'override_product_author_by_admin' ], 12, 2 );

        // Load all filters
        add_filter( 'post_types_to_delete_with_user', [ $this, 'add_wc_post_types_to_delete_user' ], 10, 2 );
        add_filter( 'sk_save_settings_value', [ $this, 'update_pages' ], 10, 2 );

        // Ajax hooks
        add_action( 'wp_ajax_sk_product_search_author', [ $this, 'search_vendors' ] );

    }

    /**
     * Send notification to the seller once a product is published from pending
     *
     * @param WP_Post $post
     *
     * @return void
     */
    public function send_notification_on_product_publish( $post ) {
        if ( $post->post_type !== 'product' ) {
            return;
        }

        $seller = get_user_by( 'id', $post->post_author );

        do_action( 'sk_pending_product_published_notification', $post, $seller );
    }

    /**
     * Remove default author metabox and added new one for sk seller
     *
     *
     * @return void
     */
    public function add_seller_meta_box() {
        remove_meta_box( 'authordiv', 'product', 'core' );
        add_meta_box( 'sellerdiv', __( 'Vendor', 'sk-core' ), [ self::class, 'seller_meta_box_content' ], 'product', 'normal', 'core' );
    }

    /**
     * Display form field with list of authors.
     *
     *
     * @param object $post
     */
    public static function seller_meta_box_content( $post ) {
        $selected = empty( $post->ID ) ? get_current_user_id() : $post->post_author;

        $user = sk()->vendor->get( $selected );

        $user = [
            [
                'id'   => $selected,
                'text' => ! empty( $user->get_shop_name() ) ? $user->get_shop_name() : $user->get_name(),
            ],
        ];
        ?>

        <select
            style="width: 40%;"
            class="sk_product_author_override"
            name="sk_product_author_override"
            data-placeholder="<?php esc_attr_e( 'Select vendor', 'sk-core' ); ?>"
            data-action="sk_product_search_author"
            data-close_on_select="true"
            data-minimum_input_length="0"
            data-data="<?php echo esc_attr( wp_json_encode( $user ) ); ?>"
        >
        </select> <?php echo wp_kses( wc_help_tip( esc_html__( 'You can search vendors and assign them.', 'sk-core' ) ), wp_kses_allowed_html( 'user_description' ) ); ?>
        <?php
    }

    /**
     * Ajax method to search vendors
     *
     *
     * @return void
     */
    public function search_vendors() {
        if ( ! current_user_can( 'manage_woocommerce' ) || empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'sk_admin_product' ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Unauthorized operation', 'sk-core' ) ], 403 );
        }

        $vendors = [];
        $results = [];
        $args    = [
            'number'   => 20,
            'status'   => [ 'all' ],
            'role__in' => [ 'seller', 'administrator', 'shop_manager' ],
        ];

        if ( ! empty( $_GET['s'] ) ) {
            $s = sanitize_text_field( wp_unslash( $_GET['s'] ) );

            $args['search']         = '*' . $s . '*';
            $args['number']         = 35;
            $args['search_columns'] = [ 'user_login', 'user_email', 'display_name', 'user_nicename' ];
        }

        $results = sk()->vendor->all( $args );

        if ( ! count( $results ) && ! empty( $_GET['s'] ) ) {
            unset( $args['search'] );
            unset( $args['search_columns'] );

            $args['meta_query'] = [ // phpcs:ignore
                [
                    'key'     => 'sk_store_name',
                    'value'   => sanitize_text_field( wp_unslash( $_GET['s'] ) ) ?? '',
                    'compare' => 'LIKE',
                ],
            ];

            $results = sk()->vendor->get_vendors( $args );
        }

        if ( ! empty( $results ) ) {
            foreach ( $results as $vendor ) {
                $vendors[] = [
                    'id'     => $vendor->get_id(),
                    'text'   => ! empty( $vendor->get_shop_name() ) ? $vendor->get_shop_name() : $vendor->get_name(),
                    'avatar' => $vendor->get_avatar(),
                ];
            }
        }

        wp_send_json_success( [ 'vendors' => $vendors ] );
    }

    /**
     * Override product vendor ID from admin panel
     *
     *
     * @return void
     */
    public function override_product_author_by_admin( $product_id ) {
        $product          = wc_get_product( $product_id );
        $posted_vendor_id = ! empty( $_POST['sk_product_author_override'] ) ? (int) sanitize_key( wp_unslash( $_POST['sk_product_author_override'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

        if ( ! $posted_vendor_id ) {
            return;
        }

        $vendor = sk_get_vendor_by_product( $product );

        if ( ! $vendor ) {
            return;
        }

        if ( $posted_vendor_id === $vendor->get_id() ) {
            return;
        }

        sk_override_product_author( $product, $posted_vendor_id );
    }

    /**
     * Assign vendor for deleted post types
     *
     * @param array   $post_types
     * @param integer $user_id
     *
     * @return array
     */
    public function add_wc_post_types_to_delete_user( $post_types, $user_id ) {
        if ( ! sk_is_user_seller( $user_id ) ) {
            return $post_types;
        }

        $wc_post_types = [ 'product', 'product_variation', 'shop_order', 'shop_coupon' ];

        return array_merge( $post_types, $wc_post_types );
    }

    /**
     * SK update pages
     *
     * @param array $value
     * @param array $name
     *
     * @return array
     */
    public function update_pages( $value, $name ) {
        if ( 'sk_pages' !== $name ) {
            return $value;
        }

        $current_settings = get_option( $name, [] );
        $current_settings = is_array( $current_settings ) ? $current_settings : [];
        $value            = is_array( $value ) ? $value : [];

        return array_replace_recursive( $current_settings, $value );
    }

}
