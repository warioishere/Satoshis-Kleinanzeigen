<?php

/**
 * Geolocation Module Widget Filter Form
 *
 */
class SK_Geolocation_Widget_Filters extends WP_Widget {

    /**
     * Instance key to keep track of the widget inside widget container in sk-lite
     *
     *
     * @var string
     */
    const INSTANCE_KEY = 'geolocation__SK_Geolocation_Widget_Filters'; // Naming Structure: {module_slug}__{ClassName}

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        $widget_ops = array(
            'classname'   => 'sk-geolocation-widget-filters',
            'description' => __( 'Show SK Geolocation Filter Form', 'sk' ),
        );

        parent::__construct( 'sk-geolocation-widget-filters', __( 'SK: Geolocation Filter Form', 'sk' ), $widget_ops );
    }

    /**
     * Widget settings form in widget settings
     *
     *
     * @param array $instance
     *
     * @return void
     */
    public function form( $instance ) {
        $instance = wp_parse_args( (array) $instance, array(
            'scope'   => 'both',
            'display' => 'inline',
        ) );

        $args = array(
            'scope_id'     => $this->get_field_id( 'scope' ),
            'scope_name'   => $this->get_field_name( 'scope' ),
            'scope'        => $instance['scope'],
            'display_id'   => $this->get_field_id( 'display' ),
            'display_name' => $this->get_field_name( 'display' ),
            'display'      => $instance['display'],
        );

        sk_geo_get_template( 'widget-form', $args );
    }

    /**
     * Update or save widget settings
     *
     *
     * @param array $new_instance
     * @param array $old_instance
     *
     * @return array
     */
    public function update( $new_instance, $old_instance ) {
        $scope   = in_array( $new_instance['scope'], array( 'both', 'product', 'vendor' ) ) ? $new_instance['scope'] : 'product';
        $display = ( 'inline' !== $new_instance['display'] ) ? '' : 'inline';

        return array(
            'scope'   => $scope,
            'display' => $display,
        );
    }

    /**
     * Display widget in frontend
     *
     *
     * @param array $args
     * @param array $instance
     *
     * @return void
     */
    public function widget( $args, $instance ) {
        if ( is_array( $args ) ) {
            extract( $args, EXTR_SKIP );
        }

        echo $before_widget;

        $scope   = isset( $instance['scope'] ) && in_array( $instance['scope'], [ 'product', 'vendor' ], true ) ? $instance['scope'] : '';
        $display = isset( $instance['display'] ) && 'inline' === $instance['display'] ? 'inline' : '';

        sk_geo_filter_form( $scope, $display );

        echo $after_widget;
    }
}
