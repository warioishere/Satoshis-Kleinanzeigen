<?php

namespace SK\Modules\StoreReviews;

use SK\Core\Cache;
use WP_Query;

class Manager {

    /**
     * Create or update a store review
     *
     *
     * @param int   $store_id
     * @param array $data
     *
     * @return int|\WP_Error
     */
    public function save_store_review( $store_id, $data ) {
        $postarr = array(
            'post_title'     => $data['title'],
            'post_content'   => $data['content'],
            'author'         => $data['reviewer_id'],
            'post_type'      => 'sk_store_reviews',
            'post_status'    => 'publish'
        );

        if ( ! empty( $data[ 'id' ] ) ) {
            $post                    = get_post( $data['id'] );
            $current_user_id         = get_current_user_id();
            $user_can_manage_reviews = current_user_can( 'sk_manage_reviews' );

            if ( $user_can_manage_reviews || $current_user_id === absint( $post->post_author ) ) {
                $postarr[ 'ID' ] = $post->ID;
                $post_id         = wp_update_post( $postarr );
            } else {
                $post_id = 0;
            }
        } else {
            $post_id = wp_insert_post( $postarr );
        }

        if ( ! is_wp_error( $post_id ) ) {
            update_post_meta( $post_id, 'store_id', $store_id );

            $rating = isset( $data['rating'] ) ? absint( $data['rating'] ) : 0;
            update_post_meta( $post_id, 'rating', $rating );

            Cache::invalidate_group( 'store_reviews' );
        }

        return $post_id;
    }

    /**
     * Returns users store review.
     *
     *
     * @param array $args
     *
     * @return array
     */
    public function get_user_review( $args ) {
        $query_args = array(
            'post_type'      => 'sk_store_reviews',
            'post_status'    => 'publish',
        );

        if ( ! empty( $args['seller_id'] ) ) {
            $query_args['meta_key'] = 'store_id';
            $query_args['meta_value'] = $args['seller_id'];
        }

        if ( ! empty( $args['author__not_in'] ) ) {
            $query_args['author__not_in'] = $args['author__not_in'];
        } elseif ( ! empty( $args['author'] ) ) {
            $query_args['author'] = $args['author'];
        }

        if ( ! empty( $args['paged'] ) ) {
            $query_args['paged'] = $args['paged'];
        }

        if ( ! empty( $args['per_page'] ) ) {
            $query_args['posts_per_page'] = $args['per_page'];
        }

        //show add review or edit review
        $query = new WP_Query( $query_args );

        return $query->posts;
    }

    /**
     * Check if Customer has bought any product for this seller
     *
     *
     * @param int $seller_id
     *
     * @param int $customer_id
     *
     * @return boolean
     */
    public function check_if_valid_customer( $seller_id, $customer_id ) {

        if ( ! is_user_logged_in() ) {
            return false;
        }

        if ( 'no' === get_option( 'woocommerce_review_rating_verification_required' ) ) {
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
}
