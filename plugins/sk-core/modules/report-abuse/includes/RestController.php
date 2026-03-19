<?php

namespace SK\Modules\ReportAbuse;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Server;

class RestController extends WP_REST_Controller {

    /**
     * Endpoint namespace.
     *
     * @var string
     */
    protected $namespace = 'sk/v1';

    /**
     * Route base.
     *
     * @var string
     */
    protected $rest_base = 'abuse-reports';

    /**
     * Register routes
     *
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_items' ],
                    'permission_callback' => [ $this, 'is_skdar' ],
                    'args'                => [
                        'page' => [
                            'description' => __( 'Current page of the collection.', 'sk' ),
                            'type'        => 'integer',
                            'default'     => 1,
                            'minimum'     => 1,
                            'required'    => false,
                        ],
                        'reason' => [
                            'description' => __( 'Filter reports by reason.', 'sk' ),
                            'type'        => 'string',
                            'required'    => false,
                        ],
                        'product_id' => [
                            'description' => __( 'Filter reports by product ID.', 'sk' ),
                            'type'        => 'integer',
                            'minimum'     => 0,
                            'required'    => false,
                        ],
                        'vendor_id' => [
                            'description' => __( 'Filter reports by vendor ID.', 'sk' ),
                            'type'        => 'integer',
                            'minimum'     => 0,
                            'required'    => false,
                        ],
                    ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            [
                'args' => [
                    'id' => [
                        'description' => __( 'Abuse report id', 'sk' ),
                        'type'        => 'integer',
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'delete_item' ],
                    'permission_callback' => [ $this, 'is_skdar' ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/batch',
            [
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'delete_items' ],
                    'permission_callback' => [ $this, 'is_skdar' ],
                    'args'                => [
                        'items' => [
                            'description'       => __( 'Array of report IDs to delete.', 'sk' ),
                            'type'             => 'array',
                            'required'         => true,
                            'minItems'         => 1,
                            'uniqueItems'      => true,
                            'items'            => [
                                'type'     => 'integer',
                                'minimum'  => 1,
                            ],
                        ],
                    ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/abuse-reasons',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_abuse_reasons' ],
                    'permission_callback' => [ $this, 'is_skdar' ],
                    'args'                => [], // Just returns static options, so no args needed
                ],
            ]
        );
    }

    /**
     * Permission callback
     *
     *
     * @return bool
     */
    public function is_skdar() {
        return current_user_can( 'skdar' );
    }

    /**
     * Get reports
     *
     *
     * @param \WP_REST_Request $request
     *
     * @return \WP_REST_Response
     */
    public function get_items( $request ) {
        global $wpdb;

        // These defaults should be replaced by schema
        $per_page   = 20;
        $page       = ! empty( $request['page'] ) ? $request['page'] : 1;
        $reason     = ! empty( $request['reason'] ) ? $request['reason'] : '';
        $product_id = ! empty( $request['product_id'] ) ? $request['product_id'] : 0;
        $vendor_id  = ! empty( $request['vendor_id'] ) ? $request['vendor_id'] : 0;

        $args =  [
            'page'       => $page,
            'reason'     => $reason,
            'product_id' => $product_id,
            'vendor_id'  => $vendor_id,
        ];

        $data     = sk_report_abuse_get_reports( $args );
        $response = rest_ensure_response( $data );

        $args['count'] = true;
        $total         = sk_report_abuse_get_reports( $args );
        $response->header( 'X-SK-AbuseReports-Total', $total );

        $max_pages = ceil( $total / $per_page );
        $response->header( 'X-SK-AbuseReports-TotalPages', (int) $max_pages );

        return $response;
    }

    /**
     * Get abuse reasons
     *
     *
     * @param \WP_REST_Request $request
     *
     * @return \WP_REST_Response
     */
    public function get_abuse_reasons( $request ) {
        $option = sk_report_abuse_get_option();

        $response = rest_ensure_response( $option['abuse_reasons'] );

        return $response;
    }

    /**
     * Delete report
     *
     *
     * @param \WP_REST_Request $request
     *
     * @return \WP_REST_Response
     */
    public function delete_item( $request ) {
        $report = sk_report_abuse_get_reports( [ 'id' => $request['id'] ] );

        if ( empty( $report ) ) {
            return new \WP_Error( 'report_not_found', __( 'Report not found', 'sk' ) );
        }

        sk_report_abuse_delete_reports( [ $report['id'] ] );

        return rest_ensure_response( $report );
    }

    /**
     * Delete reports in bulk
     *
     *
     * @param \WP_REST_Request $request
     *
     * @return \WP_REST_Response|WP_Error
     */
    public function delete_items( $request ) {
        $ids = array_filter( (array) $request['items'], 'is_numeric' );

        if ( ! is_array( $ids ) || empty( $ids ) || ! count( array_filter( $ids ) ) == count( $ids ) ) {
            return new WP_Error( 'invalid_data', __( 'Items must be an array of report ids', 'sk' ), [ 'status' => 404 ] );
        }

        $reports = sk_report_abuse_get_reports( [ 'ids' => $ids ] );
        if ( empty( $reports ) ) {
            return new \WP_Error( 'reports_not_found', __( 'No reports not found with given ids. Please check your input.', 'sk' ) );
        }

        $ids = array_map(
            function ( $report ) {
                return $report['id'];
            },
            $reports
        );

        if ( ! empty( $ids ) ) {
            sk_report_abuse_delete_reports( $ids );
        }

        return rest_ensure_response( $reports );
    }
}
