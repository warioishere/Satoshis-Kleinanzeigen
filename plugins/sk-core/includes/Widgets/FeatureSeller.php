<?php

namespace SK\Core\Widgets;

use WP_Widget;

/**
 * SK Featured Seller Widget
 *
 * Note: this is `featured` seller not `feature` seller
 *
 *
 */
class FeatureSeller extends WP_Widget {

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct() {
        $widget_ops = array(
			'classname' => 'sk-feature-seller-widget',
			'description' => 'SK featured vendor widget',
		);
        parent::__construct( 'sk-feature-seller-widget', 'SK: Featured Vendors', $widget_ops );
    }

    /**
     * Outputs the HTML for this widget.
     *
     * @param array $args An array of standard parameters for widgets in this theme.
     * @param array $instance An array of settings for this widget instance.
     *
     * @return void Echoes it's output
     */
    public function widget( $args, $instance ) {
        if ( empty( $args ) ) {
            return;
        }

        extract( $args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

        $title = isset( $instance['title'] ) ? apply_filters( 'widget_title', $instance['title'] ) : '';
        $limit = isset( $instance['count'] ) ? absint( $instance['count'] ) : 10;

        $sellers = sk_get_feature_sellers( $limit );

        echo $before_widget;

        if ( ! empty( $title ) ) {
            echo $before_title . $title . $after_title;
        }

        sk_get_template_part(
            'widgets/feature-seller', '', array(
				'pro' => true,
				'sellers' => $sellers,
            )
        );

        echo $after_widget;
    }

    /**
     * Deals with the settings when they are saved by the admin. Here is
     * where any validation should be dealt with.
     *
     * @param array $new_instance An array of new settings as submitted by the admin.
     * @param array $old_instance An array of the previous settings.
     *
     * @return array The validated and (if necessary) amended settings.
     */
    public function update( $new_instance, $old_instance ) {
        $updated_instance = $new_instance;
        return $updated_instance;
    }

    /**
     * Displays the form for this widget on the Widgets page of the WP Admin area.
     *
     * @param array $instance An array of the current settings for this widget.
     *
     * @return void Echoes it's output
     */
    public function form( $instance ) {
        $instance = wp_parse_args(
            (array) $instance, array(
				'title' => __( 'Featured Vendor', 'sk' ),
				'count' => __( '3', 'sk' ),
            )
        );

        $title = $instance['title'];
        $count = $instance['count'];
        ?>
        <p>
            <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php esc_html_e( 'Title:', 'sk' ); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id( 'count' ); ?>"><?php esc_html_e( 'No of Vendor:', 'sk' ); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'count' ); ?>" name="<?php echo $this->get_field_name( 'count' ); ?>" type="text" value="<?php echo esc_attr( $count ); ?>" />
        </p>
        <?php
    }
}
