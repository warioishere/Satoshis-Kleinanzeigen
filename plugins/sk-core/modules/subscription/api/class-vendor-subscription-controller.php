<?php

use SK\Modules\Subscription\Helper;

/**
 * Vendor Subscription API Controller.
 *
 *
 */
class SK_REST_Vendor_Subscription_Controller extends SK_REST_Subscription_Controller {

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
    protected $base = 'vendor-subscription';

    /**
     * Register Routes Related with Vendor Subscription.
     *
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace, '/' . $this->base . '/vendor/(?P<id>[\d]+)', [
                'args' => [
                    'id' => [
                        'description' => __( 'Vendor id', 'sk-core' ),
                        'type'        => 'integer',
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_active_subscription_for_vendor' ],
                    'permission_callback' => [ $this, 'check_permission' ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace, '/' . $this->base . '/update/(?P<id>[\d]+)/', [
                'args' => [
                    'id' => [
                        'description' => __( 'Vendor id', 'sk-core' ),
                        'type'        => 'integer',
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'update_subscription' ],
                    'permission_callback' => [ $this, 'check_permission' ],
                    'args'                => [
                        'action' => [
                            'description'       => __( 'Action to update.', 'sk-core' ),
                            'type'              => 'string',
                            'required'          => true,
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                    ],
                ],
            ]
        );
    }

    /**
     * Check Permission.
     *
     *
     * @return bool|WP_Error
     */
    public function check_permission() {
        if ( ! current_user_can( 'skdar' ) ) {
            return new WP_Error(
                'sk_pro_permission_failure',
                __( 'Sorry! You are not permitted to do current action.', 'sk-core' ),
                [ 'status' => 403 ]
            );
        }

        return true;
    }

    /**
     * Get currently activated subscription for a vendor.
     *
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function get_active_subscription_for_vendor( $request ) {
        $vendor_id = $this->get_vendor_id( $request );
        $vendor    = sk()->vendor->get( $vendor_id );

        $data = $this->prepare_item_for_response( $vendor, $request );

        return rest_ensure_response( $data );
    }

    /**
     * Update Subscription.
     *
     *
     * @param \WP_REST_Request $request
     *
     * @return \WP_Error|\WP_REST_Response
     */
    public function update_subscription( $request ) {
        $vendor_id          = $this->get_vendor_id( $request );
        $action             = $request->get_param( 'action' );
        $cancel_immediately = false;

        $order_id = get_user_meta( $vendor_id, 'product_order_id', true );
        $vendor   = sk()->vendor->get( $vendor_id );
        $subscription = $vendor->subscription;
        $user     = new \WP_User( $vendor_id );

        if ( ! $order_id || ! $subscription ) {
            return new WP_Error(
                'no_subscription',
                __( 'No subscription is found to be updated.', 'sk-core' ),
                [ 'status' => 404 ]
            );
        }

        if ( 'activate' === $action ) {
            Helper::log( 'Subscription re-activation check: re-activation for User #' . $vendor_id . ' on order #' . $order_id );
            do_action( 'dps_activate_non_recurring_subscription', $order_id, $vendor_id );
        }

        if ( 'cancel' === $action ) {
            Helper::log( 'Subscription cancellation check: cancellation for User #' . $vendor_id . ' on order #' . $order_id );
            do_action( 'dps_cancel_non_recurring_subscription', $order_id, $vendor_id, $cancel_immediately );
        }

        $response = $this->prepare_item_for_response( $vendor, $request );
        $response = rest_ensure_response( $response );

        return $response;
    }

    /**
     * Get seller id from Query param for Admin and currently logged-in user as Vendor.
     *
     *
     * @param WP_REST_Request $request
     *
     * @return int
     */
    public function get_vendor_id( WP_REST_Request $request ): int {
        if ( ! is_user_logged_in() ) {
            return 0;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return sk_get_current_user_id();
        }

        return (int) $request->get_param( 'id' );
    }
}
