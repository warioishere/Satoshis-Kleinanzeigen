<?php

namespace SK\Core\REST;

use WC_Admin_Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use SK\Core\REST\StoreController as StoreControllerLite;

/**
 * Store API Controller
 *
 *
 * @author  SK
 */
class ExtendedStoreController extends StoreControllerLite {

    /**
     * Register all routes releated with stores
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->base . '/(?P<id>[\d]+)/status',
            [
                'args' => [
                    'id'     => [
                        'description'       => __( 'Unique identifier for the object.', 'sk-core' ),
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                        'validate_callback' => 'sk_rest_validate_store_id',
                    ],
                    'status' => [
                        'description' => __( 'Status for the store object.', 'sk-core' ),
                        'type'        => 'string',
                        'required'    => true,
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'update_vendor_status' ],
                    'permission_callback' => [ $this, 'permission_check_for_manageable_part' ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->base . '/batch',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'batch_update' ],
                    'permission_callback' => [ $this, 'permission_check_for_manageable_part' ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->base . '/(?P<id>[\d]+)/stats',
            [
                'args' => [
                    'id' => [
                        'description'       => __( 'Unique identifier for the object.', 'sk-core' ),
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'validate_callback' => 'sk_rest_validate_store_id',
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_store_stats' ],
                    'args'                => $this->get_collection_params(),
                    'permission_callback' => [ $this, 'permission_check_for_stats' ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->base . '/(?P<id>[\d]+)/email',
            [
                'args' => [
                    'id'      => [
                        'description'       => __( 'Unique identifier for the object.', 'sk-core' ),
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'validate_callback' => 'sk_rest_validate_store_id',
                    ],
                    'subject' => [
                        'description' => __( 'Subject of the email.', 'sk-core' ),
                        'type'        => 'string',
                        'required'    => true,
                    ],
                    'body'    => [
                        'description' => __( 'Body of the email.', 'sk-core' ),
                        'type'        => 'string',
                        'required'    => true,
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'send_email' ],
                    'args'                => $this->get_collection_params(),
                    'permission_callback' => [ $this, 'permission_check_for_manageable_part' ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace, '/' . $this->base . '/current-visitor',
            [
                'args' => [],
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_current_visitor_information' ],
                    'args'                => $this->get_collection_params(),
                    'permission_callback' => [ $this, 'permission_check_for_manageable_part' ],
                ],
            ]
        );
    }

    /**
     * Update_vendor_status.
     *
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function update_vendor_status( $request ) {
        if ( ! in_array( $request['status'], [ 'active', 'inactive' ], true ) ) {
            return rest_ensure_response(
                new WP_Error( 'no_valid_status', __( 'Status parameter must be active or inactive', 'sk-core' ), [ 'status' => 400 ] )
            );
        }

        $store_id = ! empty( $request['id'] ) ? $request['id'] : 0;

        if ( empty( $store_id ) ) {
            return rest_ensure_response(
                new WP_Error( 'no_vendor_found', __( 'No vendor found for updating status', 'sk-core' ), [ 'status' => 400 ] )
            );
        }

        if ( 'active' === $request['status'] ) {
            $user = sk()->vendor->get( $store_id )->make_active();
        } else {
            $user = sk()->vendor->get( $store_id )->make_inactive();
        }

        $response = rest_ensure_response( $user );
        $response->add_links( $this->prepare_links( $user, $request ) );

        return $response;
    }

    /**
     * Batch udpate for vendor listing
     *
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function batch_update( $request ) {
        $params = $request->get_params();

        if ( empty( $params ) ) {
            return rest_ensure_response(
                new WP_Error( 'no_item_found', __( 'No items found for bulk updating', 'sk-core' ), [ 'status' => 404 ] )
            );
        }

        $allowed_status = [ 'approved', 'pending', 'delete' ];

        $response = [];

        foreach ( $params as $status => $value ) {
            if ( in_array( $status, $allowed_status, true ) ) {
                switch ( $status ) {
                    case 'approved':
                        foreach ( $value as $vendor_id ) {
                            $response['approved'][] = sk()->vendor->get( $vendor_id )->make_active();
                        }
                        break;
                    case 'pending':
                        foreach ( $value as $vendor_id ) {
                            $response['pending'][] = sk()->vendor->get( $vendor_id )->make_inactive();
                        }
                        break;
                    case 'delete':
                        foreach ( $value as $vendor_id ) {
                            $user                 = sk()->vendor->get( $vendor_id )->delete();
                            $response['delete'][] = $user;
                        }
                        break;
                }
            }
        }

        return rest_ensure_response( $response );
    }

    /**
     * Undocumented function.
     *
     *
     * @return boolean
     */
    public function permission_check_for_manageable_part() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Get stats for the vendor
     *
     * @param WP_REST_Request $request
     *
     * @return boolean
     */
    public function permission_check_for_stats( $request ) {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        if ( sk_get_current_user_id() === $request['id'] ) {
            return true;
        }

        return false;
    }

    /**
     * Fetch stats for the store
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function get_store_stats( $request ) {
        $store_id = (int) $request['id'];
        $vendor   = sk()->vendor->get( $store_id );

        $products    = sk_count_posts( 'product', $store_id );
        $orders      = sk_count_orders( $store_id );
        $reviews     = sk_count_comments( 'product', $store_id );
        $total_items = 0;

        $response = [
            'products' => [
                'total'   => $products->publish,
                'sold'    => $total_items,
                'visitor' => $vendor->get_product_views(),
            ],
            'revenue'  => [
                'orders'  => $orders->{'wc-processing'} + $orders->{'wc-completed'},
                'sales'   => 0,
            ],
            'others'   => [
                'reviews' => $reviews->{'approved'},
            ],
        ];

        return rest_ensure_response( $response );
    }

    /**
     * Send email to the vendor
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function send_email( $request ) {
        $response  = [];
        $vendor_id = $request['id'];
        $vendor    = sk()->vendor->get( $vendor_id );

        $from_name           = WC_Admin_Settings::get_option( 'woocommerce_email_from_name' );
        $from_mail           = WC_Admin_Settings::get_option( 'woocommerce_email_from_address' );
        $subject             = $request['subject'];
        $body                = $request['body'];
        $headers             = [
            "From: {$from_name} <{$from_mail}>",
            "Reply-To: {$request['replyto']}",
        ];
        $response['success'] = wp_mail( $vendor->get_email(), $subject, $body, $headers );

        return rest_ensure_response( $response );
    }

    /**
     * Get the current admin information
     * visiting the vendor page.
     *
     *
     * @return WP_REST_Response
     */
    public function get_current_visitor_information() {
        $user     = wp_get_current_user();
        $response = [
            'user' => [
                'user_login'   => $user->user_login,
                'email'        => $user->user_email,
                'first_name'   => $user->first_name,
                'last_name'    => $user->last_name,
                'display_name' => $user->display_name,
            ],
        ];

        return rest_ensure_response( $response );
    }
}
