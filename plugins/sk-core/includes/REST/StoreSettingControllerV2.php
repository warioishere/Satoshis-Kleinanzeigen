<?php

namespace SK\Core\REST;

use SK\Core\Vendor\SettingsApi\Processor;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * StoreSettings API Controller
 *
 *
 * 
 */
class StoreSettingControllerV2 extends StoreSettingController {

    /**
     * Endpoint namespace
     *
     * @var string
     */
    protected $namespace = 'sk/v2';

    /**
     * Register all routes related to settings
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
                    'callback'            => [ $this, 'get_settings_list' ],
                    'permission_callback' => [ $this, 'get_settings_permission_callback' ],
                ],
            ]
        );
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<group_id>[\w-]+)',
            [
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_settings_group' ],
					'permission_callback' => [ $this, 'get_settings_permission_callback' ],
                    'args'                => [
                        'group_id' => [
                            'description' => __( 'Unique identifier for the settings group.', 'sk-core' ),
                            'type'        => 'string',
                            'required'    => true,
                        ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'args'                => $this->update_settings_group_args(),
                    'callback'            => [ $this, 'update_settings' ],
                    'permission_callback' => [ $this, 'get_settings_permission_callback' ],
                ],
			]
        );
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<group_id>[\w-]+)/(?P<id>[\w-]+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_single_settings' ],
                    'permission_callback' => [ $this, 'get_settings_permission_callback' ],
                    'args'                => [
                        'group_id' => [
                            'description' => __( 'Unique identifier for the settings group.', 'sk-core' ),
                            'type'        => 'string',
                            'required'    => true,
                        ],
                        'id' => [
                            'description' => __( 'Unique identifier for the setting.', 'sk-core' ),
                            'type'        => 'string',
                            'required'    => true,
                        ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'args'                => $this->update_single_settings_args(),
                    'callback'            => [ $this, 'update_single_settings' ],
                    'permission_callback' => [ $this, 'get_settings_permission_callback' ],
                ],
            ]
        );
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<group_id>[\w-]+)/(?P<parent_id>[\w-]+)/(?P<id>[\w-]+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_single_settings_field' ],
                    'permission_callback' => [ $this, 'get_settings_permission_callback' ],
                    'args'                => [
                        'group_id' => [
                            'description' => __( 'Unique identifier for the settings group.', 'sk-core' ),
                            'type'        => 'string',
                            'required'    => true,
                        ],
                        'parent_id' => [
                            'description' => __( 'Unique identifier for the parent setting.', 'sk-core' ),
                            'type'        => 'string',
                            'required'    => true,
                        ],
                        'id' => [
                            'description' => __( 'Unique identifier for the setting field.', 'sk-core' ),
                            'type'        => 'string',
                            'required'    => true,
                        ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'args'                => $this->update_single_settings_args(),
                    'callback'            => [ $this, 'update_single_settings_field' ],
                    'permission_callback' => [ $this, 'get_settings_permission_callback' ],
                ],
            ]
        );
    }

    /**
     * Update Settings Group or Page.
     *
     *
     * @param WP_REST_Request $request The request object.
     *
     * @return WP_Error|WP_REST_Response
     */
    public function update_settings( $request ) {
        $group_id = $request->get_param( 'group_id' );
        $settings = $request->get_param( 'items' );

        return rest_ensure_response( ( new Processor() )->save_settings_group( $settings, $group_id ) );
    }

    /**
     * Update Store single settings.
     *
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function update_single_settings( $request ) {
        $group_id       = $request->get_param( 'group_id' );
        $settings_id    = $request->get_param( 'id' );
        $settings_value = $request->get_param( 'value' );

        return rest_ensure_response( ( new Processor() )->save_single_settings( $group_id, $settings_id, $settings_value ) );
    }

    /**
     * Update Store single settings field.
     *
     *
     * @param WP_REST_Request $request
     *
     * @return WP_Error|WP_REST_Response
     */
    public function update_single_settings_field( $request ) {
        $group_id             = $request->get_param( 'group_id' );
        $settings_id          = $request->get_param( 'parent_id' );
        $settings_field_id    = $request->get_param( 'id' );
        $settings_field_value = $request->get_param( 'value' );

        return rest_ensure_response( ( new Processor() )->save_single_settings_field( $group_id, $settings_id, $settings_field_id, $settings_field_value ) );
    }

    /**
     * @param $request
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function get_settings_list( $request ) {
        return rest_ensure_response( ( new Processor() )->get_settings_page_list() );
    }

    /**
     * @param $request
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function get_settings_group( $request ) {
        $group_id = $request->get_param( 'group_id' );
        return rest_ensure_response( ( new Processor() )->get_settings_group( $group_id ) );
    }

    /**
     * @param $request
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function get_single_settings( $request ) {
        $group_id = $request->get_param( 'group_id' );
        $id       = $request->get_param( 'id' );
        return rest_ensure_response( ( new Processor() )->get_single_settings( $group_id, $id ) );
    }

    /**
     * @param $request
     *
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function get_single_settings_field( $request ) {
        $group_id  = $request->get_param( 'group_id' );
        $parent_id = $request->get_param( 'parent_id' );
        $id        = $request->get_param( 'id' );
        return rest_ensure_response( ( new Processor() )->get_single_settings_field( $group_id, $parent_id, $id ) );
    }

    /**
     * Args for updating a single setting.
     *
     * @return array
     */
    private function update_single_settings_args() {
        $args = [
            'value' => [
                'type'        => [ 'string', 'number', 'boolean', 'object', 'array', 'null' ],
                'description' => __( 'Settings or fields value', 'sk-core' ),
                'required'    => true,
            ],
        ];
        return apply_filters( 'sk_vendor_rest_settings_child_args', $args );
    }

    /**
     * Args for updating setting group.
     *
     * @return array
     */
    private function update_settings_group_args() {
        $args = [
            'items' => [
                'type' => 'array',
                'required' => true,
                'items' => [
                    'type'   => 'object',
                    'properties' => [
                        'id' => [
                            'type'        => 'string',
                            'description' => __( 'Settings ID', 'sk-core' ),
                            'required'    => true,
                        ],
                        'value' => [
                            'type'        => [ 'string', 'number', 'boolean', 'object', 'array', 'null' ],
                            'description' => __( 'Settings value', 'sk-core' ),
                            'required'    => true,
                        ],
                    ],
                ],
            ],
        ];
        return apply_filters( 'sk_vendor_rest_settings_group_args', $args );
    }
}
