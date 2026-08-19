<?php
/**
 * Nostr Profile Sync — copy Nostr kind:0 metadata into the vendor's
 * sk_profile_settings (store name, biography, avatar, banner, npub).
 */
class UAC_Nostr_Profile_Sync {

    /**
     * Initialize the sync functionality.
     */
    public function __construct() {
        // Hook into WordPress login to check if Nostr user needs syncing
        add_action('wp_login', array($this, 'sync_on_login'), 20, 2);
    }

    /**
     * Flag a new Nostr user as needing the sync choice on their first login.
     *
     * @param string  $user_login
     * @param WP_User $user
     */
    public function sync_on_login($user_login, $user) {
        $nostr_pubkey = get_user_meta($user->ID, 'nostr_public_key', true);
        if (empty($nostr_pubkey)) {
            return;
        }

        if (!class_exists('SK_Core')) {
            return;
        }

        // Check if this is a vendor
        if (!sk_is_user_seller($user->ID)) {
            return;
        }

        // Check user's sync preference
        $sync_preference = get_user_meta($user->ID, 'uac_nostr_sync_preference', true);

        // If preference not set, this is their first time - set a flag to ask them
        if (empty($sync_preference)) {
            update_user_meta($user->ID, 'uac_nostr_sync_ask_pending', '1');
        }

        // NO automatic syncing on login - users must manually trigger sync if they want
    }

    /**
     * Copy Nostr profile data into sk_profile_settings.
     *
     * @param int $user_id WordPress user ID
     * @return bool True on success, false on failure
     */
    public function sync_nostr_to_sk($user_id) {
        $profile_settings = get_user_meta($user_id, 'sk_profile_settings', true);

        if (!is_array($profile_settings)) {
            $profile_settings = array();
        }

        $updated = false;

        // 1. Sync Store Name from Nostr display name
        $display_name = get_user_meta($user_id, 'first_name', true);
        if (empty($display_name)) {
            $user_data = get_userdata($user_id);
            $display_name = $user_data->display_name;
        }

        if (!empty($display_name) && empty($profile_settings['store_name'])) {
            // sk_set_store_name keeps sk_store_name in step, the array below is
            // written again at the end of this method with the same value.
            $profile_settings['store_name'] = sk_set_store_name($user_id, $display_name);
            $updated = true;
        }

        // 2. Sync Vendor Biography from Nostr about
        $nostr_about = get_user_meta($user_id, 'description', true);

        if (!empty($nostr_about) && empty($profile_settings['vendor_biography'])) {
            $profile_settings['vendor_biography'] = sanitize_textarea_field($nostr_about);
            $updated = true;
        }

        // 3. Sync Profile Picture from Nostr avatar
        $nostr_avatar = get_user_meta($user_id, 'nostr_avatar', true);

        if (!empty($nostr_avatar) && empty($profile_settings['gravatar_id'])) {
            $avatar_id = $this->download_and_upload_image($nostr_avatar, $user_id, 'avatar');

            if ($avatar_id) {
                $profile_settings['gravatar_id'] = $avatar_id;
                $updated = true;
            }
        }

        // 4. Sync Banner from Nostr banner (if available in metadata)
        // Note: Standard Nostr kind 0 includes 'banner' field
        $nostr_metadata = get_user_meta($user_id, 'nostr_metadata', true);

        if (is_array($nostr_metadata) && !empty($nostr_metadata['banner']) && empty($profile_settings['banner'])) {
            $banner_id = $this->download_and_upload_image($nostr_metadata['banner'], $user_id, 'banner');

            if ($banner_id) {
                $profile_settings['banner'] = $banner_id;
                $updated = true;
            }
        } else {
            // Fallback: Try to use avatar as banner if no banner is set
            if (!empty($nostr_avatar) && empty($profile_settings['banner'])) {
                $banner_id = $this->download_and_upload_image($nostr_avatar, $user_id, 'banner');

                if ($banner_id) {
                    $profile_settings['banner'] = $banner_id;
                    $updated = true;
                }
            }
        }

        // 5. Sync Nostr Public Key to contact details
        $nostr_pubkey = get_user_meta($user_id, 'nostr_public_key', true);

        if (!empty($nostr_pubkey) && empty($profile_settings['nostr'])) {
            // Convert hex to npub format using bech32
            $npub = $this->hex_to_npub($nostr_pubkey);

            if ($npub) {
                $profile_settings['nostr'] = $npub;
                $profile_settings['show_nostr'] = '1'; // Auto-enable public display
                $updated = true;
            }
        }

        if ($updated) {
            update_user_meta($user_id, 'sk_profile_settings', $profile_settings);
            return true;
        }

        return false;
    }

    /**
     * Download an image from URL and upload it to WordPress media library.
     *
     * @param string $image_url The URL of the image to download
     * @param int $user_id The user ID (for organization)
     * @param string $type Type of image (avatar or banner)
     * @return int|false Attachment ID on success, false on failure
     */
    private function download_and_upload_image($image_url, $user_id, $type = 'avatar') {
        if (empty($image_url)) {
            return false;
        }

        // Validate URL
        if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Check if we've already uploaded this image
        $existing_id = get_user_meta($user_id, "uac_nostr_{$type}_id", true);
        $existing_url = get_user_meta($user_id, "uac_nostr_{$type}_url", true);

        if ($existing_id && $existing_url === $image_url) {
            // Image already uploaded and URL hasn't changed
            return $existing_id;
        }

        // Include WordPress file functions
        if (!function_exists('media_sideload_image')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }

        // Download the image
        $tmp = download_url($image_url);

        if (is_wp_error($tmp)) {
            return false;
        }

        // Get the file extension
        $file_array = array(
            'name' => basename($image_url),
            'tmp_name' => $tmp
        );

        // Upload to media library
        $id = media_handle_sideload($file_array, 0, sprintf(
            __('Nostr %s for user %s', 'sk-core'),
            $type,
            $user_id
        ));

        // Clean up temp file
        if (file_exists($tmp)) {
            @unlink($tmp);
        }

        if (is_wp_error($id)) {
            return false;
        }

        // Store the attachment ID and URL for future reference
        update_user_meta($user_id, "uac_nostr_{$type}_id", $id);
        update_user_meta($user_id, "uac_nostr_{$type}_url", $image_url);

        return $id;
    }

    /**
     * Manually trigger a sync for a user.
     *
     * @param int $user_id WordPress user ID
     * @return bool True on success, false on failure
     */
    public function manual_sync($user_id) {
        // Reset the last sync time to force a sync
        delete_user_meta($user_id, 'uac_nostr_last_sync');

        return $this->sync_nostr_to_sk($user_id);
    }

    /**
     * Check if user needs to be asked about sync preference.
     *
     * @param int $user_id WordPress user ID
     * @return bool True if user needs to be asked, false otherwise
     */
    public function needs_sync_choice($user_id) {
        $ask_pending = get_user_meta($user_id, 'uac_nostr_sync_ask_pending', true);
        return !empty($ask_pending);
    }

    /**
     * Set user's sync preference.
     *
     * @param int $user_id WordPress user ID
     * @param bool $enabled Whether to enable sync
     * @return bool True on success
     */
    public function set_sync_preference($user_id, $enabled) {
        $preference = $enabled ? 'enabled' : 'disabled';
        update_user_meta($user_id, 'uac_nostr_sync_preference', $preference);
        delete_user_meta($user_id, 'uac_nostr_sync_ask_pending');

        // If enabled, trigger an immediate sync
        if ($enabled) {
            $this->manual_sync($user_id);
        }

        return true;
    }

    /**
     * Convert hex public key to npub (bech32) format.
     *
     * @param string $hex_pubkey Hex public key (64 characters)
     * @return string|false npub string on success, false on failure
     */
    private function hex_to_npub($hex_pubkey) {
        if (!preg_match('/^[a-f0-9]{64}$/i', $hex_pubkey)) {
            return false;
        }

        // Use centralized nostr-php library (loaded via sk-core/lib/autoload.php).
        if (class_exists('\swentel\nostr\Key\Key')) {
            try {
                $key = new \swentel\nostr\Key\Key();
                $npub = $key->convertPublicKeyToBech32($hex_pubkey);
                return $npub ?: false;
            } catch (\Throwable $e) {
                return false;
            }
        }

        // Fallback: manual bech32 encoding.
        $data = hex2bin($hex_pubkey);
        if ($data === false) {
            return false;
        }

        // Convert to 5-bit groups for bech32
        $values = array_values(unpack('C*', $data));
        $converted = $this->convertBits($values, 8, 5, true);

        if ($converted === false) {
            return false;
        }

        // Encode as bech32 with "npub" prefix
        return $this->bech32_encode('npub', $converted);
    }

    /**
     * Convert bits between bases.
     *
     * @param array $data Input data
     * @param int $fromBits Input bit length
     * @param int $toBits Output bit length
     * @param bool $pad Whether to pad
     * @return array|false Converted data or false on error
     */
    private function convertBits($data, $fromBits, $toBits, $pad = true) {
        $acc = 0;
        $bits = 0;
        $ret = [];
        $maxv = (1 << $toBits) - 1;
        $max_acc = (1 << ($fromBits + $toBits - 1)) - 1;

        foreach ($data as $value) {
            if ($value < 0 || $value >> $fromBits) {
                return false;
            }
            $acc = (($acc << $fromBits) | $value) & $max_acc;
            $bits += $fromBits;
            while ($bits >= $toBits) {
                $bits -= $toBits;
                $ret[] = (($acc >> $bits) & $maxv);
            }
        }

        if ($pad) {
            if ($bits) {
                $ret[] = ($acc << ($toBits - $bits)) & $maxv;
            }
        } elseif ($bits >= $fromBits || (($acc << ($toBits - $bits)) & $maxv)) {
            return false;
        }

        return $ret;
    }

    /**
     * Encode data using bech32 format.
     *
     * @param string $hrp Human-readable part
     * @param array $data Data to encode
     * @return string|false Encoded string or false on error
     */
    private function bech32_encode($hrp, $data) {
        $charset = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
        $checksum = $this->bech32_create_checksum($hrp, $data);
        $combined = array_merge($data, $checksum);

        $result = $hrp . '1';
        foreach ($combined as $d) {
            if ($d < 0 || $d >= strlen($charset)) {
                return false;
            }
            $result .= $charset[$d];
        }

        return $result;
    }

    /**
     * Create bech32 checksum.
     *
     * @param string $hrp Human-readable part
     * @param array $data Data
     * @return array Checksum
     */
    private function bech32_create_checksum($hrp, $data) {
        $values = array_merge($this->bech32_hrp_expand($hrp), $data, [0, 0, 0, 0, 0, 0]);
        $polymod = $this->bech32_polymod($values) ^ 1;
        $checksum = [];
        for ($i = 0; $i < 6; ++$i) {
            $checksum[] = ($polymod >> 5 * (5 - $i)) & 31;
        }
        return $checksum;
    }

    /**
     * Expand human-readable part for bech32.
     *
     * @param string $hrp Human-readable part
     * @return array Expanded values
     */
    private function bech32_hrp_expand($hrp) {
        $result = [];
        $length = strlen($hrp);
        for ($i = 0; $i < $length; ++$i) {
            $result[] = ord($hrp[$i]) >> 5;
        }
        $result[] = 0;
        for ($i = 0; $i < $length; ++$i) {
            $result[] = ord($hrp[$i]) & 31;
        }
        return $result;
    }

    /**
     * Calculate bech32 polymod.
     *
     * @param array $values Input values
     * @return int Polymod result
     */
    private function bech32_polymod($values) {
        $gen = [0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3];
        $chk = 1;
        foreach ($values as $value) {
            $top = $chk >> 25;
            $chk = (($chk & 0x1ffffff) << 5) ^ $value;
            for ($i = 0; $i < 5; ++$i) {
                if (($top >> $i) & 1) {
                    $chk ^= $gen[$i];
                }
            }
        }
        return $chk;
    }
}
