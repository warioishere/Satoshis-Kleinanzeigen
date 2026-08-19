<?php

use SK\Modules\Subscription\Helper;
use SK\Core\Utilities\OrderUtil;

/**
 * Admin related functions
 *
 * @subpackage Subscription
 */
class DPS_Admin {

    public function __construct() {
        add_action( 'init', array( $this, 'register_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

        // add product area in admin panel
        add_filter( 'product_type_selector', [ __CLASS__, 'add_product_type' ], 1 );
        add_action( 'woocommerce_product_options_general_product_data', [ __CLASS__, 'general_fields' ] );
        add_action( 'woocommerce_process_product_meta', [ __CLASS__, 'general_fields_save' ], 99 );

        // settings section
        add_filter( 'sk_settings_sections', [ __CLASS__, 'add_new_section_admin_panael' ] );
        add_filter( 'sk_settings_fields', [ __CLASS__, 'add_new_setting_field_admin_panael' ], 12, 1 );

        //add dropdown field with subscription packs
        add_action( 'sk_seller_meta_fields', [ __CLASS__, 'add_subscription_packs_dropdown' ], 10, 1 );

        //save user meta
        add_action( 'sk_process_seller_meta_fields', [ __CLASS__, 'save_meta_fields' ], 99 );

        // related orders metabox
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ], 99, 10, 2 );
        add_action( 'sk_vendor_subscription_related_orders_meta_box_rows', [ $this, 'render_subscriptions_related_order' ], 10, 1 );

        if ( sk_is_hpos_enabled() ) {
            // Add a column that indicates whether an order is parent or renewal for a subscription
            add_filter( 'manage_woocommerce_page_wc-orders_columns', [ $this, 'add_contains_subscription_column' ], 8, 1 );
            add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ $this, 'add_contains_subscription_column_content' ], 8, 2 );
        } else {
            // Add a column that indicates whether an order is parent or renewal for a subscription
            add_filter( 'manage_edit-shop_order_columns', [ $this, 'add_contains_subscription_column' ], 8, 1 );
            add_action( 'manage_shop_order_posts_custom_column', [ $this, 'add_contains_subscription_column_content' ], 8, 2 );
        }

        // remove sub-order class
        add_filter( 'post_class', [ $this, 'admin_shop_order_row_classes' ], 20, 1 ); // no need to add hpos support for this filter
        add_filter( 'sk_manage_shop_order_custom_columns_order_number', [ $this, 'remove_suborder_notes' ], 10, 2 );

        // Add a vendor subscription filter option.
        add_filter( 'sk_order_type_filter_options', [ $this, 'add_vendor_subscription_filter_option' ] );
        add_filter( 'sk_order_type_filter_query_args', [ $this, 'filter_vendor_subscription_orders' ], 10, 2 );
    }

    /**
     * Remove sub-order text from Order list items
     *
     * @param string $output
     * @param WC_Order $order
     *
     *
     * @return string
     */
    public function remove_suborder_notes( $output, $order ) {
        if ( Helper::is_vendor_subscription_order( $order ) ) {
            return '';
        }
        return $output;
    }

    /**
     * Remove sk css classes on admin shop order table
     *
     * @global WP_Post $post
     *
     * @param array $classes
     *
     *
     * @return array
     */
    public function admin_shop_order_row_classes( $classes ) {
        global $post;

        if ( $post->post_type === 'shop_order' && $post->post_parent !== 0 && Helper::is_vendor_subscription_order( $post->ID ) ) {
            $class = 'sub-order parent-' . $post->post_parent;
            $item_index = array_search( $class, $classes, true );
            if ( false !== $item_index ) {
                unset( $classes[ $item_index ] );
            }
        }

        return $classes;
    }

    /**
     * Add a column to the WooCommerce -> Orders admin screen to indicate whether an order is a
     * parent of a subscription, a renewal order for a subscription, or a regular order.
     *
     * @param array $columns The current list of columns
     *
     *
     * @return array
     */
    public function add_contains_subscription_column( $columns ) {
        if ( class_exists( 'WC_Subscriptions_Order' ) ) {
            return $columns;
        }

        $column_header = '<span class="subscription_head tips" data-tip="' . esc_attr__( 'Vendor Subscription Relationship', 'sk-core' ) . '">' . esc_attr__( 'Subscription Relationship', 'sk-core' ) . '</span>';

        $new_columns = Helper::array_insert_after( 'shipping_address', $columns, 'subscription_relationship', $column_header );

        return $new_columns;
    }

    /**
     * Add column content to the WooCommerce -> Orders admin screen to indicate whether an
     * order is a parent of a subscription, a renewal order for a subscription, or a
     * regular order.
     *
     * This method will reuse column added by wcs, if wcs is enabled we are handling values provided by
     * wcs by our end, we are also deresitering hooks of wcs
     *
     * @param string $column The string of the current column
     *
     *
     * @return void
     */
    public static function add_contains_subscription_column_content( $column, $post_id ) {
        // return if user doesn't have access
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // check if post_id is an order
        if ( ! sk_is_order( $post_id ) ) {
            return;
        }

        // early return if not subscription_relationship column
        if ( 'subscription_relationship' !== $column ) {
            return;
        }

        if ( class_exists( 'WC_Subscriptions_Order' ) ) {
            // remove wc subscription hooks to render this column content
            remove_action( 'manage_shop_order_posts_custom_column', 'WC_Subscriptions_Order::add_contains_subscription_column_content', 10 );
            add_filter( 'manage_edit-shop_order_columns', 'WC_Subscriptions_Order::add_contains_subscription_column' );
            add_action( 'manage_shop_order_posts_custom_column', 'WC_Subscriptions_Order::add_contains_subscription_column_content', 10, 1 );

            // populate wc subscription field data
            $output = '';
            if ( wcs_order_contains_subscription( $post_id, 'renewal' ) ) {
                $output = '<span class="subscription_renewal_order tips" data-tip="' . esc_attr__( 'Renewal Order', 'woocommerce-subscriptions' ) . '"></span>'; //phpcs:ignore
            } elseif ( wcs_order_contains_subscription( $post_id, 'resubscribe' ) ) {
                $output = '<span class="subscription_resubscribe_order tips" data-tip="' . esc_attr__( 'Resubscribe Order', 'woocommerce-subscriptions' ) . '"></span>'; //phpcs:ignore
            } elseif ( wcs_order_contains_subscription( $post_id, 'parent' ) ) {
                $output = '<span class="subscription_parent_order tips" data-tip="' . esc_attr__( 'Parent Order', 'woocommerce-subscriptions' ) . '"></span>';  //phpcs:ignore
            }

            // early return if its wc subscription order
            if ( ! empty( $output ) ) {
                echo $output;
                return;
            }
        }

        // get order
        $order = wc_get_order( $post_id );
        // check if vendor subscription order
        if ( ! Helper::is_vendor_subscription_order( $order ) ) {
            echo '<span class="normal_order">&ndash;</span>';
            return;
        }

        // renewal orders are children of the original order
        if ( 0 !== $order->get_parent_id() ) {
            echo '<span class="sk_vs_renew_order tips" data-tip="' . esc_attr__( 'Vendor Subscription Renewal Order', 'sk-core' ) . '"></span>';
            return;
        }

        $product = Helper::get_vendor_subscription_product_by_order( $order );

        if ( ! $product ) {
            // maybe product has been deleted
            echo '<span class="normal_order">&ndash;</span>';
            return;
        }

        echo '<span class="sk_vs_non_recurring_order tips" data-tip="' . esc_attr__( 'Vendor Subscription Order', 'sk-core' ) . '"></span>';
    }

    /**
     * Add WC Meta boxes
     *
     *
     * @return void
     */
    public function add_meta_boxes( $post_type, $post ) {
        $screen = sk_is_hpos_enabled()
            ? wc_get_page_screen_id( 'shop-order' )
            : 'shop_order';

        if ( $screen !== $post_type ) {
            return;
        }

        $order_id = OrderUtil::get_post_or_order_id( $post );

        // Only display the meta box if an order relates to a subscription
        if ( ! Helper::is_vendor_subscription_order( $order_id ) ) {
            return;
        }

        //remove woocommerce subscription metaox
        $subscription_screen_id = sk_is_hpos_enabled() ? wc_get_page_screen_id( 'shop_subscription' ) : 'shop_subscription';
        if ( ! empty( $subscription_screen_id ) ) {
            remove_meta_box( 'woocommerce-order-data', $subscription_screen_id, 'normal' );
        }

        // remove delivery time metabox
        remove_meta_box( 'sk_delivery_time_fields', $screen, 'side' );

        // add subscription metabox
        add_meta_box( 'sk_vendor_subscription_renewal_orders', __( 'Vendor Subscriptions Related Orders', 'sk-core' ), [ $this, 'subscription_metabox_content' ], $screen, 'normal', 'high' );
    }

    /**
     * Render Subscription Metabox Content
     *
     * @param $post
     *
     *
     * @return void
     */
    public function subscription_metabox_content( $post ) {
        $order_id = OrderUtil::get_post_or_order_id( $post );

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        sk_get_template_part( 'admin/related-orders-table', '', [ 'is_subscription' => true, 'post' => $post ] );

        /**
         * @args WC_Order $order
         * @args Post $post
         */
        do_action( 'sk_vendor_subscription_related_orders_meta_box', $order, $post );
    }

    /**
     * Render Related Order Data
     *
     * @param $post
     *
     *
     * @return void
     * @throws Exception
     */
    public function render_subscriptions_related_order( $post ) {
        $order_id = OrderUtil::get_post_or_order_id( $post );
        $order    = wc_get_order( $order_id );

        if ( ! $order ) {
            sk_get_template_part( 'admin/related-orders-empty-row', '', [ 'is_subscription' => true ] );
            return;
        }

        $parent_order      = null;
        $orders_to_display = [];

        // get parent order
        if ( $order->get_parent_id() === 0 ) {
            // this is the parent order
            $parent_order = $order;
        } else {
            $parent_order = wc_get_order( $order->get_parent_id() );
        }

        // collect the renewal orders belonging to this parent order
        $args = [
            'parent'  => $parent_order->get_id(),
            'limit'   => -1,
            'orderby' => 'date',
            'order'   => 'DESC',
            'type'    => 'shop_order',
        ];

        $query = new WC_Order_Query( $args );
        $orders_to_display = $query->get_orders();

        // check if we got renewal orders
        if ( empty( $orders_to_display ) ) {
            sk_get_template_part( 'admin/related-orders-empty-row', '', [ 'is_subscription' => true ] );
            return;
        }

        //include current order
        if ( $parent_order->get_id() !== $order->get_id() ) {
            $orders_to_display[] = $parent_order;
        }

        $orders_to_display = apply_filters( 'sk_vendor_subscription_admin_related_orders_to_display', $orders_to_display, $parent_order, $post );

        foreach ( $orders_to_display as $order ) {
            // Skip the order being viewed.
            if ( $order->get_id() === $order_id ) {
                continue;
            }

            sk_get_template_part( 'admin/related-orders-row', '', [ 'is_subscription' => true, 'order' => $order ] );
        }
    }

    /**
     * Register Scripts
     *
     */
    public function register_scripts() {
        [ $suffix, $version ] = sk_get_script_suffix_and_version();

        wp_register_style( 'dps-custom-style', DPS_URL . '/assets/css/style' . $suffix . '.css', false, $version );
        wp_register_style( 'sk-subscription-related-orders', DPS_URL . '/assets/css/admin-related-orders.css', false, $version );
        wp_register_style( 'sk-subscription-order-page', DPS_URL . '/assets/css/admin-order-page.css', false, $version );
        wp_register_script( 'sk-subscription-admin', DPS_URL . '/assets/js/admin-subscription.js', array(), $version, true );
        wp_register_script( 'dps-custom-admin-js', DPS_URL . '/assets/js/admin-script' . $suffix . '.js', array( 'jquery' ), $version, true );
    }

    public function admin_enqueue_scripts( $hook ) {
        wp_enqueue_style( 'dps-custom-style' );
        wp_enqueue_script( 'dps-custom-admin-js' );

        wp_localize_script(
            'dps-custom-admin-js', 'skSubscription', array(
                'ajaxurl'               => admin_url( 'admin-ajax.php' ),
                'isSubscriptionEnabled' => Helper::is_vendor_subscription_enabled(),
                            )
        );

        $screen = sk_is_hpos_enabled() ? wc_get_page_screen_id( 'shop_order' ) : 'shop_order';
        if ( $screen === $hook || $screen === get_current_screen()->post_type ) {
            wp_enqueue_style( 'sk-subscription-order-page' );
        }
    }

    /**
     * WooCommerce Orders admin table css for vendor subscription relation
     *
     *
     * @return void
     */

    /**
     * Add woocommerce extra product type
     *
     * @param array $types
     * @param array $product_type
     *
     * @return array
     */
    public static function add_product_type( $types ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return $types;
        }

        $types['product_pack'] = __( 'SK Subscription', 'sk-core' );

        return $types;
    }

    /**
     * Add extra custom field in woocommerce product type
     */
    public static function general_fields() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        global $woocommerce, $post;

        echo '<div class="options_group show_if_product_pack">';

        woocommerce_wp_text_input(
            array(
                'id'                => '_no_of_product',
                'label'             => __( 'Number of Products', 'sk-core' ),
                'placeholder'       => __( 'Put -1 for unlimited products', 'sk-core' ),
                'description'       => __( 'Enter the no of product you want to give this package.', 'sk-core' ),
                'type'              => 'number',
                'custom_attributes' => array(
                    'step' => 'any',
                    'min'  => '-1',
                ),
            )
        );

        woocommerce_wp_text_input(
            array(
                'id'                => '_pack_validity',
                'label'             => __( 'Pack Validity', 'sk-core' ),
                'placeholder'       => 'Put 0 for unlimited days',
                'description'       => __( 'Enter no of validity days you want to give this pack ', 'sk-core' ),
                'type'              => 'number',
                'custom_attributes' => array(
                    'step' => 'any',
                    'min'  => '0',
                ),
            )
        );

        do_action( 'dps_subscription_product_fields_after_pack_validity' );
        woocommerce_wp_checkbox(
            array(
                'id'          => '_exclusive_for_admin_only',
                'label'       => __( 'Exclusive for Admins only', 'sk-core' ),
                'description' => __( 'This subscription is exclusive for Admins only and will be only visible to Admin', 'sk-core' ),
            )
        );

        // vendor allowed product types
        echo '<p class="form-field sk_subscription_allowed_product_types">';
        echo '<label for="sk_subscription_allowed_product_types">' . __( 'Allowed Product Types', 'sk-core' ) . '</label>';
        echo '<select multiple="multiple" data-placeholder=" ' . __( 'Any product types', 'sk-core' ) . '" class="wc-enhanced-select" id="_vendor_allowed_product_type" name="sk_subscription_allowed_product_types[]" style="width: 350px;">';
        Helper::get_product_types_options();
        echo '</select>';
        echo '<span class="description">' . __( 'Select product type for this package. Leave empty to allow any product type.', 'sk-core' ) . '</span>';
        echo '</p>';

        // vendor allowed categories
        echo '<p class="form-field _vendor_allowed_categories">';
        $selected_cat = get_post_meta( $post->ID, '_vendor_allowed_categories', true );
        echo '<label for="_vendor_allowed_categories">' . __( 'Allowed categories', 'sk-core' ) . '</label>';
        echo '<select multiple="multiple" data-placeholder=" ' . __( 'Any categories', 'sk-core' ) . '" class="wc-enhanced-select" id="_vendor_allowed_categories" name="_vendor_allowed_categories[]" style="width: 350px;">';
        $r = array();
        $r['pad_counts']    = 1;
        $r['hierarchical']  = 1;
        $r['hide_empty']    = 0;
        $r['value']         = 'id';
        $r['selected']      = ! empty( $selected_cat ) ? array_map( 'absint', $selected_cat ) : '';
        $r['orderby']       = 'name';

        $categories = get_terms( 'product_cat', $r );
        include_once WC()->plugin_path() . '/includes/walkers/class-product-cat-dropdown-walker.php';

        echo wc_walk_category_dropdown_tree( $categories, 0, $r );
        echo '</select>';
        echo '<span class="description">' . __( 'Select specific product category for this package. Leave empty to select all categories.', 'sk-core' ) . '</span>';

        echo '</p>';

        woocommerce_wp_checkbox(
            array(
                'id'          => '_enable_gallery_restriction',
                'label'       => __( 'Restrict Gallery Image Upload', 'sk-core' ),
                'description' => __( 'Please check this if you want to restrict gallery image uploading.', 'sk-core' ),
            )
        );

        woocommerce_wp_text_input(
            array(
                'id'                => '_gallery_image_restriction_count',
                'label'             => __( 'Maximum Image', 'sk-core' ),
                'placeholder'       => 'Put -1 for unlimited image',
                'description'       => __( 'Max Image vendor can upload', 'sk-core' ),
                'type'              => 'number',
                'custom_attributes' => array(
                    'step' => 'any',
                    'min'  => '-1',
                ),
            )
        );

        echo '</div>';

        wp_nonce_field( 'dps_product_fields_nonce', 'dps_product_pack' );

        do_action( 'dps_subscription_product_fields' );
    }


    /**
     * Manupulate custom filed meta data in post meta
     *
     * @param integer $post_id
     */
    public static function general_fields_save( $post_id ) {
        if ( ! isset( $_POST['dps_product_pack'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['dps_product_pack'] ) ), 'dps_product_fields_nonce' ) ) {
            return;
        }

        if ( ! isset( $_POST['product-type'] ) || sanitize_text_field( wp_unslash( $_POST['product-type'] ) ) !== 'product_pack' ) {
            return;
        }

        update_post_meta( $post_id, '_virtual', 'yes' );
        update_post_meta( $post_id, '_sold_individually', 'yes' );

        // WC 3.0+ compatibility
        $visibility_term = array( 'exclude-from-search', 'exclude-from-catalog' );
        wp_set_post_terms( $post_id, $visibility_term, 'product_visibility', false );
        update_post_meta( $post_id, '_visibility', 'hidden' );

        $woocommerce_no_of_product_field = isset( $_POST['_no_of_product'] ) ? intval( wp_unslash( $_POST['_no_of_product'] ) ) : '';
        if ( $woocommerce_no_of_product_field !== '' ) {
            update_post_meta( $post_id, '_no_of_product', $woocommerce_no_of_product_field );
        }

        $woocommerce_pack_validity_field = isset( $_POST['_pack_validity'] ) ? intval( wp_unslash( $_POST['_pack_validity'] ) ) : '';
        if ( $woocommerce_pack_validity_field !== '' ) {
            update_post_meta( $post_id, '_pack_validity', $woocommerce_pack_validity_field );
        }

        if ( ! empty( $_POST['sk_subscription_allowed_product_types'] ) ) {
            update_post_meta( $post_id, 'sk_subscription_allowed_product_types', wc_clean( wp_unslash( $_POST['sk_subscription_allowed_product_types'] ) ) );
        } else {
            delete_post_meta( $post_id, 'sk_subscription_allowed_product_types' );
        }

        if ( ! empty( $_POST['_vendor_allowed_categories'] ) ) {
            update_post_meta( $post_id, '_vendor_allowed_categories', wc_clean( wp_unslash( $_POST['_vendor_allowed_categories'] ) ) );
        } else {
            delete_post_meta( $post_id, '_vendor_allowed_categories' );
        }

        $woocommerce_enable_gallery_restriction = isset( $_POST['_enable_gallery_restriction'] ) ? 'yes' : 'no';
        update_post_meta( $post_id, '_enable_gallery_restriction', wc_clean( $woocommerce_enable_gallery_restriction ) );

        $gallery_image_restriction_count = isset( $_POST['_gallery_image_restriction_count'] ) && intval( $_POST['_gallery_image_restriction_count'] ) >= 0 ? intval( wp_unslash( $_POST['_gallery_image_restriction_count'] ) ) : -1;
        if ( $woocommerce_enable_gallery_restriction === 'yes' ) {
            update_post_meta( $post_id, '_gallery_image_restriction_count', $gallery_image_restriction_count );
        } elseif ( $woocommerce_enable_gallery_restriction === 'no' ) {
            delete_post_meta( $post_id, '_gallery_image_restriction_count' );
        }

        $product = wc_get_product( $post_id );
        $exclusive_for_admin_only = isset( $_POST['_exclusive_for_admin_only'] ) ? 'yes' : 'no';
        $product->update_meta_data( '_exclusive_for_admin_only', $exclusive_for_admin_only );
        $product->save();

        do_action( 'dps_process_subcription_product_meta', $post_id );
    }


    /**
     * Add new Section in admin sk settings
     *
     * @param array $sections
     *
     * @return array
     */
    public static function add_new_section_admin_panael( $sections ) {
        $sections['sk_product_subscription'] = [
            'id'                   => 'sk_product_subscription',
            'title'                => __( 'Vendor Subscription', 'sk-core' ),
            'icon_url'             => DPS_URL . '/assets/images/subscription.svg',
            'description'          => __( 'Manage Subscription Plans', 'sk-core' ),
            'document_link'        => 'https://sk.co/docs/wordpress/modules/how-to-install-use-sk-subscription/',
            'settings_title'       => __( 'Vendor Subscription Settings', 'sk-core' ),
            'settings_description' => __( 'Configure marketplace settings to authorize vendors to create subscription products for their stores.', 'sk-core' ),
        ];

        return $sections;
    }

    /**
     * Get all Pages
     *
     * @param string  $post_type
     * @return array
     */
    public static function get_post_type( $post_type ) {
        $pages_array = array( '-1' => __( '- select -', 'sk-core' ) );
        $pages = get_posts(
            array(
                'post_type' => $post_type,
                'numberposts' => -1,
            )
        );

        if ( $pages ) {
            foreach ( $pages as $page ) {
                $pages_array[ $page->ID ] = $page->post_title;
            }
        }

        return $pages_array;
    }

    /**
     * Add new Settings field in admin dashboard for selection product
     * subscription page
     *
     * @param array   $settings_fields
     * @return array
     */
    public static function add_new_setting_field_admin_panael( $settings_fields ) {
        $pages_array = self::get_post_type( 'page' );

        $settings_fields['sk_product_subscription'] = array(
            'subscription_pack' => array(
                'name'    => 'subscription_pack',
                'label'   => __( 'Subscription', 'sk-core' ),
                'type'    => 'select',
                'options' => $pages_array,
                'tooltip' => __( 'Select the page in which you want to show subscription packages.', 'sk-core' ),
            ),
            'enable_pricing' => array(
                'name'  => 'enable_pricing',
                'label' => __( 'Enable Vendor Subscription', 'sk-core' ),
                'desc'  => __( 'Enable subscription for vendor', 'sk-core' ),
                'type'  => 'switcher',
            ),
            'enable_subscription_pack_in_reg' => [
                'name'    => 'enable_subscription_pack_in_reg',
                'label'   => __( 'Enable Subscription in Registration Form', 'sk-core' ),
                'desc'    => __( 'Enable subscription pack in registration form for new vendor', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'on',
                'tooltip' => __( 'If checked, vendor completes registration only after subscribing to a pack', 'sk-core' ),
            ],
            'no_of_days_before_mail' => array(
                'name'    => 'no_of_days_before_mail',
                'label'   => __( 'No. of Days', 'sk-core' ),
                'desc'    => __( 'Before an email will be sent to the vendor', 'sk-core' ),
                'type'    => 'text',
                'size'    => 'midium',
                'default' => '2',
            ),
            'product_status_after_end' => array(
                'name'    => 'product_status_after_end',
                'label'   => __( 'Product Status', 'sk-core' ),
                'desc'    => __( 'Product status when vendor pack validity will expire', 'sk-core' ),
                'type'    => 'select',
                'default' => 'draft',
                'options' => array(
                    'publish' => __( 'Published', 'sk-core' ),
                    'pending' => __( 'Pending Review', 'sk-core' ),
                    'draft'   => __( 'Draft', 'sk-core' ),
                ),
            ),
        );

        if ( sk_ext()->module->product_subscription->is_sk_plugin() ) {
            unset( $settings_fields['sk_product_subscription'][0] );
        }

        return $settings_fields;
    }

    /**
     * Add subscription packs in drowpdown to let admin select a pack for the seller
     */
    public static function add_subscription_packs_dropdown( $user ) {
        $users_assigned_pack       = get_user_meta( $user->ID, 'product_package_id', true );
        $vendor_allowed_categories = get_user_meta( $user->ID, 'vendor_allowed_categories', true );

        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => 'product_pack',
                ),
            ),
        );
        $sub_packs = get_posts( apply_filters( 'sk_get_assignable_pack_args', $args ) );
        ?>
        <tr>
            <td>
                <h3><?php esc_html_e( 'SK Subscription', 'sk-core' ); ?> </h3>
            </td>
        </tr>

        <?php if ( $users_assigned_pack ) : ?>
            <tr>
                <td><?php esc_html_e( 'Currently Activated Pack', 'sk-core' ); ?></td>
                <td> <?php echo get_the_title( $users_assigned_pack ); ?> </td>
            </tr>
            <tr>
                <td><?php esc_html_e( 'Start Date :', 'sk-core' ); ?></td>
                <td><?php echo sk_format_date( get_user_meta( $user->ID, 'product_pack_startdate', true ) ); ?>
                </td>
            </tr>
            <tr>
                <td><?php esc_html_e( 'End Date :', 'sk-core' ); ?></td>
                <td>
                    <?php
                    $product_pack_enddate = get_user_meta( $user->ID, 'product_pack_enddate', true );
                    if ( 'unlimited' === $product_pack_enddate ) {
                        printf( __( 'Lifetime package.', 'sk-core' ) );
                    } else {
                        echo sk_format_date( $product_pack_enddate );
                    }
                    ?>
                </td>
            </tr>
        <?php endif; ?>

        <tr>
            <td><?php esc_html_e( 'Allowed categories', 'sk-core' ); ?></td>
            <td>
                <?php
                $selected_cat = ! empty( $vendor_allowed_categories ) ? $vendor_allowed_categories : get_post_meta( $users_assigned_pack, '_vendor_allowed_categories', true );
                echo '<select multiple="multiple" data-placeholder=" ' . __( 'Select categories&hellip;', 'sk-core' ) . '" class="wc-enhanced-select" id="vendor_allowed_categories" name="vendor_allowed_categories[]" style="width: 350px;">';
                $r = array();
                $r['pad_counts']    = 1;
                $r['hierarchical']  = 1;
                $r['hide_empty']    = 0;
                $r['value']         = 'id';
                $r['orderby']       = 'name';
                $r['selected']      = ! empty( $selected_cat ) ? array_map( 'absint', $selected_cat ) : '';
                $r['parent']        = 0;

                $categories = get_terms( 'product_cat', $r );

                include_once WC()->plugin_path() . '/includes/walkers/class-product-cat-dropdown-walker.php';

                echo wc_walk_category_dropdown_tree( $categories, 0, $r );
                echo '</select>';
                ?>
                <p class="description"><?php esc_html_e( 'You can override allowed categories for this user. If empty then the predefined category for this pack will be selected', 'sk-core' ); ?></p>
            </td>
        </tr>

        <tr class="dps_assign_pack">
            <td><?php esc_html_e( 'Assign Subscription Pack', 'sk-core' ); ?></td>
            <td>
                <select name="_sk_user_assigned_sub_pack">
                    <option value="" <?php selected( $users_assigned_pack, '' ); ?>><?php esc_html_e( '-- Select a pack --', 'sk-core' ); ?></option>
                    <?php foreach ( $sub_packs as $pack ) : ?>
                        <option value="<?php echo $pack->ID; ?>" <?php selected( $users_assigned_pack, $pack->ID ); ?>><?php echo $pack->post_title; ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <?php
    }

    /**
     * Save meta fields
     *
     * @param int $user_id
     *
     * @return void
     * @throws Exception
     */
    public static function save_meta_fields( $user_id ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        if ( ! isset( $_POST['sk_enable_selling'] ) ) {
            return;
        }

        if ( ! isset( $_POST['_sk_user_assigned_sub_pack'] ) ) {
            return;
        }

        $pack_id = intval( $_POST['_sk_user_assigned_sub_pack'] );

        if ( ! $pack_id || empty( $pack_id ) ) {
            return;
        }

        if ( ! empty( $_POST['vendor_allowed_categories'] ) ) {
            $allowed_cat = wc_clean( $_POST['vendor_allowed_categories'] );
            update_user_meta( $user_id, 'vendor_allowed_categories', $allowed_cat );
        } else {
            delete_user_meta( $user_id, 'vendor_allowed_categories' );
        }

        if ( get_user_meta( $user_id, 'product_package_id', true ) == $pack_id ) {
            return;
        }

        // create a order for the subscription
        try {
            $order = new WC_Order();
            $order->add_product( wc_get_product( $pack_id ) );
            $order->set_created_via( 'sk' );
            $order->set_customer_id( $user_id );
            $order->calculate_totals();
            $order->set_status( 'completed' );
            $order->save();
        } catch ( Exception $e ) {
            return new WP_Error( 'sk-order-error', $e->getMessage() );
        }

        $pack_validity = get_post_meta( $pack_id, '_pack_validity', true );

        update_user_meta( $user_id, 'product_package_id', $pack_id );
        update_user_meta( $user_id, 'product_order_id', $order->get_id() );
        update_user_meta( $user_id, 'product_pack_startdate', sk_current_datetime()->format( 'Y-m-d H:i:s' ) );

        if ( absint( $pack_validity ) > 0 ) {
            update_user_meta( $user_id, 'product_pack_enddate', sk_current_datetime()->modify( "+$pack_validity days" )->format( 'Y-m-d H:i:s' ) );
        } else {
            update_user_meta( $user_id, 'product_pack_enddate', 'unlimited' );
        }

        update_user_meta( $user_id, 'can_post_product', 1 );

        do_action( 'sk_vendor_purchased_subscription', $user_id );
    }

    /**
     * Add Vendor Subscription filter option to order type filter dropdown.
     *
     *
     * @param array $filter_options Array of filter options
     *
     * @return array
     */
    public function add_vendor_subscription_filter_option( $filter_options ) {
        $filter_options['vendor_subscription'] = esc_html__( 'Vendor Subscription', 'sk-core' );
        return $filter_options;
    }

    /**
     * Filter orders by Vendor Subscription type.
     *
     *
     * @param array  $query_args  Original query arguments.
     * @param string $filter_type The selected filter type.
     *
     * @return array|null
     */
    public function filter_vendor_subscription_orders( $query_args, $filter_type ) {
        // Only handle the vendor_subscription filter type.
        if ( 'vendor_subscription' !== $filter_type ) {
            return $query_args;
        }

        // @codingStandardsIgnoreStart
        // Filter orders by vendor subscription meta - works for both HPOS and legacy.
        $query_args['meta_query'] = $query_args['meta_query'] ?? [];
        $query_args['meta_query']['relation'] = 'OR';
        $query_args['meta_query'][] = [
            'key'     => '_sk_vendor_subscription_order',
            'value'   => 'yes',
            'compare' => '=',
        ];
        $query_args['meta_query'][] = [
            'key'     => '_pack_validity',
            'compare' => 'EXISTS',
        ];
        // @codingStandardsIgnoreEnd

        return $query_args;
    }
}

new DPS_Admin();
