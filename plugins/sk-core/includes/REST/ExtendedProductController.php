<?php

namespace SK\Core\REST;

use WP_Error;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use SK\Core\REST\ProductControllerV2;

/**
* Product Variation controller
*
*
*/
class ExtendedProductController extends ProductControllerV2 {

    /**
     * Register the routes for products.
     *
     *
     * @return void
     */
    public function register_routes() {
        parent::register_routes();

        register_rest_route(
            $this->namespace, '/' . $this->base . '/(?P<id>[\d]+)/duplicate', [
                'args' => [
                    'id' => [
                        'description' => __( 'Unique identifier for the object.', 'sk' ),
                        'type'        => 'integer',
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'duplicate_product' ],
                    'permission_callback' => [ $this, 'duplicate_product_permissions_check' ],
                    'args'                => [],
                ],
                'schema' => [ $this, 'get_item_schema' ],
            ]
        );

        register_rest_route(
            $this->namespace, '/' . $this->base . '/linked-products', [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_linked_products' ],
                    'permission_callback' => [ $this, 'get_product_permissions_check' ],
					'args'                => [
						'term' => [
							'description' => __( 'Product name', 'sk' ),
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
							'required'          => true,
						],
						'user_ids' => [
							'type'              => [ 'boolean', 'number', 'array' ],
							'validate_callback' => function ( $param ) {
								if ( is_bool( $param ) ) {
									return true;
								}
								if ( is_numeric( $param ) ) {
									return true;
								}
								if ( is_array( $param ) && ! empty( $param ) ) {
									foreach ( $param as $value ) {
										if ( ! is_numeric( $value ) ) {
											return new WP_Error( 400, __( 'Accepted parameter type are boolean, number or array of numbers.', 'sk' ) );
										}
									}
									return true;
								}
								return new WP_Error( 400, __( 'Accepted parameter type are boolean, number or array of numbers.', 'sk' ) );
							},
							'sanitize_callback' => function ( $param ) {
								if ( is_array( $param ) ) {
									return array_map( 'absint', $param );
								}
								if ( is_numeric( $param ) ) {
									return absint( $param );
								}
								return $param;
							},
							'description' => 'A boolean, number, or an array of numbers.',
						],

						'exclude' => [
							'description' => __( 'Excluded product id', 'sk' ),
							'type'              => 'number',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
							'required'          => false,
						],

						'include' => [
							'description' => __( 'Included product id', 'sk' ),
							'type'              => 'number',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
							'required'          => false,
						],

						'limit' => [
							'description' => __( 'Included product id', 'sk' ),
							'type'              => 'number',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
							'required'          => false,
						],
					],
                ],
                'schema' => [ $this, 'get_item_schema' ],
            ]
        );
    }

    /**
     * Returns linked products.
     *
     * @param \WP_REST_Request $request
     *
     * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response
     */
    public function get_linked_products( WP_REST_Request $request ) {
        $term     = $request->get_param( 'term' );
        $user_ids = $request->get_param( 'user_ids' );

        $products        = [];
        $product_objects = sk_ext()->product->get_linked_products(
            $term, $user_ids,
            $request->get_param( 'exclude' ),
            $request->get_param( 'include' ),
            $request->get_param( 'limit' )
        );

        foreach ( $product_objects as $product_object ) {
            $data = $product_object->get_data();
            unset( $data['meta_data'] );
            $products[] = $data;
        }

        return rest_ensure_response( apply_filters( 'sk_json_search_found_products', $products ) );
    }

    /**
     * Checks the permission for product duplication.
     *
     *
     * @return bool
     */
    public function duplicate_product_permissions_check() {
        if ( sk_get_option( 'vendor_duplicate_product', 'sk_selling', 'on' ) === 'off' ) {
            return false;
        }

        if ( ! sk_is_user_seller( sk_get_current_user_id() ) ) {
            return false;
        }

        if ( ! apply_filters( 'sk_vendor_can_duplicate_product', true ) ) {
            return false;
        }

        return true;
    }

    /**
     * Create a duplicate copy of a product.
     *
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function duplicate_product( \WP_REST_Request $request ) {
        $product_id = $request['id'];
        $duplicate_product = sk_ext()->products->duplicate_product( $product_id );

        if ( is_wp_error( $duplicate_product ) ) {
            return rest_ensure_response( $duplicate_product );
        }

        return $this->prepare_data_for_response( $duplicate_product, $request );
    }
}
