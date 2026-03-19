<?php
/**
 * Handles merging two user accounts when linking auth methods.
 *
 * The "empty" account (fewer products/settings) is absorbed into the
 * "survivor" account. Auth meta is copied, products reassigned, and
 * the empty account is deleted.
 */
class UAC_Account_Merger {

    /**
     * @var UAC_Account_Linker
     */
    private $account_linker;

    public function __construct( UAC_Account_Linker $account_linker ) {
        $this->account_linker = $account_linker;
    }

    /**
     * Merge two accounts. The account with more data survives.
     *
     * @param int $current_user_id Currently logged-in user
     * @param int $standalone_id   The other account (owns the auth key being linked)
     * @return array|WP_Error Result array on success, WP_Error on failure
     */
    public function merge( int $current_user_id, int $standalone_id ) {
        if ( $current_user_id === $standalone_id ) {
            return new WP_Error( 'same_user', 'Kann ein Konto nicht mit sich selbst zusammenführen.' );
        }

        if ( ! get_userdata( $current_user_id ) || ! get_userdata( $standalone_id ) ) {
            return new WP_Error( 'invalid_user', 'Eines der Konten existiert nicht.' );
        }

        // Detect survivor (whoever has more products)
        $survivor_id = $this->pick_survivor( $current_user_id, $standalone_id );
        $absorbed_id = ( $survivor_id === $current_user_id ) ? $standalone_id : $current_user_id;

        // 1. Copy auth meta from absorbed → survivor
        foreach ( array( 'nostr_public_key', 'lnurl-auth-bjm-id', 'nip05' ) as $key ) {
            $val = get_user_meta( $absorbed_id, $key, true );
            if ( $val && ! get_user_meta( $survivor_id, $key, true ) ) {
                update_user_meta( $survivor_id, $key, $val );
            }
        }

        // 2. Copy UAC linked meta from absorbed → survivor
        foreach ( array( 'uac_linked_nostr_pubkey', 'uac_linked_lnurl_node_key' ) as $key ) {
            $val = get_user_meta( $absorbed_id, $key, true );
            if ( $val && ! get_user_meta( $survivor_id, $key, true ) ) {
                update_user_meta( $survivor_id, $key, $val );
            }
        }

        // 3. Reassign products (just in case absorbed has any)
        global $wpdb;
        $wpdb->update(
            $wpdb->posts,
            array( 'post_author' => $survivor_id ),
            array( 'post_author' => $absorbed_id )
        );

        // 4. Delete absorbed account (WP reassigns any remaining content)
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $absorbed_id, $survivor_id );

        // 5. Re-login if current user was absorbed
        $relogin = ( $absorbed_id === $current_user_id );
        if ( $relogin ) {
            wp_set_current_user( $survivor_id );
            wp_set_auth_cookie( $survivor_id );
        }

        // 6. Clean cache
        clean_user_cache( $survivor_id );

        error_log( "UAC Merge: user #$absorbed_id merged into #$survivor_id and deleted" );

        return array(
            'survivor_id' => $survivor_id,
            'absorbed_id' => $absorbed_id,
            'relogin'     => $relogin,
        );
    }

    /**
     * Get a preview of what the merge would do (for the confirm dialog).
     *
     * @return array with survivor_name, absorbed_name, survivor_products, absorbed_products
     */
    public function preview( int $current_user_id, int $standalone_id ): array {
        $survivor_id = $this->pick_survivor( $current_user_id, $standalone_id );
        $absorbed_id = ( $survivor_id === $current_user_id ) ? $standalone_id : $current_user_id;

        $survivor_user = get_userdata( $survivor_id );
        $absorbed_user = get_userdata( $absorbed_id );

        return array(
            'survivor_id'       => $survivor_id,
            'absorbed_id'       => $absorbed_id,
            'survivor_name'     => $survivor_user ? $survivor_user->display_name : '#' . $survivor_id,
            'absorbed_name'     => $absorbed_user ? $absorbed_user->display_name : '#' . $absorbed_id,
            'survivor_products' => $this->data_score( $survivor_id ),
            'absorbed_products' => $this->data_score( $absorbed_id ),
        );
    }

    /**
     * Pick the account with more products as the survivor.
     */
    private function pick_survivor( int $a, int $b ): int {
        return ( $this->data_score( $a ) >= $this->data_score( $b ) ) ? $a : $b;
    }

    /**
     * Score an account by real shop activity.
     *
     * An account counts as "has data" only if it has published products.
     * Just having sk_profile_settings (auto-created on first login) or
     * an auto-generated store name doesn't count.
     */
    private function data_score( int $user_id ): int {
        global $wpdb;

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d AND post_type = 'product' AND post_status IN ('publish','draft','pending')",
            $user_id
        ) );
    }
}
