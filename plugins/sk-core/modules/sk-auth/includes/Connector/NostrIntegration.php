<?php
/**
 * Nostr Login Integration
 *
 * Handles integration with the Nostr Login plugin to support unified authentication.
 */
class UAC_Nostr_Login_Integration {

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
        add_action('wp_login', array($this, 'handle_nostr_login'), 5, 2);

        // Intercept nostr login AJAX before nostr-login plugin handles it (priority 1 vs their 10).
        // Needed when a user registered via LN, linked Nostr via UAC, then tries to log in via Nostr.
        // nostr-login would fail to find them (no nostr_public_key meta on LN user) and attempt
        // to create a new account, hitting an "email already in use" error.
        add_action('wp_ajax_nopriv_nostr_login', array($this, 'maybe_handle_linked_login'), 1);
    }

    /**
     * Handle Nostr Login and redirect to primary account if linked.
     *
     * @param string $user_login Username
     * @param WP_User $user The user object
     */
    public function handle_nostr_login($user_login, $user) {
        // Check if this is a Nostr login
        $nostr_pubkey = get_user_meta($user->ID, 'nostr_public_key', true);

        if (empty($nostr_pubkey)) {
            return; // Not a Nostr login
        }

        // Check if this pubkey is linked to a different primary account
        $primary_user_id = $this->account_linker->get_user_by_nostr($nostr_pubkey);

        if ($primary_user_id && $primary_user_id !== $user->ID) {
            // This Nostr identity is linked to a different account
            // Log out current user and log into primary account
            wp_clear_auth_cookie();
            wp_set_current_user($primary_user_id);
            wp_set_auth_cookie($primary_user_id, true);

            // Prevent infinite loop by removing this hook temporarily
            remove_action('wp_login', array($this, 'handle_nostr_login'), 5);

            // Trigger wp_login for the primary user
            do_action('wp_login', get_userdata($primary_user_id)->user_login, get_user_by('id', $primary_user_id));

            // Re-add the hook
            add_action('wp_login', array($this, 'handle_nostr_login'), 5, 2);
        }
    }

    /**
     * Intercept the nostr_login AJAX action early.
     * If the pubkey from the authtoken is UAC-linked to an account that has no nostr_public_key meta
     * (e.g. an LN user who linked Nostr), log that user in directly before nostr-login tries to
     * create a new account and hits an "email already in use" error.
     */
    public function maybe_handle_linked_login() {
        // Same login-CSRF guard as the main Nostr login handler.
        if (function_exists('sk_is_same_origin_request') && !sk_is_same_origin_request()) {
            return;
        }

        $authtoken_raw = isset($_POST['authtoken']) ? sanitize_text_field(wp_unslash($_POST['authtoken'])) : '';
        if (empty($authtoken_raw)) return;

        // Read the claimed pubkey WITHOUT trusting it — it is only used to decide
        // whether this handler has to intervene at all. Nothing may be
        // authenticated on the strength of it: a Nostr pubkey is public, so
        // anyone can name someone else's.
        $claimed = json_decode(base64_decode($authtoken_raw));
        if (!$claimed || empty($claimed->pubkey) || !is_string($claimed->pubkey)) return;

        $claimed_pubkey = strtolower(sanitize_text_field($claimed->pubkey));
        if (!preg_match('/^[a-f0-9]{64}$/', $claimed_pubkey)) return;

        // Is there a linked account for it, and does nostr-login need our help?
        $linked_user_id = $this->account_linker->get_user_by_nostr($claimed_pubkey);
        if (!$linked_user_id) return;

        // If the linked user already has nostr_public_key set for this pubkey,
        // nostr-login's own get_user_by_public_key() will find them — no need to intercept.
        $existing_pubkey = get_user_meta($linked_user_id, 'nostr_public_key', true);
        if (strtolower((string) $existing_pubkey) === $claimed_pubkey) return;

        // From here on we would log somebody in, so the signature has to hold.
        // verify_nostr_identity() checks the Schnorr signature, the NIP-98 kind,
        // the timestamp window, the endpoint binding and single use.
        $verified = $this->verify_nostr_identity($authtoken_raw);
        if (is_wp_error($verified)) return;

        if (strtolower($verified['pubkey']) !== $claimed_pubkey) return;

        // Resolve the account from the VERIFIED key, not from the claim.
        $user_id = $this->account_linker->get_user_by_nostr(strtolower($verified['pubkey']));
        if (!$user_id) return;

        // Nonce stays as an extra hurdle, but it is not an authentication factor:
        // nostr-login hands one out to logged-out visitors on request.
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'nostr-login-nonce')) return;

        $user = get_user_by('ID', $user_id);
        if (!$user) return;

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);

        $redirect_type = get_option('nostr_login_redirect', 'admin');
        if ($redirect_type === 'home') {
            $redirect_url = home_url();
        } elseif ($redirect_type === 'profile') {
            $redirect_url = get_edit_profile_url($user->ID);
        } else {
            $redirect_url = admin_url();
        }

        wp_send_json_success(array('redirect' => $redirect_url));
        // wp_send_json_success calls wp_die()
    }

    /**
     * Filter to check for linked accounts (if we add a custom filter to Nostr Login plugin).
     *
     * This is a placeholder for potential future integration.
     *
     * @param int $user_id The user ID
     * @param string $nostr_pubkey The Nostr public key
     * @return int The user ID to use for authentication
     */
    public function check_linked_account($user_id, $nostr_pubkey) {
        $primary_user_id = $this->account_linker->get_user_by_nostr($nostr_pubkey);

        if ($primary_user_id && $primary_user_id !== $user_id) {
            return $primary_user_id;
        }

        return $user_id;
    }

    /**
     * Verify a Nostr public key for linking.
     *
     * This verifies that the user controls the Nostr identity by checking
     * a signed authentication event.
     *
     * @param string $authtoken Base64-encoded NIP-98 auth event
     * @return array|WP_Error Array with 'pubkey' on success, WP_Error on failure
     */
    public function verify_nostr_identity($authtoken) {
        if (empty($authtoken)) {
            return new WP_Error('invalid_authtoken', 'Authentifizierungstoken erforderlich.');
        }

        // Decode authtoken
        $authtoken_decoded = base64_decode($authtoken);
        if (!$authtoken_decoded) {
            return new WP_Error('invalid_authtoken', 'Fehler beim Dekodieren des Authentifizierungstokens.');
        }

        // Verify using Nostr Event class
        if (!class_exists('swentel\nostr\Event\Event')) {
            return new WP_Error('nostr_plugin_missing', 'Nostr-Login-Plugin ist nicht aktiv.');
        }

        $event = new \swentel\nostr\Event\Event();
        if (!$event->verify($authtoken_decoded)) {
            return new WP_Error('invalid_signature', 'Ungültige Nostr-Event-Signatur.');
        }

        // Validate NIP-98 requirements
        $nip98 = json_decode($authtoken_decoded);
        if (JSON_ERROR_NONE !== json_last_error()) {
            return new WP_Error('invalid_json', 'Ungültiges Authtoken-Format.');
        }

        // Check kind is 27235 (NIP-98)
        if (27235 !== (int) ($nip98->kind ?? 0)) {
            return new WP_Error('invalid_kind', 'Ungültiger Event-Typ. NIP-98 Auth-Event erwartet.');
        }

        // Timestamp window in BOTH directions — a one-sided check accepts a
        // token dated far in the future, which would then never expire.
        if (abs(time() - (int) ($nip98->created_at ?? 0)) > 60) {
            return new WP_Error('expired_token', 'Authentifizierungstoken ist abgelaufen.');
        }

        // Bind the token to this endpoint and method, so a NIP-98 token the user
        // signed for some other service cannot be replayed here to link its key.
        $tags = array_column($nip98->tags ?? [], 1, 0);

        $expected_url = strtolower(preg_replace('#^http://#', 'https://', admin_url('admin-ajax.php')));
        $provided_url = strtolower(preg_replace('#^http://#', 'https://', $tags['u'] ?? ''));

        if ($provided_url !== $expected_url) {
            return new WP_Error('invalid_url', 'Auth-Token gilt nicht für diese Seite.');
        }

        if (strtolower($tags['method'] ?? '') !== 'post') {
            return new WP_Error('invalid_method', 'Auth-Token hat die falsche Methode.');
        }

        // Validate public key format
        if (!preg_match('/^[a-f0-9]{64}$/i', $nip98->pubkey)) {
            return new WP_Error('invalid_pubkey', 'Ungültiges Format des öffentlichen Schlüssels.');
        }

        // Single use: linking and merging are destructive, so a captured token
        // must not work twice.
        $event_id = $nip98->id ?? '';
        if (empty($event_id)) {
            return new WP_Error('invalid_authtoken', 'Auth-Token ohne Event-ID.');
        }

        $replay_key = 'sk_uac_nip98_' . md5($event_id);
        if (get_transient($replay_key)) {
            return new WP_Error('token_reused', 'Auth-Token bereits verwendet. Bitte erneut versuchen.');
        }
        set_transient($replay_key, 1, 60);

        return array(
            'pubkey' => strtolower($nip98->pubkey)
        );
    }
}
