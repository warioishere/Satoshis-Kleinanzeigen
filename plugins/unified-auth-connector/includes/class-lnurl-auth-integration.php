<?php
/**
 * LNURL-Auth Integration
 *
 * Handles integration with the LNURL-Auth plugin to support unified authentication.
 */
class UAC_LNURL_Auth_Integration {

    /**
     * @var UAC_Account_Linker
     */
    private $account_linker;

    /**
     * Constructor.
     *
     * @param UAC_Account_Linker $account_linker The account linker instance
     */
    public function __construct($account_linker) {
        $this->account_linker = $account_linker;

        // Hook into wp_login to check for linked accounts
        add_action('wp_login', array($this, 'handle_lnurl_login'), 5, 2);
    }

    /**
     * Handle LNURL-Auth login and redirect to primary account if linked.
     *
     * @param string $user_login Username
     * @param WP_User $user The user object
     */
    public function handle_lnurl_login($user_login, $user) {
        // Check if this is an LNURL-Auth login
        $node_key = get_user_meta($user->ID, 'lnurl-auth-bjm-id', true);

        if (empty($node_key)) {
            return; // Not an LNURL login
        }

        // Check if this node key is linked to a different primary account
        $primary_user_id = $this->account_linker->get_user_by_lnurl($node_key);

        if ($primary_user_id && $primary_user_id !== $user->ID) {
            // This LNURL identity is linked to a different account
            // Log out current user and log into primary account
            wp_clear_auth_cookie();
            wp_set_current_user($primary_user_id);
            wp_set_auth_cookie($primary_user_id, true);

            // Prevent infinite loop by removing this hook temporarily
            remove_action('wp_login', array($this, 'handle_lnurl_login'), 5);

            // Trigger wp_login for the primary user
            do_action('wp_login', get_userdata($primary_user_id)->user_login, get_user_by('id', $primary_user_id));

            // Re-add the hook
            add_action('wp_login', array($this, 'handle_lnurl_login'), 5, 2);
        }
    }

    /**
     * Filter to check for linked accounts (if we add a custom filter to LNURL-Auth plugin).
     *
     * This is a placeholder for potential future integration.
     *
     * @param int $user_id The user ID
     * @param string $node_key The LNURL node linking key
     * @return int The user ID to use for authentication
     */
    public function check_linked_account($user_id, $node_key) {
        $primary_user_id = $this->account_linker->get_user_by_lnurl($node_key);

        if ($primary_user_id && $primary_user_id !== $user_id) {
            return $primary_user_id;
        }

        return $user_id;
    }

    /**
     * Verify an LNURL-Auth identity for linking.
     *
     * This is used during the linking process to verify that the user
     * controls the LNURL identity they're trying to link.
     *
     * @param string $k1 The k1 session key
     * @return array|false Array with 'node_key' on success, false on failure
     */
    public function verify_linking_session($k1) {
        if (empty($k1)) {
            return false;
        }

        // Check if lnurl-auth plugin is active
        if (!function_exists('lnurl_auth')) {
            return false;
        }

        // Get the transient data from LNURL-Auth plugin
        $transient = lnurl_auth()->Plugin->Transients->get($k1);

        if (empty($transient) || empty($transient['signed']) || empty($transient['user_id'])) {
            return false;
        }

        // Get the node key from the temporary user that was created
        $temp_user_id = $transient['user_id'];
        $node_key = get_user_meta($temp_user_id, 'lnurl-auth-bjm-id', true);

        if (empty($node_key)) {
            return false;
        }

        return array(
            'node_key' => $node_key,
            'temp_user_id' => $temp_user_id
        );
    }
}
