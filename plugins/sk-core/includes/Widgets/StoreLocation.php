<?php

namespace SK\Core\Widgets;

use WP_Widget;

/**
 * SK Store Location Widget
 *
 *
 */
class StoreLocation extends WP_Widget {

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct() {
        $widget_ops = array(
			'classname' => 'sk-store-location',
			'description' => __( 'SK Vendor Store Location', 'sk-core' ),
		);
        parent::__construct( 'sk-store-location', __( 'SK: Store Location', 'sk-core' ), $widget_ops );
    }

    /**
     * Outputs the HTML for this widget.
     *
     * @param array  An array of standard parameters for widgets in this theme
     * @param array  An array of settings for this widget instance
     *
     * @return void Echoes it's output
     */
    public function widget( $args, $instance ) {
        if ( sk_is_store_page() ) {
            $defaults = array(
                'title' => __( 'Store Location', 'sk-core' ),
            );

            $instance = wp_parse_args( $instance, $defaults );

            $title        = isset( $instance['title'] ) ? apply_filters( 'widget_title', $instance['title'] ) : '';
            $store_info   = sk_get_store_info( get_query_var( 'author' ) );
            $map_location = isset( $store_info['location'] ) ? esc_attr( $store_info['location'] ) : '';

            if ( empty( $map_location ) || ! sk_has_map_api_key() || 'on' !== sk_get_option( 'store_map', 'sk_appearance', 'off' ) ) {
                return;
            }

            echo wp_kses_post( $args['before_widget'] );

            if ( ! empty( $title ) ) {
                echo wp_kses_post( $args['before_title'] . $title . $args['after_title'] );
            }
            do_action( 'sk_store_widget_before_map', $store_info );

            sk_get_template_part(
                'widgets/store-map', '', array(
					'store_info' => $store_info,
					'map_location' => $map_location,
                )
            );

            do_action( 'sk_store_widget_after_map', $store_info );

            echo wp_kses_post( $args['after_widget'] );
        }

        do_action( 'sk_widget_store_location_render', $args, $instance, $this );
    }

    /**
     * Deals with the settings when they are saved by the admin. Here is
     * where any validation should be dealt with.
     *
     * @param array $new_instance array of new settings as submitted by the admin
     * @param array $old_instance array of the previous settings
     *
     * @return array The validated and (if necessary) amended settings
     */
    public function update( $new_instance, $old_instance ) {

        // update logic goes here
        $updated_instance = $new_instance;
        return $updated_instance;
    }

    /**
     * Displays the form for this widget on the Widgets page of the WP Admin area.
     *
     * @param array $instance array of the current settings for this widget
     *
     * @return void Echoes it's output
     */
    public function form( $instance ) {
        $instance = wp_parse_args(
            (array) $instance, array(
				'title' => __( 'Store Location', 'sk-core' ),
            )
        );

        $title = $instance['title'];
        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'sk-core' ); ?></label>
            <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
        </p>
        <?php
    }
}
