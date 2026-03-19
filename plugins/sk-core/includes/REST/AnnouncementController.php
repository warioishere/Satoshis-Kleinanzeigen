<?php

namespace SK\Core\REST;

use SK\Core\Abstracts\SkRESTController;
use SK\Core\Announcement\Single;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * REST API Announcement controller
 */
class AnnouncementController extends SkRESTController {

    /**
     * Endpoint namespace.
     *
     * @var string
     */
    protected $namespace = 'sk/v1';

    /**
     * Route name
     *
     * @var string
     */
    protected $rest_base = 'announcement';

    /**
     * Rest route base.
     *
     * @var string
     */
    protected $base = 'announcement';

    /**
     * Post type.
     *
     * @var string
     */
    protected $post_type = 'sk_announcement';

    /**
     * Post type.
     *
     * @var string
     */
    protected $post_status = [ 'publish' ];

    /**
     * Announcement manager
     *
     * @var \SK\Core\Announcement\Manager
     */
    protected $manager;

    /**
     * AnnouncementController constructor.
     *
     */
    public function __construct() {
        $this->manager = new \SK\Core\Announcement\Manager();
    }

    /**
     * Register all announcement routes
     *
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace, '/' . $this->rest_base, [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_items' ],
                    'args'                => array_merge(
                        $this->get_collection_params(), [
                            'vendor_id'   => [
                                'description'       => __( 'If set, specified vendor announcement will be returned.', 'sk' ),
                                'type'              => 'integer',
                                'sanitize_callback' => 'absint',
                                'validate_callback' => 'sk_rest_validate_store_id',
                                'required'          => false,
                            ],
                            'status'      => [
                                'type'        => 'string',
                                'description' => __( 'Announcement status, this is a admin only feature', 'sk' ),
                                'required'    => false,
                                'enum'        => [ 'all', 'publish', 'pending', 'draft', 'future', 'trash' ],
                                'default'     => 'all',
                            ],
                            'read_status' => [
                                'type'        => 'string',
                                'description' => __( 'Announcement read status, this is a vendor only feature', 'sk' ),
                                'required'    => false,
                                'enum'        => [ 'read', 'trash', 'unread', 'all' ],
                                'default'     => 'all',
                            ],
                            'from'        => [
                                'type'        => 'string',
                                'format'      => 'date-time',
                                'description' => __( 'Announcement date time from', 'sk' ),
                                'required'    => false,
                            ],
                            'to'          => [
                                'type'        => 'string',
                                'format'      => 'date-time',
                                'description' => __( 'Announcement date time to', 'sk' ),
                                'required'    => false,
                            ],
                        ]
                    ),
                    'permission_callback' => [ $this, 'get_items_permissions_check' ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'create_item' ],
                    'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
                    'permission_callback' => [ $this, 'create_item_permissions_check' ],
                ],
                'schema' => [ $this, 'get_public_item_schema' ],
            ]
        );

        register_rest_route(
            $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/', [
                'args'   => [
                    'id' => [
                        'description'       => __( 'Unique identifier for the object.', 'sk' ),
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'validate_callback' => [ $this, 'rest_validate_announcement_id' ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_item' ],
                    'permission_callback' => [ $this, 'get_item_permissions_check' ],
                ],

                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'update_item' ],
                    'permission_callback' => [ $this, 'update_item_permissions_check' ],
                ],

                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'delete_announcement' ],
                    'args'                => [
                        'force' => [
                            'description' => __( 'Force delete announcement.', 'sk' ),
                            'type'        => 'boolean',
                            'default'     => false,
                        ],
                    ],
                    'permission_callback' => [ $this, 'delete_item_permissions_check' ],
                ],
                'schema' => [ $this, 'get_public_item_schema' ],
            ]
        );

        register_rest_route(
            $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/restore', [
                'args'   => [
                    'id' => [
                        'description'       => __( 'Unique identifier for the object.', 'sk' ),
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'validate_callback' => [ $this, 'rest_validate_announcement_id' ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'restore_item' ],
                    'permission_callback' => [ $this, 'restore_item_permissions_check' ],
                ],
                'schema' => [ $this, 'get_public_item_schema' ],
            ]
        );

        register_rest_route(
            $this->namespace, '/' . $this->rest_base . '/batch', [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'batch_items' ],
                    'permission_callback' => [ $this, 'batch_items_permissions_check' ],
                    'args'                => [
                        'trash'   => [
                            'description' => __( 'Batch trash announcements.', 'sk' ),
                            'type'        => 'array',
                            'required'    => false,
                            'context'     => [ 'edit' ],
                            'items'       => [
                                'type'              => 'integer',
                                'sanitize_callback' => 'absint',
                                'validate_callback' => [ $this, 'rest_validate_announcement_id' ],
                            ],
                        ],
                        'delete'  => [
                            'description' => __( 'Batch delete announcements.', 'sk' ),
                            'type'        => 'array',
                            'required'    => false,
                            'context'     => [ 'edit' ],
                            'items'       => [
                                'type'              => 'integer',
                                'sanitize_callback' => 'absint',
                                'validate_callback' => [ $this, 'rest_validate_announcement_id' ],
                            ],
                        ],
                        'restore' => [
                            'description' => __( 'Batch untrash announcements.', 'sk' ),
                            'type'        => 'array',
                            'required'    => false,
                            'context'     => [ 'edit' ],
                            'items'       => [
                                'type'              => 'integer',
                                'sanitize_callback' => 'absint',
                                'validate_callback' => [ $this, 'rest_validate_announcement_id' ],
                            ],
                        ],
                    ],
                ],
                'schema' => [ $this, 'get_public_item_schema' ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/notice/(?P<id>[\d]+)',
            [
                'args'   => [
                    'id' => [
                        'description'       => __( 'Unique identifier for the object.', 'sk' ),
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'validate_callback' => [ $this, 'rest_validate_notice_id' ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_notice' ],
                    'permission_callback' => [ $this, 'get_notice_permissions_check' ],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'update_read_status' ],
                    'permission_callback' => [ $this, 'update_read_status_permissions_check' ],
                    'args'                => [
                        'read_status' => [
                            'description' => __( 'Announcement read status', 'sk' ),
                            'type'        => 'string',
                            'required'    => true,
                            'enum'        => [ 'read', 'unread' ],
                            'default'     => 'read',
                        ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'delete_notice' ],
                    'permission_callback' => [ $this, 'delete_notice_permissions_check' ],
                ],
                'schema' => [ $this, 'get_public_item_schema' ],
            ]
        );
    }

    /**
     * Get a single announcement object
     *
     *
     * @return WP_Error|Single
     */
    public function get_object( $id ) {
        return $this->manager->get_single_announcement( $id );
    }

    /**
     * Get all announcements
     *
     *
     * @return WP_REST_Response
     */
    public function get_items( $request ) {
        $args = $request->get_params();

        if ( ! current_user_can( sk_admin_menu_capability() ) ) {
            // request is comming from vendor dashboard
            unset( $args['status'] );
            $args['vendor_id'] = sk_get_current_user_id();
        }

        if ( ! empty( $args['vendor_id'] ) ) {
            $args['vendor_id'] = current_user_can( sk_admin_menu_capability() ) ? $args['vendor_id'] : sk_get_current_user_id();
            $args['status']    = 'publish';
        } else {
            // request is coming from admin dashboard
            unset( $args['read_status'] );
            unset( $args['vendor_id'] );
        }

        $announcements = $this->manager->all( $args );
        if ( is_wp_error( $announcements ) ) {
            return rest_ensure_response( $announcements );
        }

        $pagination_data = $this->manager->get_pagination_data( $args );
        $data            = [];


        foreach ( $announcements as $announcement ) {
            $response = $this->prepare_item_for_response( $announcement, $request );
            $data[]   = $this->prepare_response_for_collection( $response );
        }

        $response = rest_ensure_response( $data );
        $response = $this->format_collection_response( $response, $request, $pagination_data['total_count'] );

        if ( ! current_user_can( sk_admin_menu_capability() ) ) {
            return $response;
        }

        $count = wp_count_posts( 'sk_announcement' );

        $response->header( 'X-Status-All', ( $count->pending + $count->publish + $count->draft + $count->future + $count->trash ) );
        $response->header( 'X-Status-Pending', $count->pending );
        $response->header( 'X-Status-Publish', $count->publish );
        $response->header( 'X-Status-Draft', $count->draft );
        $response->header( 'X-Status-Trash', $count->trash );
        $response->header( 'X-Status-Future', $count->future );

        return $response;
    }

    /**
     * Get single announcement object
     *
     *
     * @return WP_Error|WP_REST_Response
     */
    public function get_item( $request ) {
        $announcement_id = $request->get_param( 'id' );
        $announcement    = $this->get_object( $announcement_id );
        $data            = $this->prepare_item_for_response( $announcement, $request );

        return rest_ensure_response( $data );
    }

    /**
     * Create announcement
     *
     *
     * @args  WP_Rest_Request $request
     *
     * @return WP_REST_Response
     */
    public function create_item( $request ) {
        $announcement = $this->manager->create_announcement( $request->get_params() );
        if ( is_wp_error( $announcement ) ) {
            return rest_ensure_response( $announcement );
        }

        $announcement = $this->prepare_item_for_response( $this->get_object( $announcement ), $request );

        return rest_ensure_response( $announcement );
    }

    /**
     * Update announcement
     *
     *
     * @args  WP_Rest_Request $request
     *
     * @return WP_REST_Response
     */
    public function update_item( $request ) {
        $announcement = $this->manager->create_announcement( $request->get_params(), true );
        if ( is_wp_error( $announcement ) ) {
            return rest_ensure_response( $announcement );
        }

        $announcement = $this->prepare_item_for_response( $this->get_object( $announcement ), $request );

        return rest_ensure_response( $announcement );
    }

    /**
     * Delete announcement
     *
     *
     * @args  WP_Rest_Request $request
     *
     * @return WP_REST_Response
     */
    public function delete_announcement( $request ) {
        $announcement = $this->get_object( $request->get_param( 'id' ) );

        $data   = $this->prepare_item_for_response( $announcement, $request );
        $result = $this->manager->delete_announcement( $announcement->get_id(), $request->get_param( 'force' ) );

        if ( is_wp_error( $result ) ) {
            return rest_ensure_response( $result );
        }

        return rest_ensure_response( $data );
    }

    /**
     * Restore announcement
     *
     *
     * @return WP_REST_Response
     */
    public function restore_item( $request ) {
        $announcement = $this->get_object( $request->get_param( 'id' ) );

        $result = $this->manager->untrash_announcement( $announcement->get_id() );
        if ( is_wp_error( $result ) ) {
            return rest_ensure_response( $result );
        }

        $response = $this->prepare_item_for_response( $announcement, $request );

        return rest_ensure_response( $response );
    }

    /**
     * Trash, delete and restore bulk action
     *
     * JSON data format for sending to API
     *     {
     *         "trash" : [
     *             "1", "9", "7"
     *         ],
     *         "delete" : [
     *             "2"
     *         ],
     *         "restore" : [
     *             "4"
     *         ]
     *     }
     *
     *
     * @return WP_REST_Response
     */
    public function batch_items( $request ) {
        $params = $request->get_params();

        if ( empty( $params ) ) {
            return rest_ensure_response(
                new WP_Error( 'no_item_found', __( 'No items found for bulk actions.', 'sk' ), [ 'status' => 404 ] )
            );
        }

        $allowed_status = [ 'trash', 'delete', 'restore' ];

        foreach ( $params as $status => $value ) {
            if ( in_array( $status, $allowed_status, true ) ) {
                if ( 'delete' === $status ) {
                    foreach ( $value as $announcement_id ) {
                        // delete individual announcement cache
                        $this->manager->delete_announcement( $announcement_id, true );
                    }
                } elseif ( 'trash' === $status ) {
                    foreach ( $value as $announcement_id ) {
                        // trash individual announcement cache
                        $this->manager->delete_announcement( $announcement_id );
                    }
                } elseif ( 'restore' === $status ) {
                    foreach ( $value as $announcement_id ) {
                        // untrash individual announcement cache
                        $this->manager->untrash_announcement( $announcement_id );
                    }
                }
            }
        }

        return rest_ensure_response( [ 'success' => true ] );
    }

    /**
     * Get a single notice
     *
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function get_notice( $request ) {
        $notice_id = $request->get_param( 'id' );

        $notice = $this->manager->get_notice( $notice_id );

        // update read status
        if ( 'unread' === $notice->get_read_status() ) {
            $this->manager->update_read_status( $notice_id, 'read' );
            $notice = $notice->set_read_status( 'read' );
        }

        $response = $this->prepare_item_for_response( $notice, $request );

        return rest_ensure_response( $response );
    }

    /**
     * Update read status for a notice
     *
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function update_read_status( $request ) {
        $notice_id   = $request->get_param( 'id' );
        $read_status = $request->get_param( 'read_status' );

        $updated = $this->manager->update_read_status( $notice_id, $read_status );
        if ( is_wp_error( $updated ) ) {
            return rest_ensure_response( $updated );
        }

        $notice   = $this->manager->get_notice( $notice_id );
        $response = $this->prepare_item_for_response( $notice, $request );

        return rest_ensure_response( $response );
    }

    /**
     * Delete a vendor notice
     *
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function delete_notice( $request ) {
        $notice_id = $request->get_param( 'id' );

        $deleted = $this->manager->delete_notice( $notice_id );
        if ( is_wp_error( $deleted ) ) {
            return rest_ensure_response( $deleted );
        }

        return rest_ensure_response(
            [
                'success' => true,
            ]
        );
    }

    /**
     * Get Items permission checking.
     *
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return bool|WP_Error
     */
    public function get_items_permissions_check( $request ) {
        // phpcs:ignore WordPress.WP.Capabilities.Unknown
        if ( current_user_can( sk_admin_menu_capability() ) || current_user_can( 'skdar' ) ) {
            return true;
        }

        return new WP_Error( 'sk_pro_permission_failure', esc_html__( 'You are not allowed to do this action.', 'sk' ) );
    }

    /**
     * Get Item permission checking.
     *
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return bool|WP_Error
     */
    public function get_item_permissions_check( $request ) {
        // phpcs:ignore WordPress.WP.Capabilities.Unknown
        if ( current_user_can( sk_admin_menu_capability() ) ) {
            return true;
        }

        return new WP_Error( 'sk_pro_permission_failure', esc_html__( 'You are not allowed to do this action.', 'sk' ) );
    }

    /**
     * Create announcement permissions check
     *
     *
     * @param WP_REST_Request $request
     *
     * @return bool
     */
    public function create_item_permissions_check( $request ) {
        return current_user_can( sk_admin_menu_capability() );
    }

    /**
     * Update announcement permissions check
     *
     *
     * @param WP_REST_Request $request
     *
     * @return bool
     */
    public function update_item_permissions_check( $request ) {
        return $this->create_item_permissions_check( $request );
    }

    /**
     * Delete announcement permissions check
     *
     *
     * @param WP_REST_Request $request
     *
     * @return bool
     */
    public function delete_item_permissions_check( $request ) {
        return $this->get_item_permissions_check( $request );
    }

    /**
     * Check permission for batch actions
     *
     *
     * @return bool
     */
    public function batch_items_permissions_check() {
        return current_user_can( sk_admin_menu_capability() );
    }

    /**
     * Get restore announcement permissions check
     *
     *
     * @return bool
     */
    public function restore_item_permissions_check() {
        return current_user_can( sk_admin_menu_capability() );
    }

    /**
     * Get a single notice permission check
     *
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return bool|WP_Error
     */
    public function get_notice_permissions_check( $request ) {
        // phpcs:ignore WordPress.WP.Capabilities.Unknown
        if ( current_user_can( 'skdar' ) ) {
            return true;
        }

        return new WP_Error( 'sk_pro_permission_failure', esc_html__( 'You are not allowed to do this action.', 'sk' ) );
    }

    /**
     * Update read status permission check.
     *
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return bool|WP_Error
     */
    public function update_read_status_permissions_check( $request ) {
        return $this->get_notice_permissions_check( $request );
    }

    /**
     * Update read status permission check.
     *
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return bool|WP_Error
     */
    public function delete_notice_permissions_check( $request ) {
        return $this->get_notice_permissions_check( $request );
    }

    /**
     * Prepare Item for response.
     *
     *
     * @param Single          $item    Item.
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function prepare_item_for_response( $item, $request ) {
        $data = $item->get_data();

        $data['announcement_sellers'] = [];

        if ( ! $item->get_vendor_id() ) {
            // this is an admin request
            $sender_ids = get_post_meta( $item->get_id(), '_announcement_selected_user', true );
            if ( ! empty( $sender_ids ) ) {
                foreach ( $sender_ids as $id ) {
                    $vendor                         = sk()->vendor->get( $id );
                    $data['announcement_sellers'][] = [
                        'id'        => $id,
                        'name'      => $vendor->get_shop_name() . '(' . $vendor->get_email() . ')',
                        'shop_name' => $vendor->get_shop_name(),
                        'email'     => $vendor->get_email(),
                    ];
                }
            }
            $announcement_type         = get_post_meta( $item->get_id(), '_announcement_type', true );
            $data['announcement_type'] = $announcement_type;
        }

        $context  = ! empty( $request['context'] ) ? $request['context'] : 'view';
        $data     = $this->add_additional_fields_to_object( $data, $request );
        $data     = $this->filter_response_by_context( $data, $context );
        $response = rest_ensure_response( $data );
        $response->add_links( $this->prepare_links( $item, $request ) );

        return apply_filters( 'sk_rest_prepare_announcement_object', $response, $item, $request );
    }

    /**
     * Prepare links for the request.
     *
     * @param Single          $item    Object data.
     * @param WP_REST_Request $request Request object.
     *
     * @return array                   Links for the given post.
     */
    protected function prepare_links( $item, $request ) {
        if ( $item->get_vendor_id() ) {
            // this is coming from the frontend
            $links = [
                'self'       => [
                    'href' => rest_url( sprintf( '/%s/%s/notice/%d', $this->namespace, $this->rest_base, $item->get_notice_id() ) ),
                ],
                'collection' => [
                    'href' => rest_url( sprintf( '/%s/%s', $this->namespace, $this->rest_base ) ),
                ],
            ];
        } else {
            $links = [
                'self'       => [
                    'href' => rest_url( sprintf( '/%s/%s/%d', $this->namespace, $this->rest_base, $item->get_id() ) ),
                ],
                'collection' => [
                    'href' => rest_url( sprintf( '/%s/%s', $this->namespace, $this->rest_base ) ),
                ],
            ];
        }


        return $links;
    }

    /**
     * This method will check if an announcement exists with given id
     *
     *
     * @param $value
     * @param $request WP_REST_Request
     * @param $key
     *
     * @return bool|WP_Error
     */
    public function rest_validate_announcement_id( $value, $request, $key ) {
        // permission check
        $check_permission = $this->get_item_permissions_check( $request );
        if ( ! is_user_logged_in() || is_wp_error( $check_permission) ) {
            return new WP_Error( 'rest_invalid_param', __( 'You do not have permission to do this action.', 'sk' ), [ 'status' => 400 ] );
        }

        $attributes = $request->get_attributes();

        if ( isset( $attributes['args'][ $key ] ) ) {
            $argument = $attributes['args'][ $key ];
            // Check to make sure our argument is an int.
            if ( 'integer' === $argument['type'] && ! is_numeric( $value ) ) {
                // translators: 1) argument name, 2) argument value
                return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( '%1$s is not of type %2$s', 'sk' ), $key, 'integer' ), [ 'status' => 400 ] );
            }
        } else {
            // this code won't execute because we have specified this argument as required.
            // if we reused this validation callback and did not have required args then this would fire.
            // translators: 1) argument name
            return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( '%s was not registered as a request argument.', 'sk' ), $key ), [ 'status' => 400 ] );
        }

        $announcement = $this->manager->get_single_announcement( intval( $value ) );
        if ( $announcement instanceof Single ) {
            return true;
        }

        // translators: 1) rest api endpoint key name
        return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( 'No announcement found with given id.', 'sk' ), $key ), [ 'status' => 400 ] );
    }

    /**
     * This method will check if a notice exists with given id
     *
     *
     * @param $value
     * @param $request WP_REST_Request
     * @param $key
     *
     * @return bool|WP_Error
     */
    public function rest_validate_notice_id( $value, $request, $key ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_invalid_param', __( 'You do not have permission to do this action.', 'sk' ), [ 'status' => 400 ] );
        }

        $attributes = $request->get_attributes();

        if ( isset( $attributes['args'][ $key ] ) ) {
            $argument = $attributes['args'][ $key ];
            // Check to make sure our argument is an int.
            if ( 'integer' === $argument['type'] && ! is_numeric( $value ) ) {
                // translators: 1) argument name, 2) argument value
                return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( '%1$s is not of type %2$s', 'sk' ), $key, 'integer' ), [ 'status' => 400 ] );
            }
        } else {
            // this code won't execute because we have specified this argument as required.
            // if we reused this validation callback and did not have required args then this would fire.
            // translators: 1) argument name
            return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( '%s was not registered as a request argument.', 'sk' ), $key ), [ 'status' => 400 ] );
        }

        $notice = $this->manager->get_notice( intval( $value ) );
        if ( $notice instanceof Single && sk_get_current_user_id() === $notice->get_vendor_id() ) {
            return true;
        }

        // translators: 1) rest api endpoint key name
        return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( 'No notice found with given id.', 'sk' ), $key ), [ 'status' => 400 ] );
    }

    /**
     * Get Announcement schema.
     *
     *
     * @return array
     */
    public function get_item_schema(): array {
        if ( $this->schema ) {
            // Since WordPress 5.3, the schema can be cached in the $schema property.
            return $this->schema;
        }

        $this->schema = [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'announcement',
            'type'       => 'object',
            'properties' => [
                'id'                   => [
                    'description' => esc_html__( 'Unique identifier for the object.', 'sk' ),
                    'type'        => 'integer',
                    'context'     => [ 'view', 'edit', 'embed' ],
                    'readonly'    => true,
                ],
                'notice_id'            => [
                    'description' => esc_html__( 'If returning a single notice, notice id will be available .', 'sk' ),
                    'type'        => 'integer',
                    'context'     => [ 'view', 'embed' ],
                ],
                'vendor_id'            => [
                    'description' => esc_html__( 'If returning a single notice, vendor id will be available .', 'sk' ),
                    'type'        => 'integer',
                    'context'     => [ 'view' ],
                    'readonly'    => true,
                ],
                'title'                => [
                    'description' => __( 'Title of the Announcement', 'sk' ),
                    'type'        => 'string',
                    'readonly'    => true,
                    'context'     => [ 'view', 'edit' ],
                ],
                'content'              => [
                    'description' => __( 'Content of the Announcement', 'sk' ),
                    'type'        => 'string',
                    'format'      => 'text-area',
                    'readonly'    => true,
                    'context'     => [ 'view', 'edit' ],
                ],
                'status'               => [
                    'description' => __( 'Status of the announcement', 'sk' ),
                    'type'        => 'string',
                    'required'    => false,
                    'context'     => [ 'view', 'edit' ],
                    'enum'        => [
                        'publish',
                        'pending',
                        'future',
                        'draft',
                    ],
                ],
                'date'                 => [
                    'description' => __( 'Created date of the Announcement', 'sk' ),
                    'type'        => 'string',
                    'readonly'    => true,
                    'context'     => [ 'view', 'edit' ],
                ],
                'date_gmt'             => [
                    'description' => __( 'Created date of the Announcement in GMT', 'sk' ),
                    'type'        => 'string',
                    'readonly'    => true,
                    'context'     => [ 'view', 'edit' ],
                ],
                'human_readable_date'  => [
                    'description' => __( 'Human readable Created time', 'sk' ),
                    'type'        => 'string',
                    'readonly'    => true,
                    'context'     => [ 'view' ],
                ],
                'read_status'          => [
                    'description' => __( 'Vendor read status of the single notice', 'sk' ),
                    'type'        => 'string',
                    'required'    => false,
                    'context'     => [ 'view', 'edit' ],
                    'enum'        => [
                        'read',
                        'unread',
                        'trash',
                    ],
                ],
                'announcement_type'    => [
                    'description' => __( 'Send announcement to: this is a admin only field', 'sk' ),
                    'type'        => 'string',
                    'required'    => false,
                    'context'     => [ 'view' ],
                    'enum'        => [
                        'all_seller',
                        'selected_seller',
                        'enabled_seller',
                        'disabled_seller',
                        'featured_seller',
                    ],
                ],
                'announcement_sellers' => [
                    'description' => __( 'Send announcement to: this is a admin only field', 'sk' ),
                    'type'        => 'array',
                    'required'    => false,
                    'context'     => [ 'view' ],
                    'items'       => [
                        'type'       => 'object',
                        'properties' => [
                            'id'        => [
                                'description' => __( 'Vendor id', 'sk' ),
                                'type'        => 'integer',
                            ],
                            'name'      => [
                                'description' => __( 'Vendor name', 'sk' ),
                                'type'        => 'string',
                            ],
                            'shop_name' => [
                                'description' => __( 'Vendor shop name', 'sk' ),
                                'type'        => 'string',
                            ],
                            'email'     => [
                                'description' => __( 'Vendor email address', 'sk' ),
                                'type'        => 'string',
                            ],
                        ],
                    ],
                ],
                'sender_ids'           => [
                    'description' => __( 'Send announcement to: this is a admin only field', 'sk' ),
                    'type'        => 'array',
                    'required'    => false,
                    'context'     => [ 'edit' ],
                    'items'       => [
                        'type' => 'integer',
                    ],
                ],
                'exclude_seller_ids'   => [
                    'description' => __( 'Exclude seller ids', 'sk' ),
                    'type'        => 'array',
                    'required'    => false,
                    'context'     => [ 'edit' ],
                    'items'       => [
                        'type' => 'integer',
                    ],
                ],
            ],
        ];

        return $this->schema;
    }
}
