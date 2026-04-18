<?php

namespace SK\Core\Admin;

/**
 * SK tinyMce Shortcode Button class
 *
 */
class ShortcodesButton {

    /**
     * Constructor for shortcode class
     */
    public function __construct() {
        add_filter( 'mce_external_plugins', array( $this, 'enqueue_plugin_scripts' ) );
        add_filter( 'mce_buttons', array( $this, 'register_buttons_editor' ) );

        add_action( 'admin_enqueue_scripts', array( $this, 'localize_shortcodes' ), 90 );
    }

    /**
     * Generate shortcode array
     *
     */
    public function localize_shortcodes() {
        $screen = get_current_screen();

        $shortcodes = array(
            'sk-dashboard'            => array(
                'title'   => __( 'Vendor Dasboard', 'sk' ),
                'content' => '[sk-dashboard]',
            ),
            'sk-stores'               => array(
                'title'   => __( 'Stores List', 'sk' ),
                'content' => '[sk-stores]',
            ),
            'sk-best-selling-product' => array(
                'title'   => __( 'Best Selling Product', 'sk' ),
                'content' => '[sk-best-selling-product no_of_product="5" seller_id="" ]',
            ),
            'sk-top-rated-product'    => array(
                'title'   => __( 'Top Rated Products', 'sk' ),
                'content' => '[sk-top-rated-product]',
            ),
            'sk-my-orders' => array(
                'title'   => __( 'SK My Orders', 'sk' ),
                'content' => '[sk-my-orders]',
            ),
        );

        $assets_url = SK_CORE_ASSETS;

        if ( 'page' === $screen->post_type || 'product' === $screen->post_type ) {
            wp_localize_script( 'sk_pro_admin', 'sk_shortcodes', apply_filters( 'sk_button_shortcodes', $shortcodes ) );
            wp_add_inline_script( 'sk_pro_admin', "var sk_assets_url = \"{$assets_url}\";" );
        }
    }

    /**
     * * Singleton object
     *
     * @staticvar boolean $instance
     *
     * @return \self
     */
    public static function init() {
        static $instance = false;

        if ( ! $instance ) {
            $instance = new ShortcodesButton();
        }

        return $instance;
    }

    /**
     * Add button on Post Editor
     *
     *
     * @param array $plugin_array
     *
     * @return array
     */
    public function enqueue_plugin_scripts( $plugin_array ) {
        //enqueue TinyMCE plugin script with its ID.
        $screen = get_current_screen();

        list( $suffix ) = sk_get_script_suffix_and_version();


        return $plugin_array;
    }

    /**
     * Register tinyMce button
     *
     *
     * @param array $buttons
     *
     * @return array
     */
    public function register_buttons_editor( $buttons ) {
        //register buttons with their id.
        array_push( $buttons, 'sk_button' );

        return $buttons;
    }

}
