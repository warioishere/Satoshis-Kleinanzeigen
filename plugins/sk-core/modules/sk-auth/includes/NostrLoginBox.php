<?php

namespace SK\Modules\Auth;

defined( 'ABSPATH' ) || exit;

/**
 * Nostr Login Box — Frontend shortcode + npub sync to SK profile.
 *
 * Original: mu-plugin nostr-login-box.php by Wario.
 */
class NostrLoginBox {

    public static function init(): void {
        // Sync nostr_public_key → sk_profile_settings['nostr'] (npub).
        add_action( 'added_user_meta',   [ __CLASS__, 'sync_npub' ], 10, 4 );
        add_action( 'updated_user_meta', [ __CLASS__, 'sync_npub' ], 10, 4 );
        add_action( 'set_auth_cookie',   [ __CLASS__, 'sync_on_login' ], 10, 4 );

        // Shortcode.
        add_shortcode( 'nostr_login_box', [ __CLASS__, 'render_shortcode' ] );

        // Use Nostr avatar when available.
        add_filter( 'get_avatar_url', [ __CLASS__, 'nostr_avatar_url' ], 10, 3 );

        // Auth check endpoint (used by JS after NetworkError during login).
        add_action( 'wp_ajax_sk_auth_check', [ __CLASS__, 'ajax_auth_check' ] );
        add_action( 'wp_ajax_nopriv_sk_auth_check', [ __CLASS__, 'ajax_auth_check' ] );
    }

    /**
     * Sync nostr hex pubkey → npub bech32 in sk_profile_settings.
     */
    public static function sync_npub( $meta_id, $user_id, $meta_key, $hex_pubkey ): void {
        if ( $meta_key !== 'nostr_public_key' || empty( $hex_pubkey ) ) {
            return;
        }

        // Libs loaded via sk-core/lib/autoload.php.
        if ( ! class_exists( '\swentel\nostr\Key\Key' ) ) {
            return;
        }

        try {
            $key  = new \swentel\nostr\Key\Key();
            $npub = $key->convertPublicKeyToBech32( $hex_pubkey );
        } catch ( \Throwable $e ) {
            return;
        }

        if ( empty( $npub ) || strpos( $npub, 'npub' ) !== 0 ) {
            return;
        }

        $settings = get_user_meta( $user_id, 'sk_profile_settings', true );
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }

        if ( empty( $settings['nostr'] ) ) {
            $settings['nostr'] = $npub;
            update_user_meta( $user_id, 'sk_profile_settings', $settings );
        }
    }

    /**
     * Fallback sync on login for existing nostr users.
     */
    public static function sync_on_login( $auth_cookie, $expire, $expiration, $user_id ): void {
        $hex_pubkey = get_user_meta( $user_id, 'nostr_public_key', true );
        if ( empty( $hex_pubkey ) ) {
            return;
        }

        $settings = get_user_meta( $user_id, 'sk_profile_settings', true );
        if ( ! empty( $settings['nostr'] ) ) {
            return;
        }

        self::sync_npub( null, $user_id, 'nostr_public_key', $hex_pubkey );
    }

    /**
     * Filter avatar URL: use Nostr avatar if stored.
     */
    public static function nostr_avatar_url( $url, $id_or_email, $args ) {
        $user_id = 0;
        if ( is_numeric( $id_or_email ) ) {
            $user_id = (int) $id_or_email;
        } elseif ( is_object( $id_or_email ) && ! empty( $id_or_email->user_id ) ) {
            $user_id = (int) $id_or_email->user_id;
        } elseif ( is_string( $id_or_email ) ) {
            $user = get_user_by( 'email', $id_or_email );
            if ( $user ) {
                $user_id = $user->ID;
            }
        }

        if ( $user_id > 0 ) {
            $nostr_avatar = get_user_meta( $user_id, 'nostr_avatar', true );
            if ( ! empty( $nostr_avatar ) && filter_var( $nostr_avatar, FILTER_VALIDATE_URL ) ) {
                return $nostr_avatar;
            }
        }

        return $url;
    }

    /**
     * AJAX: Check if the user is logged in (used after NetworkError during login).
     */
    public static function ajax_auth_check(): void {
        if ( is_user_logged_in() ) {
            $redirect_type = get_option( 'nostr_login_redirect', 'dashboard' );
            $redirect_url = match( $redirect_type ) {
                'home'      => home_url(),
                'profile'   => get_edit_profile_url( get_current_user_id() ),
                'dashboard' => function_exists( 'sk_get_navigation_url' ) ? sk_get_navigation_url( 'dashboard' ) : home_url( '/dashboard/' ),
                default     => admin_url(),
            };
            wp_send_json_success( [ 'logged_in' => true, 'redirect' => $redirect_url ] );
        }
        // Return fresh nonce for nopriv users (solves cached-page stale nonce).
        wp_send_json_success( [ 'logged_in' => false, 'nonce' => wp_create_nonce( 'nostr-login-nonce' ) ] );
    }

    /**
     * Shortcode [nostr_login_box].
     */
    public static function render_shortcode(): string {
        wp_enqueue_style( 'sk-nostr-login-box', SK_AUTH_ASSETS . '/css/nostr-login-box.css', array(), SK_AUTH_VERSION );

        if ( is_user_logged_in() ) {
            return '';
        }

        wp_enqueue_script(
            'sk-nostr-login-box',
            SK_AUTH_ASSETS . '/js/nostr-login-box.js',
            array(),
            SK_AUTH_VERSION,
            true
        );
        wp_localize_script(
            'sk-nostr-login-box',
            'skNostrLogin',
            array(
                'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                'dashboardUrl' => function_exists( 'sk_get_navigation_url' ) ? sk_get_navigation_url( 'dashboard' ) : home_url( '/dashboard/' ),
                'nonce'        => wp_create_nonce( 'nostr-login-nonce' ),
                'noExtension'  => __( 'Nostr-kompatible Browsererweiterung nicht gefunden. Bitte z.B. Alby installieren.', 'sk-core' ),
            )
        );

        ob_start();
        ?>
        <div class="nostr-login-box" style="text-align:center;padding:1rem 0;">
          <button id="nostr-login-button" class="sk-btn" aria-label="<?php esc_attr_e( 'Mit Nostr einloggen', 'sk-core' ); ?>" style="font-size:16px;padding:12px 24px;"><?php esc_html_e( 'Mit Nostr einloggen', 'sk-core' ); ?></button>
          <p style="margin-top:1rem;color:#8b949e;font-size:13px;"><?php esc_html_e( 'Benötigt eine Nostr-Erweiterung (z.B. Alby)', 'sk-core' ); ?></p>
        </div>


        <?php
        return ob_get_clean();
    }
}
