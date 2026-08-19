<?php

namespace SK\Core\REST;

use WC_Data;

/**
 * API_Registrar class
 */
class Manager {

    /**
     * Class dir and class name mapping
     *
     * @var array
     */
    protected $class_map;

    /**
     * Constructor
     */
    public function __construct() {
        if ( ! class_exists( 'WP_REST_Server' ) ) {
            return;
        }

        // Init REST API routes.
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ), 10 );
        add_filter( 'woocommerce_rest_prepare_product_object', array( $this, 'prepare_product_response' ) );
        // Send email to admin on adding a new product
        add_action( 'sk_rest_insert_product_object', array( $this, 'on_sk_rest_insert_product' ), 10, 3 );
        add_filter( 'sk_vendor_to_array', [ $this, 'filter_vendor_info_visibility' ] );
        add_filter( 'sk_vendor_to_array', [ $this, 'filter_payment_response' ] );

    }

    /**
     * Register REST API routes.
     *
     */
    public function register_rest_routes() {
        // get rest api class map
        $this->get_rest_api_class_map();

        foreach ( $this->class_map as $file_name => $controller ) {
            // return if file not exists
            if ( ! file_exists( $file_name ) ) {
                continue;
            }

            // include file
            require_once $file_name;

            // check if class exists
            if ( ! class_exists( $controller ) ) {
                continue;
            }

            // get controller object
            $object = new $controller();
            // check if object is instance of WP_REST_Controller
            if ( ! is_a( $object, 'WP_REST_Controller' ) ) {
                continue;
            }

            // register routes
            $object->register_routes();
        }
    }

    /**
     * Prepare object for product response
     *
     *
     * @return void
     */
    public function prepare_product_response( $response ) {
        $data = $response->get_data();
        $author_id = get_post_field( 'post_author', $data['id'] );

        $store = sk()->vendor->get( $author_id );

        $data['store'] = array(
            'id'        => $store->get_id(),
            'name'      => $store->get_name(),
            'shop_name' => $store->get_shop_name(),
            'url'       => $store->get_shop_url(),
            'address'   => $store->get_address(),
            'avatar'    => $store->get_avatar(),
            'banner'    => $store->get_banner(),
        );

        $response->set_data( $data );
        return $response;
    }

    /**
     * Hide vendor contact info from API response based on admin settings.
     *
     * @param array $data
     *
     * @return array
     */
    public function filter_vendor_info_visibility( $data ) {
        $vendor_id = ! empty( $data['id'] ) ? absint( $data['id'] ) : 0;

        if ( current_user_can( 'manage_woocommerce' ) || $vendor_id === absint( sk_get_current_user_id() ) ) {
            return $data;
        }

        if ( sk_is_vendor_info_hidden( 'address' ) ) {
            unset( $data['address'] );
        }

        if ( sk_is_vendor_info_hidden( 'phone' ) ) {
            unset( $data['phone'] );
        }

        if ( sk_is_vendor_info_hidden( 'email' ) || empty( $data['show_email'] ) ) {
            unset( $data['email'] );
        }

        return $data;
    }

    /**
     * Send email to admin on adding a new product
     *
     * @param WC_Data $data
     * @param  \WP_REST_Request $request
     * @param  Boolean $creating
     *
     * @return void
     */
    public function on_sk_rest_insert_product( $data, $request, $creating ) {
        // if not creating, meaning product is updating. So return early
        if ( ! $creating ) {
            return;
        }

        do_action( 'sk_new_product_added', $data->get_id(), $request->get_params() );
    }

    /**
     * Make payment field hidden in api response for other vendor
     *
     * @param array $data
     *
     *
     * @return array
     */
    public function filter_payment_response( $data ) {
        if ( current_user_can( 'manage_woocommerce' ) ) {
            return $data;
        }

        $vendor_id = ! empty( $data['id'] ) ? absint( $data['id'] ) : 0;

        if ( $vendor_id !== sk_get_current_user_id() ) {
            $data['payment'] = '******';
        }

        return $data;
    }

    /**
     * Generate Rest API class map
     *
     *
     * @return void
     */
    private function get_rest_api_class_map() {
        if ( ! empty( $this->class_map ) ) {
            return;
        }
        $this->class_map = apply_filters(
            'sk_rest_api_class_map', array(
                SK_CORE_DIR . '/includes/REST/AdminReportController.php'             => 'SK\Core\REST\AdminReportController',
                SK_CORE_DIR . '/includes/REST/AdminDashboardController.php'          => 'SK\Core\REST\AdminDashboardController',
                SK_CORE_DIR . '/includes/REST/AdminMiscController.php'               => 'SK\Core\REST\AdminMiscController',
                SK_CORE_DIR . '/includes/REST/AdminSetupGuideController.php'         => 'SK\Core\REST\AdminSetupGuideController',
                SK_CORE_DIR . '/includes/REST/StoreController.php'                   => '\SK\Core\REST\StoreController',
                SK_CORE_DIR . '/includes/REST/ProductController.php'                 => '\SK\Core\REST\ProductController',
                SK_CORE_DIR . '/includes/REST/ProductControllerV2.php'               => '\SK\Core\REST\ProductControllerV2',
                SK_CORE_DIR . '/includes/REST/StoreSettingController.php'            => '\SK\Core\REST\StoreSettingController',
                SK_CORE_DIR . '/includes/REST/StoreSettingControllerV2.php'          => '\SK\Core\REST\StoreSettingControllerV2',
                SK_CORE_DIR . '/includes/REST/VendorDashboardController.php'         => '\SK\Core\REST\VendorDashboardController',
                SK_CORE_DIR . '/includes/REST/ProductBlockController.php'            => '\SK\Core\REST\ProductBlockController',
                SK_CORE_DIR . '/includes/REST/CustomersController.php'               => '\SK\Core\REST\CustomersController',
                SK_CORE_DIR . '/includes/REST/SkDataCountriesController.php'      => '\SK\Core\REST\SkDataCountriesController',
                SK_CORE_DIR . '/includes/REST/SkDataContinentsController.php'     => '\SK\Core\REST\SkDataContinentsController',
                SK_CORE_DIR . '/includes/REST/VendorProductCategoriesController.php' => '\SK\Core\REST\VendorProductCategoriesController',
                SK_CORE_DIR . '/includes/REST/ExportController.php'                => '\SK\Core\REST\ExportController',
            )
        );
    }
}
