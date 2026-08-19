<?php

namespace SK\Core\REST;

use WC_REST_Taxes_Controller;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * SK REST API Taxes controller class.
 *
 *
 */
class TaxesController extends WC_REST_Taxes_Controller {

    /**
     * Endpoint namespace.
     *
     * @var string
     */
    protected $namespace = 'sk/v1';

    /**
     * Check if a given request has access to read taxes.
     *
     * @param  WP_REST_Request $request Full details about the request.
     * @return WP_Error|boolean
     */
    public function get_items_permissions_check( $request ) {
        if ( ! $this->check_permission() ) {
            return new WP_Error(
                'sk_rest_cannot_view',
                esc_html__( 'Sorry, you cannot list resources.', 'sk-core' ),
                array( 'status' => rest_authorization_required_code() )
            );
        }
        return true;
    }

    /**
     * Check if a given request has access to read a tax.
     *
     * @param  WP_REST_Request $request Full details about the request.
     * @return WP_Error|boolean
     */
    public function get_item_permissions_check( $request ) {
        if ( ! $this->check_permission() ) {
            return new WP_Error(
                'sk_rest_cannot_view',
                esc_html__( 'Sorry, you cannot view this resource.', 'sk-core' ),
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
