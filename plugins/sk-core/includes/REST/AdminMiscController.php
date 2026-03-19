<?php

namespace SK\Core\REST;

use WP_REST_Server;
use SK\Core\Abstracts\SkRESTAdminController;

/**
* Admin Dashboard
*
*
*/
class AdminMiscController extends SkRESTAdminController {

    /**
     * Route base.
     *
     * @var string
     */
    protected $base = '';

    /**
     * Register all routes releated with stores
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace, '/help', array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_help' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
            )
        );

        register_rest_route(
            $this->namespace, '/option', [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_option' ],
                    'args'                => [
                        'section' => [
                            'type'              => 'string',
                            'description'       => __( 'SK setting section', 'sk-core' ),
                            'required'          => true,
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                        'option'  => [
                            'type'              => 'string',
                            'description'       => __( 'SK setting section key', 'sk-core' ),
                            'required'          => true,
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                    ],
                    'permission_callback' => [ $this, 'check_permission' ],
                ],
            ]
        );
    }

    /**
     * Get help documents
     *
     * @return \WP_REST_Response
     */
    public function get_help() {
        require_once SK_CORE_INC_DIR . '/Admin/functions.php';

        $help = sk_admin_get_help();

        return rest_ensure_response( $help );
    }

    /**
     * Get sk option.
     *
     *
     * @param \WP_REST_Request $request
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_option( $request ) {
        $section = $request->get_param( 'section' );
        $option  = $request->get_param( 'option' );
        $default = '';

        return rest_ensure_response( sk_get_option( $option, $section, $default ) );
    }
}
