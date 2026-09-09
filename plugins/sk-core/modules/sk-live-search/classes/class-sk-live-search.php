<?php

// don't call the file directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SK_Live_Search_Widget class
 *
 * @extends WP_Widget
 * @class SK_Live_Search_Widget The class that registered a new widget
 * entire SK_Live_Search plugin
 */
class SK_Live_Search_Widget extends WP_Widget {

    /**
     * Instance key to keep track of the widget inside widget container in sk-lite
     *
     *
     * @var string
     */
    const INSTANCE_KEY = 'live_search__SK_Live_Search_Widget'; // Naming Structure: {module_slug}__{ClassName}

    /**
     * Constructor for the SK_Live_Search_Widget class
     *
     * @uses is_admin()
     */
    public function __construct() {
        parent::__construct(
            'dokna_product_search',
            __( 'SK Live Search', 'sk-core' ),
            array( 'description' => __( 'Search products live', 'sk-core' ) )
        );
    }

    /**
     * Front-end display of widget.
     *
     * @see WP_Widget::widget()
     *
     * @param array $args     Widget arguments.
     * @param array $instance Saved values from database.
     */
    public function widget( $args, $instance ) {
        if ( $args && is_array( $args ) ) {
            extract( $args, EXTR_SKIP ); // phpcs:ignore
        }

        $title              = isset( $instance['title'] ) ? apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base ) : '';
        $live_search_option = sk_get_option( 'live_search_option', 'sk_live_search_setting', 'old_live_search' );

        if ( 'old_live_search' === $live_search_option ) {
            $live_search_option_class = '';
        } else {
            $live_search_option_class = 'sk-ajax-search-suggestion';
        }

        wp_enqueue_style( 'sk-ls-custom-style' );

        echo isset( $before_widget ) ? $before_widget : '';

        if ( $title ) {
            echo $before_title . $title . $after_title;
        }
        ?>
        <div class="sk-product-search">
            <form role="search" method="get" class="ajaxsearchform ajaxsearchform-sk" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                <div class="input-group">
                    <input type="text" autocomplete="off" class="form-control sk-ajax-search-textfield <?php echo $live_search_option_class; ?>" value="<?php echo get_search_query(); ?>" name="s" placeholder="<?php echo __( 'Just type ...', 'sk-core' ); ?>" />
                    <span class="input-group-addon" id="sk-ls-ajax-cat-dropdown">
                        <?php
                        wp_dropdown_categories(
                            array(
                                'taxonomy' => 'product_cat',
                                'show_option_all' => __( 'All', 'sk-core' ),
                                'hierarchical' => true,
                                'hide_empty' => false,
                                'orderby' => 'name',
                                'order' => 'ASC',
                                'class' => 'orderby sk-ajax-search-category',
                                'walker' => new SK_LS_Walker_CategoryDropdown(),
                            )
                        );
                        ?>
                    </span>
                    <input type="hidden" name="sk-live-search-option" value="<?php echo $live_search_option; ?>" class="sk-live-search-option" id="sk-live-search-option">
                </div>
                <div id="sk-ajax-search-suggestion-result" class="sk-ajax-search-result">
                </div>
            </form>
        </div>
        <?php
        echo isset( $after_widget ) ? $after_widget : '';

        wp_enqueue_script( 'sk-ls-custom-js' );

    }

    /**
     * Back-end widget form.
     *
     * @see WP_Widget::form()
     *
     * @param array $instance Previously saved values from database.
     */
    public function form( $instance ) {
        if ( isset( $instance['title'] ) ) {
            $title = $instance['title'];
        } else {
            $title = __( 'Live Search', 'sk-core' );
        }
        ?>
        <p>
            <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php esc_html_e( 'Title:', 'sk-core' ); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
        </p>
        <?php
    }

    /**
     * Sanitize widget form values as they are saved.
     *
     * @see WP_Widget::update()
     *
     * @param array $new_instance Values just sent to be saved.
     * @param array $old_instance Previously saved values from database.
     *
     * @return array Updated safe values to be saved.
     */
    public function update( $new_instance, $old_instance ) {
        $instance = array();
        $instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';

        return $instance;
    }

} // class SK_Live_Search_Widget

/**
 * Create HTML dropdown list of Categories.
 *
 * @uses Walker
 */
class SK_LS_Walker_CategoryDropdown extends Walker {
    /**
     * @see Walker::$tree_type
     * @var string
     */
    public $tree_type = 'category';

    /**
     * @see Walker::$db_fields
     * @var array
     */
    public $db_fields = array(
        'parent' => 'parent',
        'id'     => 'term_id',
    );

    /**
     * Start the element output.
     *
     * @see Walker::start_el()
     *
     * @param string $output   Passed by reference. Used to append additional content.
     * @param object $category Category data object.
     * @param int    $depth    Depth of category. Used for padding.
     * @param array  $args     Uses 'selected' and 'show_count' keys, if they exist. @see wp_dropdown_categories()
     */
    public function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {
        $pad = str_repeat( '&nbsp;', $depth * 3 );

        $cat_name = apply_filters( 'list_cats', $category->name, $category );
        $output .= "\t<option class=\"level-$depth\" value=\"" . esc_attr( $category->slug ) . '"';

        $output .= '>';
        $output .= $pad . $cat_name;
        if ( $args['show_count'] ) {
            $output .= '&nbsp;&nbsp;(' . $category->count . ')';
        }
        $output .= "</option>\n";
    }
}
