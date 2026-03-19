<?php

use SK\Core\Cache;

/**
 * Include Follow Store template
 *
 *
 * @param string $name
 * @param array  $args
 *
 * @return void
 */
function sk_follow_store_get_template( $name, $args = [] ) {
    sk_get_template( "$name.php", $args, SK_FOLLOW_STORE_VIEWS, trailingslashit( SK_FOLLOW_STORE_VIEWS ) );
}

/**
 * Follow button labels
 *
 *
 * @return array
 */
function sk_follow_store_button_labels() {
    /**
     * Filter to change the follow button label when not following
     *
     *
     * @param $string
     */
    return array(
        'follow'    => 'Folgen',
        'following' => 'Folge ich',
        'unfollow'  => 'Nicht mehr folgen',
    );
}

/**
 * Toggle store follow status for a customer
 *
 *
 * @param int $vendor_id   Vendor WP User ID
 * @param int $follower_id Follower WP User ID
 *
 * @return string Follow status
 */
function sk_follow_store_toggle_status( $vendor_id, $follower_id ) {
    global $wpdb;

    $result = $wpdb->get_row( $wpdb->prepare(
          "select *"
        . " from {$wpdb->prefix}sk_follow_store_followers"
        . " where vendor_id = %d and follower_id = %d"
        . " limit 1",
        $vendor_id,
        $follower_id
    ) );

    $current_time = current_time( 'mysql' );

    if ( empty( $result ) ) {
        $wpdb->insert(
            "{$wpdb->prefix}sk_follow_store_followers",
            array(
                'vendor_id'   => $vendor_id,
                'follower_id' => $follower_id,
                'followed_at' => $current_time,
            ),
            array(
                '%d', '%d', '%s'
            )
        );

        $status = 'following';

    } else {
        if ( $result->unfollowed_at ) {
            $status = 'following';

            $data = array(
                'followed_at'   => $current_time,
                'unfollowed_at' => null
            );

            $format = array( '%s', '%s' );

        } else {
            $status = 'unfollowed';

            $data = array(
                'unfollowed_at' => $current_time,
            );

            $format = array( '%s' );
        }

        $wpdb->update(
            "{$wpdb->prefix}sk_follow_store_followers",
            $data,
            array(
                'vendor_id'   => $vendor_id,
                'follower_id' => $follower_id,
            ),
            $format,
            array(
                '%d', '%d'
            )
        );
    }

    /**
     * Action hook after toggle follow status
     *
     *
     * @param $vendor_id
     * @param $follower_id
     * @param $status
     * @param $current_time
     */
    do_action( 'sk_follow_store_toggle_status', $vendor_id, $follower_id, $status, $current_time );

    return $status;
}

/**
 * Is customer following a store
 *
 *
 * @param int $vendor_id
 * @param int $follower_id
 *
 * @return bool|WP_Error
 */
function sk_follow_store_is_following_store( $vendor_id, $follower_id ) {
    global $wpdb;

    // check following from database
    $following = $wpdb->get_var(
        $wpdb->prepare(
            "select 1 from {$wpdb->prefix}sk_follow_store_followers
            where vendor_id = %d and follower_id = %d and unfollowed_at is null limit 1",
            $vendor_id,
            $follower_id
        )
    );

	if ( ! empty( $wpdb->last_error ) ) {
		// translators: 1) query error
		return new WP_Error( 'follow_store_is_following_store_db_error', sprintf( __( 'Database Error: %s', 'sk' ), $wpdb->last_error ) );
	}

    return ! empty( $following );
}

/**
 * Check if a follower can use coupon
 *
 *
 * @param array     $follower_emails
 * @param WC_Coupon $coupon
 *
 * @return bool
 */
function sk_follower_can_user_coupon( $follower_emails, $coupon ) {
    if ( ! class_exists( 'WC_Cart' ) ) {
        include_once WC_ABSPATH . 'includes/class-wc-cart.php';
    }

    $cart = new WC_Cart();

    $restrictions = $coupon->get_email_restrictions();

    if ( is_array( $restrictions ) && 0 < count( $restrictions ) && ! $cart->is_coupon_emails_allowed( $follower_emails, $restrictions ) ) {
        return false;
    }

    return true;
}

/**
 * Get arg values for Follow Store button
 *
 *
 * @param WP_User $vendor
 * @param array   $button_classes
 *
 * @return array
 */
function sk_follow_store_get_button_args( $vendor, $button_classes = array() ) {
    $btn_labels = sk_follow_store_button_labels();

    $customer_id = get_current_user_id();

    $status = null;

    if ( sk_follow_store_is_following_store( $vendor->ID, $customer_id ) ) {
        $label_current = $btn_labels['following'];
        $status = 'following';
    } else {
        $label_current = $btn_labels['follow'];
    }

    $button_classes = array_merge(
        array( 'sk-btn', 'sk-btn-theme', 'sk-follow-store-button' ),
        $button_classes
    );

    $args = array(
        'label_current'  => $label_current,
        'label_unfollow' => $btn_labels['unfollow'],
        'vendor_id'      => $vendor->ID,
        'status'         => $status,
        'button_classes' => implode( ' ', $button_classes ),
        'is_logged_in'   => $customer_id,
    );

    return $args;
}

/**
 * Get all followers of a vendor
 *
 *
 * @param $vendor_id
 *
 * @return array
 */
function sk_follow_store_get_vendor_followers( $vendor_id ) {
    global $wpdb;
    $cache_group = "followers_$vendor_id";
    $cache_key   = "get_followers";
    $followers   = Cache::get( $cache_key, $cache_group );

    if ( false === $followers ) {
        $followers = [];
        $sk_followers = $wpdb->get_results(
            $wpdb->prepare(
                "select follower_id, followed_at from {$wpdb->prefix}sk_follow_store_followers
                where vendor_id = %d and unfollowed_at is null",
                $vendor_id
            ),
            OBJECT_K
        );

        $customers = [];
        if ( ! empty( $sk_followers ) ) {
            $query = new WP_User_Query(
                [
                    'include' => array_keys( $sk_followers ),
                    'number'  => -1,
                    'fields'  => 'ID',
                ]
            );

            $customers = $query->get_results();
        }

        $followers[ 'followers' ] = $sk_followers;
        $followers[ 'customers' ] = $customers;

        Cache::set( $cache_key, $followers, $cache_group );
    }

    return $followers;
}
