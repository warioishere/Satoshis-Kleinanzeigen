<?php

namespace SK\Core\REST;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use SK\Core\Abstracts\SkRESTAdminController;

class ModulesController extends SkRESTAdminController {

    /**
     * Route name
     *
     * @var string
     */
    protected $base = 'modules';

    /**
     * Register all routes related with modules
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace, '/' . $this->base, [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_items' ],
                    'permission_callback' => [ $this, 'check_permission' ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace, '/' . $this->base . '/activate', [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'activate_modules' ],
                    'permission_callback' => [ $this, 'check_permission' ],
                    'args'                => $this->module_toggle_request_args(),
                ],
            ]
        );

        register_rest_route(
            $this->namespace, '/' . $this->base . '/deactivate', [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'deactivate_modules' ],
                    'permission_callback' => [ $this, 'check_permission' ],
                    'args'                => $this->module_toggle_request_args(),
                ],
            ]
        );
    }

    /**
     * Activation/deactivation request args
     *
     * @return array
     */
    public function module_toggle_request_args() {
        return [
            'module' => [
                'description'       => __( 'Basename of the module as array', 'sk' ),
                'required'          => true,
                'type'              => 'array',
                'validate_callback' => [ $this, 'validate_modules' ],
                'items'             => [
                    'type' => 'string',
                ],
            ],
        ];
    }

    /**
     * Validate module ids
     *
     *
     * @param array $modules
     *
     * @return bool|WP_Error
     */
    public function validate_modules( $modules ) {
        if ( ! is_array( $modules ) ) {
            return new WP_Error( 'sk_pro_rest_error', __( 'module parameter must be an array of id of SK Pro modules.', 'sk' ) );
        }

        if ( empty( $modules ) ) {
            return new WP_Error( 'sk_pro_rest_error', 'module parameter is empty', 'sk' );
        }

        $available_modules = sk_ext()->module->get_available_modules();

        foreach ( $modules as $module ) {
            if ( ! in_array( $module, $available_modules, true ) ) {
                /* Translators: %s: module name */
                return new WP_Error( 'sk_pro_rest_error', sprintf( __( '%s module is not available in your system.', 'sk' ), $module ) );
            }
        }

        return true;
    }

    /**
     * Get all modules
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function get_items( $request ) {
        $data             = [];
        $modules          = sk_ext()->module->get_all_modules();
        $activate_modules = sk_ext()->module->get_active_modules();

        $status = isset( $request['status'] ) ? sanitize_text_field( wp_unslash( $request['status'] ) ) : 'all';

        foreach ( $modules as $module ) {
            if ( $status === 'active' && ! in_array( $module['id'], $activate_modules, true ) ) {
                continue;
            }
            if ( $status === 'inactive' && in_array( $module['id'], $activate_modules, true ) ) {
                continue;
            }

            $data[] = [
                'id'             => $module['id'],
                'name'           => $module['name'],
                'description'    => $module['description'],
                'thumbnail'      => $module['thumbnail'],
                'plan'           => $module['plan'],
                'active'         => in_array( $module['id'], $activate_modules, true ),
                'available'      => file_exists( $module['module_file'] ) && sk_ext()->module->is_module_available_under_package( $module ),
                'doc_id'         => isset( $module['doc_id'] ) ? $module['doc_id'] : null,
                'doc_link'       => isset( $module['doc_link'] ) ? $module['doc_link'] : null,
                'mod_link'       => isset( $module['mod_link'] ) ? $module['mod_link'] : null,
                'pre_requisites' => isset( $module['pre_requisites'] ) ? $module['pre_requisites'] : null,
                'categories'     => isset( $module['categories'] ) ? $module['categories'] : null,
                'video_id'       => isset( $module['video_id'] ) ? $module['video_id'] : null,
            ];
        }

        $response = rest_ensure_response( $data );

        $sk_pro_current_plan = sk_ext()->get_plan();
        $sk_pro_plans        = wp_json_encode( sk_ext()->get_sk_pro_plans() );

        $response->header( 'X-SK-Current-Plan', $sk_pro_current_plan );
        $response->header( 'X-SK-Plans', $sk_pro_plans );

        return $response;
    }

    /**
     * Activate modules
     *
     * @param  WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function activate_modules( $request ) {
        $modules = $request['module'];
        sk_ext()->module->activate_modules( $modules );
        sk_ext()->module->set_modules( [] );

        return $this->get_items( $request );
    }

    /**
     * Deactivate modules
     *
     * @param  WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function deactivate_modules( $request ) {
        $modules = $request['module'];
        sk_ext()->module->deactivate_modules( $modules );
        sk_ext()->module->set_modules( [] );

        return $this->get_items( $request );
    }
}
