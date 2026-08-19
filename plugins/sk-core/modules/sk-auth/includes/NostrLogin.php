<?php

namespace SK\Modules\Auth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use swentel\nostr\Event\Event;

/**
 * Nostr Login Handler — NIP-07/NIP-98 authentication.
 *
 * Original plugin by Yeghro (https://github.com/Yeghro/YEGHRO_NostrLogin).
 */
class Nostr_Login_Handler {
    private static $field_added = false;
    private $default_relays = [
        "wss://purplepag.es",
        "wss://relay.nostr.band",
        "wss://relay.primal.net",
        "wss://relay.damus.io",

    ];

    public function init() {
        add_action( 'login_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'login_form', array( $this, 'add_nostr_login_field' ) );
        add_action( 'wp_ajax_nostr_login', array( $this, 'ajax_nostr_login' ) );
        add_action( 'wp_ajax_nopriv_nostr_login', array( $this, 'ajax_nostr_login' ) );
        add_action( 'show_user_profile', array( $this, 'add_custom_user_profile_fields' ) );
        add_action( 'edit_user_profile', array( $this, 'add_custom_user_profile_fields' ) );
        add_action( 'personal_options_update', array( $this, 'save_custom_user_profile_fields' ) );
        add_action( 'edit_user_profile_update', array( $this, 'save_custom_user_profile_fields' ) );
        add_action( 'wp_ajax_nostr_sync_profile', array( $this, 'ajax_nostr_sync_profile' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        nostr_login_debug_log( "Nostr_Login_Handler class initialized" );
    }

    public function add_custom_user_profile_fields( $user ) {
        ?>
        <h3><?php esc_html_e( "Nostr Information", "sk-core" ); ?></h3>
        <?php wp_nonce_field('nostr_login_save_profile', 'nostr_login_nonce'); ?>

        <table class="form-table">
            <tr>
                <th><label><?php esc_html_e("Connect Nostr Account", "sk-core"); ?></label></th>
                <td>
                    <?php if (!get_user_meta($user->ID, 'nostr_public_key', true)): ?>
                        <button type="button" id="nostr-connect-extension" class="button">
                            <?php esc_html_e("Sync with Nostr Extension", "sk-core"); ?>
                        </button>
                        <p class="description">
                            <?php esc_html_e("Connect your Nostr account to sync your public key, NIP-05, and avatar", "sk-core"); ?>
                        </p>
                    <?php else: ?>
                        <button type="button" id="nostr-resync-extension" class="button">
                            <?php esc_html_e("Resync Nostr Data", "sk-core"); ?>
                        </button>
                    <?php endif; ?>
                    <div id="nostr-connect-feedback" style="display:none; margin-top:10px;"></div>
                </td>
            </tr>

            <!-- Existing fields as read-only -->
            <tr>
                <th><label><?php esc_html_e("Nostr Public Key", "sk-core"); ?></label></th>
                <td>
                    <input type="text" id="nostr_public_key"
                           value="<?php echo esc_attr(get_user_meta($user->ID, 'nostr_public_key', true)); ?>"
                           class="regular-text" readonly />
                </td>
            </tr>
            <tr>
                <th><label><?php esc_html_e("Nostr NIP-05", "sk-core"); ?></label></th>
                <td>
                    <input type="text" id="nip05"
                           value="<?php echo esc_attr(get_user_meta($user->ID, 'nip05', true)); ?>"
                           class="regular-text" readonly />
                </td>
            </tr>
            <!-- Add more custom fields here -->
        </table>
        <?php
    }

    public function save_custom_user_profile_fields( $user_id ) {
        // Verify nonce to prevent CSRF attacks
        if ( ! isset( $_POST['nostr_login_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nostr_login_nonce'] ) ), 'nostr_login_save_profile' ) ) {
            // Nonce is invalid; stop processing
            return;
        }

        // Check user permissions to ensure that only authorized users can edit
        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            // User does not have permission; stop processing
            return false;
        }

        // Save Nostr public key securely
        if ( isset( $_POST['nostr_public_key'] ) ) {
            $nostr_public_key = sanitize_text_field( wp_unslash( $_POST['nostr_public_key'] ) );
            if ( $this->is_valid_public_key( $nostr_public_key ) ) {
                update_user_meta( $user_id, 'nostr_public_key', $nostr_public_key );
            } else {
                // Handle invalid public key
            }
        }

        // Save Nip05 securely
        if ( isset( $_POST['nip05'] ) ) {
            $nip05 = sanitize_text_field( wp_unslash( $_POST['nip05'] ) );
            if ( $this->is_valid_nip05( $nip05 ) ) {
                update_user_meta( $user_id, 'nip05', $nip05 );
            } else {
                // Handle invalid nip05
            }
        }
    }

    private function is_valid_public_key( $key ) {
        // Implement your validation logic for Nostr public keys
        return preg_match( '/^[a-f0-9]{64}$/i', $key );
    }

    private function is_valid_nip05( $nip05 ) {
        // NIP-05: user@domain.com format.
        return (bool) preg_match( '/^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $nip05 );
    }

    public function ajax_nostr_login() {
        // No WP nonce check — login page may serve cached nonces for nopriv users.
        // Security is provided by the NIP-98 signature + timestamp window.

        // A foreign page must not be able to submit a login and leave the visitor
        // signed in as the attacker. A nonce cannot prevent that here (see
        // sk_is_same_origin_request()), the request origin can.
        if ( function_exists( 'sk_is_same_origin_request' ) && ! sk_is_same_origin_request() ) {
            wp_send_json_error( [ 'message' => __( 'Anfrage von fremder Herkunft.', 'sk-core' ) ] );
        }

        // Sanitize input data
        $metadata_json = sanitize_text_field( wp_unslash( $_POST['metadata'] ?? '' ) );
        $authtoken = sanitize_text_field( wp_unslash( $_POST['authtoken'] ?? '' ) );
        $authtoken = base64_decode( $authtoken );

        // Verify authtoken event signature and format
        $event = new Event();
        if ( ! $event->verify( $authtoken ) ) {
            wp_send_json_error( [ 'message' => __( 'Ungültige Signatur.', 'sk-core' ) ] );
        }

        // NIP-98 validation
        $nip98 = json_decode( $authtoken );
        if ( JSON_ERROR_NONE !== json_last_error() ) {
            wp_send_json_error( [ 'message' => __( 'Ungültiges Auth-Token.', 'sk-core' ) ] );
        }

        $tags = array_column( $nip98->tags ?? [], 1, 0 );

        // Strict type checks + timestamp window (120s).
        $valid = true;
        if ( (int) ( $nip98->kind ?? 0 ) !== 27235 ) {
            $valid = false;
        }
        if ( abs( time() - (int) ( $nip98->created_at ?? 0 ) ) > 120 ) {
            $valid = false;
        }
        // URL check: normalize both sides to https to avoid http/https mismatch from caching.
        $expected_url = strtolower( preg_replace( '#^http://#', 'https://', admin_url( 'admin-ajax.php' ) ) );
        $provided_url = strtolower( preg_replace( '#^http://#', 'https://', $tags['u'] ?? '' ) );
        if ( $provided_url !== $expected_url ) {
            $valid = false;
        }
        if ( strtolower( $tags['method'] ?? '' ) !== 'post' ) {
            $valid = false;
        }

        if ( ! $valid ) {
            wp_send_json_error( [ 'message' => __( 'Autorisierung ungültig oder abgelaufen.', 'sk-core' ) ] );
        }

        // Replay protection: check AFTER validation so invalid events don't pollute the cache.
        $event_id = $nip98->id ?? '';
        if ( ! empty( $event_id ) ) {
            $replay_key = 'sk_nip98_' . md5( $event_id );
            if ( get_transient( $replay_key ) ) {
                wp_send_json_error( [ 'message' => __( 'Auth-Token bereits verwendet. Bitte Seite neu laden.', 'sk-core' ) ] );
            }
            set_transient( $replay_key, 1, 120 ); // 2 min — matches timestamp window.
        }

        // Validate public key format
        $public_key = $nip98->pubkey;
        if (!$this->is_valid_public_key($public_key)) {
            wp_send_json_error(array('message' => __('Invalid public key format.', 'sk-core')));
        }

        // Decode and sanitize metadata
        $metadata = json_decode( $metadata_json, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            nostr_login_debug_log( 'Invalid metadata JSON: ' . json_last_error_msg() );
            wp_send_json_error( array( 'message' => __( 'Invalid metadata: ', 'sk-core' ) . json_last_error_msg() ) );
        }

        // Sanitize and validate each field
        $sanitized_metadata = array();
        if ( isset( $metadata['name'] ) ) {
            $sanitized_metadata['name'] = sanitize_text_field( $metadata['name'] );
        }
        if ( isset( $metadata['about'] ) ) {
            $sanitized_metadata['about'] = sanitize_textarea_field( $metadata['about'] );
        }
        if ( isset( $metadata['nip05'] ) ) {
            $sanitized_metadata['nip05'] = sanitize_text_field( $metadata['nip05'] );
            // Optionally validate nip05 format
        }
        if ( isset( $metadata['image'] ) ) {
            $sanitized_metadata['image'] = esc_url_raw( $metadata['image'] );
            // Optionally validate URL
        }
        if ( isset( $metadata['website'] ) ) {
            $sanitized_metadata['website'] = esc_url_raw( $metadata['website'] );
            // Optionally validate URL
        }
        // No email is taken from the metadata: it is not covered by the NIP-98
        // signature, so it is a claim by the caller and must never influence
        // which account this login ends up on.
        if ( isset( $metadata['lud16'] ) ) {
            $sanitized_metadata['lud16'] = sanitize_text_field( $metadata['lud16'] );
        }

        // Check if a user with this public key already exists
        $user = $this->get_user_by_public_key( $public_key );

        if ( ! $user ) {
            // Create a new user if one doesn't exist
            $user_id = $this->create_new_user( $public_key, $sanitized_metadata );
            if ( is_wp_error( $user_id ) ) {
                nostr_login_debug_log( 'Failed to create new user: ' . $user_id->get_error_message() );
                wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
            }
            $user = get_user_by( 'ID', $user_id );
            nostr_login_debug_log( 'New user created with ID: ' . $user_id );
        } else {
            // Update existing user's metadata
            $this->update_user_metadata( $user->ID, $sanitized_metadata );
            nostr_login_debug_log( 'Updated metadata for user ID: ' . $user->ID );
        }

        if ( $user ) {
            wp_set_current_user( $user->ID );
            wp_set_auth_cookie( $user->ID );
            nostr_login_debug_log( 'User logged in successfully: ' . $user->ID );
            $redirect_type = get_option('nostr_login_redirect', 'dashboard');
            $redirect_url = match($redirect_type) {
                'home' => home_url(),
                'profile' => get_edit_profile_url($user->ID),
                'dashboard' => function_exists('sk_get_navigation_url') ? sk_get_navigation_url('dashboard') : home_url('/dashboard/'),
                default => admin_url()
            };
            wp_send_json_success(array('redirect' => $redirect_url));
        } else {
            nostr_login_debug_log( 'Login failed for public key: ' . $public_key );
            wp_send_json_error( array( 'message' => __( 'Login failed. Please try again.', 'sk-core' ) ) );
        }
    }

    private function get_user_by_public_key( $public_key ) {
        $users = get_users( array(
            'meta_key'     => 'nostr_public_key',
            'meta_value'   => sanitize_text_field( $public_key ),
            'number'       => 1,
            'count_total'  => false,
            'fields'       => 'all',
        ) );

        return ! empty( $users ) ? $users[0] : false;
    }

    private function create_new_user( $public_key, $sanitized_metadata ) {
        // Always generate anonymous `satoshi-XXXXX` login — matches BtcLogin
        // pattern. The Nostr profile's `name` is applied separately as
        // display_name via update_user_metadata(), so the user-facing label
        // stays as-is; only the slug/login is anonymized.
        $username = 'satoshi-' . wp_generate_password( 5, false, false );
        while ( username_exists( $username ) ) {
            $username = 'satoshi-' . wp_generate_password( 5, false, false );
        }

        // The account address is derived from the verified public key, never from
        // the submitted metadata: the metadata travels outside the signed NIP-98
        // event, so anyone could claim any address. Matching an existing account
        // by such an address would hand out that account to the caller.
        $email = sanitize_text_field( $public_key ) . '@nostr.local';

        $user_id = wp_create_user( $username, wp_generate_password(), $email );

        if ( ! is_wp_error( $user_id ) ) {
            update_user_meta( $user_id, 'nostr_public_key', sanitize_text_field( $public_key ) );
            $this->update_user_metadata( $user_id, $sanitized_metadata );
        }

        return $user_id;
    }

    private function update_user_metadata( $user_id, $sanitized_metadata ) {
        if ( ! empty( $sanitized_metadata['name'] ) ) {
            wp_update_user( array( 'ID' => $user_id, 'display_name' => sanitize_text_field( $sanitized_metadata['name'] ) ) );
        }
        if ( ! empty( $sanitized_metadata['about'] ) ) {
            update_user_meta( $user_id, 'description', sanitize_textarea_field( $sanitized_metadata['about'] ) );
        }
        if ( ! empty( $sanitized_metadata['nip05'] ) ) {
            update_user_meta( $user_id, 'nip05', sanitize_text_field( $sanitized_metadata['nip05'] ) );
        }
        if ( ! empty( $sanitized_metadata['image'] ) ) {
            $avatar_url = esc_url_raw( $sanitized_metadata['image'] );
            update_user_meta( $user_id, 'nostr_avatar', $avatar_url );
            $saved_avatar_url = get_user_meta( $user_id, 'nostr_avatar', true );
            nostr_login_debug_log( "Saved Nostr avatar URL for user $user_id: " . esc_url( $saved_avatar_url ) );
        }
        if ( ! empty( $sanitized_metadata['website'] ) ) {
            wp_update_user( array(
                'ID'       => $user_id,
                'user_url' => esc_url_raw( $sanitized_metadata['website'] ),
            ) );
        }
        // Save Lightning Address (lud16) from Nostr profile for zap support.
        if ( ! empty( $sanitized_metadata['lud16'] ) ) {
            $lud16 = sanitize_text_field( $sanitized_metadata['lud16'] );
            $settings = get_user_meta( $user_id, 'sk_profile_settings', true );
            if ( ! is_array( $settings ) ) {
                $settings = [];
            }
            if ( empty( $settings['lightning_address'] ) ) {
                $settings['lightning_address'] = $lud16;
                update_user_meta( $user_id, 'sk_profile_settings', $settings );
            }
        }
    }

    public function enqueue_scripts($hook = '') {
        // Check if we're on the login page
        if (in_array($GLOBALS['pagenow'], array('wp-login.php')) || did_action('login_enqueue_scripts')) {
            wp_enqueue_script('nostr-login', SK_AUTH_ASSETS . '/js/nostr-login.min.js', array('jquery'), SK_AUTH_VERSION, true);

            // Sanitize relay URLs
            $relays_option = get_option('nostr_login_relays', implode("\n", $this->default_relays));
            $relays_array = explode("\n", $relays_option);
            $relays = array_filter(array_map('esc_url', array_map('trim', $relays_array)));

            wp_localize_script('nostr-login', 'nostr_login_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('nostr-login-nonce'),
                'relays' => $relays,
            ));
        }

        // For profile page
        if (in_array($hook, array('profile.php', 'user-edit.php'))) {
            wp_enqueue_script('nostr-login', SK_AUTH_ASSETS . '/js/nostr-login.min.js', array('jquery'), SK_AUTH_VERSION, true);

            wp_localize_script('nostr-login', 'nostr_login_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('nostr-login-nonce'),
                'relays' => $this->get_relay_urls()
            ));
        }
    }

    private function get_relay_urls() {
        $relays_option = get_option('nostr_login_relays', implode("\n", $this->default_relays));
        $relays_array = explode("\n", $relays_option);
        return array_filter(array_map('esc_url', array_map('trim', $relays_array)));
    }

    public function add_nostr_login_field() {
        if ( self::$field_added ) {
            return;
        }
        self::$field_added = true;
        ?>
        <div class="nostr-login-container">
            <label for="nostr_login_toggle" class="nostr-toggle-label">
                <input type="checkbox" id="nostr_login_toggle">
                <span><?php esc_html_e( 'Use Nostr Login', 'sk-core' ); ?></span>
            </label>
            <?php wp_nonce_field( 'nostr-login-nonce', 'nostr_login_nonce' ); ?>
        </div>
        <p class="nostr-login-field" style="display:none;">
            <label for="nostr_private_key"><?php esc_html_e( 'Nostr Private Key', 'sk-core' ); ?></label>
            <input type="password" name="nostr_private_key" id="nostr_private_key" class="input" size="20" autocapitalize="off" />
        </p>
        <p class="nostr-login-buttons" style="display:none;">
            <button type="button" id="use_nostr_extension" class="button"><?php esc_html_e( 'Use Nostr Extension', 'sk-core' ); ?></button>
            <input type="submit" name="wp-submit" id="nostr-wp-submit" class="button button-primary" value="<?php esc_attr_e( 'Log In with Nostr', 'sk-core' ); ?>">
        </p>
        <div id="nostr-login-feedback" style="display:none;"></div>
        <?php
        remove_action( 'login_form', array( $this, 'add_nostr_login_field' ) );
    }

    public function ajax_nostr_sync_profile() {
        try {
            if (!check_ajax_referer('nostr-login-nonce', 'nonce', false)) {
                throw new Exception(__('Security check failed.', 'sk-core'));
            }

            if (!is_user_logged_in()) {
                throw new Exception(__('You must be logged in.', 'sk-core'));
            }

            $user_id = get_current_user_id();
            if (!current_user_can('edit_user', $user_id)) {
                throw new Exception(__('You do not have permission to perform this action.', 'sk-core'));
            }

            // Validate and sanitize metadata input
            if (!isset($_POST['metadata']) || empty($_POST['metadata'])) {
                throw new Exception(__('No metadata provided.', 'sk-core'));
            }

            // Sanitize the JSON string before decoding
            $raw_metadata = sanitize_text_field(wp_unslash($_POST['metadata']));
            $metadata = json_decode($raw_metadata, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception(__('Invalid metadata format.', 'sk-core'));
            }

            // Validate public key
            if (empty($metadata['public_key']) || !$this->is_valid_public_key($metadata['public_key'])) {
                throw new Exception(__('Invalid public key.', 'sk-core'));
            }

            // Check for existing public key
            $existing_user = $this->get_user_by_public_key($metadata['public_key']);
            if ($existing_user && $existing_user->ID !== $user_id) {
                throw new Exception(__('This Nostr account is already linked to another user.', 'sk-core'));
            }

            // Update Nostr-specific data
            update_user_meta($user_id, 'nostr_public_key', sanitize_text_field($metadata['public_key']));

            if (!empty($metadata['nip05'])) {
                update_user_meta($user_id, 'nip05', sanitize_text_field($metadata['nip05']));
            }

            if (!empty($metadata['image'])) {
                $avatar_url = esc_url_raw($metadata['image']);
                update_user_meta($user_id, 'nostr_avatar', $avatar_url);
                nostr_login_debug_log("Updated avatar for user $user_id: $avatar_url");
            }

            wp_send_json_success(array('message' => __('Nostr data successfully synced!', 'sk-core')));

        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
}
