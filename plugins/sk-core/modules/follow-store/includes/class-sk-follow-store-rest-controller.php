<?php

use SK\Core\Cache;
use SK\Core\Abstracts\SkRESTController;

/**
 * Class SkFollowStoreRestController
 *
 *
 * @author weDevs
 */
class SkFollowStoreRestController extends SkRESTController {
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
    protected $base = 'follow-store';

    /**
     * Register follow-store routes
     *
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace, '/' . $this->base, [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'is_following' ],
                    'args'                => $this->get_is_following_collection_params(),
                    'permission_callback' => [ $this, 'get_is_following_permissions_check' ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'toggle_follow_status' ],
                    'args'                => $this->get_toggle_follow_status_collection_params(),
                    'permission_callback' => [ $this, 'create_item_permissions_check' ],
                ],
                'schema' => [ $this, 'get_item_schema' ],
            ]
        );

        register_rest_route(
            $this->namespace, '/' . $this->base . '/followers', [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_followers' ],
                    'args'                => $this->get_followers_collection_params(),
                    'permission_callback' => [ $this, 'get_items_permissions_check' ],
                ],
                'schema' => [ $this, 'get_item_schema' ],
            ]
        );
    }

    /**
     * Checks if a given request has access to get items.
     *
     *
     * @param WP_REST_Request $request Full details about the request.
     *
     * @return bool True if the request has read access, WP_Error object otherwise.
     */
    public function create_item_permissions_check( $request ) {
        return is_user_logged_in();
    }

    /**
     * Toggle follow status of a store
     *
     *
     * @param $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function toggle_follow_status( $request ) {
        $vendor_id   = absint( $request->get_param( 'vendor_id' ) );
        $customer_id = get_current_user_id();

        // sk()->vendor->get() answers for any user id, so ask whether the id is
        // a listed store instead of whether the object came back.
        if ( ! sk_follow_store_is_followable_vendor( $vendor_id ) ) {
            return rest_ensure_response(
                new WP_Error(
                    'sk_rest_no_vendor_found',
                    __( 'No vendor found', 'sk-core' ),
                    [ 'status' => 404 ]
                )
            );
        }

        if ( $vendor_id === $customer_id ) {
            return rest_ensure_response(
                new WP_Error(
                    'sk_rest_self_follow',
                    __( 'Du kannst deinem eigenen Shop nicht folgen.', 'sk-core' ),
                    [ 'status' => 422 ]
                )
            );
        }

        if ( ! sk_rate_limit( 'follow-toggle:' . $customer_id, 20 ) ) {
            return rest_ensure_response(
                new WP_Error(
                    'sk_rest_rate_limited',
                    __( 'Zu viele Anfragen. Bitte kurz warten.', 'sk-core' ),
                    [ 'status' => 429 ]
                )
            );
        }

        $status = sk_follow_store_toggle_status( $vendor_id, $customer_id );
        if ( is_wp_error( $status ) ) {
            return rest_ensure_response(
                new WP_Error(
                    'sk_rest_vendor_follow_toggle',
                    $status->get_error_message(),
                    [ 'status' => 422 ]
                )
            );
        }

        $data = [ 'status' => $status ];

        return rest_ensure_response( $data );
    }

    /**
     * Checks if a given request has access to get items.
     *
     *
     * @param WP_REST_Request $request Full details about the request.
     *
     * @return bool True if the request has read access, WP_Error object otherwise.
     */
    public function get_items_permissions_check( $request ) {
        return is_user_logged_in() && sk_is_user_seller( sk_get_current_user_id() );
    }

    /**
     * Get the followers of a vendor
     *
     *
     * @param WP_REST_Request $request Full details about the request.
     *
     * @return WP_Error|WP_REST_Response
     */
    public function get_followers( $request ) {
        $request_args = $request->get_params();

        if ( empty( $request_args['vendor_id'] ) ) {
            $request_args['vendor_id'] = sk_get_current_user_id();
        }

        // validate vendor_id, admin can check any vendor's followers, but vendor only can view their own followers
        if ( intval( $request_args['vendor_id'] ) !== sk_get_current_user_id() && ! current_user_can( sk_admin_menu_capability() ) ) {
            $request_args['vendor_id'] = sk_get_current_user_id();
        }

        $request_args['return'] = 'count';
        $follower_count         = $this->get_vendor_followers( $request_args );
        if ( is_wp_error( $follower_count ) ) {
            return $follower_count;
        }

        $request_args['return'] = 'all';
        $followers              = $this->get_vendor_followers( $request_args );
        if ( is_wp_error( $followers ) ) {
            return $followers;
        }

        if ( ! empty( $followers ) ) {
            foreach ( $followers as $item ) {
                $item   = $this->prepare_item_for_response( $item, $request );
                $data[] = $this->prepare_response_for_collection( $item );
            }
            $followers = $data;
            unset( $data );
        }

        $response = rest_ensure_response( $followers );
        $response = $this->format_collection_response( $response, $request, $follower_count );

        return $response;
    }

    /**
     * Prepare follower data for response
     *
     *
     * @param object          $item
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function prepare_item_for_response( $item, $request ) {
        $customer_id = $item->follower_id;
        $first_name  = get_user_meta( $customer_id, 'first_name', true );
        $last_name   = get_user_meta( $customer_id, 'last_name', true );
        $full_name   = trim( $first_name . ' ' . $last_name );
        $full_name   = ! empty( $full_name ) ? $full_name : sprintf( '(%s)', __( 'no name', 'sk-core' ) );
        $followed_at = sk_current_datetime()->modify( $item->followed_at );
        $followed_at = $followed_at ?? sk_current_datetime();

        $data = [
            'id'                    => absint( $item->id ),
            'first_name'            => sanitize_text_field( $first_name ),
            'last_name'             => sanitize_text_field( $last_name ),
            'full_name'             => sanitize_text_field( $full_name ),
            'avatar_url'            => esc_url_raw( get_avatar_url( $customer_id, [ 'size' => 32 ] ) ),
            'avatar_url_2x'         => esc_url_raw( get_avatar_url( $customer_id, [ 'size' => 32 * 2 ] ) ),
            'followed_at'           => $followed_at->format( 'c' ),
            'formatted_followed_at' => human_time_diff( $followed_at->getTimestamp(), time() ),
        ];

        return apply_filters( 'sk_rest_prepare_follow_store_follower_object', rest_ensure_response( $data ), $item, $request );
    }

    /**
     * Get Vendor followers
     *
     * todo: temporary adding this method here, kindly move it under Manager() class while refactoring this module
     *
     *
     * @param $args
     *
     * @return WP_Error|Object[]
     */
    private function get_vendor_followers( $args = [] ) {
        $defaults = [
            'vendor_id' => 0,
            'search'    => '',
            'order'     => 'desc',
            'orderby'   => 'followed_at',
            'page'      => 1,
            'per_page'  => 10,
            'return'    => 'all', // possible values are all, count
        ];

        $args = wp_parse_args( $args, $defaults );

        if ( empty( $args['vendor_id'] ) ) {
            return new WP_Error( 'invalid-vendor-id', __( 'Please provide a valid vendor id.', 'sk-core' ) );
        }

        global $wpdb;
        $fields      = '';
        $from        = "{$wpdb->prefix}sk_follow_store_followers AS f";
        $join        = '';
        $where       = '';
        $inner_where = '';
        $groupby     = '';
        $orderby     = '';
        $limits      = '';
        $query_args  = [];
        $status      = '';

        if ( 'count' === $args['return'] ) {
            $fields = ' COUNT(f.follower_id) as count';
        } else {
            $fields = ' f.id, f.follower_id, f.followed_at';
        }

        if ( ! empty( $args['search'] ) ) {
            $from = " (
                SELECT user.ID, user.full_name FROM (
                    SELECT
                        ID,
                        concat(first_meta.meta_value, ' ', last_meta.meta_value) as full_name
                    FROM {$wpdb->users} AS users
                    LEFT JOIN {$wpdb->usermeta} AS first_meta ON users.ID = first_meta.user_id
                    LEFT JOIN {$wpdb->usermeta} AS last_meta ON users.ID = last_meta.user_id
                    WHERE
                        first_meta.meta_key = 'first_name' and first_meta.meta_value != ''
                        AND last_meta.meta_key = 'last_name' and last_meta.meta_value != ''
                ) AS user
                WHERE user.full_name LIKE %s
            ) AS users";

            $join         .= " LEFT JOIN {$wpdb->prefix}sk_follow_store_followers AS f ON f.follower_id = users.ID";
            $like         = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $query_args[] = $like;
        }

        $where .= 'AND f.vendor_id = %d AND f.unfollowed_at IS null';
        $query_args[] = $args['vendor_id'];

        // order by param
        $available_order_by_param = [
            'follower_id' => 'f.follower_id',
            'followed_at' => 'f.followed_at',
        ];

        // order by parameter
        if ( ! empty( $args['orderby'] ) && array_key_exists( $args['orderby'], $available_order_by_param ) ) {
            $order   = in_array( strtolower( $args['order'] ), [ 'asc', 'desc' ], true ) ? strtoupper( $args['order'] ) : 'ASC';
            $orderby = "ORDER BY {$available_order_by_param[ $args['orderby'] ]} {$order}"; //no need for prepare, we've already whitelisted the parameters
        }

        // pagination parameters
        if ( 'count' !== $args['return'] && ! empty( $args['per_page'] ) && -1 !== intval( $args['per_page'] ) ) {
            $limit  = absint( $args['per_page'] );
            $page   = absint( $args['page'] );
            $page   = $page ? $page : 1;
            $offset = ( $page - 1 ) * $limit;

            $limits       = 'LIMIT %d, %d';
            $query_args[] = $offset;
            $query_args[] = $limit;
        }

        $cache_group = "followers_{$args['vendor_id']}";
        $cache_key   = 'get_followers_' . md5( wp_json_encode( $args ) );;
        $followers = Cache::get( $cache_key, $cache_group );

        if ( false !== $followers ) {
            return $followers;
        }

        if ( 'count' === $args['return'] ) {
            // @codingStandardsIgnoreStart
            $results = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT $fields FROM $from $join WHERE 1=1 $where",
                    $query_args
                )
            );
            // @codingStandardsIgnoreEnd

            if ( ! empty( $wpdb->last_error ) ) {
                // translators: 1) query error
                return new WP_Error( 'get_store_follower_db_error', sprintf( __( 'Database Error: %s', 'sk-core' ), $wpdb->last_error ) );
            }

            Cache::set( $cache_key, $results, $cache_group );
        } else {
            // @codingStandardsIgnoreStart
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT $fields FROM $from $join WHERE 1=1 $where $groupby $orderby $limits",
                    $query_args
                )
            );
            // @codingStandardsIgnoreEnd
            if ( ! empty( $wpdb->last_error ) ) {
                // translators: 1) query error
                return new WP_Error( 'get_store_follower_db_error', sprintf( __( 'Database Error: %s', 'sk-core' ), $wpdb->last_error ) );
            }

            if ( ! empty( $results ) ) {
                Cache::set( $cache_key, $results, $cache_group );
            }
        }

        return $results;
    }

    /**
     * Checks if a given request has access to get items.
     *
     *
     * @param WP_REST_Request $request Full details about the request.
     *
     * @return bool True if the request has read access, WP_Error object otherwise.
     */
    public function get_is_following_permissions_check( $request ) {
        return is_user_logged_in();
    }

    /**
     * Get current follow status for a store
     *
     *
     * @param $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function is_following( $request ) {
        $vendor_id = absint( $request->get_param( 'vendor_id' ) );

        if ( ! sk_follow_store_is_followable_vendor( $vendor_id ) ) {
            return new WP_Error(
                'sk_rest_no_vendor_found',
                __( 'No vendor found with given vendor id.', 'sk-core' ),
                [ 'status' => 404 ]
            );
        }

        $is_following = sk_follow_store_is_following_store( $vendor_id, get_current_user_id() );
        if ( is_wp_error( $is_following ) ) {
            return new WP_Error(
                'sk_rest_vendor_follow_status',
                __( 'Database Error: Please contact with site admin.', 'sk-core' ),
                [ 'status' => 422 ]
            );
        }

        return rest_ensure_response( [ 'status' => $is_following ] );
    }

    /**
     * This method will verify per page item value, will be used only with rest api validate callback
     *
     *
     * @param $value
     * @param $request WP_REST_Request
     * @param $key
     *
     * @return bool|WP_Error
     */
    public function validate_per_page( $value, $request, $key ) {
        $attributes = $request->get_attributes();

        if ( isset( $attributes['args'][ $key ] ) ) {
            $argument = $attributes['args'][ $key ];
            // Check to make sure our argument is an int.
            if ( 'integer' === $argument['type'] && ! is_numeric( $value ) ) {
                // translators: 1) argument name, 2) argument value
                return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( '%1$s is not of type %2$s', 'sk-core' ), $key, 'integer' ), [ 'status' => 400 ] );
            }
        } else {
            // This code won't execute because we have specified this argument as required.
            // If we reused this validation callback and did not have required args then this would fire.
            // translators: 1) argument name
            return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( '%s was not registered as a request argument.', 'sk-core' ), $key ), [ 'status' => 400 ] );
        }

        if ( -1 === intval( $value ) || $value > 0 ) {
            return true;
        }

        // translators: 1) rest api endpoint key name
        return new WP_Error( 'rest_invalid_param', sprintf( esc_html__( 'Accepted value for %1$s is -1 or non-zero positive integer', 'sk-core' ), $key ), [ 'status' => 400 ] );
    }

    /**
     * Get is following collection params.
     *
     *
     * @return array
     */
    public function get_is_following_collection_params() {
        $context            = $this->get_context_param();
        $context['default'] = 'view';

        return [
            'context'   => $context,
            'vendor_id' => [
                'description'       => __( 'Vendor id to check if user is following that vendor', 'sk-core' ),
                'type'              => 'integer',
                'required'          => true,
                'sanitize_callback' => 'absint',
                'minimum'           => 1,
                'validate_callback' => 'rest_validate_request_arg',
            ],
        ];
    }

    /**
     * Get is following collection params.
     *
     *
     * @return array
     */
    public function get_toggle_follow_status_collection_params() {
        $context            = $this->get_context_param();
        $context['default'] = 'edit';

        return [
            'context'   => $context,
            'vendor_id' => [
                'description'       => __( 'Vendor id to follow or unfollow', 'sk-core' ),
                'type'              => 'integer',
                'context'           => [ 'edit' ],
                'required'          => true,
                'sanitize_callback' => 'absint',
                'minimum'           => 1,
                'validate_callback' => 'rest_validate_request_arg',
            ],
        ];
    }

    /**
     * Get follow store collection params.
     *
     *
     * @return array
     */
    public function get_followers_collection_params() {
        $context            = $this->get_context_param();
        $context['default'] = 'view';

        return [
            'context'   => $context,
            'vendor_id' => [
                'description'       => __( 'Vendor id follow/unfollow', 'sk-core' ),
                'type'              => 'integer',
                'context'           => [ 'view', 'edit' ],
                'sanitize_callback' => 'absint',
                'minimum'           => 1,
                'validate_callback' => 'rest_validate_request_arg',
            ],
            'search'    => [
                'description'       => __( 'search followers', 'sk-core' ),
                'type'              => 'string',
                'validate_callback' => 'rest_validate_request_arg',
            ],
            'order'     => [
                'description'       => __( 'order parameter', 'sk-core' ),
                'type'              => 'string',
                'enum'              => [ 'asc', 'ASC', 'desc', 'DESC' ],
                'default'           => 'asc',
                'validate_callback' => 'rest_validate_request_arg',
            ],
            'orderby'   => [
                'description'       => __( 'order by parameter', 'sk-core' ),
                'type'              => 'string',
                'enum'              => [ 'follower_id', 'followed_at' ],
                'validate_callback' => 'rest_validate_request_arg',
                'default'           => 'followed_at',
            ],
            'page'      => [
                'description'       => __( 'Current page of the collection.', 'sk-core' ),
                'type'              => 'integer',
                'default'           => 1,
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg',
                'minimum'           => 1,
            ],
            'per_page'  => [
                'description'       => __( 'Maximum number of items to be returned in result set.', 'sk-core' ),
                'type'              => 'integer',
                'default'           => 10,
                'minimum'           => -1,
                'maximum'           => 100,
                'validate_callback' => [ $this, 'validate_per_page' ],
            ],
        ];
    }

    /**
     * Get follow store schema, conforming to JSON Schema
     *
     *
     * @return array
     */
    public function get_item_schema() {
        $schema = [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => __( 'Get Follow Store Item Schema.', 'sk-core' ),
            'type'       => 'object',
            'properties' => [
                'vendor_id' => [
                    'description'       => __( 'Vendor id to follow or unfollow', 'sk-core' ),
                    'type'              => 'integer',
                    'context'           => [ 'view', 'edit' ],
                    'sanitize_callback' => 'absint',
                    'minimum'           => 1,
                    'validate_callback' => 'rest_validate_request_arg',
                ],
                'status'    => [
                    'description'       => __( 'status of the corresponding operation', 'sk-core' ),
                    'type'              => 'boolean',
                    'context'           => [ 'view' ],
                    'required'          => false,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                    'validate_callback' => 'rest_validate_request_arg',
                ],
                'followers' => [
                    'type'    => 'array',
                    'context' => [ 'view' ],
                    'items'   => [
                        'type'       => 'object',
                        'properties' => [
                            'id'                    => [
                                'description'       => __( 'Unique identifier for the object', 'sk-core' ),
                                'type'              => 'integer',
                                'context'           => [ 'view', 'edit' ],
                                'readonly'          => true,
                                'validate_callback' => 'rest_validate_request_arg',
                                'sanitize_callback' => 'absint',
                            ],
                            'first_name'            => [
                                'description'       => __( 'Follower First Name', 'sk-core' ),
                                'type'              => 'string',
                                'default'           => '',
                                'context'           => [ 'view' ],
                                'sanitize_callback' => 'sanitize_text_field',
                                'validate_callback' => 'rest_validate_request_arg',
                            ],
                            'last_name'             => [
                                'description'       => __( 'Follower Last Name', 'sk-core' ),
                                'type'              => 'string',
                                'default'           => '',
                                'context'           => [ 'view' ],
                                'sanitize_callback' => 'sanitize_text_field',
                                'validate_callback' => 'rest_validate_request_arg',
                            ],
                            'full_name'             => [
                                'description'       => __( 'Follower Full Name', 'sk-core' ),
                                'type'              => 'string',
                                'default'           => '',
                                'context'           => [ 'view' ],
                                'sanitize_callback' => 'sanitize_text_field',
                                'validate_callback' => 'rest_validate_request_arg',
                            ],
                            'avatar_url'            => [
                                'description'       => __( 'Follower Avatar URL', 'sk-core' ),
                                'type'              => 'string',
                                'default'           => '',
                                'context'           => [ 'view' ],
                                'sanitize_callback' => 'sanitize_text_field',
                                'validate_callback' => 'rest_validate_request_arg',
                            ],
                            'avatar_url_2x'         => [
                                'description'       => __( 'Follower Avatar URL 2x', 'sk-core' ),
                                'type'              => 'string',
                                'default'           => '',
                                'context'           => [ 'view' ],
                                'sanitize_callback' => 'sanitize_text_field',
                                'validate_callback' => 'rest_validate_request_arg',
                            ],
                            'followed_at'           => [
                                'description'       => __( 'Followed at mysql timestamp', 'sk-core' ),
                                'type'              => 'string',
                                'format'            => 'date-time',
                                'default'           => '',
                                'context'           => [ 'view' ],
                                'sanitize_callback' => 'sanitize_text_field',
                                'validate_callback' => 'rest_validate_request_arg',
                            ],
                            'formatted_followed_at' => [
                                'description'       => __( 'Human readable followed at time', 'sk-core' ),
                                'type'              => 'string',
                                'default'           => '',
                                'context'           => [ 'view' ],
                                'sanitize_callback' => 'sanitize_text_field',
                                'validate_callback' => 'rest_validate_request_arg',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return $this->add_additional_fields_schema( $schema );
    }
}
