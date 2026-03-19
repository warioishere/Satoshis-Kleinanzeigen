<?php

use SK\Core\Cache;

/**
 * Description of DSR_View
 *
 * @author weDevs
 */
class DSR_View {

    public function __construct() {
        add_action( 'wp_ajax_sk_store_rating_ajax_handler', array( $this, 'ajax_handler' ) );
        add_action( 'wp_ajax_nopriv_sk_store_rating_ajax_handler', array( $this, 'ajax_handler' ) );
        add_action( 'sk_after_store_lists_filter_category', array( $this, 'after_store_lists_filter_category' ), 15 );
    }

    /**
     * Initializes the DSR_View() class
     *
     * Checks for an existing DSR_View() instance
     * and if it doesn't find one, creates it.
     */
    public static function init() {
        static $instance = false;

        if ( !$instance ) {
            $instance = new DSR_View();
        }

        return $instance;
    }

    /**
     * Hnadles all ajax requests
     *
     *
     * @return void
     */
    function ajax_handler() {

        switch ( $_POST['data'] ) {

            case 'review_form':
                wp_send_json_success( $this->review_form() );
                break;

            case 'edit_review_form':
                wp_send_json_success( $this->edit_review_form() );
                break;

            case 'submit_review':
                    $this->submit_review();
                break;

            default:
                wp_send_json_success( '<div>Error!! try again!</div>' );
                break;
        }
    }

    /**
     * Render Review form
     *
     *
     * @return string
     */
    function review_form(){
        ob_start();
        include_once SK_SELLER_RATINGS_DIR.'/templates/add-review.php';
        return ob_get_clean();
    }

    /**
     * Render edit review form
     *
     *
     * @return string
     */
    function edit_review_form() {
        ob_start();
        include_once SK_SELLER_RATINGS_DIR.'/templates/edit-review.php';
        return ob_get_clean();
    }

    /**
     * Render add button for review
     *
     *
     * @param int $seller_id
     *
     * @return string
     */
    function render_add_review_button( $seller_id ) {
        ?>
        <div class="sk-review-wrapper" style="margin-bottom: 25px;">
            <button class='sk-btn sk-btn-sm sk-btn-theme add-review-btn' data-store_id ='<?php echo $seller_id ?>' ><?php _e(' Write a Review ', 'sk' ) ?></button>
        </div>
        <div class="sk-clearfix"></div>

        <?php
    }

    /**
     * Render edit button for review
     *
     *
     * @param int $seller_id
     * @param int $post_id
     *
     * @return string
     */
    function render_edit_review_button( $seller_id, $post_id ) {
        ?>
        <div class="sk-review-wrapper" style="margin-bottom: 25px;">
            <button class='sk-btn sk-btn-sm sk-btn-theme edit-review-btn' data-post_id='<?php echo esc_attr( $post_id ); ?>' data-store_id ='<?php echo esc_attr( $seller_id ); ?>' ><?php _e(' Edit', 'sk' ) ?></button>
        </div>
        <div class="sk-clearfix"></div>

        <?php
    }

    /**
     * Submit or Edit new review
     *
     *
     * @return void Success | Error
     */
    function submit_review() {

        parse_str( $_POST['form_data'], $postdata );

        if ( !wp_verify_nonce( $postdata['sk-seller-rating-form-nonce'], 'sk-seller-rating-form-action' ) ) {
            wp_send_json( array(
                'success' => false,
                'msg'     => __( 'Nonce verification failed, please refresh current page and try again!.', 'sk' ),
            ) );
        }

        //check if valid customer to proceed
        if ( !$this->check_if_valid_customer( $postdata['store_id'], get_current_user_id() ) ) {
            wp_send_json( array(
                'success' => false,
                'msg'     => is_user_logged_in()
                    ?  __( 'Sorry, You must be logged in to leave a review!', 'sk' )
                    : __( 'Sorry, You need to be a verified owner to leave a review.' ),
            ) );
        }

        $rating = intval ( $_POST['rating'] );

        $my_post = array(
            'post_title'     => sanitize_text_field( $postdata['sk-review-title'] ),
            'post_content'   => wp_kses_post( $postdata['sk-review-details'] ),
            'author'         => get_current_user_id(),
            'post_type'      => 'sk_store_reviews',
            'post_status'    => 'publish'
        );

        if ( isset( $postdata[ 'post_id' ] ) ) {
            $post_id = intval( $postdata[ 'post_id' ] );
            $post    = get_post( $post_id );

            if ( get_current_user_id() == $post->post_author ) {
                $my_post[ 'ID' ] = $post->ID;
                $post_id = wp_update_post( $my_post );
            } else {
                $post_id = 0;
            }

        } else {
            $post_id = wp_insert_post( $my_post );
        }

        if ( $post_id ) {
            update_post_meta( $post_id, 'store_id', $postdata['store_id'] );
            update_post_meta( $post_id, 'rating', $rating );

            Cache::invalidate_group( 'store_reviews' );

            /**
             * This hook will call After successfully saved store review.
             *
             * @param int   $post_id
             * @param array $postdata
             * @param int   $rating
             *
             */
            WC_Emails::instance();
            do_action( 'sk_store_review_saved', $post_id, $postdata, $rating );

            wp_send_json( array(
                'success' => true,
                'msg'     => __( 'Thank you for your review.', 'sk' ),
            ) );
        } else {
            wp_send_json( array(
                'success' => false,
                'msg'     => __( 'Sorry, something went wrong!', 'sk' ),
            ) );
        }
    }

    public function print_store_reviews( $posts, $msg ) {
        // Print current user review or print add review button
        list( $current_user_review, $seller_id ) = $this->get_current_user_review();
        if ( count( $current_user_review ) ) {
            ?>
            <ol class="commentlist" id="sk-store-review-single">
                <?php echo $this->get_review_list( $current_user_review );?>
            </ol>
            <?php
        } elseif( $seller_id != sk_get_current_user_id() && $this->check_if_valid_customer( $seller_id, get_current_user_id() ) ) {
            $this->render_add_review_button( $seller_id );
        }

        // Print all reviews.
        echo $this->get_review_list( $posts );

        // Print no review found message.
        if ( ! count( $current_user_review) && ! count( $posts ) ){
            echo '<span colspan="5">' . $msg . '</span>';
        }
    }

    /**
     * Render review list for store by all customer
     *
     *
     * @param object $posts
     *
     * @return String List of reviews
     */
    public function get_review_list( $posts ) {
        ob_start();

        foreach ( $posts as $review ) {
            $review_timestamp  = sk_current_datetime()->setTimezone( new DateTimeZone( 'UTC' ) )->modify( $review->post_date_gmt )->getTimestamp();
            $review_date       = sk_format_date( $review_timestamp );
            $user_info         = get_userdata( $review->post_author );
            $review_author_img = get_avatar( $user_info->user_email, 180 );
            $permalink         = '';
            $author_name       = $user_info->display_name ? $user_info->display_name : $user_info->user_nicename;

            $rating = get_post_meta( $review->ID, 'rating', true );
            ?>
            <li itemtype="http://schema.org/Review" itemscope="" itemprop="reviews">
                        <div class="review_comment_container">
                            <div class="sk-review-author-img"><?php echo $review_author_img; ?></div>
                            <div class="comment-text">
                                <a href="<?php echo $permalink; ?>">
                                        <div class="sk-rating">
                                            <div itemprop="reviewRating" itemscope itemtype="http://schema.org/Rating" class="star-rating" title="<?php echo sprintf( __( 'Rated %d out of 5', 'sk' ), $rating ) ?>">
                                                <span style="width:<?php echo ( intval( $rating ) / 5 ) * 100; ?>%"><strong itemprop="ratingValue"><?php echo $rating; ?></strong> <?php _e( 'out of 5', 'sk' ); ?></span>
                                            </div>
                                        </div>
                                </a>
                                <p>
                                    <strong itemprop="author"><?php echo $author_name ?></strong>
                                    <em class="verified"><?php //echo $single_comment->user_id == 0 ? '(Guest)' : ''; ?></em>
                                    –
                                    <a href="<?php echo $permalink; ?>">
                                        <time datetime="<?php echo date( 'c', strtotime( $review_date ) ); ?>" itemprop="datePublished"><?php echo $review_date; ?></time>
                                    </a>
                                </p>
                                <div class="description" itemprop="description">
                                    <h4><?php echo $review->post_title ?></h4>
                                    <p><?php echo $review->post_content ?></p>
                                </div>
                                <?php
                                    if ( get_current_user_id() == $review->post_author ) {
                                        $seller_id = get_post_meta( $review->ID, 'store_id', true );
                                        ob_start();
                                        $this->render_edit_review_button( $seller_id, $review->ID );
                                        ob_get_flush();
                                    }
                                ?>
                            </div>
                        </div>
                    </li>
            <?php
        }

        return ob_get_clean();
    }

    /**
     * Render Single Review Section
     *
     *
     * @return array
     */
    function get_current_user_review() {
        $user_data = get_userdata( get_query_var( 'author' ) );
        if ( false === $user_data ) {
            return [ [], 0 ];
        }

        $seller_id       = $user_data->ID;
        $current_user_id = get_current_user_id();

        //check if valid customer to proceed
        if ( ! $this->check_if_valid_customer( $seller_id, $current_user_id ) ) {
            return [ [], $seller_id ];
        }

        //get review given by current user for this store
        $args = array(
            'post_type'   => 'sk_store_reviews',
            'meta_key'    => 'store_id',
            'meta_value'  => $seller_id,
            'author'      => $current_user_id,
            'post_status' => 'publish'
        );

        $query = new WP_Query( $args );

        return [  $query->posts, $seller_id ];
    }

    /**
     * Check if Customer has bought any product for this seller
     *
     *
     * @param int $seller_id
     *
     * @param int $customer_id
     *
     * @return bool
     */
    function check_if_valid_customer( $seller_id, $customer_id ) {

        if ( ! is_user_logged_in() ) {
            return false;
        }

        if ( get_option( 'woocommerce_review_rating_verification_required' ) === 'no' ) {
            return true;
        }

        $order = wc_get_orders( 
            [
                'customer_id' => $customer_id,
                'seller_id'   => $seller_id,
                'status'      => 'wc-completed',
                'limit'       => 1,
                'return'      => 'ids',
            ]
        );

        return ! empty( $order );
    }

    /**
     * Include store lists filter category template
     *
     *
     * @return void
     */
    public function after_store_lists_filter_category() {
        include_once SK_SELLER_RATINGS_DIR . '/templates/store-ratings.php';
    }
}

$dsr_view = DSR_View::init();
