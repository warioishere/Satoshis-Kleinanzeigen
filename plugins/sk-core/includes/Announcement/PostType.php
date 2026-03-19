<?php

namespace SK\Core\Announcement;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Announcement Post Type
 *
 */
class PostType {
    /*
     * Post type name
     *
     *
     * @var string
     */
    private $post_type = 'sk_announcement';

    /**
     * Class constructor
     *
     */
    public function __construct() {
        add_action( 'init', [ $this, 'register_post_type' ],20 );
    }

    /**
     * Register announcement post type
     *
     *
     * @return void
     */
    public function register_post_type() {
        register_post_type(
            $this->post_type, array(
                'label'           => __( 'Announcement', 'sk' ),
                'description'     => '',
                'public'          => false,
                'show_ui'         => true,
                'show_in_menu'    => false,
                'capability_type' => 'post',
                'hierarchical'    => false,
                'rewrite'         => array( 'slug' => '' ),
                'query_var'       => false,
                'supports'        => array( 'title', 'editor' ),
                'labels'          => array(
                    'name'               => __( 'Announcement', 'sk' ),
                    'singular_name'      => __( 'Announcement', 'sk' ),
                    'menu_name'          => __( 'SK Announcement', 'sk' ),
                    'add_new'            => __( 'Add Announcement', 'sk' ),
                    'add_new_item'       => __( 'Add New Announcement', 'sk' ),
                    'edit'               => __( 'Edit', 'sk' ),
                    'edit_item'          => __( 'Edit Announcement', 'sk' ),
                    'new_item'           => __( 'New Announcement', 'sk' ),
                    'view'               => __( 'View Announcement', 'sk' ),
                    'view_item'          => __( 'View Announcement', 'sk' ),
                    'search_items'       => __( 'Search Announcement', 'sk' ),
                    'not_found'          => __( 'No Announcement Found', 'sk' ),
                    'not_found_in_trash' => __( 'No Announcement found in trash', 'sk' ),
                    'parent'             => __( 'Parent Announcement', 'sk' ),
                ),
            )
        );
    }
}
