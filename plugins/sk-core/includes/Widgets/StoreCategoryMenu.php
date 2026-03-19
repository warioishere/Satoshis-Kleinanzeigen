<?php

namespace SK\Core\Widgets;

use WP_Widget;

class StoreCategoryMenu extends WP_Widget {

    /**
     * Constructor
     *
     * @return void
     **/
    public function __construct() {
        $widget_ops = array(
			'classname' => 'sk-store-menu',
			'description' => __( 'SK Seller Store Menu', 'sk-core' ),
		);
        parent::__construct( 'sk-store-menu', __( 'SK: Store Product Category Menu', 'sk-core' ), $widget_ops );
    }

    /**
     * Outputs the HTML for this widget.
     *
     * @param array $args       An array of standard parameters for widgets in this theme
     * @param array $instance   An array of settings for this widget instance
     * @return void Echoes it's output
     **/
    public function widget( $args, $instance ) {
        if ( sk_is_store_page() ) {
            // load frontend script
            wp_enqueue_script( 'sk-frontend' );

            echo wp_kses_post( $args['before_widget'] );

            $defaults = array(
                'title' => __( 'Store Product Category', 'sk-core' ),
            );

            $instance = wp_parse_args( $instance, $defaults );

            $title      = isset( $instance['title'] ) ? apply_filters( 'widget_title', $instance['title'] ) : '';
            $seller_id  = (int) get_query_var( 'author' );

            if ( ! empty( $title ) ) {
                echo wp_kses_post( $args['before_title'] . $title . $args['after_title'] );
            }

            sk_store_category_menu( $seller_id, $title );

            echo wp_kses_post( $args['after_widget'] );
        }

        do_action( 'sk_widget_store_categories_render', $args, $instance, $this );
    }

    /**
     * Deals with the settings when they are saved by the admin. Here is
     * where any validation should be dealt with.
     *
     * @param array $new_instance   An array of new settings as submitted by the admin
     * @param array $old_instance   An array of the previous settings
     * @return array The validated and (if necessary) amended settings
     **/
    public function update( $new_instance, $old_instance ) {

        // update logic goes here
        $updated_instance = $new_instance;
        return $updated_instance;
    }

    /**
     * Displays the form for this widget on the Widgets page of the WP Admin area.
     *
     * @param array $instance An array of the current settings for this widget
     * @return void Echoes it's output
     **/
    public function form( $instance ) {
        $instance = wp_parse_args(
            (array) $instance, array(
				'title' => __( 'Store Product Category', 'sk-core' ),
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
