<?php

namespace SK\Core\Dashboard\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Account Deletion — lets vendors delete their own account.
 *
 * Deletes: user, products, store data, all user meta.
 */
class AccountDeletion {

    public function __construct() {
        add_action( 'wp_ajax_sk_delete_own_account', [ $this, 'handle_delete' ] );
    }

    public function handle_delete() {
        check_ajax_referer( 'sk_delete_account', '_nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Nicht eingeloggt.' ] );
        }

        $user_id = get_current_user_id();

        // Don't allow admins to delete themselves.
        if ( user_can( $user_id, 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Admin-Accounts können nicht über das Dashboard gelöscht werden.' ] );
        }

        // Delete all products by this vendor.
        $products = get_posts( [
            'post_type'      => 'product',
            'post_status'    => 'any',
            'author'         => $user_id,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        foreach ( $products as $product_id ) {
            wp_delete_post( $product_id, true );
        }

        // Delete all feed posts by this vendor.
        $feed_posts = get_posts( [
            'post_type'      => 'sk_vendor_post',
            'post_status'    => 'any',
            'author'         => $user_id,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        foreach ( $feed_posts as $post_id ) {
            wp_delete_post( $post_id, true );
        }

        // Clean up Nostr identity data.
        delete_user_meta( $user_id, 'nostr_public_key' );
        delete_user_meta( $user_id, 'sk_nostr_private_key' );
        delete_user_meta( $user_id, 'sk_nostr_identity_source' );
        delete_user_meta( $user_id, '_sk_nostr_profile_event_id' );
        delete_user_meta( $user_id, 'nostr_avatar' );
        delete_user_meta( $user_id, 'nip05' );
        delete_user_meta( $user_id, 'uac_linked_nostr_pubkey' );
        delete_user_meta( $user_id, 'uac_nostr_sync_preference' );
        delete_user_meta( $user_id, 'lnurl-auth-bjm-id' );

        // Log out the user before deleting.
        wp_logout();

        // Reassign remaining content (comments etc.) to nobody, then delete.
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $user_id );

        wp_send_json_success( [ 'message' => 'Account gelöscht.' ] );
    }
}
