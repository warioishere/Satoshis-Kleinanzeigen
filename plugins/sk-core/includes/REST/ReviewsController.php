<?php

namespace SK\Core\REST;

use DSR_View;
use SK\Core\Modules\StoreReviews\Manager;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Response;
use WP_REST_Server;
use SK\Core\Abstracts\SkRESTController;

/**
 * Reviews API controller
 *
 *
 * 
 */
class ReviewsController extends SkRESTController {

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
    protected $base = 'reviews';


    /**
     * Register all routes related with coupons
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace, '/' . $this->base, [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_reviews' ],
                    'permission_callback' => [ $this, 'get_reviews_permission_check' ],
                    'args'                => $this->get_collection_params(),
                ],
            ]
        );

        register_rest_route(
            $this->namespace, '/' . $this->base . '/summary', [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_reviews_summary' ],
                    'permission_callback' => [ $this, 'check_reviews_summary_permission' ],
                    'args'                => $this->get_collection_params(),
                ],
            ]
        );

        register_rest_route(
            $this->namespace, '/' . $this->base . '/(?P<id>[\d]+)', [
                'args' => [
                    'id' => [
                        'description' => __( 'Unique identifier for the object.', 'sk' ),
                        'type'        => 'integer',
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'update_review_status' ],
                    'permission_callback' => [ $this, 'manage_reviews_permission_check' ],
                    'args'                => [
                        'status' => [
                            'description' => __( 'Review Status', 'sk' ),
                            'required'    => true,
                            'type'        => 'string',
                        ],
                    ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace, '/stores/(?P<id>[\d]+)/reviews', [
                'args' => [
                    'id' => [
                        'description'       => __( 'Unique identifier for the object.', 'sk' ),
                        'type'              => 'integer',
                        'validate_callback' => [ $this, 'is_valid_store' ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'create_item' ],
                    'permission_callback' => [ $this, 'create_item_permissions_check' ],
                    'args'                => [
                        'title'   => [
                            'required'    => true,
                            'type'        => 'string',
                            'description' => __( 'Review title.', 'sk' ),
                        ],
                        'content' => [
                            'required'    => true,
                            'type'        => 'string',
                            'description' => __( 'Review content.', 'sk' ),
                        ],
                        'rating'  => [
                            'required'          => false,
                            'type'              => 'integer',
                            'description'       => __( 'Review rating', 'sk' ),
                            'validate_callback' => [ $this, 'is_valid_rating' ],
                            'default'           => 0,
                        ],
                    ],
                ],
            ]
        );

        // batch update reviews

        register_rest_route(
            $this->namespace, '/' . $this->base . '/batch-update', [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'batch_update_reviews' ],
                    'permission_callback' => [ $this, 'manage_reviews_permission_check' ],
                    'args'                => [
                        'reviews' => [
                            'required'    => true,
                            'type'        => 'array',
                            'description' => __( 'Array of reviews to update', 'sk' ),
                            'items'       => [
                                'type'       => 'object',
                                'properties' => [
                                    'id'     => [
                                        'description' => __( 'Unique identifier for the object.', 'sk' ),
                                        'type'        => 'integer',
                                    ],
                                    'status' => [
                                        'description' => __( 'Review Status', 'sk' ),
                                        'required'    => true,
                                        'type'        => 'string',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );

        // delete review
        register_rest_route(
            $this->namespace, '/' . $this->base . '/(?P<id>[\d]+)', [
                'args' => [
                    'id' => [
                        'description' => __( 'Unique identifier for the object.', 'sk' ),
                        'type'        => 'integer',
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'delete_item' ],
                    'permission_callback' => [ $this, 'manage_reviews_permission_check' ],
                ],
            ]
        );
        // batch delete reviews
        register_rest_route(
            $this->namespace, '/' . $this->base . '/batch-delete', [
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'batch_delete_reviews' ],
                    'permission_callback' => [ $this, 'manage_reviews_permission_check' ],
                    'args'                => [
                        'reviews' => [
                            'required'    => true,
                            'type'        => 'array',
                            'description' => __( 'Array of reviews to delete', 'sk' ),
                            'items'       => [
                                'type'       => 'object',
                                'properties' => [
                                    'id' => [
                                        'description' => __( 'Unique identifier for the object.', 'sk' ),
                                        'type'        => 'integer',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );
    }

    /**
     * Validate store
     *
     * @param mixed $value
     * @param \WP_REST_Request $request
     * @param string $param
     *
     *
     * @return bool|\WP_Error
     */
    public function is_valid_store( $value, $request, $param ) {
        $store = sk()->vendor->get( $value );

        if ( absint( $store->id ) ) {
            return true;
        }

        return new WP_Error( 'rest_sk_store_review_invalid_store', __( 'Invalid store id. Store not exists.', 'sk' ) );
    }

    /**
     * Get reviews permissions
     *
     *
     * @return bool
     */
    public function get_reviews_permission_check() {
        return current_user_can( 'sk_view_review_menu' );
    }

    /**
     * Get reviews permissions
     *
     *
     * @return bool
     */
    public function manage_reviews_permission_check(): bool {

        $has_permission = current_user_can( 'sk_manage_reviews' );
        $seller_review_manage = sk_get_option( 'seller_review_manage', 'sk_selling', 'on' );

        if ( 'on' === $seller_review_manage && $has_permission ) {
            return true;
        }

        return false;
    }

    /**
     * Get reviews permissions
     *
     *
     * @return bool
     */
    public function check_reviews_summary_permission() {
        return current_user_can( 'sk_view_review_reports' );
    }

    /**
     * Get all reviews
     *
     * @param $request
     * @return WP_Error|\WP_REST_Response /WP_REST_Response $response | \WP_Error
     */
    public function get_reviews( $request ) {
        $store_id     = sk_get_current_user_id();
        $review_class = sk_ext()->review;

        if ( empty( $store_id ) ) {
            return new WP_Error( 'no_store_found', __( 'No seller found', 'sk' ), [ 'status' => 404 ] );
        }

        $limit  = $request['per_page'];
        $offset = ( $request['page'] - 1 ) * $request['per_page'];

        $status   = $this->get_status( $request );
        $comments = $review_class->comment_query( $store_id, 'product', $limit, $status, $offset );

        $count = $this->get_total_count( $status );

        $data = [];
        foreach ( $comments as $comment ) {
            $data[] = $this->prepare_item_for_response( $comment, $request );
        }

        $response = rest_ensure_response( $data );
        $response = $this->format_collection_response( $response, $request, $count );

        return $response;
    }

    /**
     * Create review permission callback
     *
     * @param \WP_REST_Request $request
     *
     *
     * @return bool
     */
    public function create_item_permissions_check( $request ) {
        if ( ! sk_ext()->module->is_active( 'store_reviews' ) ) {
            return false;
        }

        if ( current_user_can( 'sk_manage_reviews' ) ) {
            return true;
        }

        $dsr_view = DSR_View::init();

        return $dsr_view->check_if_valid_customer( $request['id'], get_current_user_id() );
    }

    /**
     * Validate rating
     *
     * @param mixed $value
     * @param \WP_REST_Request $request
     * @param string $param
     *
     *
     * @return bool|\WP_Error
     */
    public function is_valid_rating( $value, $request, $param ) {
        $rating = absint( $request['rating'] );

        if ( $rating >= 0 && $rating <= 5 ) {
            return true;
        }

        return false;
    }

    /**
     * Creates a store review
     *
     * @param \WP_REST_Request $request
     *
     *
     * @return \WP_Error|\WP_REST_Response
     */
    public function create_item( $request ) {
        $manager = new Manager();
        $post_id = $manager->save_store_review(
            $request['id'],
            [
                'title'       => $request['title'],
                'content'     => $request['content'],
                'reviewer_id' => get_current_user_id(),
                'rating'      => $request['rating'],
            ]
        );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        $post = get_post( $post_id );

        $user          = get_user_by( 'id', $post->post_author );
        $user_gravatar = get_avatar_url( $user->user_email );

        $data = [
            'id'         => absint( $post->ID ),
            'author'     => [
                'id'     => $user->ID,
                'name'   => $user->user_login,
                'email'  => $user->user_email,
                'url'    => $user->user_url,
                'avatar' => $user_gravatar,
            ],
            'title'      => $post->post_title,
            'content'    => $post->post_content,
            'permalink'  => null,
            'product_id' => null,
            'approved'   => true,
            'date'       => mysql_to_rfc3339( $post->post_date ),
            'rating'     => absint( get_post_meta( $post->ID, 'rating', true ) ),
        ];

        return rest_ensure_response( $data );
    }

    /**
     * Manaage reviews
     * @return WP_Error|WP_REST_Response
     */
    public function update_review_status( $request ) {
        $store_id     = sk_get_current_user_id();
        $review_class = sk_ext()->review;

        if ( empty( $store_id ) ) {
            return new WP_Error( 'no_store_found', __( 'No seller found', 'sk' ), [ 'status' => 404 ] );
        }

        if ( empty( $request['id'] ) ) {
            return new WP_Error( 'no_reivew_found', __( 'No review id found', 'sk' ), [ 'status' => 404 ] );
        }

        if ( empty( $request['status'] ) ) {
            return new WP_Error( 'no_reivew_status_found', __( 'No review status found for updating review', 'sk' ), [ 'status' => 404 ] );
        }

        // check invalid status
        if ( ! in_array( $request['status'], [ 'hold', 'spam', 'trash','approve' ], true ) ) {
            return new WP_Error( 'invalid_status', __( 'Invalid status', 'sk' ), [ 'status' => 400 ] );
        }

        $status = $this->get_status( $request );

        $comment_id = $request['id'];

        // check if the comment is valid
        $comment = get_comment( $comment_id );
        if ( ! $comment ) {
            return new WP_Error( 'no_reivew_found', __( 'No review found', 'sk' ), [ 'status' => 404 ] );
        }

        wp_set_comment_status( $comment_id, $status );

        $comment = get_comment( $comment_id );
        $data    = $this->prepare_item_for_response( $comment, $request );

        return rest_ensure_response( $data );
    }

    /**
     * Get review status
     *
     *
     * @return mixed
     */
    public function get_status( $request ) {
        $status = isset( $request['status'] ) ? $request['status'] : '';

        switch ( $status ) {
            case 'hold':
                return '0';
            case 'spam':
                return 'spam';
            case 'trash':
                return 'trash';
            default:
                return '1';
        }
    }

    /**
     * Get total count of comment
     *
     * @param $status
     *
     *
     * @return int
     */
    public function get_total_count( $status ) {
        global $wpdb;
        $user_id = sk_get_current_user_id();

        $total = $wpdb->get_var(
            "SELECT COUNT(*)
            FROM $wpdb->comments, $wpdb->posts
            WHERE   $wpdb->posts.post_author='$user_id' AND
            $wpdb->posts.post_status='publish' AND
            $wpdb->comments.comment_post_ID=$wpdb->posts.ID AND
            $wpdb->comments.comment_approved='$status' AND
            $wpdb->posts.post_type='product'"
        );

        return $total;
    }

    /**
     * Get review summary
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function get_reviews_summary( $request ) {
        $seller_id = sk_get_current_user_id();

        $data = [
            'comment_counts' => sk_count_comments( 'product', $seller_id ),
            'reviews_url'    => sk_get_navigation_url( 'reviews' ),
        ];

        return rest_ensure_response( $data );
    }

    /**
     * Prepare a single product review output for response.
     *
     * @param  $review Product review object.
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response | WP_Error Response object on success, or WP_Error object on failure.
     */
    public function prepare_item_for_response( $review, $request ) {
        $data = [
            'id'           => (int) $review->comment_ID,
            'date_created' => wc_rest_prepare_date_response( $review->comment_date ),
            'review'       => $review->comment_content,
            'rating'       => (int) get_comment_meta( (int) $review->comment_ID, 'rating', true ),
            'name'         => $review->comment_author,
            'email'        => $review->comment_author_email,
            'verified'     => wc_review_is_from_verified_owner( (int) $review->comment_ID ),
            'link'        => get_comment_link( (int) $review->comment_ID ),
        ];

        $context = ! empty( $request['context'] ) ? $request['context'] : 'view';
        $data    = $this->add_additional_fields_to_object( $data, $request );
        $data    = $this->filter_response_by_context( $data, $context );

        return apply_filters( 'woocommerce_rest_prepare_product_review', $data, $review, $request );
    }

    /**
     * Get the Product Review's schema, conforming to JSON Schema.
     *
     * @return array
     */
    public function get_item_schema() {
        $schema = [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'product_review',
            'type'       => 'object',
            'properties' => [
                'id'               => [
                    'description' => __( 'Unique identifier for the resource.', 'sk' ),
                    'type'        => 'integer',
                    'context'     => [ 'view', 'edit' ],
                    'readonly'    => true,
                ],
                'review'           => [
                    'description' => __( 'The content of the review.', 'sk' ),
                    'type'        => 'string',
                    'context'     => [ 'view', 'edit' ],
                ],
                'date_created'     => [
                    'description' => __( "The date the review was created, in the site's timezone.", 'sk' ),
                    'type'        => 'date-time',
                    'context'     => [ 'view', 'edit' ],
                ],
                'date_created_gmt' => [
                    'description' => __( 'The date the review was created, as GMT.', 'sk' ),
                    'type'        => 'date-time',
                    'context'     => [ 'view', 'edit' ],
                ],
                'rating'           => [
                    'description' => __( 'Review rating (0 to 5).', 'sk' ),
                    'type'        => 'integer',
                    'context'     => [ 'view', 'edit' ],
                ],
                'name'             => [
                    'description' => __( 'Reviewer name.', 'sk' ),
                    'type'        => 'string',
                    'context'     => [ 'view', 'edit' ],
                ],
                'email'            => [
                    'description' => __( 'Reviewer email.', 'sk' ),
                    'type'        => 'string',
                    'context'     => [ 'view', 'edit' ],
                ],
                'verified'         => [
                    'description' => __( 'Shows if the reviewer bought the product or not.', 'sk' ),
                    'type'        => 'boolean',
                    'context'     => [ 'view', 'edit' ],
                    'readonly'    => true,
                ],
            ],
        ];

        return $this->add_additional_fields_schema( $schema );
    }

    /**
     * Delete a review
     *
     *
     * @param \WP_REST_Request $request
     *
     * @return WP_HTTP_Response | WP_Error
     */


    public function delete_item( $request ) {
        try {
            $store_id = sk_get_current_user_id();

            if ( empty( $store_id ) ) {
                return new WP_Error( 'no_store_found', __( 'No seller found', 'sk' ), [ 'status' => 404 ] );
            }

            if ( empty( $request['id'] ) ) {
                return new WP_Error( 'no_reivew_found', __( 'No review id found', 'sk' ), [ 'status' => 404 ] );
            }

            $review_id = $request['id'];
            $result = $this->delete_review( $review_id, $store_id );

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            return new WP_HTTP_Response(
                [
                    'message' => __( 'Review deleted successfully', 'sk' ),
                    'status'  => 200,
                    'id'      => $review_id,
                ]
            );
        } catch ( Exception $e ) {
            return new WP_Error( 'error', $e->getMessage(), [ 'status' => 400 ] );
        }
    }

	//    Batch update reviews
    public function batch_update_reviews( $request ) {
        $store_id     = sk_get_current_user_id();
        $review_class = sk_ext()->review;

        if ( empty( $store_id ) ) {
            return new WP_Error( 'no_store_found', __( 'No seller found', 'sk' ), [ 'status' => 404 ] );
        }

        if ( empty( $request['reviews'] ) ) {
            return new WP_Error( 'no_reivew_found', __( 'No review id found', 'sk' ), [ 'status' => 404 ] );
        }

        $reviews = $request['reviews'];

        /**
         * Fires before batch updating reviews.
         *
         *
         * @param array    $reviews   Array of reviews to be updated
         * @param int      $store_id  Current store/vendor ID
         * @param WP_REST_Request $request   The request object
         */
        do_action( 'sk_before_batch_review_update', $reviews, $store_id, $request );

        $updated_reviews = [];
        foreach ( $reviews as $review ) {
            $comment_id = $review['id'];

            // check if the comment is valid
            $comment = get_comment( $comment_id );
            if ( ! $comment ) {
                continue;
            }

            $status = $this->get_status( $review );

            wp_set_comment_status( $comment_id, $status );

            $comment = get_comment( $comment_id );
            $data    = $this->prepare_item_for_response( $comment, $request );

            $updated_reviews[] = $data;
        }

        /**
         * Fires after batch updating reviews.
         *
         *
         * @param array    $updated_reviews   Array of updated review data
         * @param array    $reviews           Original array of reviews that were requested to update
         * @param int      $store_id          Current store/vendor ID
         * @param WP_REST_Request $request    The request object
         */
        do_action( 'sk_after_batch_review_update', $updated_reviews, $reviews, $store_id, $request );

        return rest_ensure_response( $updated_reviews );
    }


    //    Batch delete reviews


    public function batch_delete_reviews( $request ) {
        $store_id = sk_get_current_user_id();

        if ( empty( $store_id ) ) {
            return new WP_Error( 'no_store_found', __( 'No seller found', 'sk' ), [ 'status' => 404 ] );
        }

        if ( empty( $request['reviews'] ) ) {
            return new WP_Error( 'no_reivew_found', __( 'No review id found', 'sk' ), [ 'status' => 404 ] );
        }

        $reviews = $request['reviews'];

        /**
         * Fires before batch deleting reviews.
         *
         *
         * @param array    $reviews   Array of reviews to be deleted
         * @param int      $store_id  Current store/vendor ID
         * @param WP_REST_Request $request   The request object
         */
        do_action( 'sk_before_batch_review_delete', $reviews, $store_id, $request );

        $deleted_reviews = [];
        $failed_reviews = [];

        foreach ( $reviews as $review ) {
            $comment_id = $review['id'];
            $result = $this->delete_review( $comment_id, $store_id );

            if ( is_wp_error( $result ) ) {
                $failed_reviews[] = [
                    'id' => $comment_id,
                    'message' => $result->get_error_message(),
                ];
                continue;
            }

            $deleted_reviews[] = $comment_id;
        }

        /**
         * Fires after batch deleting reviews.
         *
         *
         * @param array    $deleted_reviews  Array of successfully deleted review IDs
         * @param array    $failed_reviews   Array of reviews that failed to delete with error messages
         * @param array    $reviews          Original array of reviews that were requested to delete
         * @param int      $store_id         Current store/vendor ID
         * @param WP_REST_Request $request   The request object
         */
        do_action( 'sk_after_batch_review_delete', $deleted_reviews, $failed_reviews, $reviews, $store_id, $request );

        return rest_ensure_response( $deleted_reviews );
    }

    /**
     * Helper method to delete a review
     *
     *
     * @param int $review_id The review ID to delete
     * @param int $store_id The store/vendor ID
     *
     * @return array|WP_Error Array with status and message on success, WP_Error on failure
     */
    protected function delete_review( $review_id, $store_id ) {
        // check if the comment is valid
        $comment = get_comment( $review_id );
        if ( ! $comment ) {
            return new WP_Error( 'no_review_found', __( 'No review found', 'sk' ), [ 'status' => 404 ] );
        }

        // check if the comment status is not trash then can't delete
        if ( 'trash' !== $comment->comment_approved ) {
            return new WP_Error( 'cannot_delete_review', __( 'Can not delete review', 'sk' ), [ 'status' => 404 ] );
        }

        /**
         * Fires before a review is deleted.
         *
         *
         * @param int        $review_id The review ID being deleted
         * @param WP_Comment $comment   The comment/review object
         * @param int        $store_id  Current store/vendor ID
         */
        do_action( 'sk_before_review_delete', $review_id, $comment, $store_id );

        $deleted = wp_delete_comment( $review_id );

        if ( ! $deleted ) {
            return new WP_Error( 'error', __( 'Error deleting review', 'sk' ), [ 'status' => 400 ] );
        }

        /**
         * Fires after a review is deleted.
         *
         *
         * @param int  $review_id The review ID that was deleted
         * @param bool $deleted   Whether the deletion was successful
         * @param int  $store_id  Current store/vendor ID
         */
        do_action( 'sk_after_review_delete', $review_id, $deleted, $store_id );

        return [
            'deleted' => true,
            'id'      => $review_id,
        ];
    }
}
