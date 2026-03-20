<?php
/**
 * Handles the core account linking functionality.
 *
 * This class manages linking and unlinking of authentication methods
 * (LNURL-Auth and Nostr Login) to WordPress user accounts.
 */
class UAC_Account_Linker {

    /**
     * User meta keys for storing linked authentication methods
     */
    const META_LINKED_NOSTR = 'uac_linked_nostr_pubkey';
    const META_LINKED_LNURL = 'uac_linked_lnurl_node_key';
    const META_PRIMARY_AUTH = 'uac_primary_auth_method';
    const META_AUTH_MAPPING = 'uac_auth_identity_mapping';

    /**
     * Link a Nostr public key to a WordPress user account.
     *
     * @param int $user_id The WordPress user ID
     * @param string $nostr_pubkey The Nostr public key to link
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function link_nostr($user_id, $nostr_pubkey) {
        if (empty($nostr_pubkey)) {
            return new WP_Error('invalid_pubkey', 'Ungültiger öffentlicher Nostr-Schlüssel.');
        }

        // Check if this Nostr key is already linked to another account via UAC only
        // (not via nostr-login's nostr_public_key meta — standalone Nostr accounts must not block linking)
        $existing_user_id = $this->get_auth_mapping('nostr', $nostr_pubkey);
        if ($existing_user_id && $existing_user_id !== $user_id) {
            return new WP_Error('already_linked', 'Diese Nostr-Identität ist bereits mit einem anderen Konto verknüpft.');
        }

        // Store the linked Nostr public key
        update_user_meta($user_id, self::META_LINKED_NOSTR, $nostr_pubkey);

        // Create reverse mapping: Nostr pubkey → user ID
        $this->set_auth_mapping('nostr', $nostr_pubkey, $user_id);

        // Set primary auth method if not set
        if (!get_user_meta($user_id, self::META_PRIMARY_AUTH, true)) {
            $this->set_primary_auth_method($user_id);
        }

        do_action('uac_nostr_linked', $user_id, $nostr_pubkey);

        return true;
    }

    /**
     * Link an LNURL-Auth node key to a WordPress user account.
     *
     * @param int $user_id The WordPress user ID
     * @param string $node_key The LNURL-Auth node linking key
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function link_lnurl($user_id, $node_key) {
        if (empty($node_key)) {
            return new WP_Error('invalid_node_key', 'Ungültiger LNURL-Auth Node-Schlüssel.');
        }

        // Check if this LNURL key is already linked to another account via UAC only
        // (not via lnurl-auth's lnurl-auth-bjm-id meta — standalone LNURL accounts must not block linking)
        $existing_user_id = $this->get_auth_mapping('lnurl', $node_key);
        if ($existing_user_id && $existing_user_id !== $user_id) {
            return new WP_Error('already_linked', 'Diese LNURL-Auth-Identität ist bereits mit einem anderen Konto verknüpft.');
        }

        // Store the linked LNURL node key
        update_user_meta($user_id, self::META_LINKED_LNURL, $node_key);

        // Create reverse mapping: LNURL node key → user ID
        $this->set_auth_mapping('lnurl', $node_key, $user_id);

        // Set primary auth method if not set
        if (!get_user_meta($user_id, self::META_PRIMARY_AUTH, true)) {
            $this->set_primary_auth_method($user_id);
        }

        do_action('uac_lnurl_linked', $user_id, $node_key);

        return true;
    }

    /**
     * Unlink a Nostr public key from a WordPress user account.
     *
     * @param int $user_id The WordPress user ID
     * @return bool True on success, false on failure
     */
    public function unlink_nostr($user_id) {
        $nostr_pubkey = get_user_meta($user_id, self::META_LINKED_NOSTR, true);

        if ($nostr_pubkey) {
            $this->delete_auth_mapping('nostr', $nostr_pubkey);
        }

        delete_user_meta($user_id, self::META_LINKED_NOSTR);

        do_action('uac_nostr_unlinked', $user_id, $nostr_pubkey);

        return true;
    }

    /**
     * Unlink an LNURL-Auth node key from a WordPress user account.
     *
     * @param int $user_id The WordPress user ID
     * @return bool True on success, false on failure
     */
    public function unlink_lnurl($user_id) {
        $node_key = get_user_meta($user_id, self::META_LINKED_LNURL, true);

        if ($node_key) {
            $this->delete_auth_mapping('lnurl', $node_key);
        }

        delete_user_meta($user_id, self::META_LINKED_LNURL);

        do_action('uac_lnurl_unlinked', $user_id, $node_key);

        return true;
    }

    /**
     * Get WordPress user ID by Nostr public key (from linked accounts or original nostr-login).
     *
     * @param string $nostr_pubkey The Nostr public key
     * @return int|false User ID if found, false otherwise
     */
    public function get_user_by_nostr($nostr_pubkey) {
        // First check if this Nostr key is linked to an account via our plugin
        $user_id = $this->get_auth_mapping('nostr', $nostr_pubkey);
        if ($user_id) {
            return (int) $user_id;
        }

        // Fall back to checking the original nostr-login plugin meta
        $users = get_users(array(
            'meta_key' => 'nostr_public_key',
            'meta_value' => $nostr_pubkey,
            'number' => 1,
            'fields' => 'ID'
        ));

        if (!empty($users)) {
            return (int) $users[0];
        }

        return false;
    }

    /**
     * Get WordPress user ID by LNURL-Auth node key (from linked accounts or original lnurl-auth).
     *
     * @param string $node_key The LNURL-Auth node linking key
     * @return int|false User ID if found, false otherwise
     */
    public function get_user_by_lnurl($node_key) {
        // First check if this LNURL key is linked to an account via our plugin
        $user_id = $this->get_auth_mapping('lnurl', $node_key);
        if ($user_id) {
            return (int) $user_id;
        }

        // Fall back to checking the original lnurl-auth plugin meta
        $users = get_users(array(
            'meta_key' => 'lnurl-auth-bjm-id',
            'meta_value' => $node_key,
            'number' => 1,
            'fields' => 'ID'
        ));

        if (!empty($users)) {
            return (int) $users[0];
        }

        return false;
    }

    /**
     * Check if a user has Nostr linked.
     *
     * @param int $user_id The WordPress user ID
     * @return bool True if linked, false otherwise
     */
    public function has_nostr_linked($user_id) {
        return !empty(get_user_meta($user_id, self::META_LINKED_NOSTR, true));
    }

    /**
     * Check if a user has LNURL-Auth linked.
     *
     * @param int $user_id The WordPress user ID
     * @return bool True if linked, false otherwise
     */
    public function has_lnurl_linked($user_id) {
        return !empty(get_user_meta($user_id, self::META_LINKED_LNURL, true));
    }

    /**
     * Get the linked Nostr public key for a user.
     *
     * @param int $user_id The WordPress user ID
     * @return string|false The Nostr public key if linked, false otherwise
     */
    public function get_linked_nostr($user_id) {
        $pubkey = get_user_meta($user_id, self::META_LINKED_NOSTR, true);
        return !empty($pubkey) ? $pubkey : false;
    }

    /**
     * Get the linked LNURL-Auth node key for a user.
     *
     * @param int $user_id The WordPress user ID
     * @return string|false The node key if linked, false otherwise
     */
    public function get_linked_lnurl($user_id) {
        $node_key = get_user_meta($user_id, self::META_LINKED_LNURL, true);
        return !empty($node_key) ? $node_key : false;
    }

    /**
     * Get the primary authentication method for a user.
     *
     * @param int $user_id The WordPress user ID
     * @return string The primary auth method (wordpress, nostr, lnurl)
     */
    public function get_primary_auth_method($user_id) {
        $method = get_user_meta($user_id, self::META_PRIMARY_AUTH, true);
        return !empty($method) ? $method : 'wordpress';
    }

    /**
     * Set the primary authentication method for a user.
     *
     * @param int $user_id The WordPress user ID
     * @param string $method The auth method to set as primary (wordpress, nostr, lnurl)
     * @return bool True on success, false on failure
     */
    public function set_primary_auth_method($user_id, $method = null) {
        // Auto-detect if not provided
        if ($method === null) {
            $user = get_user_by('ID', $user_id);

            // Check if user was created by nostr-login
            if (get_user_meta($user_id, 'nostr_public_key', true)) {
                $method = 'nostr';
            }
            // Check if user was created by lnurl-auth
            elseif (get_user_meta($user_id, 'lnurl-auth-bjm-id', true)) {
                $method = 'lnurl';
            }
            // Default to WordPress
            else {
                $method = 'wordpress';
            }
        }

        return update_user_meta($user_id, self::META_PRIMARY_AUTH, $method);
    }

    /**
     * Store an auth identity to user ID mapping.
     *
     * @param string $type The type of auth (nostr, lnurl)
     * @param string $identity The identity string (pubkey or node key)
     * @param int $user_id The WordPress user ID
     */
    private function set_auth_mapping($type, $identity, $user_id) {
        $option_key = self::META_AUTH_MAPPING . '_' . $type . '_' . md5($identity);
        update_option($option_key, $user_id, false);
    }

    /**
     * Get user ID from an auth identity mapping.
     *
     * @param string $type The type of auth (nostr, lnurl)
     * @param string $identity The identity string (pubkey or node key)
     * @return int|false User ID if found, false otherwise
     */
    private function get_auth_mapping($type, $identity) {
        $option_key = self::META_AUTH_MAPPING . '_' . $type . '_' . md5($identity);
        $user_id = get_option($option_key);
        return $user_id ? (int) $user_id : false;
    }

    /**
     * Delete an auth identity mapping.
     *
     * @param string $type The type of auth (nostr, lnurl)
     * @param string $identity The identity string (pubkey or node key)
     */
    private function delete_auth_mapping($type, $identity) {
        $option_key = self::META_AUTH_MAPPING . '_' . $type . '_' . md5($identity);
        delete_option($option_key);
    }

    /**
     * Get user ID of an existing standalone Nostr account (registered via nostr-login plugin,
     * not via UAC). Used to warn before linking would redirect logins away from that account.
     *
     * @param string $nostr_pubkey
     * @return int|false
     */
    public function get_standalone_user_by_nostr($nostr_pubkey) {
        $users = get_users(array(
            'meta_key'   => 'nostr_public_key',
            'meta_value' => $nostr_pubkey,
            'number'     => 1,
            'fields'     => 'ID',
        ));
        return !empty($users) ? (int) $users[0] : false;
    }

    /**
     * Get user ID of an existing standalone LNURL account (registered via lnurl-auth plugin,
     * not via UAC). Used to warn before linking would redirect logins away from that account.
     *
     * @param string $node_key
     * @return int|false
     */
    public function get_standalone_user_by_lnurl($node_key) {
        $users = get_users(array(
            'meta_key'   => 'lnurl-auth-bjm-id',
            'meta_value' => $node_key,
            'number'     => 1,
            'fields'     => 'ID',
        ));
        return !empty($users) ? (int) $users[0] : false;
    }

    /**
     * Get all linked authentication methods for a user.
     *
     * @param int $user_id The WordPress user ID
     * @return array Array with 'nostr' and 'lnurl' keys
     */
    public function get_linked_methods($user_id) {
        return array(
            'nostr' => $this->get_linked_nostr($user_id),
            'lnurl' => $this->get_linked_lnurl($user_id),
            'primary' => $this->get_primary_auth_method($user_id)
        );
    }
}
