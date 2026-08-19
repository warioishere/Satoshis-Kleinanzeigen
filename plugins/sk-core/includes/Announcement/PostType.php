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
                'label'           => __( 'Announcement', 'sk-core' ),
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
                    'name'               => __( 'Announcement', 'sk-core' ),
                    'singular_name'      => __( 'Announcement', 'sk-core' ),
                    'menu_name'          => __( 'SK Announcement', 'sk-core' ),
                    'add_new'            => __( 'Add Announcement', 'sk-core' ),
                    'add_new_item'       => __( 'Add New Announcement', 'sk-core' ),
                    'edit'               => __( 'Edit', 'sk-core' ),
                    'edit_item'          => __( 'Edit Announcement', 'sk-core' ),
                    'new_item'           => __( 'New Announcement', 'sk-core' ),
                    'view'               => __( 'View Announcement', 'sk-core' ),
                    'view_item'          => __( 'View Announcement', 'sk-core' ),
                    'search_items'       => __( 'Search Announcement', 'sk-core' ),
                    'not_found'          => __( 'No Announcement Found', 'sk-core' ),
                    'not_found_in_trash' => __( 'No Announcement found in trash', 'sk-core' ),
                    'parent'             => __( 'Parent Announcement', 'sk-core' ),
                ),
            )
        );
    }
}
