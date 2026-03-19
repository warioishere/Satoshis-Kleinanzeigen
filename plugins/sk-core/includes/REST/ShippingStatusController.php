<?php

namespace SK\Core\REST;

use stdClass;
use WC_Order_Item_Product;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Shipping status api controller.
 *
 */
class ShippingStatusController extends WP_REST_Controller {

    protected $namespace = 'sk/v1';

    protected $rest_base = 'shipping-status';

    /**
     * Register the routes for the objects of the controller.
     *
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace, '/' . $this->rest_base, [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_shipping_status' ],
                    'permission_callback' => [ $this, 'get_shipping_status_permissions_check' ],
                ],
            ]
        );
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/orders/(?P<order_id>[\d]+)',
            [
                'args'   => [
                    'order_id' => [
                        'description' => __( 'Unique identifier for the order.', 'sk' ),
                        'type'        => 'integer',
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_items_by_order' ],
                    'args'                => $this->get_collection_params(),
                    'permission_callback' => [ $this, 'get_items_permissions_check' ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'create_item_by_order' ],
                    'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
                    'permission_callback' => [ $this, 'create_item_permissions_check' ],
                ],
                'schema' => [ $this, 'get_public_item_schema' ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/orders/(?P<order_id>[\d]+)/shipment/(?P<id>[\d]+)',
            [
                'args'   => [
                    'order_id' => [
                        'description' => __( 'Unique identifier for the order.', 'sk' ),
                        'type'        => 'integer',
                    ],
                    'id' => [
                        'description' => __( 'Unique identifier for the shipment.', 'sk' ),
                        'type'        => 'integer',
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_item' ],
                    'permission_callback' => [ $this, 'get_item_permissions_check' ],
                    'args'                => [
                        'context' => [
                            'default' => 'view',
                        ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'update_item' ],
                    'permission_callback' => [ $this, 'update_item_permissions_check' ],
                    'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'delete_item' ],
                    'permission_callback' => [ $this, 'delete_item_permissions_check' ],
                    'args'                => [
                        'force' => [
                            'default' => false,
                        ],
                    ],
                ],
            ]
        );
    }

    /**
     * Retrieves shipping status.
     *
     *
     * @param WP_REST_Request $request Rest request.
     *
     * @return WP_Error|WP_REST_Response
     */
    public function get_shipping_status( WP_REST_Request $request ) {
        $shipping_status               = sk_ext()->shipment;
        $shipping_status_enabled       = (
            $shipping_status->wc_shipping_enabled
            && 'on' === $shipping_status->enabled
            && 'sell_digital' !== sk_ext()->digital_product->get_selling_product_type()
        );
        $shipping_status_settings_data = get_option( 'sk_shipping_status_setting', [] );
        $shipping_status_list          = $shipping_status_settings_data['shipping_status_list'] ?? [];
        $shipping_status_list          = array_map(
            function ( $status ) {

                $status['value'] = apply_filters( 'sk_pro_shipping_status', $status['value'] );

                return $status;
            },
            $shipping_status_list
        );
        $shipping_providers = sk_get_shipping_tracking_providers_list();

        $data = [
            'enabled'     => $shipping_status_enabled,
            'status_list' => $shipping_status_list,
            'providers'   => $shipping_providers,
        ];

        return rest_ensure_response( $data );
    }

    /**
     * Retrieves a list of items.
     *
     *
     * @param WP_REST_Request $request Rest request.
     *
     * @return WP_Error|WP_REST_Response
     */
    public function get_items_by_order( $request ) {
        $order_id = $request->get_param( 'order_id' );

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return new WP_Error( 'sk_pro_rest_invalid_order_id', esc_html__( 'Invalid order id.', 'sk' ), [ 'status' => 404 ] );
        }

        $items = sk_ext()->shipment->get_shipping_tracking_info( $order->get_id() );

        $data = [];
        $total = count( $items );

        foreach ( $items as $item ) {
            $item_data = $this->prepare_item_for_response( $item, $request );
            $data[]    = $this->prepare_response_for_collection( $item_data );
        }

        $response = rest_ensure_response( $data );

        $response->header( 'X-WP-Total', $total );

        return $response;
    }

    /**
     * Create a shipping status.
     *
     *
     * @param WP_REST_Request $request Rest Request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function create_item_by_order( $request ) {
        $order_id = $request->get_param( 'order_id' );

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return new WP_Error( 'sk_pro_rest_invalid_order_id', esc_html__( 'Invalid order id.', 'sk' ), [ 'status' => 404 ] );
        }

        try {
            $item_id = sk_ext()->shipment->create( $order->get_id(), $request->get_params() );
        } catch ( \Exception $exception ) {
            return new WP_Error( 'sk_pro_rest_shipping_status_creation_error', $exception->getMessage(), [ 'status' => 400 ] );
        }

        $item     = sk_ext()->shipment->get_shipping_tracking_info( $item_id, 'shipment_item' );
        $response = rest_ensure_response( $this->prepare_item_for_response( $item, $request ) );

        $response->set_status( 201 );

        return $response;
    }

    /**
     * Retrieves a shipping status.
     *
     *
     * @param WP_REST_Request $request Rest request.
     *
     * @return WP_Error|WP_REST_Response
     */
    public function get_item( $request ) {
        $order_id = $request->get_param( 'order_id' );
        $id       = $request->get_param( 'id' );
        $order    = wc_get_order( $order_id );

        if ( ! $order ) {
            return new WP_Error( 'sk_pro_rest_invalid_order_id', esc_html__( 'Invalid order id.', 'sk' ), [ 'status' => 404 ] );
        }

        $item = sk_ext()->shipment->get_shipping_tracking_info( $id, 'shipment_item' );

        return rest_ensure_response( $this->prepare_item_for_response( $item, $request ) );
    }

    /**
     * Updates a shipping status.
     *
     *
     * @param WP_REST_Request $request Rest request.
     *
     * @return WP_Error|WP_REST_Response
     */
    public function update_item( $request ) {
        $order_id = $request->get_param( 'order_id' );
        $id       = $request->get_param( 'id' );
        $order    = wc_get_order( $order_id );

        if ( ! $order ) {
            return new WP_Error(
                'sk_pro_rest_invalid_order_id', esc_html__( 'Invalid order id.', 'sk' ),
                [ 'status' => 404 ]
            );
        }

        $old_item = sk_ext()->shipment->get_shipping_tracking_info( $id, 'shipment_item' );

        if ( empty( $old_item ) ) {
            return new WP_Error(
                'sk_pro_rest_invalid_shipment_id', esc_html__( 'Invalid shipment id.', 'sk' ),
                [ 'status' => 404 ]
            );
        }

        $data = $request->get_params();

        if ( empty( $data['shipment_comments'] ) ) {
            $data['shipment_comments'] = '';
        }

        if ( empty( $data['shipping_provider'] ) ) {
            $data['shipping_provider'] = $old_item->provider;
        }

        if ( empty( $data['shipping_number'] ) ) {
            $data['shipping_number'] = $old_item->number;
        }

        if ( empty( $data['shipped_date'] ) ) {
            $data['shipped_date'] = $old_item->date;
        }

        if ( empty( $data['shipped_status'] ) ) {
            $data['shipped_status'] = $old_item->shipping_status;
        }

        if ( empty( $data['is_notify'] ) ) {
            $data['is_notify'] = 'off';
        }

        if ( empty( $data['other_provider'] ) ) {
            $data['other_provider'] = $old_item->provider_label;
        }

        if ( empty( $data['other_p_url'] ) ) {
            $data['other_p_url'] = $old_item->provider_url;
        }

        $data['shipped_status_date']    = $data['shipped_date'];
        $data['tracking_status_number'] = $data['shipping_number'];
        $data['status_other_provider']  = $data['other_provider'];
        $data['status_other_p_url']     = $data['other_p_url'];

        try {
            sk_ext()->shipment->update( $id, $order_id, $data );
        } catch ( \Exception $exception ) {
            return new WP_Error( 'sk_pro_rest_shipping_status_update_error', $exception->getMessage() );
        }

        $item = sk_ext()->shipment->get_shipping_tracking_info( $id, 'shipment_item' );

        return rest_ensure_response( $this->prepare_item_for_response( $item, $request ) );
    }

    /**
     * Delete Shipping status.
     *
     *
     * @param WP_REST_Request $request Rest Request.
     *
     * @return WP_Error
     */
    public function delete_item( $request ) {
        $order_id = $request->get_param( 'order_id' );
        $id       = $request->get_param( 'id' );
        $order    = wc_get_order( $order_id );

        if ( ! $order ) {
            return new WP_Error(
                'sk_pro_rest_invalid_order_id', esc_html__( 'Invalid order id.', 'sk' ),
                [ 'status' => 404 ]
            );
        }

        return new WP_Error(
            'sk_pro_rest_cannot_delete', esc_html__( 'Shipping status tracking can not be deleted.', 'sk' ),
            [ 'status' => 400 ]
        );
    }

    /**
     * Checks if a given request has permission to read shipping status.
     *
     *
     * @param WP_REST_Request $request Rest Request.
     *
     * @return true|WP_Error
     */
    public function get_shipping_status_permissions_check( $request ) {
        if ( ! current_user_can( 'read' ) ) {
            return new WP_Error( 'sk_pro_rest_permission_error', esc_html__( 'You dont have permission to perform current action.', 'sk' ) );
        }

        return true;
    }

    /**
     * Checks if a given request has permission to read shipping status list.
     *
     *
     * @param WP_REST_Request $request
     *
     * @return true|WP_Error
     */
    public function get_items_permissions_check( $request ) {
        try {
            if (
                ( current_user_can( 'skdar' ) && sk_is_seller_has_order( sk_get_current_user_id(), $request->get_param( 'order_id' ) ) )
                || ( get_current_user_id() === wc_get_order( $request->get_param( 'order_id' ) )->get_customer_id() )
            ) {
                return true;
            }
        } catch ( \Exception $e ) {
            return new WP_Error( 'sk_pro_rest_invalid_order_id', esc_html__( 'Invalid order id.', 'sk' ), [ 'status' => 404 ] );
        }

        return new WP_Error( 'sk_pro_rest_permission_error', esc_html__( 'You dont have permission to perform current action.', 'sk' ) );
    }

    /**
     * Checks if a given request has permission to create shipping status.
     *
     *
     * @param WP_REST_Request $request
     *
     * @return true|WP_Error
     */
    public function create_item_permissions_check( $request ) {
        return $this->get_items_permissions_check( $request );
    }

    /**
     * Checks if a given request has permission to get shipping status.
     *
     *
     * @param WP_REST_Request $request
     *
     * @return true|WP_Error
     */
    public function get_item_permissions_check( $request ) {
        return $this->get_items_permissions_check( $request );
    }

    /**
     * Checks if a given request has permission to update shipping status.
     *
     *
     * @param WP_REST_Request $request
     *
     * @return true|WP_Error
     */
    public function update_item_permissions_check( $request ) {
        return $this->create_item_permissions_check( $request );
    }

    /**
     * Checks if a given request has permission to delete shipping status.
     *
     *
     * @param WP_REST_Request $request
     *
     * @return true|WP_Error
     */
    public function delete_item_permissions_check( $request ) {
        return $this->create_item_permissions_check( $request );
    }


    /**
     * Prepare the item for the REST response.
     *
     * @param mixed           $item WordPress representation of the item.
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response $response
     */
    public function prepare_item_for_response( $item, $request ) {
        $order_id    = absint( $item->order_id );
        $shipment_id = absint( $item->id );
        $comments    = get_comments(
            [
                'post_id' => $order_id,
                'parent'  => $shipment_id,
            ]
        );
        $comments    = array_map(
            function ( $comment ) {
                return [
                    'id'      => absint( $comment->comment_ID ),
                    'content' => $comment->comment_content,
                ];
            },
            $comments
        );
        $item_qty    = json_decode( $item->item_qty, true );
        $items       = [];

        foreach ( $item_qty as $line_item_id => $line_item_qty ) {
            $line_item_product = new WC_Order_Item_Product( $line_item_id );
            $line_item_product->set_quantity( $line_item_qty );
            $data = $line_item_product->get_data();
            unset( $data['meta_data'] );
            $items[ $line_item_id ] = $data;
            unset( $line_item_product, $data );
        }

        try {
            $shipped_date = sk_current_datetime()->modify( $item->date );
            $shipped_date = $shipped_date->format( 'c' );
        } catch ( \Exception $e ) {
            $shipped_date = $item->date;
        }

        $data = [
            'id'                      => $shipment_id,
            'order_id'                => $order_id,
            'shipping_provider'       => $item->provider,
            'shipping_provider_label' => $item->provider_label,
            'shipped_status'          => $item->shipping_status,
            'shipping_status_label'   => $item->status_label,
            'shipment_description'    => $comments,
            'shipping_number'         => $item->number,
            'shipped_date'            => $shipped_date,
            'is_notify'               => $item->is_notify,
            'item_id'                 => json_decode( $item->item_id, true ),
            'item_qty'                => $item_qty,
            'other_provider'          => $item->provider_label,
            'other_p_url'             => $item->provider_url,
            'items'                   => $items,
        ];

        $context  = ! empty( $request['context'] ) ? $request['context'] : 'view';
        $data     = $this->add_additional_fields_to_object( $data, $request );
        $data     = $this->filter_response_by_context( $data, $context );
        $response = rest_ensure_response( $data );
        $response->add_links( $this->prepare_links( $item, $request ) );

        return $response;
    }


    /**
     * Prepare links for the request.
     *
     * @param stdClass $item data.
     * @param WP_REST_Request $request Request object.
     *
     * @return array Links for the given item.
     */
    protected function prepare_links( $item, $request ): array {
        return [
            'self'       => [
                'href' => rest_url( sprintf( '/%s/%s/orders/%d/shipment/%d', $this->namespace, $this->rest_base, $item->order_id, $item->id ) ),
            ],
            'collection' => [
                'href' => rest_url( sprintf( '/%s/%s/orders/%d', $this->namespace, $this->rest_base, $item->order_id ) ),
            ],
        ];
    }

    /**
     * Item schema.
     *
     * @return array
     */
    public function get_item_schema(): array {
        $schema = [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'shipping_status',
            'type'       => 'object',
            'properties' => [
                'id'         => [
                    'description' => __( 'Unique identifier for the resource.', 'sk' ),
                    'type'        => 'integer',
                    'context'     => [ 'view', 'edit' ],
                    'readonly'    => true,
                    'required'    => false,
                ],
                'shipment_comments'   => [
                    'description' => __( 'Shipment comment.', 'sk' ),
                    'type'        => 'string',
                    'context'     => [ 'view', 'edit' ],
                    'readonly'    => false,
                    'required'    => false,
                    'default'     => '',
                ],
                'shipment_description'   => [
                    'description' => __( 'Shipment comment description.', 'sk' ),
                    'type'        => 'array',
                    'context'     => [ 'view', 'edit' ],
                    'readonly'    => true,
                    'required'    => false,
                    'items'       => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'description' => __( 'Unique identifier for the resource.', 'sk' ),
                                'type'        => 'integer',
                                'context'     => [ 'view', 'edit' ],
                                'readonly'    => true,
                            ],
                            'content' => [
                                'description' => __( 'Description.', 'sk' ),
                                'type'        => 'string',
                                'context'     => [ 'view', 'edit' ],
                                'readonly'    => true,
                            ],
                        ],
                    ],
                ],
                'shipping_provider'   => [
                    'description' => __( 'Shipment Provider.', 'sk' ),
                    'type'        => 'string',
                    'context'     => [ 'view', 'edit' ],
                    'readonly'    => false,
                    'required'    => true,
                ],
                'other_provider'   => [
                    'description' => __( 'Shipment Provider Name.', 'sk' ),
                    'type'        => 'string',
                    'context'     => [ 'view', 'edit' ],
                    'readonly'    => false,
                    'required'    => false,
                    'default'     => '',
                ],
                'other_p_url'   => [
                    'description' => __( 'Shipment Provider URL.', 'sk' ),
                    'type'        => 'string',
                    'format'      => 'uri',
                    'context'     => [ 'view', 'edit' ],
                    'readonly'    => false,
                    'required'    => false,
                    'default'     => '',
                ],
				'shipping_number'   => [
					'description' => __( 'Shipment tracking Number', 'sk' ),
					'type'        => 'string',
					'context'     => [ 'view', 'edit' ],
					'readonly'    => false,
					'required'    => true,
				],
                'shipped_status'     => [
                    'description' => __( 'The Status of the Shipment.', 'sk' ),
                    'type'        => 'string',
                    'format'      => 'text',
                    'context'     => [ 'view', 'edit' ],
                    'readonly'    => false,
                    'required'    => true,
                ],
                'is_notify'       => [
                    'description' => __( 'Shipment notification send to customer or not.', 'sk' ),
                    'type'        => 'string',
                    'context'     => [ 'view', 'edit' ],
                    'readonly'    => false,
                    'required'    => false,
                    'enum'        => [ 'on', 'off' ],
                    'default'     => 'off',
                ],
                'shipped_date' => [
                    'description' => __( 'Shipment Date.', 'sk' ),
                    'type'        => 'string',
                    'format'      => 'date-time',
                    'context'     => [ 'view', 'edit' ],
                    'readonly'    => false,
                    'required'    => true,
                ],
                'item_id' => [
                    'description' => __( 'Shipment Items', 'sk' ),
                    'type'        => 'array',
                    'context'     => [ 'view', 'edit' ],
                    'readonly'    => false,
                    'required'    => true,
                    'uniqueItems' => true,
                    'items' => [
                        'description' => __( 'Shipment item id.', 'sk' ),
                        'type'   => 'integer',
                        'context'     => [ 'view', 'edit' ],
                        'required'    => true,
                    ],
                ],
                'item_qty' => [
                    'description' => __( 'Shipment items quantity.', 'sk' ),
                    'type'        => 'object',
                    'context'     => [ 'view', 'edit' ],
                    'minItems'    => 1,
                    'readonly'    => false,
                    'required'    => true,
                    'uniqueItems' => true,
                ],
                'updated_at' => [
                    'description' => __( 'Shipment updated date time.', 'sk' ),
                    'type'        => 'string',
                    'format'      => 'date-time',
                    'context'     => [ 'view', 'edit' ],
                    'readonly'    => true,
                    'required'    => false,
                ],
            ],
        ];

        return $schema;
    }
}
