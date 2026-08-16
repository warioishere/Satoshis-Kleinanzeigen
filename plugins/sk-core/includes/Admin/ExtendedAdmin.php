<?php

namespace SK\Core\Admin;

/**
 * Class SK_Pro_Admin_Settings
 *
 * Class for load Admin functionality for Pro Version
 *
 *
 * 
 */
class ExtendedAdmin {

    /**
     * Constructor for the SK_Pro_Admin_Settings class
     *
     * Sets up all the appropriate hooks and actions
     * within our plugin.
     *
     * @return void
     */
    public function __construct() {
        add_action( 'sk_admin_menu', array( $this, 'load_admin_settings' ), 10, 2 );
        add_filter( 'sk_php_dashboard_pages', array( $this, 'register_php_dashboard_pages' ) );
        add_action( 'wp_ajax_create_pages', array( $this, 'create_default_pages' ) );
        \SK\Core\Admin\PhpDashboard\ModulesPage::register_ajax();
        add_filter( 'sk_settings_fields', array( $this, 'load_settings_sections_fields' ), 10, 2 );
        add_filter( 'sk_settings_general_vendor_store_options', array( $this, 'add_settings_general_vendor_store_options' ), 9 );
        add_filter( 'sk_settings_selling_option_vendor_capability', array( $this, 'add_settings_selling_option_vendor_capability' ), 9 );
        add_filter( 'sk_admin_settings_rearrange_map', array( $this, 'admin_settings_rearrange_map' ) );
        add_action( 'sk_render_admin_toolbar', array( $this, 'render_pro_admin_toolbar' ) );
        add_action( 'admin_menu', array( $this, 'remove_add_on_menu' ), 80 );
        add_action( 'admin_init', array( $this, 'handle_seller_bulk_action' ), 10 );

        add_action( 'wp_ajax_check_all_sk_pages_exists', [ $this, 'check_all_sk_pages_exists' ], 10, 2 );

        add_action( 'wp_trash_post', array( $this, 'sk_page_trash_handler' ) );
        add_action( 'untrash_post', array( $this, 'sk_page_untrash_handler' ), 10, 2 );
        add_action( 'delete_post', array( $this, 'sk_page_delete_handler' ) );
        add_action( 'trash_to_draft', array( $this, 'sk_draft_to_publish' ) );

    }

    /**
     * Load Admin Pro settings
     *
     *
     * @param  string $capability
     * @param  integer $menu_position
     *
     * @return void
     */
    public function load_admin_settings( $capability, $menu_position ) {
        remove_submenu_page( 'sk', 'sk-pro-features' );

        // Submenu items are now registered by PhpDashboard via the sk_php_dashboard_pages filter.
        add_submenu_page( '', __( 'Whats New', 'sk' ), __( 'Whats New', 'sk' ), $capability, 'whats-new-sk', array( $this, 'whats_new_page' ) );
    }

    /**
     * Register pro pages into the PHP dashboard.
     *
     * @param array $pages
     * @return array
     */
    public function register_php_dashboard_pages( $pages ) {
        $pages['announcements']  = new \SK\Core\Admin\PhpDashboard\AnnouncementsPage();
        $pages['modules']        = new \SK\Core\Admin\PhpDashboard\ModulesPage();
        $pages['store-reviews']  = new \SK\Core\Admin\PhpDashboard\StoreReviewsPage();
        $pages['subscriptions']  = new \SK\Core\Admin\PhpDashboard\SubscriptionsPage();
        $pages['advertisements'] = new \SK\Core\Admin\PhpDashboard\AdvertisementsPage();
        $pages['abuse-reports']  = new \SK\Core\Admin\PhpDashboard\AbuseReportsPage();
        $pages['feedback']       = new \SK\Core\Admin\PhpDashboard\FeedbackPage();

        // Only when the anti-fraud module is loaded — the page reads its classes.
        if ( \SK\Core\Admin\PhpDashboard\AntiFraudPage::is_available() ) {
            $pages['antifraud'] = new \SK\Core\Admin\PhpDashboard\AntiFraudPage();
        }

        return $pages;
    }

    /**
     * Remove addon submenu from sk admin menu
     *
     *
     * @return void
     */
    public function remove_add_on_menu() {
        remove_submenu_page( 'sk', 'sk-addons' );
    }

    /**
     * Add vendor store options in general settings
     *
     *
     * @param array $settings_fields
     *
     * @return array
     */
    public function add_settings_general_vendor_store_options( $settings_fields ) {
        $settings_fields['enable_tc_on_reg'] = [
            'name'    => 'enable_tc_on_reg',
            'label'   => __( 'Enable Terms and Condition', 'sk' ),
            'desc'    => __( 'Enable the terms & conditions checkbox on vendor registration form.', 'sk' ),
            'type'    => 'switcher',
            'default' => 'on',
            'tooltip' => __( 'Prompt terms and condition check for vendors when creating store on your site', 'sk' ),
            'is_lite' => false,
        ];
        $settings_fields['enable_single_seller_mode'] = [
            'name'    => 'enable_single_seller_mode',
            'label'   => __( 'Enable Single Seller Mode', 'sk' ),
            'desc'    => __( 'Enable single seller mode', 'sk' ),
            'type'    => 'switcher',
            'default' => 'off',
            'tooltip' => __( 'Restrict customers from buying from multiple vendors at a time.', 'sk' ),
            'is_lite' => false,
        ];

        return $settings_fields;
    }

    /**
     * Add vendor capability settings in selling option settings
     *
     *
     * @param array $settings_fields
     *
     * @return array
     */
    public function add_settings_selling_option_vendor_capability( $settings_fields ) {
        $settings_fields['product_status'] = [
            'name'    => 'product_status',
            'label'   => __( 'Product Status', 'sk' ),
            'desc'    => __( 'The status of a product when a vendor creates or updates it.', 'sk' ),
            'type'    => 'radio',
            'default' => 'pending',
            'tooltip' => __( 'The status of a product when a vendor creates or updates it.', 'sk' ),
            'options' => [
                'publish' => __( 'Published', 'sk' ),
                'pending' => __( 'Pending Review', 'sk' ),
            ],
            'is_lite' => false,
        ];

        $settings_fields['vendor_duplicate_product'] = array(
            'name'    => 'vendor_duplicate_product',
            'label'   => __( 'Duplicate Product', 'sk' ),
            'desc'    => __( 'Allow vendor to duplicate their product', 'sk' ),
            'type'    => 'switcher',
            'default' => 'on',
            'is_lite' => false,
        );

        $settings_fields['product_category_style'] = array(
            'name'    => 'product_category_style',
            'label'   => __( 'Product Category Selection', 'sk' ),
            'desc'    => __( 'Select a category type for products', 'sk' ),
            'type'    => 'radio',
            'default' => 'single',
            'options' => [
                'single'   => __( 'Single', 'sk' ),
                'multiple' => __( 'Multiple', 'sk' ),
            ],
            'is_lite' => false,
        );

        $settings_fields['product_vendors_can_create_tags'] = array(
            'name'    => 'product_vendors_can_create_tags',
            'label'   => __( 'Vendors Can Create Tags', 'sk' ),
            'desc'    => __( 'Allow vendors to create new product tags from vendor dashboard.', 'sk' ),
            'type'    => 'switcher',
            'default' => 'off',
            'is_lite' => false,
        );

        $settings_fields['add_new_attribute'] = array(
            'name'    => 'add_new_attribute',
            'label'   => __( 'Add New Attribute Values', 'sk' ),
            'desc'    => __( 'Allow vendors to add new values to predefined attribute', 'sk' ),
            'type'    => 'switcher',
            'default' => 'off',
            'is_lite' => false,
        );

        $settings_fields['hide_customer_info'] = [
            'name'    => 'hide_customer_info',
            'label'   => __( 'Hide Customer Info', 'sk' ),
            'desc'    => __( 'Hide customer information from order details of vendors', 'sk' ),
            'type'    => 'switcher',
            'default' => 'off',
            'tooltip' => __( 'It will hide customer information from the "General Details" section of the single order details page.', 'sk' ),
            'is_lite' => false,
        ];

        $settings_fields['seller_review_manage'] = array(
            'name'    => 'seller_review_manage',
            'label'   => __( 'Vendor Product Review Status Change', 'sk' ),
            'desc'    => __( 'Vendor can change product review status from vendor dashboard', 'sk' ),
            'type'    => 'switcher',
            'default' => 'on',
            'is_lite' => false,
        );

        return $settings_fields;
    }

    /**
     * Backward compatible settings option map
     *
     *
     * @param array $map
     *
     * @return array
     */
    public function admin_settings_rearrange_map( $map ) {
        return array_merge(
            $map, array(
                'seller_review_manage_sk_general' => array( 'seller_review_manage', 'sk_selling' ),
                'store_banner_width_sk_general'   => array( 'store_banner_width', 'sk_appearance' ),
                'store_banner_height_sk_general'  => array( 'store_banner_height', 'sk_appearance' ),
            )
        );
    }

    /**
     * Load all pro settings field
     *
     *
     * @param  array $settings_fields
     *
     * @return array
     */
    public function load_settings_sections_fields( $settings_fields, $sk_settings ) {
        $appearence_settings = array(
            'store_banner_width' => [
                'name'    => 'store_banner_width',
                'label'   => __( 'Store Banner Width', 'sk' ),
                'type'    => 'text',
                'default' => 625,
                'tooltip' => __( 'Choose the width for your Vendor\'s banner image to be displayed on Vendor store page.', 'sk' ),
            ],
            'store_banner_height' => [
                'name'    => 'store_banner_height',
                'label'   => __( 'Store Banner Height', 'sk' ),
                'type'    => 'text',
                'default' => 300,
                'tooltip' => __( 'Choose the height for your Vendor\'s banner image which is displayed on Vendor store page', 'sk' ),
            ],
        );

        $settings_fields = $sk_settings->add_settings_after(
            $settings_fields,
            'sk_appearance',
            'store_header_template',
            $appearence_settings
        );

        return $settings_fields;
    }

    /**
     * Load Report Scripts
     *
     *
     * @return void
     */
    public function common_scripts() {
        wp_enqueue_style( 'jquery-ui' );
        wp_enqueue_style( 'sk-select2-css' );

        wp_enqueue_script( 'jquery-ui-datepicker' );
        wp_enqueue_script( 'sk-select2-js' );
    }

    /**
     * Whats new page for sk pro
     *
     * @return void
     */
    public function whats_new_page() {
        include dirname( __FILE__ ) . '/Views/whats-new.php';
    }

    /**
     * Create default pages
     *
     *
     * @return void
     */
    public function create_default_pages() {
        if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'create_pages' ) {
            return wp_send_json_error( __( 'You don\'t have enough permission', 'sk', '403' ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return wp_send_json_error( __( 'You don\'t have enough permission', 'sk', '403' ) );
        }

        $page_created = get_option( 'sk_pages_created', false );
        $pages = array(
            array(
                'post_title' => __( 'Dashboard', 'sk' ),
                'slug'       => 'dashboard',
                'page_id'    => 'dashboard',
                'content'    => '[sk-dashboard]',
            ),
            array(
                'post_title' => __( 'Store List', 'sk' ),
                'slug'       => 'store-listing',
                'page_id'    => 'store_listing',
                'content'    => '[sk-stores]',
            ),
            array(
                'post_title' => __( 'My Orders', 'sk' ),
                'slug'       => 'my-orders',
                'page_id'    => 'my_orders',
                'content'    => '[sk-my-orders]',
            ),
        );

        $sk_pages = array();

        if ( ! $page_created ) {
            $old_pages = get_option( 'sk_pages', [] );

            foreach ( $pages as $page ) {
                if ( in_array( $page['page_id'], array_keys( $old_pages ), true ) ) {
                    $sk_pages[ $page['page_id'] ] = $old_pages[ $page['page_id'] ];
                    continue;
                }

                $page_id = wp_insert_post(
                    array(
                        'post_title'     => $page['post_title'],
                        'post_name'      => $page['slug'],
                        'post_content'   => $page['content'],
                        'post_status'    => 'publish',
                        'post_type'      => 'page',
                        'comment_status' => 'closed',
                    )
                );
                $sk_pages[ $page['page_id'] ] = $page_id;
            }

            update_option( 'sk_pages', $sk_pages );
            flush_rewrite_rules();
        } else {
            foreach ( $pages as $page ) {
                if ( ! $this->sk_page_exist( $page['slug'] ) && ! $this->sk_is_post_slug_exists( $page['slug'] ) ) {
                    $page_id = wp_insert_post(
                        array(
                            'post_title'     => $page['post_title'],
                            'post_name'      => $page['slug'],
                            'post_content'   => $page['content'],
                            'post_status'    => 'publish',
                            'post_type'      => 'page',
                            'comment_status' => 'closed',
                        )
                    );
                    $sk_pages[ $page['page_id'] ] = $page_id;
                    update_option( 'sk_pages', $sk_pages );
                }
            }

            flush_rewrite_rules();
        }

        update_option( 'sk_pages_created', 1 );
        wp_send_json_success(
            array(
                'message' => __( 'All the default pages has been created!', 'sk' ),
            ), 201
        );
        exit;
    }

    /**
     * Check a Donan shortcode  page exist or not
     *
     *
     * @param type $slug
     *
     * @return boolean
     */
    public function sk_page_exist( $slug ) {
        if ( ! $slug ) {
            return false;
        }

        $page_created = get_option( 'sk_pages_created', false );

        if ( ! $page_created ) {
            return false;
        }

        $page_list = get_option( 'sk_pages', '' );
        $slug      = str_replace( '-', '_', $slug );
        $page      = isset( $page_list[ $slug ] ) ? get_post( $page_list[ $slug ] ) : null;

        if ( $page === null ) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Render pro admin toolbar
     *
     *
     * @param obj $wp_admin_bar
     *
     * @return void
     */
    public function render_pro_admin_toolbar( $wp_admin_bar ) {
        $wp_admin_bar->remove_menu( 'sk-pro-features' );

        $wp_admin_bar->add_menu(
            array(
                'id'     => 'sk-sellers',
                'parent' => 'sk',
                'title'  => __( 'Vendors', 'sk' ),
                'href'   => admin_url( 'admin.php?page=sk&tab=vendors' ),
            )
        );

        $wp_admin_bar->add_menu(
            array(
                'id'     => 'sk-settings',
                'parent' => 'sk',
                'title'  => __( 'Settings', 'sk' ),
                'href'   => admin_url( 'admin.php?page=sk&tab=settings' ),
            )
        );
    }

    /**
     * Handle seller bulk action
     *
     *
     * @return void
     */
    public function handle_seller_bulk_action() {
        if ( ! isset( $_REQUEST['sk-seller-bulk-action'] ) ) {
            return;
        }

        if ( isset( $_REQUEST['action2'] ) && $_REQUEST['action2'] == 'delete' ) {
            $users = $_REQUEST['users'];

            if ( $users ) {
                foreach ( $users as $key => $user ) {
                    sk()->vendor->get( intval( $user ) )->delete();
                }
            }
        }

        $redirect_url = add_query_arg( array( 'page' => 'sk-sellers' ), admin_url( 'admin.php' ) );
        wp_redirect( $redirect_url );
        exit();
    }

    /**
     * Checks if all sk pages are created
     *
     *
     * @return void
     *
     * TODO: We need to check if all pages are consist of the required shortcode
     */
    public function check_all_sk_pages_exists() {
        if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'check_all_sk_pages_exists' ) {
            return wp_send_json_error( __( 'You don\'t have enough permission', 'sk', '403' ) );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return wp_send_json_error( __( 'You don\'t have enough permission', 'sk', '403' ) );
        }

        $all_pages_created = get_option( 'sk_pages_created', false );
        wp_send_json_success( [
            'all_pages_exists' => $all_pages_created
        ], 201 );
    }

    /**
     * Check post slug exits for sk pages
     *
     *
     * @param string $post_slug
     *
     * @return boolean
     */
    public function sk_is_post_slug_exists( $post_slug ) {
        if ( ! $post_slug ) {
            return false;
        }

        global $wpdb;

        $results = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT `post_name` FROM {$wpdb->prefix}posts WHERE `post_name` = %s", $post_slug
            ), ARRAY_A
        );

        if ( $results ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Update 'sk_pages' and 'sk_pages_created' options when pages are trashed
     *
     *
     * @param $page_id
     *
     * @return void
     */
    public function sk_page_trash_handler( $page_id ) {
        if ( 'page' !== get_post_type( $page_id ) ) {
            return;
        }

        $page_id = (int) $page_id;

        $selected_slug = $this->update_sk_page_options( $page_id );

        if ( empty( $selected_slug ) ) {
            return;
        }

        //track trashed pages to handle untrash later
        $sk_trashed_pages = get_option( 'sk_trashed_pages', [] );

        if ( ! isset( $sk_trashed_pages[ $selected_slug ] ) ) {
            $sk_trashed_pages[ $selected_slug ] = [];
        }

        $sk_trashed_pages[ $selected_slug ][] = $page_id;
        update_option( 'sk_trashed_pages', $sk_trashed_pages );
    }

    /**
     * Handle sk untrash page
     *
     *
     * @param $page_id
     * @param $previous_status
     *
     * @return void
     */
    public function sk_page_untrash_handler( $page_id, $previous_status ) {
        if ( 'page' !== get_post_type( $page_id ) ) {
            return;
        }

        $page_id = (int) $page_id;

        $selected_slug = $this->update_sk_trashed_page_options( $page_id );

        if ( empty( $selected_slug ) ) {
            return;
        }

        //check if a similar page already exists in published pages
        $sk_pages = get_option( 'sk_pages', [] );
        if ( ! isset( $sk_pages[ $selected_slug ] ) ) {//a similar page already doesn't exist, then we make use the restored page
            $sk_pages[ $selected_slug ] = $page_id;
            update_option( 'sk_pages', $sk_pages );

            if ( 3 === count( array_keys( $sk_pages ) ) ) { //if all the three pages(dashboard, my-order, store-list) are restored
                update_option( 'sk_pages_created', true );
            }

            //to use later in sk_draft_to_publish method
            update_option( 'sk_page_to_publish', $page_id . ',' . $previous_status );
        }
    }

    /**
     * To Restore a sk page in its previous status, say publish
     *
     *
     * @param $page
     */
    public function sk_draft_to_publish( $page ) {
        $option   = get_option( 'sk_page_to_publish', '' );
        $splitted = explode( ',', $option );

        if (
            2 !== count( $splitted ) ||
            $page->ID !== (int) $splitted[0] ||
            ! in_array( $splitted[1], array_keys( get_post_statuses() ), true )
        ) {
            return;
        }

        wp_update_post(
            [
                'ID'          => $page->ID,
                'post_status' => $splitted[1],
            ]
        );

        update_option( 'sk_page_to_publish', '' );
    }

    /**
     * Handle deletion of a sk page
     *
     *
     * @param $page_id
     *
     * @return void
     */
    public function sk_page_delete_handler( $page_id ) {
        if ( 'page' !== get_post_type( $page_id ) ) {
            return;
        }

        $page_id = (int) $page_id;

        if ( 'trash' === get_post_status( $page_id ) ) {
            $this->update_sk_trashed_page_options( $page_id );
        } else {
            $this->update_sk_page_options( $page_id );
        }
    }

    /**
     * Update the associated options
     *
     *
     * @param int $page_id
     *
     * @return string
     */
    private function update_sk_page_options( $page_id ) {
        $sk_pages   = get_option( 'sk_pages', [] );
        $selected_slug = '';

        foreach ( $sk_pages as $slug => $id ) {
            if ( (int) $id === $page_id ) {
                $selected_slug = $slug;
                break;
            }
        }

        if ( empty( $selected_slug ) ) {
            return $selected_slug;
        }

        unset( $sk_pages[ $selected_slug ] );
        update_option( 'sk_pages_created', false );
        update_option( 'sk_pages', $sk_pages );

        return $selected_slug;
    }

    /**
     * Update sk trashed pages option
     *
     *
     * @param int $page_id
     *
     * @return string
     */
    private function update_sk_trashed_page_options( $page_id ) {
        $sk_trashed_pages = get_option( 'sk_trashed_pages', [] );

        $selected_slug = '';

        foreach ( $sk_trashed_pages as $slug => $ids ) {
            $int_ids = array_map( 'intval', $ids );
            if ( in_array( $page_id, $int_ids, true ) ) {
                $selected_slug = $slug;
                break;
            }
        }

        if ( empty( $selected_slug ) ) {
            return $selected_slug;
        }

        $int_ids = array_filter(
            $sk_trashed_pages[ $selected_slug ],
            function ( $id ) use ( $page_id ) {
                return (int) $id !== $page_id;
            }
        );

        $sk_trashed_pages[ $selected_slug ] = $int_ids;
        update_option( 'sk_trashed_pages', $sk_trashed_pages );

        return $selected_slug;
    }

}
// End of SK\Pro\Admin\Admin class;
