<?php

namespace SK\Core\REST;

use WP_REST_Terms_Controller;
use WP_REST_Server;
use WP_Error;

class StoreCategoryController extends WP_REST_Terms_Controller {

    /**
     * Endpoint namespace.
     *
     *
     * @var string
     */
    protected $namespace = 'sk/v1';

    /**
     * Route name
     *
     *
     * @var string
     */
    protected $base = 'store-categories';

    /**
     * Taxonomy key.
     *
     *
     * @var string
     */
    protected $taxonomy = 'store_category';

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        parent::__construct( $this->taxonomy );
        $this->namespace = 'sk/v1';
        $this->rest_base = $this->base;
    }

    /**
     * Register routes
     *
     *
     * @return void
     */
    public function register_routes() {
        parent::register_routes();

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/default-category',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_default_category' ),
                    'permission_callback' => array( $this, 'get_items_permissions_check' ),
                    'args'                => $this->get_collection_params(),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'set_default_category' ),
                    'permission_callback' => array( $this, 'create_item_permissions_check' ),
                    'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
                ),
                'schema' => array( $this, 'get_public_item_schema' ),
            )
        );
    }

    /**
     * Get Categories
     *
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function get_items( $request ) {
        $response = parent::get_items( $request );

        $response->header( 'X-WP-Store-Category-Type', sk_get_option( 'store_category_type', 'sk_general', 'none' ) );
        $response->header( 'X-WP-Default-Category', sk_get_default_store_category_id() );

        return $response;
    }

    /**
     * Get default store category
     *
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function get_default_category( $request ) {
        $default_category = sk_get_default_store_category_id();

        $term = $this->get_term( $default_category );

        $response = $this->prepare_item_for_response( $term, $request );

        return rest_ensure_response( $response );
    }

    /**
     * Set default store category
     *
     *
     * @param WP_REST_Request $request
     *
     * @return mixed
     */
    public function set_default_category( $request ) {
        $term_id = $request->get_param( 'id' );

        if ( empty( $term_id ) ) {
            return new WP_Error( 'missing_param', __( 'Missing param id', 'sk-core' ), array( 'status' => 400 ) );
        }

        $term = $this->get_term( $term_id );

        if ( is_wp_error( $term ) ) {
            return $term;
        }

        sk_set_default_store_category_id( $term->term_id );

        $response = $this->prepare_item_for_response( $term, $request );

        return rest_ensure_response( $response );
    }
}

