<?php

namespace SK\Core\Shipping;

use Exception;
use stdClass;
use WC_Order;
use WC_Order_Item_Product;
use SK\Core\Cache;
use WP_Post;
use WP_REST_Response;

/**
 * Shipping Status Class
 *
 */
class ShippingStatus {

    /**
     * Shipping status option
     *
     */
    public $enabled;

    /**
     * Shipping status option
     *
     */
    public $wc_shipping_enabled;

    /**
     * Shipping Status class construct
     *
     */
    public function __construct() {
        $this->wc_shipping_enabled = get_option( 'woocommerce_calc_shipping' ) === 'yes' ? true : false;

        add_filter( 'sk_settings_sections', [ $this, 'render_shipping_status_section' ] );
        add_filter( 'sk_settings_fields', [ $this, 'render_shipping_status_settings' ] );

        $this->enabled = sk_get_option( 'enabled', 'sk_shipping_status_setting', 'off' );
        $this->add_default_shipping_status();

        $this->load_hooks();
    }

    /**
     * Load hooks for this shippping
     * tracking
     *
     *
     * @return void
     */
    public function load_hooks() {
        // Add localized data for the "Mark as Received" feature if allowed.
        add_filter( 'sk_frontend_localize_script', [ $this, 'add_localize_data_for_mark_received' ] );
        add_filter( 'sk_admin_localize_script', [ $this, 'add_localize_data_for_admin_mark_received' ] );

        $digital = sk_ext()->digital_product ?? null;
        if ( 'on' !== $this->enabled || ! $this->wc_shipping_enabled || ( $digital && 'sell_digital' === $digital->get_selling_product_type() ) ) {
            return;
        }

        add_action( 'sk_order_detail_after_order_items', [ $this, 'render_shipment_content' ], 15 );
        add_action( 'sk_marked_order_as_recieve', [ $this, 'marked_order_as_recieve' ], 10, 2 );

        add_filter( 'sk_rest_prepare_shop_order_object', [ $this, 'add_shipment_data_to_item' ], 10, 2 );
        add_action( 'wp_ajax_sk_add_shipping_status_tracking_info', [ $this, 'add_shipping_status_tracking_info' ] );
        add_action( 'wp_ajax_sk_update_shipping_status_tracking_info', [
            $this,
            'update_shipping_status_tracking_info'
        ] );
        add_action( 'wp_ajax_sk_order_mark_as_received', [ $this, 'handle_mark_receive_actions' ] );
        add_action( 'wp_ajax_nopriv_sk_order_mark_as_received', [ $this, 'handle_mark_receive_actions' ] );

        add_action( 'woocommerce_order_details_after_order_table', [
            $this,
            'shipment_order_details_after_order_table'
        ], 11 );
        add_action( 'woocommerce_account_orders_columns', [ $this, 'shipment_my_account_my_orders_columns' ], 11 );
        add_action( 'woocommerce_my_account_my_orders_column_sk-shipment-status', [
            $this,
            'shipment_my_account_orders_column_data'
        ], 11 );
        add_action( 'add_meta_boxes', [ $this, 'shipment_order_add_meta_boxes' ], 11, 2 );
        add_filter( 'sk_localized_args', [ $this, 'set_localized_data' ] );
        add_action( 'sk_after_saving_settings', [ $this, 'after_save_settings' ], 10, 3 );

        if ( sk_is_hpos_enabled() ) {
            // hpos equivalent hooks for manage_edit-shop_order_columns
            add_filter( 'manage_woocommerce_page_wc-orders_columns', [
                $this,
                'admin_shipping_status_tracking_columns'
            ], 10 );
            // hpos equivalent hooks for `manage_shop_order_posts_custom_column`
            add_action( 'manage_woocommerce_page_wc-orders_custom_column', [
                $this,
                'shop_order_shipping_status_columns'
            ], 11, 2 );
        } else {
            add_filter( 'manage_edit-shop_order_columns', [ $this, 'admin_shipping_status_tracking_columns' ], 10 );
            add_action( 'manage_shop_order_posts_custom_column', [
                $this,
                'shop_order_shipping_status_columns'
            ], 11, 2 );
        }
    }

    /**
     * Add a shipping status section in SK settings
     *
     *
     * @param array $sections
     *
     * @return array
     */
    public function render_shipping_status_section( $sections ) {
        $sections[] = [
            'id'                   => 'sk_shipping_status_setting',
            'title'                => __( 'Shipping Status', 'sk' ),
            'description'          => __( 'Manage Shipping Status', 'sk' ),
            'document_link'        => 'https://sk.co/docs/wordpress/settings/sk-shipping-status/',
            'settings_title'       => __( 'Shipping Status Settings', 'sk' ),
            'settings_description' => __( 'You can configure settings to allow customers to track their products.', 'sk' ),
        ];

        return $sections;
    }

    /**
     * Load all settings fields
     *
     *
     * @return void
     */
    public function render_shipping_status_settings( $fields ) {
        $shipment_warning = [];
        $selling_type     = sk_ext()->digital_product->get_selling_product_type();

        if ( 'sell_digital' === $selling_type ) {
            $shipment_warning['digital_warning'] = [
                'name'  => 'digital_warning',
                'label' => __( 'Warning!', 'sk' ),
                'type'  => 'warning',
                'desc'  => __( 'Your selling product type is Digital mode, shipping tracking system work with physical products only.', 'sk' ),
            ];
        }

        if ( ! $this->wc_shipping_enabled ) {
            $shipment_warning['wc_warning'] = [
                'name'  => 'wc_warning',
                'label' => __( 'Warning!', 'sk' ),
                'type'  => 'warning',
                'desc'  => __( 'Your WooCommerce shipping is currently disabled, therefore you first need to enable WC Shipping then it will work for vendors', 'sk' ),
            ];
        }

        $fields['sk_shipping_status_setting'] = [
            'enabled'                  => [
                'name'  => 'enabled',
                'label' => __( 'Allow Shipment Tracking', 'sk' ),
                'type'  => 'switcher',
                'desc'  => __( 'Allow shipment tracking service for vendors', 'sk' ),
            ],
            'allow_mark_received'      => [
                'name'    => 'allow_mark_received',
                'type'    => 'switcher',
                'label'   => __( 'Allow Mark as Received', 'sk' ),
                'desc'    => __( 'Allow customers to mark order as received', 'sk' ),
                'default' => 'off',
                'show_if' => [
                    'sk_shipping_status_setting.enabled' => [ 'equal' => 'on' ],
                ],
            ],
            'shipping_status_provider' => [
                'name'    => 'shipping_status_provider',
                'label'   => __( 'Shipping Providers', 'sk' ),
                'desc'    => __( 'Select multiples shipping providers.', 'sk' ),
                'type'    => 'multicheck',
                'default' => sk_get_shipping_tracking_default_providers_list(),
                'options' => sk_get_shipping_tracking_providers_list(),
                'tooltip' => __( 'Choose the 3rd party shipping providers.', 'sk' ),
            ],
            'shipping_status_list'     => [
                'name'  => 'shipping_status_list',
                'label' => __( 'Shipping Status', 'sk' ),
                'type'  => 'repeatable',
                'desc'  => __( 'Add custom shipping status', 'sk' ),
            ],
        ];

        $fields['sk_shipping_status_setting'] = array_merge( $shipment_warning, $fields['sk_shipping_status_setting'] );

        return $fields;
    }

    /**
     * Add default shipping status when get blank
     *
     *
     * @return void
     */
    public function add_default_shipping_status() {
        $option = get_option( 'sk_shipping_status_setting', [] );

        if ( empty( $option['shipping_status_list'] ) ) {
            $option['shipping_status_list'] = [
                [
                    'id'       => 'ss_delivered',
                    'value'    => esc_html__( 'Delivered', 'sk' ),
                    'must_use' => true,
                    'desc'     => esc_html__( '(This is must use item)', 'sk' ),
                ],
                [
                    'id'       => 'ss_cancelled',
                    'value'    => esc_html__( 'Cancelled', 'sk' ),
                    'must_use' => true,
                    'desc'     => esc_html__( '(This is must use item)', 'sk' ),
                ],
                [
                    'id'    => 'ss_proceccing',
                    'value' => esc_html__( 'Processing', 'sk' ),
                ],
                [
                    'id'    => 'ss_ready_for_pickup',
                    'value' => esc_html__( 'Ready for pickup', 'sk' ),
                ],
                [
                    'id'    => 'ss_pickedup',
                    'value' => esc_html__( 'Pickedup', 'sk' ),
                ],
                [
                    'id'    => 'ss_on_the_way',
                    'value' => esc_html__( 'On the way', 'sk' ),
                ],
            ];

            foreach ( $option['shipping_status_list'] as $key => $status ) {
                do_action( 'sk_pro_register_shipping_status', $status['value'] );
            }

            update_option( 'sk_shipping_status_setting', $option, false );
        }
    }

    /**
     * After Save Admin Settings.
     *
     *
     * @param string $option_name  Option Key (Section Key).
     * @param array  $option_value Option value.
     *
     * @return void
     */
    public function after_save_settings( $option_name, $option_value ) {
        if ( 'sk_shipping_status_setting' !== $option_name ) {
            return;
        }

        foreach ( $option_value['shipping_status_list'] as $status ) {
            do_action( 'sk_pro_register_shipping_status', $status['value'] );
        }
    }

    /**
     * Get shipping status main content
     *
     *
     * @return void
     */
    public function render_shipment_content() {
        // Verify the nonce for order view page.
        if ( isset( $_REQUEST['_wpnonce'] ) && ! wp_verify_nonce( sanitize_key( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'sk_view_order' ) ) {
            wp_send_json_error( __( 'Invalid nonce', 'sk' ) ); // Handle nonce verification failure.
        }

        $line_items    = [];
        $shipment_info = [];
        $order_id      = isset( $_GET['order_id'] ) ? intval( $_GET['order_id'] ) : 0;

        if ( ! $order_id ) {
            return;
        }

        $default_providers    = sk_get_shipping_tracking_providers_list();
        $selected_providers   = sk_get_option( 'shipping_status_provider', 'sk_shipping_status_setting' );
        $status_list          = sk_get_option( 'shipping_status_list', 'sk_shipping_status_setting' );
        $order                = wc_get_order( $order_id );
        $disabled_create_btn  = false;
        $allowed_mark_receive = Helper::is_mark_as_received_allowed_for_customers();

        if ( $order ) {
            $line_items    = $order->get_items( apply_filters( 'woocommerce_admin_order_item_types', 'line_item' ) );
            $shipment_info = $this->get_shipping_tracking_info( $order_id );
            $is_shipped    = $this->is_order_shipped( $order );

            if ( $order->get_status() === 'cancelled' || $order->get_status() === 'refunded' ) {
                $disabled_create_btn = true;
            }
        }

        if ( $allowed_mark_receive ) { // Check mark as received allowed for customers.
            // Ignore mark as received status from vendor shipment.
            foreach ( $status_list as $key => $status ) {
                if ( $status['id'] === 'ss_mark_received' ) {
                    unset( $status_list[ $key ] );
                    break; // Exit the loop once the item is found and removed
                }
            }
        }

        sk_get_template_part(
            'orders/shipment/html-shipping-status', '', [
                'pro'                  => true,
                'd_providers'          => $default_providers,
                's_providers'          => $selected_providers,
                'status_list'          => $status_list,
                'order_id'             => $order_id,
                'order'                => $order,
                'line_items'           => $line_items,
                'shipment_info'        => $shipment_info,
                'is_shipped'           => $is_shipped,
                'disabled_create_btn'  => $disabled_create_btn,
                'allowed_mark_receive' => $allowed_mark_receive,
            ]
        );
    }

    /**
     * Get order shipment status
     *
     *
     * @param WC_Order $order
     *
     * @return bool
     */
    public function is_order_shipped( $order = '' ) {
        if ( empty( $order ) ) {
            return false;
        }

        $get_items       = $order->get_items( 'line_item' );
        $order_id        = $order->get_id();
        $items_available = [];

        foreach ( $get_items as $item_id => $item ) {
            if ( ! $this->get_status_order_item_shipped( $order_id, $item_id, $item['qty'], 0 ) ) {
                $items_available[] = $item_id;
            }
        }

        if ( empty( $items_available ) ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Create shipping tracking status.
     *
     *
     * @param int   $order_id Order ID.
     * @param array $data     Shipping tracking data.
     * @param int   $user_id  User Id.
     *
     * @return int
     * @throws Exception If necessary data not provided or shipping status data not created.
     */
    public function create( int $order_id, array $data, int $user_id = 0 ): int {
        $user_id = $user_id ? $user_id : sk_get_current_user_id();
        if ( ! sk_is_seller_has_order( $user_id, $order_id ) ) {
            throw new Exception( esc_html__( 'You do not have permission to create new shipping tracking because this order is not yours.', 'sk' ) );
        }

        $data['post_id']   = $order_id; // Settings it for backward compatability.
        $shipment_comments = isset( $data['shipment_comments'] ) ? trim( $data['shipment_comments'] ) : '';
        $tracking_info     = $this->prepare_for_db( $data );

        if ( ! $tracking_info ) {
            throw new Exception( esc_html__( 'Please provide all necessary data.', 'sk' ) );
        }

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            throw new Exception( esc_html__( 'Order not found.', 'sk' ) );
        }

        if ( $order->get_status() === 'cancelled' || $order->get_status() === 'refunded' ) {
            throw new Exception( esc_html__( 'Shipping tracking status can not be created for the current status of the order.', 'sk' ) );
        }

        if ( empty( $order ) || $tracking_info['number'] === '' || $tracking_info['shipping_status'] === '' || $tracking_info['provider'] === '' ) {
            throw new Exception( esc_html__( 'Shipping tracking number or status or provider missing.', 'sk' ) );
        }

        $shipment_id   = $this->create_shipping_tracking( $tracking_info );
        $tracking_info = (object) $tracking_info;

        if ( $shipment_id ) {
            sk_shipment_cache_clear_group( $order_id );
            do_action( 'sk_order_shipping_status_tracking_new_added', $order_id, $tracking_info, $user_id, $shipment_id );
        } else {
            throw new Exception( esc_html__( 'Error creating new shipping tracking status.', 'sk' ) );
        }

        $ship_info = esc_html__( 'Shipping Provider:', 'sk' ) . ' <strong>' . $tracking_info->provider_label . ' </strong><br />' . esc_html__( 'Shipping number:', 'sk' ) . ' <strong>' . $tracking_info->number . ' </strong><br />' . esc_html__( 'Shipped date:', 'sk' ) . ' <strong>' . $tracking_info->date . ' </strong><br />' . esc_html__( 'Shipped status:', 'sk' ) . ' <strong>' . $tracking_info->status_label . '</strong>';

        if ( ! empty( $shipment_comments ) ) {
            $ship_info .= '<br><br><strong> ' . esc_html__( 'Comments:', 'sk' ) . ' </strong>' . $shipment_comments;
        }

        if ( 'on' === $tracking_info->is_notify ) {
            do_action( 'sk_order_shipping_status_tracking_notify', $order_id, $tracking_info, $ship_info, $user_id, true );
        }

        if ( Helper::is_mark_as_received_allowed_for_customers() && $tracking_info->shipping_status === 'ss_delivered' ) {
            $this->schedule_to_mark_order_as_received( [ $order->get_id(), $shipment_id ] );
        }

        $this->add_shipping_status_tracking_notes( $order_id, $shipment_id, $ship_info, $order );

        return $shipment_id;
    }

    /**
     * Schedule an action to mark the order as received after a specified time.
     *
     *
     * @param array $info Information required to mark the order as received.
     *
     * @return void
     */
    protected function schedule_to_mark_order_as_received( array $info ) {
        /**
         * Filters the duration after which an order should be automatically marked as received.
         *
         * This filter allows developers to modify the default time period after which an order is
         * automatically considered as received by the customer. This is particularly useful for
         * adjusting the auto-receive functionality based on specific business rules or logistics considerations.
         *
         *
         * @param string $duration  The default duration to wait before marking the order as received.
         *                          Defaults to '+7 days'. The duration should be a valid strtotime string.
         * @param array  $info      An array containing contextual information where:
         *                          - [0] \WC_Order $order         The WooCommerce order object.
         *                          - [1] int       $shipment_id   The shipment ID related to the order.
         */
        $action_duration = apply_filters( 'sk_order_auto_mark_as_received_duration', '+7 days', $info );

        as_schedule_single_action(
            strtotime( $action_duration ),
            'sk_marked_order_as_recieve',
            $info,
            'sk_marked_order_recieve'
        );
    }

    /**
     * Mark the order as received based on the scheduled action.
     *
     *
     * @param int $order_id    WC Order object.
     * @param int $shipment_id The ID of the shipment related to the order.
     *
     * @return void
     */
    public function marked_order_as_recieve( $order_id, $shipment_id ) {
        // Check $order is an instance of wc order.
        if ( ! $order_id ) {
            return;
        }

        if ( ! $shipment_id ) {
            return;
        }

        $order = wc_get_order( $order_id ); // Get the order.

        // Retrieve the marked received order meta.
        $order_marked_as_received = (array) $order->get_meta( '_sk_order_marked_received' );
        if ( in_array( $shipment_id, $order_marked_as_received, true ) ) { // Check the order already has been received or not.
            return;
        }

        $order_marked_as_received   = ! empty( $order_marked_as_received ) ? $order_marked_as_received : [];
        $order_marked_as_received[] = $shipment_id;

        $order->update_meta_data( '_sk_order_marked_received', $order_marked_as_received );

        /**
         * After marked order receive by customer then trigger.
         *
         *
         * @param \WC_Order $order
         * @param stdClass  $tracking_info
         * @param string    $ship_info
         */
        do_action( 'sk_marked_order_as_receive', $order, $shipment_id );

        // Update order status to complete if order items fully received.
        $completely_received = Helper::is_order_fully_shipped( $order );
        if ( $completely_received ) {
            $order->update_status( 'completed', __( 'Order marked as received by the customer.', 'sk' ) );
        }

        $order->save(); // Save order.
    }

    /**
     * Handle mark order receive actions for via ajax.
     *
     *
     * @return void
     */
    public function handle_mark_receive_actions() {
        check_ajax_referer( 'sk_mark_received_nonce', 'nonce' ); // Check the nonce

        $order_id    = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        $shipment_id = isset( $_POST['shipment_id'] ) ? absint( $_POST['shipment_id'] ) : 0;

        if ( $order_id < 1 ) {
            wp_send_json_error( __( 'Order id is required for marked as received.', 'sk' ) );
        }

        if ( $shipment_id < 1 ) {
            wp_send_json_error( __( 'Shipment id is not valid for order marking as received.', 'sk' ) );
        }

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            wp_send_json_error( __( 'Order not found.', 'sk' ) );
        }

        // Marked order as receive and get complete receive status.
        $this->marked_order_as_recieve( $order_id, $shipment_id );

        // Return a success response.
        wp_send_json_success(
            [
                'message'                => __( 'Order marked as received.', 'sk' ),
                'is_completely_received' => Helper::is_order_fully_shipped( $order ),
            ]
        );
    }

    /**
     * Update Shipping tracking data.
     *
     *
     * @param int   $shipment_id Shipment ID.
     * @param int   $order_id    Order Id.
     * @param array $data        Shipping tracking shipment data.
     * @param int   $user_id     User Id.
     *
     * @return stdClass
     * @throws Exception
     */
    public function update( int $shipment_id, int $order_id, array $data, int $user_id = 0 ): stdClass {
        $user_id = $user_id ? $user_id : sk_get_current_user_id();
        if ( ! sk_is_seller_has_order( $user_id, $order_id ) ) {
            throw new Exception( esc_html__( 'You do not have permission to update shipping tracking because this order is not yours.', 'sk' ) );
        }

        $data['post_id']     = $order_id;
        $data['shipment_id'] = $shipment_id;

        $status        = isset( $data['shipped_status'] ) ? trim( sanitize_text_field( wp_unslash( $data['shipped_status'] ) ) ) : '';
        $provider      = isset( $data['shipping_provider'] ) ? trim( sanitize_text_field( wp_unslash( $data['shipping_provider'] ) ) ) : '';
        $status_date   = isset( $data['shipped_status_date'] ) ? trim( sanitize_text_field( wp_unslash( $data['shipped_status_date'] ) ) ) : '';
        $number        = isset( $data['tracking_status_number'] ) ? trim( sanitize_text_field( wp_unslash( $data['tracking_status_number'] ) ) ) : '';
        $is_notify     = isset( $data['is_notify'] ) ? sanitize_text_field( wp_unslash( $data['is_notify'] ) ) : '';
        $ship_comments = isset( $data['shipment_comments'] ) ? trim( sanitize_text_field( wp_unslash( $data['shipment_comments'] ) ) ) : '';

        $provider_label = sk_get_shipping_tracking_provider_by_key( $provider, 'label' );
        $provider_url   = sk_get_shipping_tracking_provider_by_key( $provider, 'url', $number );
        $status_label   = sk_get_shipping_tracking_status_by_key( $status );

        if ( 'sp-other' === $provider ) {
            $provider_label = isset( $data['status_other_provider'] ) ? sanitize_text_field( wp_unslash( $data['status_other_provider'] ) ) : '';
            $provider_url   = isset( $data['status_other_p_url'] ) ? sanitize_text_field( wp_unslash( $data['status_other_p_url'] ) ) : '';
        }

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            throw new Exception( esc_html__( 'Order not found.', 'sk' ) );
        }

        if ( $order->get_status() === 'cancelled' || $order->get_status() === 'refunded' ) {
            throw new Exception( esc_html__( 'Shipping tracking status can not be updated for the current status of the order.', 'sk' ) );
        }

        if ( empty( $order ) || $number === '' || $status === '' || $provider === '' || $order_id < 1 || $shipment_id < 1 ) {
            throw new Exception( esc_html__( 'Please provide all necessary data.', 'sk' ) );
        }

        global $wpdb;

        $old_tracking_info = $this->get_shipping_tracking_info( $shipment_id, 'shipment_item' );

        $ship_info = '';

        if ( $old_tracking_info->provider !== $provider ) {
            // translators: %1$s: Old provider label, %2$s: New provider label
            $ship_info .= sprintf( __( 'Shipping Provider: %1$s to %2$s', 'sk' ), '<strong>' . $old_tracking_info->provider_label . '</strong>', '<strong>' . $provider_label . '</strong><br>' );
        }

        if ( $old_tracking_info->number !== $number ) {
            // translators: %1$s: Old provider label, %2$s: New provider label
            $ship_info .= sprintf( __( 'Shipping number: %1$s to %2$s', 'sk' ), '<strong>' . $old_tracking_info->number . '</strong>', '<strong>' . $number . '</strong><br>' );
        }

        if ( $old_tracking_info->date !== $status_date ) {
            // translators: %1$s: Old provider label, %2$s: New provider label
            $ship_info .= sprintf( __( 'Shipping date: %1$s to %2$s', 'sk' ), '<strong>' . $old_tracking_info->date . '</strong>', '<strong>' . $status_date . '</strong><br>' );
        }

        if ( $old_tracking_info->shipping_status !== $status ) {
            // translators: %1$s: Old provider label, %2$s: New provider label
            $ship_info .= sprintf( __( 'Shipping status: %1$s to %2$s', 'sk' ), '<strong>' . $old_tracking_info->status_label . '</strong>', '<strong>' . $status_label . '</strong><br>' );
        }

        if ( ! empty( $ship_comments ) && ! empty( $ship_info ) ) {
            $ship_info .= '<br><strong>' . __( 'Comments: ', 'sk' ) . '</strong>' . $ship_comments;
        }

        if ( empty( $ship_info ) ) {
            throw new Exception( esc_html__( 'Please provide updated data.', 'sk' ) );
        }

        $updated = $wpdb->update(
            $wpdb->prefix . 'sk_shipping_tracking',
            [
                'provider'        => $provider,
                'provider_label'  => $provider_label,
                'provider_url'    => $provider_url,
                'number'          => $number,
                'date'            => $status_date,
                'shipping_status' => $status,
                'status_label'    => $status_label,
                'last_update'     => current_time( 'mysql' ),

            ],
            [ 'id' => $shipment_id ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );

        if ( $updated !== 1 ) {
            throw new Exception( esc_html__( 'Shipping status tracking can not be updated.', 'sk' ) );
        }

        sk_shipment_cache_clear_group( $order_id );

        $this->add_shipping_status_tracking_notes( $order_id, $shipment_id, $ship_info, $order );

        $tracking_item = $this->get_shipping_tracking_info( $shipment_id, 'shipment_item' );
        if ( 'on' === $is_notify ) {
            do_action( 'sk_order_shipping_status_tracking_notify', $order_id, $tracking_item, $ship_info, sk_get_current_user_id(), false );
        }

        if ( Helper::is_mark_as_received_allowed_for_customers() && $tracking_item->shipping_status === 'ss_delivered' ) {
            $this->schedule_to_mark_order_as_received(
                [ $order->get_id(), $shipment_id ]
            );
        }

        do_action( 'sk_shipping_tracking_updated', $shipment_id, $order_id );

        return $tracking_item;
    }

    /**
     * Add shipment tracking data to order REST response
     *
     * Enhances the order REST API response with detailed shipment tracking information
     * for each line item in the order.
     *
     *
     * @param WP_REST_Response $response The REST response object
     * @param WC_Order         $order    The order object
     *
     * @return WP_REST_Response Modified response with shipment information added
     */
    public function add_shipment_data_to_item( WP_REST_Response $response, WC_Order $order ): WP_REST_Response {
        if ( 'on' !== $this->enabled || ! $this->wc_shipping_enabled ) {
            return $response;
        }

        // Fetch the order data
        $data = $response->get_data();

        // Get all shipments for this order
        $shipments = $this->get_shipping_tracking_info( $order->get_id() );

        if ( empty( $shipments ) ) {
            return $response;
        }

        // Process each line item to add shipment data
        foreach ( $data['line_items'] as &$line_item ) {
            $item_id = $line_item['id'];

            $item_shipments    = [];
            $total_shipped_qty = 0;

            foreach ( $shipments as $shipment ) {
                $shipped_item_ids = json_decode( $shipment->item_id );
                $shipped_item_qty = json_decode( $shipment->item_qty, true );

                // Check if this item is in the shipment
                if ( is_array( $shipped_item_ids ) && in_array( $item_id, $shipped_item_ids, true ) ) {
                    // Get shipped quantity for this item
                    $shipped_qty = isset( $shipped_item_qty[ $item_id ] ) ? (int) $shipped_item_qty[ $item_id ] : 0;

                    // Create tracking information object
                    $tracking_info = [
                        'shipment_id'     => $shipment->id,
                        'provider'        => $shipment->provider,
                        'provider_label'  => $shipment->provider_label,
                        'provider_url'    => $shipment->provider_url,
                        'tracking_number' => $shipment->number,
                        'shipped_date'    => $shipment->date,
                        'status'          => $shipment->shipping_status,
                        'status_label'    => $shipment->status_label,
                        'shipped_qty'     => $shipped_qty,
                        'is_notify'       => $shipment->is_notify,
                        'last_update'     => $shipment->last_update,
                    ];

                    $item_shipments[] = $tracking_info;

                    // Calculate total shipped quantity (excluding cancelled shipments)
                    if ( $shipment->shipping_status !== 'ss_cancelled' ) {
                        $total_shipped_qty += $shipped_qty;
                    }
                }
            }

            // Update shipment data for this line item
            $line_item['shipment_info'] = [
                'is_shipped'           => ( $total_shipped_qty >= $line_item['quantity'] ),
                'is_partially_shipped' => ( $total_shipped_qty > 0 && $total_shipped_qty < $line_item['quantity'] ),
                'shipped_qty'          => $total_shipped_qty,
                'remaining_qty'        => max( 0, $line_item['quantity'] - $total_shipped_qty ),
                'total_qty'            => $line_item['quantity'],
                'tracking'             => $item_shipments,
            ];

            /**
             * Filters the shipment data for an order item.
             *
             * Allows developers to modify the shipment tracking data structure before
             * it's added to the order line item API response.
             *
             *
             * @param array          $shipment_data The shipment data compiled for the order item.
             *                                      Contains shipping status, quantities and tracking details.
             * @param array          $line_item     The order line item data as it appears in the API response.
             * @param int            $order_id      The ID of the parent order.
             * @param array          $shipments     The original shipment records retrieved from the database.
             *
             * @return array Modified shipment data.
             */
            $line_item['shipment_info'] = apply_filters(
                'sk_order_item_shipment_data',
                $line_item['shipment_info'],
                $line_item,
                $order->get_id(),
                $shipments
            );
        }

        // Update the response with our modified data
        $response->set_data( $data );

        return $response;
    }

    /**
     * Add shipping tracking info via ajax
     *
     *
     * @param void
     */
    public function add_shipping_status_tracking_info() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( esc_html__( 'Sorry! You\'re not a logged-in user', 'sk' ) );
        }

        if ( ! isset( $_REQUEST['security'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['security'] ), 'add-shipping-status-tracking-info' ) ) {
            wp_send_json_error( esc_html__( 'Are you cheating?', 'sk' ) );
        }

        $post_id   = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $item_list = (array) isset( $_POST['item_id'] ) ? sanitize_text_field( wp_unslash( $_POST['item_id'] ) ) : '';
        $item_list = json_decode( $item_list, true );

        /**
         * Filters to validate order shipment items before creating a shipment.
         *
         * This filter allows developers to check the list of items in an order
         * for any invalid entries before the shipment is created. If an invalid
         * item is found, it can be flagged using this filter.
         *
         *
         * @param bool  $invalid_items Indicates whether there are invalid items in the shipment. Default is false.
         * @param int   $post_id       The order id for travers in the shipment data.
         * @param array $item_list     The list of items in the shipment to validate.
         */
        $invalid_items = apply_filters( 'sk_order_validate_shipment_items', false, $post_id, $item_list );

        if ( $invalid_items ) {
            wp_send_json_error( esc_html__( 'Invalid items found. Please check your shipment.', 'sk' ) );
        }

        try {
            $this->create( $post_id, wp_unslash( $_POST ) );
        } catch ( Exception $e ) {
            sk_log( $e->getMessage() );
            wp_send_json_error( esc_html__( 'Error! Please enter the correct data for all shipments', 'sk' ) );
        }

        wp_send_json_success( esc_html__( 'Successfully Created New Shipment', 'sk' ) );
    }

    /**
     * Update shipping tracking info via ajax
     *
     *
     * @param void
     */
    public function update_shipping_status_tracking_info() {
        if ( ! is_user_logged_in() ) {
            die( - 1 );
        }

        if ( ! isset( $_REQUEST['security'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['security'] ), 'update-shipping-status-tracking-info' ) ) {
            die( - 1 );
        }

        $post_id     = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $shipment_id = isset( $_POST['shipment_id'] ) ? absint( $_POST['shipment_id'] ) : 0;

        try {
            $tracking_info = $this->update( $shipment_id, $post_id, wp_unslash( $_POST ) );
        } catch ( Exception $e ) {
            die( - 1 );
        }

        echo $tracking_info->status_label ?? '';

        die();
    }

    /**
     * Add shipping tracking info as customer notes
     *
     *
     * @param int      $post_id
     * @param int      $shipment_id
     * @param string   $ship_info
     * @param WC_Order $order
     *
     * @return false|int
     */
    public function add_shipping_status_tracking_notes( $post_id, $shipment_id, $ship_info, $order ) {
        if ( 'on' !== $this->enabled || ! $this->wc_shipping_enabled ) {
            return false;
        }

        if ( empty( $post_id ) || empty( $ship_info ) ) {
            return false;
        }

        $data = [
            'comment_post_ID'      => $post_id,
            'comment_author'       => 'WooCommerce',
            'comment_author_email' => '',
            'comment_author_url'   => '',
            'comment_content'      => $ship_info,
            'comment_type'         => 'shipment_order_note',
            'comment_parent'       => $shipment_id,
            'user_id'              => sk_get_current_user_id(),
            'comment_author_IP'    => sk_get_client_ip(),
            'comment_agent'        => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
            'comment_date'         => current_time( 'mysql' ),
            'comment_approved'     => 1,
        ];

        $comment_id = wp_insert_comment( $data );

        return $comment_id;
    }

    /**
     * Get all approved shipment tracking notes
     *
     *
     * @param int $order_id
     * @param int $shipment_id
     *
     * @return array $notes
     */
    public function custom_get_order_notes( $order_id, $shipment_id, $make_clickable = true ) {
        $notes = [];
        $args  = [
            'post_id' => (int) $order_id,
            'approve' => 'approve',
            'parent'  => $shipment_id,
            'type'    => 'shipment_order_note',
        ];

        remove_filter( 'comments_clauses', [ 'WC_Comments', 'exclude_order_comments' ] );

        $comments = get_comments( $args );

        foreach ( $comments as $comment ) {
            $comment->comment_content = $make_clickable ? make_clickable( $comment->comment_content ) : $comment->comment_content;
            $notes[]                  = $comment;
        }

        add_filter( 'comments_clauses', [ 'WC_Comments', 'exclude_order_comments' ] );

        return $notes;
    }

    /**
     * Create a shipping tracking info
     *
     *
     * @param array $data
     *
     * @return int insert_id
     */
    public function create_shipping_tracking( $data ) {
        global $wpdb;

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'sk_shipping_tracking',
            $data,
            [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( $inserted !== 1 ) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Prepare shipping tracking data
     *
     *
     * @param array $post_data
     *
     * @return array|bool
     * @throws Exception
     */
    public function prepare_for_db( $post_data ) {
        if ( empty( $post_data ) ) {
            return false;
        }

        $order_id          = isset( $post_data['post_id'] ) ? absint( sanitize_text_field( wp_unslash( $post_data['post_id'] ) ) ) : 0;
        $shipping_provider = isset( $post_data['shipping_provider'] ) ? sanitize_text_field( wp_unslash( $post_data['shipping_provider'] ) ) : '';
        $shipping_number   = isset( $post_data['shipping_number'] ) ? sanitize_text_field( wp_unslash( $post_data['shipping_number'] ) ) : '';
        $shipping_number   = trim( stripslashes( $shipping_number ) );
        $shipped_date      = isset( $post_data['shipped_date'] ) ? trim( sanitize_text_field( wp_unslash( $post_data['shipped_date'] ) ) ) : '';
        $shipped_status    = isset( $post_data['shipped_status'] ) ? trim( sanitize_text_field( wp_unslash( $post_data['shipped_status'] ) ) ) : '';
        $is_notify         = isset( $post_data['is_notify'] ) ? sanitize_text_field( wp_unslash( $post_data['is_notify'] ) ) : '';
        $item_id           = isset( $post_data['item_id'] ) ? sanitize_text_field( wp_unslash( $post_data['item_id'] ) ) : '';
        $item_qty          = isset( $post_data['item_qty'] ) ? wp_unslash( $post_data['item_qty'] ) : '';
        $provider_label    = sk_get_shipping_tracking_provider_by_key( $shipping_provider, 'label' );
        $provider_url      = sk_get_shipping_tracking_provider_by_key( $shipping_provider, 'url', $shipping_number );

        if ( 'sp-other' === $shipping_provider ) {
            $provider_label = isset( $post_data['other_provider'] ) ? sanitize_text_field( wp_unslash( $post_data['other_provider'] ) ) : '';
            $provider_url   = isset( $post_data['other_p_url'] ) ? sanitize_text_field( wp_unslash( $post_data['other_p_url'] ) ) : '';
        }

        $request_items = is_array( $item_qty ) ? (object) $item_qty : json_decode( $item_qty );
        $item_id_data  = [];
        $item_qty_data = [];

        if ( is_object( $request_items ) ) {
            foreach ( $request_items as $item_id => $quantity ) {
                $item_id  = intval( $item_id );
                $quantity = intval( $quantity );

                $order_item_details = new WC_Order_Item_Product( $item_id );
                if ( $order_id !== $order_item_details->get_order_id() ) {
                    throw new Exception( esc_html__( 'Invalid order item.', 'sk' ) );
                }
                $order_quantity = $order_item_details->get_quantity();

                $is_shiptted = $this->get_status_order_item_shipped( $order_id, $item_id, $order_quantity, 1 );
                $item_qty    = $is_shiptted ? $is_shiptted : 0;

                if ( $quantity <= (int) $item_qty && $quantity > 0 ) {
                    $item_id_data[]            = $item_id;
                    $item_qty_data[ $item_id ] = $quantity;
                }
            }
        }

        if ( empty( $item_id_data ) || empty( $item_qty_data ) ) {
            throw new Exception( esc_html__( 'Items quantity does not match the quantity available for shipping.', 'sk' ) );
        }

        $item_id_data  = wp_json_encode( $item_id_data );
        $item_qty_data = wp_json_encode( $item_qty_data );

        $data = [
            'order_id'        => $order_id,
            'seller_id'       => sk_get_current_user_id(),
            'provider'        => $shipping_provider,
            'provider_label'  => $provider_label,
            'provider_url'    => $provider_url,
            'number'          => $shipping_number,
            'date'            => $shipped_date,
            'shipping_status' => $shipped_status,
            'status_label'    => sk_get_shipping_tracking_status_by_key( $shipped_status ),
            'is_notify'       => $is_notify,
            'item_id'         => $item_id_data,
            'item_qty'        => $item_qty_data,
            'last_update'     => current_time( 'mysql' ),
            'status'          => 0,
        ];

        return $data;
    }

    /**
     * Get shipping tracking data by order id
     *
     *
     * @param int   $order_id
     *
     * @param array $shipment
     */
    public function get_shipping_tracking_data( $order_id ) {
        // getting a result from cache
        $cache_group = 'seller_shipment_tracking_data_' . $order_id;
        $cache_key   = 'shipping_tracking_data_' . $order_id;
        $results     = Cache::get( $cache_key, $cache_group );

        if ( false !== $results ) {
            return $results;
        }

        // get all data from database
        $tracking_info = $this->get_shipping_tracking_info( $order_id );

        if ( empty( $tracking_info ) ) {
            // no shipment is added, so set cache and return empty array
            Cache::set( $cache_key, [], $cache_group );

            return [];
        }

        $line_item_count                    = [];
        $shipping_status_count              = [];
        $total_item_count                   = 0;
        $total_item_count_without_cancelled = 0;

        foreach ( $tracking_info as $shipment_data ) {
            // count shipping status
            $shipping_status = $shipment_data->shipping_status;

            $shipping_status_count[ $shipping_status ] = isset( $shipping_status_count[ $shipping_status ] ) ? $shipping_status_count[ $shipping_status ] + 1 : 1;

            // count total item
            ++ $total_item_count;

            // count total item without cancelled shipping
            if ( 'ss_cancelled' !== $shipping_status ) {
                ++ $total_item_count_without_cancelled;
            }

            // count line item
            $shipment_items = json_decode( $shipment_data->item_qty );

            if ( is_object( $shipment_items ) && 'ss_cancelled' !== $shipping_status ) {
                foreach ( $shipment_items as $item_id => $count ) {
                    $line_item_count[ $item_id ] = isset( $line_item_count[ $item_id ] ) ? $line_item_count[ $item_id ] + (int) $count : (int) $count;
                }
            }
        }

        $results = [
            'line_item_count'        => $line_item_count,
            'shipping_status_count'  => $shipping_status_count,
            'total_count'            => $total_item_count,
            'total_except_cancelled' => $total_item_count_without_cancelled,
        ];

        // set cache
        Cache::set( $cache_key, $results, $cache_group );

        return $results;
    }

    /**
     * Change the columns shown in admin area
     *
     *
     * @param array $existing_columns
     *
     * @return array
     */
    public function admin_shipping_status_tracking_columns( $existing_columns ) {
        // Remove seller, suborder column if seller is viewing his own product
        if ( ! current_user_can( 'manage_woocommerce' ) || ( ! empty( $_GET['author'] ) ) ) { // phpcs:ignore
            return $existing_columns;
        }

        $existing_columns['shipping_status_tracking'] = __( 'Shipment', 'sk' );

        return apply_filters( 'sk_edit_shop_order_columns', $existing_columns );
    }

    /**
     * Adds custom column on sk admin shop order table
     *
     *
     * @param string       $col
     * @param int|WC_Order $post_id
     *
     * @return void
     */
    public function shop_order_shipping_status_columns( $col, $post_id ) {
        // return if user doesn't have access
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // check if post_id is an order
        if ( ! sk_is_order( $post_id ) ) {
            return;
        }

        if ( 'shipping_status_tracking' !== $col ) {
            return;
        }

        $order = wc_get_order( $post_id );
        if ( $order->get_meta( 'has_sub_order' ) ) {
            $status = sk_get_main_order_shipment_current_status( $order->get_id() );
        } else {
            $status = sk_get_order_shipment_current_status( $order->get_id() );
        }

        switch ( $col ) {
            case 'shipping_status_tracking':
                echo $status;
                break;
        }
    }

    /**
     * Shipment order meta box for admin order page
     *
     *
     * $param string $post_type
     * $param WP_POST|WC_Order $post
     *
     * @return void
     */
    public function shipment_order_add_meta_boxes( $post_type, $post ) {
        $screen = sk_is_hpos_enabled()
            ? wc_get_page_screen_id( 'shop-order' )
            : 'shop_order';

        if ( $screen !== $post_type ) {
            return;
        }

        $order_id = $post instanceof \WC_Abstract_Order ? $post->get_id() : $post->ID;
        $order    = wc_get_order( $order_id );

        if ( empty( $order ) ) {
            return;
        }

        if ( $order->get_meta( 'has_sub_order' ) ) {
            return;
        }

        add_meta_box( 'sk_shipment_status_details', __( 'Shipments', 'sk' ), [
            self::class,
            'shipment_order_details_add_meta_boxes'
        ], $screen, 'normal', 'core' );
    }

    /**
     * Get shipping tracking info by order/shipment id
     *
     *
     * @param int    $id
     * @param string $context
     * @param bool   $ignore_cancelled
     *
     * @return array|stdClass  $shipment
     */
    public function get_shipping_tracking_info( $id, $context = 'shipment_info', $ignore_cancelled = false ) {
        if ( empty( $id ) || ! in_array( $context, [ 'shipment_info', 'shipment_item' ], true ) ) {
            return [];
        }

        global $wpdb;

        $ignore_cancel = '';

        if ( $ignore_cancelled ) {
            $ignore_cancel = " AND shipping_status != 'ss_cancelled' ";
        }

        if ( 'shipment_info' === $context ) {
            $sql = "SELECT * from {$wpdb->prefix}sk_shipping_tracking WHERE order_id = %d {$ignore_cancel} ORDER BY id ASC";
        } elseif ( 'shipment_item' === $context ) {
            $sql = "SELECT * from {$wpdb->prefix}sk_shipping_tracking WHERE id = %d {$ignore_cancel}";
        }

        $shipment = $wpdb->get_results( $wpdb->prepare( $sql, $id ) );

        return 'shipment_item' === $context && $shipment ? $shipment[0] : $shipment;
    }

    /**
     * Is order item fully shiptted
     *
     *
     * @param int $order_id
     * @param int $item_id
     * @param int $item_qty
     * @param int $need_available
     *
     * @return  bool|int
     */
    public function get_status_order_item_shipped( $order_id, $item_id, $item_qty = 0, $need_available = 0 ) {
        // based on $need_available decide what to return in case of validation error
        $return = $need_available ? $item_qty : false;

        if ( empty( $order_id ) ) {
            return $return;
        }

        // get all shipment-related data for this order
        $shipping_data = $this->get_shipping_tracking_data( $order_id );

        // check if data exits
        if ( empty( $shipping_data ) || ! isset( $shipping_data['line_item_count'] ) ) {
            return $return;
        }

        // get line item count data
        $line_item_count = $shipping_data['line_item_count'];

        // check if $item_id exists
        if ( ! array_key_exists( $item_id, $line_item_count ) ) {
            return $return;
        }

        // if $need_available is true return remaining item count
        if ( $need_available ) {
            return intval( $item_qty ) - intval( $line_item_count[ $item_id ] );
        }

        if ( intval( $item_qty ) === intval( $line_item_count[ $item_id ] ) ) {
            return true;
        }

        return false;
    }

    /**
     * Shipment order details meta box for admin area order page
     *
     *
     * @return void
     */
    public static function shipment_order_details_add_meta_boxes( $post_object ) {
        $order = ( $post_object instanceof WP_Post ) ? wc_get_order( $post_object->ID ) : $post_object;
        if ( empty( $order ) ) {
            return;
        }

        $order_id      = $order->get_id();
        $shipment_info = sk_ext()->shipment->get_shipping_tracking_info( $order_id );
        $incre         = 1;

        if ( empty( $shipment_info ) ) {
            echo __( 'No shipment added for this order', 'sk' );

            return;
        }

        $line_items = $order->get_items( apply_filters( 'woocommerce_admin_order_item_types', 'line_item' ) );

        foreach ( $shipment_info as $key => $shipment ) :
            $shipment_id           = $shipment->id;
            $order_id              = $shipment->order_id;
            $provider              = $shipment->provider_label;
            $number                = $shipment->number;
            $status_label          = $shipment->status_label;
            $shipping_status       = $shipment->shipping_status;
            $provider_url          = $shipment->provider_url;
            $item_qty              = json_decode( $shipment->item_qty );
            $shipment_timeline     = sk_ext()->shipment->custom_get_order_notes( $order_id, $shipment_id );
            $recipient_status      = $order->get_meta( 'sk_customer_order_receipt_status' );
            $shipment_mark_receive = Helper::is_order_marked_as_received( $order_id, $shipment_id );

            sk_get_template_part(
                'orders/shipment/html-shipments-list-admin', '', [
                    'pro'                   => true,
                    'shipment_id'           => $shipment_id,
                    'order_id'              => $order_id,
                    'provider'              => $provider,
                    'number'                => $number,
                    'status_label'          => $status_label,
                    'shipping_status'       => $shipping_status,
                    'provider_url'          => $provider_url,
                    'item_qty'              => $item_qty,
                    'order'                 => $order,
                    'line_items'            => $line_items,
                    'incre'                 => $incre ++,
                    'customer_status'       => $recipient_status,
                    'shipment_timeline'     => $shipment_timeline,
                    'shipment_mark_receive' => $shipment_mark_receive,
                ]
            );
        endforeach;
    }

    /**
     * Shipment order details show after order table WC my account
     *
     *
     * @param WC_Order $order
     *
     * @return void
     */
    public function shipment_order_details_after_order_table( $order ) {
        if ( empty( $order ) ) {
            return;
        }

        $order_id      = $order->get_id();
        $shipment_info = $this->get_shipping_tracking_info( $order_id );

        if ( empty( $shipment_info ) ) {
            return;
        }

        $line_items           = $order->get_items( 'line_item' );
        $is_order_shipped     = Helper::is_order_fully_shipped( $order );
        $allowed_mark_receive = Helper::is_mark_as_received_allowed_for_customers();

        sk_get_template_part(
            'orders/shipment/html-customer-shipments-list', '', [
                'pro'                  => true,
                'order'                => $order,
                'line_items'           => $line_items,
                'shipment_info'        => $shipment_info,
                'is_order_shipped'     => $is_order_shipped,
                'allowed_mark_receive' => $allowed_mark_receive,
            ]
        );
    }

    /**
     * Shipment column added on my account page order listing page
     *
     *
     * @param array $columns
     *
     * @return array
     */
    public function shipment_my_account_my_orders_columns( $columns ) {
        $new_columns = [];

        foreach ( $columns as $key => $name ) {
            $new_columns[ $key ] = $name;

            // add ship-to after order status column
            if ( 'order-status' === $key ) {
                $new_columns['sk-shipment-status'] = __( 'Shipment', 'sk' );
            }
        }

        return $new_columns;
    }

    /**
     * Shipment data show on my account page order listing page
     *
     *
     * @param WC_Order $order
     *
     * @return void
     */
    public function shipment_my_account_orders_column_data( $order ) {
        if ( $order->get_meta( 'has_sub_order' ) ) {
            echo sk_get_main_order_shipment_current_status( $order->get_id() );

            return;
        }

        echo sk_get_order_shipment_current_status( $order->get_id() );
    }

    /**
     * Add SK Pro localized vars
     *
     *
     * @param array $args
     *
     * @return array
     */
    public function set_localized_data( $args ) {
        $args['shipment_status_update_msg'] = __( 'Shipment Successfully Updated', 'sk' );

        return $args;
    }

    /**
     * Adds localized data for the "Mark as Received" feature.
     *
     * This method sets the localization data for the "Mark as Received" feature,
     * which will be used in the frontend.
     *
     *
     * @param array $data The existing localization data.
     *
     * @return array The modified localization data with "Mark as Received" feature information.
     */
    public function add_localize_data_for_mark_received( array $data ): array {
        $data['mark_received']['nonce']                 = wp_create_nonce( 'sk_mark_received_nonce' );
        $data['mark_received']['status_label']          = __( 'Received', 'sk' );
        $data['mark_received']['confirmation_msg']      = __( 'Do you want to mark this order as received?', 'sk' );
        $data['mark_received']['complete_status_label'] = __( 'Complete', 'sk' );

        return $data;
    }

    /**
     * Adds admin localized data for the "Mark as Received" feature.
     *
     * This method sets the localization data for the "Mark as Received" feature,
     * which will be used in the admin panel.
     *
     *
     * @param array $data The existing localization data.
     *
     * @return array The modified localization data with "Mark as Received" feature information.
     */
    public function add_localize_data_for_admin_mark_received( array $data ): array {
        $data['mark_received']['status_label']         = __( 'Received', 'sk' );
        $data['mark_received']['must_use_description'] = __( '(This is must use item)', 'sk' );

        return $data;
    }
}
