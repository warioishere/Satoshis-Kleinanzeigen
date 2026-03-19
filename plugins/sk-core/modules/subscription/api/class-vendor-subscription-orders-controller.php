<?php

use SK\Modules\Subscription\Helper;

/**
 * Vendor Subscription Orders API Controller.
 *
 *
 */
class SK_REST_Vendor_Subscription_Orders_Controller extends SK_REST_Vendor_Subscription_Controller {

    /**
     * Endpoint Namespace.
     *
     * @var string
     */
    protected $namespace = 'sk/v1';

    /**
     * Route Name.
     *
     * @var string
     */
    protected $base = 'vendor-subscription/orders';

    /**
     * Register Routes Related with Vendor Subscription Orders.
     *
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace, '/' . $this->base . '/(?P<id>[\d]+)/', [
                'args' => [
                    'id' => [
                        'description' => __( 'Vendor id', 'sk' ),
                        'type'        => 'integer',
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_items' ],
                    'permission_callback' => [ $this, 'check_permission' ],
                    'args'                => $this->get_collection_params(),
                ],
            ]
        );
    }

    /**
     * Get Vendor Subscription Orders.
     *
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function get_items( $request ) {
        $vendor_id = $this->get_vendor_id( $request );
        $params    = $request->get_params();

        // Fetch subscription orders.
        $orders = Helper::get_paginated_subscription_orders_by_vendor_id( $vendor_id, $params['page'], $params['per_page'] );

        if ( empty( $orders ) || ! isset( $orders['orders'] ) ) {
            return new WP_Error( 'no_orders', __( 'No subscription orders found.', 'sk' ), [ 'status' => 404 ] );
        }

        $data = [];
        foreach ( $orders['orders'] as $order ) {
            if ( ! $order instanceof WC_Order ) {
                continue;
            }

            $item_data = $this->prepare_item_for_response( $order, $request );
            $data[]    = $this->prepare_response_for_collection( $item_data );
        }

        $response = rest_ensure_response( $data );

        return $this->format_collection_response( $response, $request, $orders['total_orders'] );
    }

    /**
     * Prepare Item for REST Response.
     *
     *
     * @param mixed           $item    WordPress representation of the item.
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response $response
     */
    public function prepare_item_for_response( $item, $request ): WP_REST_Response {
        $fields = $this->get_fields_for_response( $request );
        $data   = [];

        if ( in_array( 'id', $fields, true ) ) {
            $data['id'] = absint( $item->get_id() );
        }

        if ( in_array( 'status', $fields, true ) ) {
            $data['status'] = $item->get_status();
        }

        if ( in_array( 'total', $fields, true ) ) {
            $data['total'] = $item->get_total();
        }

        if ( in_array( 'date_created', $fields, true ) ) {
            $data['date_created'] = wc_rest_prepare_date_response( $item->get_date_created(), false );
        }

        if ( in_array( 'date_created_gmt', $fields, true ) ) {
            $data['date_created_gmt'] = wc_rest_prepare_date_response( $item->get_date_created() );
        }

        if ( in_array( 'actions', $fields, true ) ) {
            $data['actions'] = wc_get_account_orders_actions( $item );
        }

        $context  = ! empty( $request['context'] ) ? $request['context'] : 'view';
        $data     = $this->filter_response_by_context( $data, $context );
        $data     = $this->add_additional_fields_to_object( $data, $request );

        // Wrap the data in a response object.
        $response = rest_ensure_response( $data );

        /**
         * Filter vendor subscription order object returned from the REST API.
         *
         * @param WP_REST_Response $response The response object.
         * @param WC_Order         $item     Order object used to create response.
         * @param WP_REST_Request  $request  Request object.
         */
        return apply_filters( 'sk_rest_prepare_vendor_subscription_order', $response, $item, $request );
    }

    /**
     * Get Item Schema.
     *
     *
     * @return array
     */
    public function get_item_schema(): array {
        $schema = [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'vendor_subscription_orders',
            'type'       => 'object',
            'properties' => [
                'id'               => [
                    'description' => __( 'The unique identifier of the item.', 'sk' ),
                    'type'        => 'integer',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                    'required'    => true,
                ],
                'status'           => [
                    'description' => __( 'The status of the item.', 'sk' ),
                    'type'        => 'string',
                    'format'      => 'text',
                    'enum'        => [ 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' ],
                    'context'     => [ 'view', 'edit' ],
                    'readonly'    => false,
                    'required'    => true,
                ],
                'total'            => [
                    'description' => __( 'The total amount of the item.', 'sk' ),
                    'type'        => 'number',
                    'context'     => [ 'view', 'edit' ],
                    'readonly'    => false,
                    'required'    => true,
                ],
                'date_created'     => [
                    'description' => __( 'Creation date of the item.', 'sk' ),
                    'type'        => 'string',
                    'format'      => 'date-time',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                    'required'    => true,
                ],
                'date_created_gmt' => [
                    'description' => __( 'Creation date in GMT of the item.', 'sk' ),
                    'type'        => 'string',
                    'format'      => 'date-time',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                    'required'    => true,
                ],
                'actions'          => [
                    'description'          => __( 'Available actions for the item.', 'sk' ),
                    'type'                 => 'object',
                    'context'              => [ 'view' ],
                    'additionalProperties' => [
                        'type'       => 'object',
                        'properties' => [
                            'url' => [
                                'description' => __( 'URL for the action.', 'sk' ),
                                'type'        => 'string',
                                'format'      => 'uri',
                                'context'     => [ 'view' ],
                            ],
                            'name' => [
                                'description' => __( 'Name of the action.', 'sk' ),
                                'type'        => 'string',
                                'context'     => [ 'view' ],
                            ],
                            'aria-label' => [
                                'description' => __( 'ARIA label for accessibility.', 'sk' ),
                                'type'        => 'string',
                                'context'     => [ 'view' ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return $schema;
    }
}
