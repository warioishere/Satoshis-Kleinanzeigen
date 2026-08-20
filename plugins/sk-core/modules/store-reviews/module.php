<?php

namespace SK\Modules\StoreReviews;

use SK\Modules\StoreReviews\Emails\Manager as EmailManager;
use SK\Modules\StoreReviews\Manager as StoreReviewsManager;

class Module {

    /**
     * Constructor for the SK_Store_Reviews class
     *
     * Sets up all the appropriate hooks and actions
     * within our plugin.
     *
     * @uses register_activation_hook()
     * @uses register_deactivation_hook()
     * @uses is_admin()
     * @uses add_action()
     */
    public function __construct() {
        define( 'SK_SELLER_RATINGS_PLUGIN_VERSION', '1.1.0' );
        define( 'SK_SELLER_RATINGS_DIR', __DIR__ );
        define( 'SK_SELLER_RATINGS_PLUGIN_ASSEST', plugins_url( 'assets', __FILE__ ) );

        //hooks
        add_action( 'init', array( $this, 'register_sk_store_review_type' ) );
        add_action( 'sk_seller_rating_value', array( $this, 'replace_rating_value' ), 10, 2 );
        add_filter( 'sk_seller_tab_reviews_list', array( $this, 'replace_ratings_list' ), 10, 2 );

        $this->includes();
        $this->instances();

        // Loads frontend scripts and styles
        add_action( 'init', array( $this, 'register_scripts' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Register scripts
     *
     */
    public function register_scripts() {
        list( $suffix, $version ) = sk_get_script_suffix_and_version();

        wp_register_style( 'dsr-styles', plugins_url( 'assets/js/style' . $suffix . '.css', __FILE__ ), false, $version );
        wp_register_script( 'dsr-rateyo', plugins_url( 'assets/vendor/rateyo/rateyo.min.js', __FILE__ ), array( 'jquery' ), '2.1.1', true );
        wp_register_script( 'dsr-scripts', plugins_url( 'assets/js/script' . $suffix . '.js', __FILE__ ), array( 'jquery', 'dsr-rateyo' ), $version, true );
        wp_register_style( 'dsr-admin-styles', plugins_url( 'assets/js/admin' . $suffix . '.css', __FILE__ ), false, $version );
    }

    /**
     * Enqueue admin scripts.
     *
     * Allows plugin assets to be loaded.
     *
     * @uses wp_enqueue_script()
     * @uses wp_localize_script()
     * @uses wp_enqueue_style
     *
     * @return void
     */
    public function enqueue_scripts() {
        // Only load the scripts on store page for optimization.
        if ( sk_is_store_page() ) {
            wp_enqueue_style( 'dsr-styles' );
            wp_enqueue_script( 'dsr-scripts' );
        }

        if ( is_admin() ) {
            wp_enqueue_style( 'dsr-admin-styles' );
        }

        if ( sk_is_store_listing() ) {
            wp_enqueue_style( 'dsr-styles' );
        }
    }

    /**
     * Include files
     *
     * @return void
     */
    public function includes() {
        if ( is_admin() ) {
            require_once SK_SELLER_RATINGS_DIR . '/classes/admin.php';
        }
        require_once SK_SELLER_RATINGS_DIR . '/classes/Emails/Manager.php';
        require_once SK_SELLER_RATINGS_DIR . '/classes/DSR_View.php';
        require_once SK_SELLER_RATINGS_DIR . '/classes/DSR_SPMV.php';
    }

    public function instances() {
        new \DSR_SPMV();
        new EmailManager();
    }

    /**
     * REST API classes Mapping
     *
     *
     * @return void
     */

    /**
     * Register Custom Post type for Store Reviews
     *
     *
     * @return void
     */
    public function register_sk_store_review_type() {
        $labels = array(
            'name'               => _x( 'Store Reviews', 'Post Type General Name', 'sk-core' ),
            'singular_name'      => _x( 'Store Review', 'Post Type Singular Name', 'sk-core' ),
            'menu_name'          => __( 'Store Reviews', 'sk-core' ),
            'name_admin_bar'     => __( 'Store Reviews', 'sk-core' ),
            'parent_item_colon'  => __( 'Parent Item', 'sk-core' ),
            'all_items'          => __( 'All Reviews', 'sk-core' ),
            'add_new_item'       => __( 'Add New review', 'sk-core' ),
            'add_new'            => __( 'Add New', 'sk-core' ),
            'new_item'           => __( 'New review', 'sk-core' ),
            'edit_item'          => __( 'Edit review', 'sk-core' ),
            'update_item'        => __( 'Update review', 'sk-core' ),
            'view_item'          => __( 'View review', 'sk-core' ),
            'search_items'       => __( 'Search review', 'sk-core' ),
            'not_found'          => __( 'Not found', 'sk-core' ),
            'not_found_in_trash' => __( 'Not found in Trash', 'sk-core' ),
        );

        $args = array(
            'label'             => __( 'Store Reviews', 'sk-core' ),
            'description'       => __( 'Store Reviews by customer', 'sk-core' ),
            'labels'            => $labels,
            'supports'          => array( 'title', 'author', 'editor' ),
            'hierarchical'      => false,
            'public'            => false,
            'publicly_queryable' => true,
            'show_in_menu'      => false,
            'show_in_rest'      => true,
            'menu_position'     => 5,
            'show_in_admin_bar' => false,
            'rewrite'           => array( 'slug' => '' ),
            'can_export'        => true,
            'has_archive'       => true,
        );

        register_post_type( 'sk_store_reviews', $args );
    }

    /**
     * Filter rating calculation value
     *
     *
     * @param array $rating
     * @param int $store_id
     *
     * @return array calculated Rating
     */
    public function replace_rating_value( $rating, $store_id ) {
        $args = array(
            'seller_id'     => $store_id,
        );

        $manager = new StoreReviewsManager();
        $reviews = $manager->get_user_review( $args );

        if ( count( $reviews ) ) {
            $rating = 0;
            foreach ( $reviews as $review ) {
                $rating += intval( get_post_meta( $review->ID, 'rating', true ) );
            }

            $rating = number_format( $rating / count( $reviews ), 2 );
        } else {
            $rating = __( 'No Ratings found yet', 'sk-core' );
        }

        return array(
            'rating' => $rating,
            'count'  => count( $reviews ),
        );
    }

    /**
     * Filter the Review list shown on review tab by default core
     *
     *
     * @param string $review_list
     * @param int $store_id
     *
     * @return string Review List HTML
     */
    public function replace_ratings_list( $review_list, $store_id ) {
        $paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
        $args = [
            'author__not_in' => array( get_current_user_id(), $store_id ),
            'seller_id'      => $store_id,
            'paged'          => $paged,
            'per_page'       => 20,
        ];

        $namager = new StoreReviewsManager();
        $posts = $namager->get_user_review( $args );
        $no_review_msg = apply_filters( 'dsr_no_review_found_msg', __( 'No Reviews found', 'sk-core' ), $posts );
        ob_start();

        \DSR_View::init()->print_store_reviews( $posts, $no_review_msg );

        wp_reset_postdata();

        return ob_get_clean();
    }
}
