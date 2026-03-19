<?php

namespace SK\Core\REST;

use WC_REST_Data_Continents_Controller;
use WP_Error;
use WP_REST_Request;

class SkDataContinentsController extends WC_REST_Data_Continents_Controller {

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
    protected $rest_base = 'data/continents';

    /**
     * Check the permission of the request for sk.
     *
     *
     * @param  WP_REST_Request $request Full details about the request.
     * @return WP_Error|boolean
     */
    public function check_sk_permission( $request ) {
        // phpcs:ignore WordPress.WP.Capabilities.Unknown
        if ( current_user_can( sk_admin_menu_capability() ) || current_user_can( 'skdar' ) ) {
            return true;
        }

        return new WP_Error(
            'sk_pro_permission_failure',
            __( 'You are not allowed to do this action.', 'sk-core' ),
            [
                'status' => rest_authorization_required_code(),
            ]
        );
    }

    /**
     * Check if a given request has access to read an item.
     *
     * @param  WP_REST_Request $request Full details about the request.
     * @return WP_Error|boolean
     */
    public function get_item_permissions_check( $request ) {
        return $this->check_sk_permission( $request );
    }

    /**
     * Check if a given request has access to read items.
     *
     *
     * @param  WP_REST_Request $request Full details about the request.
     *
     * @return WP_Error|boolean
     */
    public function get_items_permissions_check( $request ) {
        return $this->check_sk_permission( $request );
    }
}
