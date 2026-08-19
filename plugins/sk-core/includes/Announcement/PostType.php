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
        // Announcements are written on the SK admin page, not in the post editor.
        // With the generic `post` capabilities every role holding `edit_posts` could
        // create them and every role holding `edit_others_posts` (Editor) could read,
        // rewrite and delete them, so both the UI and the caps are locked down here.
        // Only primitive capabilities belong here. Mapping the meta caps
        // edit_post/read_post/delete_post registers the target as a meta-cap alias
        // in the global $post_type_meta_caps, which makes every
        // current_user_can( 'manage_woocommerce' ) recurse into a post check
        // without a post id and fail. With map_meta_cap => true WordPress derives
        // the meta caps from the primitives below anyway.
        $capabilities = array_fill_keys(
            array(
                'edit_posts',
                'edit_others_posts',
                'delete_posts',
                'delete_others_posts',
                'delete_private_posts',
                'delete_published_posts',
                'edit_private_posts',
                'edit_published_posts',
                'publish_posts',
                'read_private_posts',
                'create_posts',
            ),
            'manage_woocommerce'
        );

        register_post_type(
            $this->post_type, array(
                'label'           => __( 'Announcement', 'sk-core' ),
                'description'     => '',
                'public'          => false,
                'show_ui'         => false,
                'show_in_menu'    => false,
                'capability_type' => 'post',
                'capabilities'    => $capabilities,
                'map_meta_cap'    => true,
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
