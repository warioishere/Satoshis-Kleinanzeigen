<?php

namespace SK\Core\Admin;

/**
 * Adds and controls pointers for contextual help/tutorials
 *
 *
 * @author   weDevs
 *
 * @category Admin
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SK_Admin_Pointers Class.
 */
class Pointers {

    /**
     * Hold current screen ID
     *
     * @var integer
     */
    private $screen_id;

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'setup_pointers_for_screen' ), 20 );
        add_action( 'wp_ajax_sk-dismiss-wp-pointer', array( $this, 'dismiss_screen' ) );
    }

    /**
     * Dismiss a screen pointers after clicking dismiss
     *
     * @param String $screen
     *
     * @return void
     */
    public function dismiss_screen( $screen = false ) {
        $screen = isset( $_POST['screen'] ) ? sanitize_text_field( wp_unslash( $_POST['screen'] ) ) : $screen; // phpcs:ignore.

        if ( ! $screen ) {
            return;
        }

        update_option( 'sk_pointer_' . $screen, true );
    }

    /**
     * Check if pointers for screen is dismissed
     *
     * @param String $screen
     *
     * @return bool
     */
    public function is_dismissed( $screen ) {
        return get_option( 'sk_pointer_' . $screen, false );
    }

    /**
     * Setup pointers for screen.
     */
    public function setup_pointers_for_screen() {
        if ( ! $screen = get_current_screen() ) { // phpcs:ignore
            return;
        }

        $this->screen_id = $screen->id;

        switch ( $screen->id ) {
            case 'toplevel_page_sk':
                $this->dashboard_tutorial();
                break;

            case 'sk_page_sk-settings':
                $this->settings_tutorial();
                break;
        }

        do_action( 'sk_after_pointer_setup', $screen, $this );
    }

    /**
     *  Render pointers on Dashboard Page
     */
    public function dashboard_tutorial() {
        if ( $this->is_dismissed( $this->screen_id ) ) {
            return;
        }

        $pointers = array(
            'pointers' => array(
                'title'    => array(
                    'target'       => '.at-glance',
                    'next'         => 'overview',
                    'next_trigger' => array(
                        'target' => '.next',
                        'event'  => 'click',
                    ),
                    'options'      => array(
                        'content'  => '<h3>' . esc_html__( 'Important Details At a Glance', 'sk-core' ) . '</h3>' .
                        '<p>' . esc_html__( 'View the status of your marketplace including vendors from here.', 'sk-core' ) . '</p>',
                        'position' => array(
                            'edge'  => 'top',
                            'align' => 'left',
                        ),
                    ),
                    'next_button'  => "<button class='next button button-primary right'>" . __( 'Next', 'sk-core' ) . '</button>',
                ),
                'overview' => array(
                    'target'       => '.overview',
                    'next'         => 'updates',
                    'next_trigger' => array(
                        'target' => '.next',
                        'event'  => 'click',
                    ),
                    'options'      => array(
                        'content'  => '<h3>' . esc_html__( 'Your Sales Overview', 'sk-core' ) . '</h3>' .
                        '<p>' . esc_html__( 'Get a complete overview of your sales and orders.', 'sk-core' ) . '</p>',
                        'position' => array(
                            'edge'  => 'top',
                            'align' => 'left',
                        ),
                    ),
                    'next_button'  => "<button class='next button button-primary right'>" . __( 'Next', 'sk-core' ) . '</button>',
                ),
                'updates'  => array(
                    'target'       => '.news-updates',
                    'next'         => '',
                    'next_trigger' => array(),
                    'options'      => array(
                        'content'  => '<h3>' . esc_html__( 'News & Updates', 'sk-core' ) . '</h3>' .
                        '<p>' . esc_html__( 'Get all the latest news and updates of SK from here.', 'sk-core' ) . '</p>',
                        'position' => array(
                            'edge'  => 'top',
                            'align' => 'left',
                        ),
                    ),
                ),
            ),
        );

        $this->enqueue_pointers( apply_filters( 'sk_pointer_' . $this->screen_id, $pointers ) );

        $this->dismiss_screen( $this->screen_id );
    }

    /**
     * Renders Settings tutorial pointers
     *
     * @return void
     */
    public function settings_tutorial() {
        if ( $this->is_dismissed( $this->screen_id ) ) {
            return;
        }

        $pointers = array(
            'pointers' => array(
                'general'  => array(
                    'target'       => '#sk_general-tab',
                    'next'         => 'selling',
                    'next_trigger' => array(
                        'target' => '.next',
                        'event'  => 'click',
                    ),
                    'options'      => array(
                        'content'  => '<h3>' . esc_html__( 'General Settings', 'sk-core' ) . '</h3>' .
                        '<p>' . esc_html__( 'Configure all general settings for your marketplace from this tab.', 'sk-core' ) . '</p>',
                        'position' => array(
                            'edge'  => 'top',
                            'align' => 'left',
                        ),
                    ),
                    'next_button'  => "<button class='next button button-primary right'>" . __( 'Next', 'sk-core' ) . '</button>',
                ),
                'selling'  => array(
                    'target'       => '#sk_selling-tab',
                    'next'         => 'pages',
                    'next_trigger' => array(
                        'target' => '.next',
                        'event'  => 'click',
                    ),
                    'options'      => array(
                        'content'  => '<h3>' . esc_html__( 'Selling Options', 'sk-core' ) . '</h3>' .
                        '<p>' . esc_html__( 'You can configure different selling options for your vendors', 'sk-core' ) . '</p>',
                        'position' => array(
                            'edge'  => 'top',
                            'align' => 'left',
                        ),
                    ),
                    'next_button'  => "<button class='next button button-primary right'>" . __( 'Next', 'sk-core' ) . '</button>',
                ),
                'pages'    => array(
                    'target'  => '#sk_pages-tab',
                    'next'    => '',
                    'options' => array(
                        'content'  => '<h3>' . esc_html__( 'SK Pages', 'sk-core' ) . '</h3>' .
                        '<p>' . esc_html__( 'SK requires some pages to be configured and you can set them up here', 'sk-core' ) . '</p>',
                        'position' => array(
                            'edge'  => 'top',
                            'align' => 'left',
                        ),
                    ),
                ),
            ),
        );

        $this->enqueue_pointers( apply_filters( 'sk_pointer_' . $this->screen_id, $pointers ) );

        $this->dismiss_screen( $this->screen_id );
    }

    /**
     * Enqueue pointers and add script to page.
     * @param array $pointers
     */
    public function enqueue_pointers( $pointers ) {
        wp_enqueue_style( 'wp-pointer' );
        wp_enqueue_script( 'wp-pointer' );

        wp_register_script( 'sk-pointers', SK_CORE_ASSETS . '/js/pointers.js', array( 'wp-pointer' ), SK_CORE_VERSION, true );
        wp_enqueue_script( 'sk-pointers' );

        $data = array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'screen'  => $this->screen_id,
        );

        wp_localize_script( 'sk-pointers', 'SK_Pointers', $pointers );
        wp_localize_script( 'sk-pointers', 'sk_pointer_data', $data );
    }
}
