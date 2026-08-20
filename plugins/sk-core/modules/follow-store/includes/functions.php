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
    return array(
        'follow'    => 'Folgen',
        'following' => 'Folge ich',
        'unfollow'  => 'Nicht mehr folgen',
    );
}

/**
 * May this user be followed?
 *
 * Mirrors the definition the store listing uses (Vendor\Manager::get_vendors):
 * role seller or administrator, selling enabled. Without it sk()->vendor->get()
 * returns an object for *any* user id, so the "invalid vendor" guards in the
 * ajax and rest endpoints never triggered and a customer — or any account at
 * all — could be followed, mail included.
 *
 * @param int $vendor_id
 *
 * @return bool
 */
function sk_follow_store_is_followable_vendor( $vendor_id ) {
    $vendor_id = absint( $vendor_id );

    if ( ! $vendor_id ) {
        return false;
    }

    $user = get_userdata( $vendor_id );

    if ( ! $user instanceof WP_User ) {
        return false;
    }

    if ( ! array_intersect( [ 'seller', 'administrator' ], (array) $user->roles ) ) {
        return false;
    }

    return 'yes' === get_user_meta( $vendor_id, 'sk_enable_selling', true );
}

/**
 * Vendor IDs the given user follows — one query per request, shared by every
 * follow button on the page instead of one query per button.
 *
 * @param int  $follower_id
 * @param bool $refresh     Drop the memo, e.g. after a toggle.
 *
 * @return int[]
 */
function sk_follow_store_get_following_ids( $follower_id = 0, $refresh = false ) {
    static $memo = [];

    global $wpdb;

    $follower_id = $follower_id ? absint( $follower_id ) : get_current_user_id();

    if ( ! $follower_id ) {
        return [];
    }

    if ( $refresh ) {
        unset( $memo[ $follower_id ] );

        return [];
    }

    if ( isset( $memo[ $follower_id ] ) ) {
        return $memo[ $follower_id ];
    }

    $ids = $wpdb->get_col( $wpdb->prepare(
        "select vendor_id from {$wpdb->prefix}sk_follow_store_followers"
        . " where follower_id = %d and unfollowed_at is null",
        $follower_id
    ) );

    $memo[ $follower_id ] = array_map( 'intval', (array) $ids );

    return $memo[ $follower_id ];
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
		return new WP_Error( 'follow_store_is_following_store_db_error', sprintf( __( 'Database Error: %s', 'sk-core' ), $wpdb->last_error ) );
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

    if ( in_array( (int) $vendor->ID, sk_follow_store_get_following_ids( $customer_id ), true ) ) {
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
        'is_logged_in'   => $customer_id ? 1 : 0,
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
