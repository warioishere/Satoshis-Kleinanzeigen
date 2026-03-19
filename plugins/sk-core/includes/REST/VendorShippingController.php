<?php

namespace SK\Core\REST;

use SK\Core\REST\SkBaseVendorController;
use SK\Core\Shipping\ShippingZone;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class VendorShippingController extends SkBaseVendorController {

    public function __construct() {
        $this->rest_base = 'shipping';
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_items' ],
                    'permission_callback' => [ $this, 'check_permission' ],
                    'args'                => $this->get_collection_params(),
                ],
                'schema' => [ $this, 'get_public_item_schema' ],
            ]
        );
        register_rest_route(
            $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)',
            [
                [
                    'methods'  => WP_REST_Server::READABLE,
                    'callback' => [ $this, 'get_item' ],
                    'permission_callback' => [ $this, 'get_item_permissions_check' ],
                    'args' => [
                        'context' => [
                            'default' => 'view',
                        ],
                        'id' => [
                            'description' => __( 'Unique identifier for the object.', 'sk' ),
                            'type'        => 'integer',
                        ],
                    ],
                ],
                [
                    'methods'  => WP_REST_Server::EDITABLE,
                    'callback' => [ $this, 'update_item' ],
                    'permission_callback' => [ $this, 'update_item_permissions_check' ],
                    'args' => [
                        'continent' => [
                            'description' => __( 'Continents.', 'sk' ),
                            'type'        => 'array',
                            'required'    => false,
                            'items'       => [
                                'type' => 'object',
                                'properties' => [
                                    'code' => [
                                        'description' => __( 'Continent code.', 'sk' ),
                                        'type'        => 'string',
                                    ],
                                ],
                            ],
                        ],
                        'country' => [
                            'description' => __( 'Countries.', 'sk' ),
                            'type'        => 'array',
                            'required'    => false,
                            'items'       => [
                                'type' => 'object',
                                'properties' => [
                                    'code' => [
                                        'description' => __( 'Country code.', 'sk' ),
                                        'type'        => 'string',
                                    ],
                                ],
                            ],
                        ],
                        'state' => [
                            'description' => __( 'States.', 'sk' ),
                            'type'        => 'array',
                            'required'    => false,
                            'items'       => [
                                'type' => 'object',
                                'properties' => [
                                    'code' => [
                                        'description' => __( 'State code.', 'sk' ),
                                        'type'        => 'string',
                                    ],
                                ],
                            ],
                        ],
                        'postcode' => [
                            'description' => __( 'Postcodes.', 'sk' ),
                            'type'        => 'string',
                            'required'    => false,
                        ],
                    ],
                ],
			]
        );
        register_rest_route(
            $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/methods',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_methods' ],
                    'permission_callback' => [ $this, 'check_permission' ],
                    'args'                => [
                        'context' => [
                            'default' => 'view',
                        ],
                        'id'      => [
                            'description' => __( 'Unique identifier for the object.', 'sk' ),
                            'type'        => 'integer',
                        ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'create_method' ],
                    'permission_callback' => [ $this, 'create_item_permissions_check' ],
                    'args'                => [
                        'method_id' => [
                            'description' => __( 'Shipping method.', 'sk' ),
                            'type'        => 'string',
                            'required'    => true,
                        ],
                        'settings'  => [
                            'description' => __( 'Shipping method settings.', 'sk' ),
                            'type'        => 'object',
                            'required'    => true,
                        ],
                    ],
                ],
			]
        );
        register_rest_route(
            $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/methods/(?P<instance_id>[\d]+)',
            [
                [
                    'methods'  => WP_REST_Server::EDITABLE,
                    'callback' => [ $this, 'update_method' ],
                    'permission_callback' => [ $this, 'update_item_permissions_check' ],
                    'args' => [
                        'method_id' => [
                            'description' => __( 'Shipping method.', 'sk' ),
                            'type'        => 'string',
                            'required'    => true,
                        ],
                        'settings' => [
                            'description' => __( 'Shipping method settings.', 'sk' ),
                            'type'        => 'object',
                            'required'    => true,
                        ],
                    ],
                ],
                [
                    'methods'  => WP_REST_Server::DELETABLE,
                    'callback' => [ $this, 'delete_method' ],
                    'permission_callback' => [ $this, 'delete_item_permissions_check' ],
                    'args' => [],
                ],
            ]
        );
        register_rest_route(
            $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/methods/(?P<instance_id>[\d]+)/status',
            [
                [
                    'methods'  => WP_REST_Server::EDITABLE,
                    'callback' => [ $this, 'update_method_status' ],
                    'permission_callback' => [ $this, 'update_item_permissions_check' ],
                    'args' => [
                        'enabled' => [
                            'description' => __( 'Shipping method is enabled.', 'sk' ),
                            'type'        => 'boolean',
                            'required'    => true,
                        ],
                    ],
                ],
            ]
        );
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/policy',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_policy' ],
                    'permission_callback' => [ $this, 'check_permission' ],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'update_policy' ],
                    'permission_callback' => [ $this, 'update_item_permissions_check' ],
                    'args'                => [
                        'processing_time' => [
                            'description' => esc_html__( 'The ID of Processing duration for shipping orders.', 'sk' ),
                            'type'        => 'string',
                            'enum'        => [ '', '1', '2', '3', '4', '5', '6', '7', '8', '9' ],
                            'context'     => [ 'view', 'edit' ],
                        ],
                        'shipping_policy' => [
                            'description' => esc_html__( 'Shipping policy details.', 'sk' ),
                            'type'        => 'string',
                        ],
                        'refund_policy' => [
                            'description' => esc_html__( 'Refund policy details.', 'sk' ),
                            'type'        => 'string',
                        ],
                    ],
                ],
                'schema' => [ $this, 'get_policy_schema' ],
            ]
        );
    }

    /**
     * Prepares the item for the REST response.
     *
     *
     * @param mixed           $item    WordPress representation of the item.
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function prepare_item_for_response( $item, $request ) {
        $data = [
            'shipping_methods'        => $item['shipping_methods'] ?? [],
            'available_methods'       => $item['available_methods'] ?? [],
            'formatted_zone_location' => $item['formatted_zone_location'] ?? '',
        ];

        // Prepare data for single item if `data` key found.
        if ( ! empty( $item['data'] ) ) {
            $data['locations'] = $item['locations'] ?? [];
            $data['data']      = [
                'id'             => $item['data']['id'] ?? 0,
                'zone_name'      => $item['data']['zone_name'] ?? '',
                'zone_order'     => $item['data']['zone_order'] ?? 0,
                'zone_locations' => $item['data']['zone_locations'] ?? [],
                'meta_data'      => $item['data']['meta_data'] ?? [],
            ];
        } else {
            $data['id']             = $item['id'] ?? 0;
            $data['zone_id']        = $item['zone_id'] ?? 0;
            $data['zone_name']      = $item['zone_name'] ?? '';
            $data['zone_order']     = $item['zone_order'] ?? 0;
            $data['zone_locations'] = $item['zone_locations'] ?? [];
            $data['meta_data']      = $item['meta_data'] ?? [];
        }

        /**
         * Filters for prepare shipping zone response.
         *
         *
         * @param array           $data
         * @param mixed           $item
         * @param WP_REST_Request $request
         */
        $data = apply_filters( 'sk_rest_prepare_shipping_zone_response', $data, $item, $request );

        return rest_ensure_response( $data );
    }

    /**
     * Get a collection of items.
     *
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function get_items( $request ) {
        $zones = ShippingZone::get_zones();
        // we are sorting by `zone_order` key of the zone.
        usort(
            $zones,
            function ( $zone1, $zone2 ) {
                // handle the `Locations not covered by your other zones`
                // here zone id = 0 is the zone "not covered by your other zones";.
                if ( 0 === $zone1['id'] ) {
                    return 1;
                } elseif ( 0 === $zone2['id'] ) {
                    return -1;
                }

                return $zone1['zone_order'] <=> $zone2['zone_order'];
            }
        );

        $prepared_zones = [];
        foreach ( $zones as $zone ) {
            $item             = $this->prepare_item_for_response( $zone, $request );
            $prepared_zones[] = $this->prepare_response_for_collection( $item );
        }

        return rest_ensure_response( $prepared_zones );
    }

    /**
     * Get an item.
     *
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function get_item( $request ) {
        $zone_id = $request->get_param( 'id' );
        $zone    = ShippingZone::get_zone( $zone_id );

		if ( empty( $zone ) ) {
			return new WP_Error(
				'sk_rest_shipping_zone_not_found',
				esc_html__( 'Shipping zone not found', 'sk' ),
				[ 'status' => 404 ]
			);
		}

        return $this->prepare_item_for_response( $zone, $request );
    }

    /**
     * Get shipping policy.
     *
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function get_policy() {
        $user_id = sk_get_current_user_id();

        return rest_ensure_response(
            [
                'processing_time' => get_user_meta( $user_id, '_dps_pt', true ),
                'shipping_policy' => get_user_meta( $user_id, '_dps_ship_policy', true ),
                'refund_policy'   => get_user_meta( $user_id, '_dps_refund_policy', true ),
            ]
        );
    }

    /**
     * Update an item.
     *
     *
     * @param WP_REST_Request $request Rest request object.
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function update_item( $request ) {
        $zone_id  = $request->get_param( 'id' );
        $location = [];

        if ( ! empty( $request['continent'] ) && is_array( $request['continent'] ) ) {
            $continent_array = [];

            foreach ( $request['continent'] as $continent ) {
                $continent_array[] = [
                    'code' => $continent['code'],
                    'type' => 'continent',
                ];
            }

            $location = array_merge( $location, $continent_array );
        }

        if ( ! empty( $request['country'] ) && is_array( $request['country'] ) ) {
            $country_array = [];

            foreach ( $request['country'] as $country ) {
                $country_array[] = [
                    'code' => $country['code'],
                    'type' => 'country',
                ];
            }

            $location = array_merge( $location, $country_array );
        }

        if ( ! empty( $request['state'] ) && is_array( $request['state'] ) ) {
            $state_array = [];

            foreach ( $request['state'] as $state ) {
                $state_array[] = [
                    'code' => $state['code'],
                    'type' => 'state',
                ];
            }

            $location = array_merge( $location, $state_array );
        }

        if ( ! empty( $request['postcode'] ) ) {
            $postcodes      = explode( ',', $request['postcode'] );
            $postcode_array = [];

            foreach ( $postcodes as $postcode ) {
                if ( false !== strpos( $postcode, '...' ) ) {
                    $postcode = implode( '...', array_map( 'trim', explode( '...', $postcode ) ) );
                }

                $postcode_array[] = [
                    'code' => trim( $postcode ),
                    'type' => 'postcode',
                ];
            }

            $location = array_merge( $location, $postcode_array );
        }

        $result = ShippingZone::save_location( $location, $zone_id );

        if ( ! $result ) {
            return new WP_Error( 'sk_rest_shipping_zone_update_failed', __( 'Failed to update shipping zone', 'sk' ), [ 'status' => 400 ] );
        }

        $zone          = ShippingZone::get_zone( $zone_id );
        $prepared_zone = $this->prepare_item_for_response( $zone, $request );

        return rest_ensure_response( $prepared_zone );
    }

    /**
     * Update shipping policy.
     *
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function update_policy( $request ) {
        $user_id = sk_get_current_user_id();

        update_user_meta( $user_id, '_dps_pt', sanitize_text_field( $request['processing_time'] ?? '' ) );
        update_user_meta( $user_id, '_dps_ship_policy', wp_kses_post( wp_unslash( $request['shipping_policy'] ?? '' ) ) );
        update_user_meta( $user_id, '_dps_refund_policy', wp_kses_post( wp_unslash( $request['refund_policy'] ?? '' ) ) );

        /**
         * Action hook to run after shipping policy updated.
         *
         *
         * @param int $user_id
         */
        do_action( 'sk_shipping_policy_updated', $user_id );

        return rest_ensure_response(
            [
                'success' => true,
                'message' => esc_html__( 'Policy settings updated successfully', 'sk' ),
            ]
        );
    }

    /**
     * Get shipping methods for a zone.
     *
     *
     * @param WP_REST_Request $request Rest request object.
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function get_methods( WP_REST_Request $request ) {
        $zone_id   = $request->get_param( 'id' );
        $vendor_id = sk_get_current_user_id();
        $methods   = ShippingZone::get_shipping_methods( $zone_id, $vendor_id );

        return rest_ensure_response( $methods );
    }

    /**
     * Get a shipping method for a zone.
     *
     *
     * @param WP_REST_Request $request Rest request object.
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function create_method( WP_REST_Request $request ) {
        $zone_id   = $request->get_param( 'id' );
        $method_id = $request->get_param( 'method_id' );
        $settings  = $request->get_param( 'settings' );

        $data = [
            'zone_id'   => $zone_id,
            'method_id' => $method_id,
            'settings'  => $settings,
        ];

        /**
         * Filter shipping method data before creation.
         *
         *
         * @param array           $data    Method data
         * @param WP_REST_Request $request Request object
         */
        $data = apply_filters( 'sk_shipping_zone_before_add_method', $data, $request );

        $result = ShippingZone::add_shipping_methods( $data );
        if ( is_wp_error( $result ) ) {
            return new WP_Error( 'sk_rest_shipping_method_create_failed', $result->get_error_message(), [ 'status' => 400 ] );
        }

	    /**
	     * Action hook fired after shipping method is created.
	     *
	     *
	     * @param array $data   Method data
	     * @param mixed $result Result of the operation
	     */
	    do_action( 'sk_shipping_zone_after_add_method', $data, $result );

        $methods  = ShippingZone::get_shipping_methods( $zone_id, sk_get_current_user_id() );
        $response = new WP_REST_Response( $methods, 201 );

        return rest_ensure_response( $response );
    }

    /**
     * Get update a shipping method for a zone.
     *
     *
     * @param WP_REST_Request $request Rest request object.
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function update_method( WP_REST_Request $request ) {
        $zone_id     = $request->get_param( 'id' );
        $instance_id = $request->get_param( 'instance_id' );
        $params      = $request->get_params();
        $params['zone_id']     = $zone_id;
        $result = ShippingZone::update_shipping_method( $params );

        if ( ! $result ) {
            return new WP_Error( 'sk_rest_shipping_method_update_failed', __( 'Failed to update shipping method.', 'sk' ), [ 'status' => 400 ] );
        }

        $methods = ShippingZone::get_shipping_methods( $zone_id, sk_get_current_user_id() );

        $method = array_filter(
            $methods,
            function ( $method ) use ( $instance_id ) {
                return $method['instance_id'] === $instance_id;
            }
        );

        return rest_ensure_response( $method );
    }

    /**
     * Delete a shipping method for a zone.
     *
     *
     * @param WP_REST_Request $request Rest request object.
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function delete_method( WP_REST_Request $request ) {
        $zone_id     = $request->get_param( 'id' );
        $instance_id = $request->get_param( 'instance_id' );

        $args = [
            'zone_id'     => $zone_id,
            'instance_id' => $instance_id,
        ];
        $result = ShippingZone::delete_shipping_methods( $args );

        if ( is_wp_error( $result ) ) {
            return new WP_Error( 'sk_rest_shipping_method_delete_failed', $result->get_error_message(), [ 'status' => 400 ] );
        }

        $response = rest_ensure_response(
            [
                'success' => true,
            ]
        );

        $response->set_status( 204 );

        return $response;
    }

    /**
     * Update a shipping method status for a zone.
     *
     *
     * @param WP_REST_Request $request Rest request object.
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function update_method_status( WP_REST_Request $request ) {
        $zone_id     = $request->get_param( 'id' );
        $instance_id = $request->get_param( 'instance_id' );
        $enabled     = $request->get_param( 'enabled' );

        $args = [
            'zone_id'     => $zone_id,
            'instance_id' => $instance_id,
            'checked'     => $enabled,
        ];

        /**
         * Filter method status data before update.
         *
         *
         * @param array           $args    Status update args
         * @param WP_REST_Request $request Request object
         */
        $args = apply_filters( 'sk_shipping_zone_before_toggle_method', $args, $request );

        $result = ShippingZone::toggle_shipping_method( $args );
        if ( is_wp_error( $result ) ) {
            return new WP_Error( 'sk_rest_shipping_method_status_update_failed', $result->get_error_message(), [ 'status' => 400 ] );
        }

        /**
         * Action hook fired after shipping method status is updated.
         *
         *
         * @param array $args   Status update args
         * @param mixed $result Result of the operation
         */
        do_action( 'sk_shipping_zone_after_toggle_method', $args, $result );

        return rest_ensure_response( [ 'success' => true ] );
    }

    /**
     * Check if a given request has access to get item.
     *
     * @param WP_REST_Request $request Rest request object.
     *
     * @return bool
     */
    public function get_item_permissions_check( $request ): bool {
        return $this->check_permission();
    }

    /**
     * Check if a given request has access to update item.
     *
     * @param WP_REST_Request $request Rest request object.
     *
     * @return bool
     */
    public function update_item_permissions_check( $request ): bool {
        return $this->get_item_permissions_check( $request );
    }

    /**
     * Check if a given request has access to create item.
     *
     * @param WP_REST_Request $request Rest request object.
     *
     * @return bool
     */
    public function create_item_permissions_check( $request ): bool {
        return $this->get_item_permissions_check( $request );
    }

    /**
     * Check if a given request has access to delete item.
     *
     * @param WP_REST_Request $request Rest request object.
     *
     * @return bool
     */
    public function delete_item_permissions_check( $request ): bool {
        return $this->get_item_permissions_check( $request );
    }

    /**
     * Get the query params for collections.
     *
     *
     * @return array
     */
    public function get_policy_schema(): array {
        return [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'shipping_policy',
            'type'       => 'object',
            'properties' => [
                'processing_time' => [
                    'description' => __( 'The ID of Processing duration for shipping orders.', 'sk' ),
                    'type'        => 'string',
                    'enum'        => [ '', '1', '2', '3', '4', '5', '6', '7', '8', '9' ],
                    'context'     => [ 'view', 'edit' ],
                ],
                'shipping_policy' => [
                    'description' => esc_html__( 'Shipping policy details.', 'sk' ),
                    'type'        => 'string',
                    'context'     => [ 'view', 'edit' ],
                ],
                'refund_policy' => [
                    'description' => esc_html__( 'Refund policy details.', 'sk' ),
                    'type'        => 'string',
                    'context'     => [ 'view', 'edit' ],
                ],
            ],
        ];
    }
}
