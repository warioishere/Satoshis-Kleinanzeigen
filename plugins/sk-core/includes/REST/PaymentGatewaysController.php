<?php

namespace SK\Core\REST;

use WC_REST_Payment_Gateways_Controller;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * SK REST API Payment Gateways controller class.
 *
 *
 */
class PaymentGatewaysController extends WC_REST_Payment_Gateways_Controller {

    /**
     * Endpoint namespace.
     *
     * @var string
     */
    protected $namespace = 'sk/v1';

    /**
     * Check whether a given request has permission to read payment gateways.
     *
     * @param  WP_REST_Request $request Full details about the request.
     * @return WP_Error|boolean
     */
    public function get_items_permissions_check( $request ) {
        if ( ! $this->check_permission() ) {
            return new WP_Error(
                'sk_rest_cannot_view',
                esc_html__( 'Sorry, you cannot list resources.', 'sk' ),
                array( 'status' => rest_authorization_required_code() )
            );
        }
        return true;
    }

    /**
     * Check whether a given request has permission to view a specific payment gateway.
     *
     * @param  WP_REST_Request $request Full details about the request.
     * @return WP_Error|boolean
     */
    public function get_item_permissions_check( $request ) {
        if ( ! $this->check_permission() ) {
            return new WP_Error(
                'sk_rest_cannot_view',
                esc_html__( 'Sorry, you cannot view this resource.', 'sk' ),
                array( 'status' => rest_authorization_required_code() )
            );
        }
        return true;
    }

    /**
     * Check vendor permission.
     *
     * @return bool
     */
    protected function check_permission(): bool {
        return user_can( sk_get_current_user_id(), 'sk_manage_manual_order' );
    }
}
