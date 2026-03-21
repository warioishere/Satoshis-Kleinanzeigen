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
        if ( is_user_logged_in() ) {
            return '';
        }

        ob_start();
        ?>
        <?php
        $dashboard_url = esc_url( function_exists( 'sk_get_navigation_url' ) ? sk_get_navigation_url( 'dashboard' ) : home_url( '/dashboard/' ) );
        $ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
        ?>
        <div class="nostr-login-box" style="text-align:center;padding:1rem 0;">
          <button id="nostr-login-button" class="sk-btn" aria-label="<?php esc_attr_e( 'Mit Nostr einloggen', 'sk-core' ); ?>" style="font-size:16px;padding:12px 24px;"><?php esc_html_e( 'Mit Nostr einloggen', 'sk-core' ); ?></button>
          <p style="margin-top:1rem;color:#8b949e;font-size:13px;"><?php esc_html_e( 'Benötigt eine Nostr-Erweiterung (z.B. Alby)', 'sk-core' ); ?></p>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', () => {
          const btn = document.getElementById('nostr-login-button');
          if (!btn) return;

          const ajaxUrl = '<?php echo $ajax_url; ?>';
          const dashboardUrl = '<?php echo $dashboard_url; ?>';
          let redirecting = false;

          // Fetch fresh nonce via AJAX (avoids cached-page stale nonce).
          let freshNonce = '';
          const nonceController = new AbortController();
          fetch(ajaxUrl + '?action=sk_auth_check', { credentials: 'same-origin', signal: nonceController.signal })
            .then(r => r.json())
            .then(d => {
              if (d.data && d.data.logged_in) {
                // Already logged in — redirect immediately, no need for login form.
                redirecting = true;
                window.location.href = d.data.redirect || dashboardUrl;
                return;
              }
              if (d.data && d.data.nonce) freshNonce = d.data.nonce;
            })
            .catch(() => {});

          btn.addEventListener('click', async () => {
            if (redirecting) return;
            if (!window.nostr) {
              alert("<?php echo esc_js( __( 'Nostr-kompatible Browsererweiterung nicht gefunden. Bitte z.B. Alby installieren.', 'sk-core' ) ); ?>");
              return;
            }

            try {
              const pubkey = await window.nostr.getPublicKey();

              const metadata = {
                name: "Nostr User",
                about: "Login via Nostr",
                pubkey: pubkey
              };

              const authEvent = await window.nostr.signEvent({
                kind: 27235,
                created_at: Math.floor(Date.now() / 1000),
                tags: [
                  ["u", ajaxUrl],
                  ["method", "post"]
                ],
                content: "Login via Nostr",
                pubkey
              });

              const formData = new URLSearchParams();
              formData.append("action", "nostr_login");
              formData.append("nonce", freshNonce || '<?php echo wp_create_nonce( 'nostr-login-nonce' ); ?>');
              formData.append("metadata", JSON.stringify(metadata));
              formData.append("authtoken", btoa(JSON.stringify(authEvent)));

              const response = await fetch("<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: formData
              });

              let result;
              try {
                result = await response.json();
              } catch (parseErr) {
                // JSON parse failed — likely PHP warnings before JSON output.
                // If response was 200, login probably succeeded (cookie set server-side).
                if (response.ok) {
                  redirecting = true;
                  nonceController.abort();
                  window.location.href = dashboardUrl;
                  return;
                }
                throw parseErr;
              }

              if (result.success) {
                redirecting = true;
                nonceController.abort();
                window.location.href = (result.data && result.data.redirect) || result.redirect || dashboardUrl;
              } else {
                alert("Login fehlgeschlagen: " + (result.data?.message || "Unbekannter Fehler"));
              }

            } catch (err) {
              // NetworkError can happen when wp_set_auth_cookie changes the session
              // mid-request, causing the browser to abort the fetch.
              // Check if we're actually logged in now.
              try {
                const check = await fetch("<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>?action=sk_auth_check", { credentials: "same-origin" });
                const checkResult = await check.json();
                if (checkResult.success && checkResult.data && checkResult.data.logged_in) {
                  redirecting = true;
                  nonceController.abort();
                  window.location.href = checkResult.data.redirect || dashboardUrl;
                  return;
                }
              } catch (e) { /* check failed too */ }
              alert("Login nicht möglich.");
            }
          });
        });
        </script>

        <style>
        .nostr-login-box {
          background: #181e27;
          padding: 2rem;
          border-radius: 12px;
          color: white;
          text-align: center;
          max-width: 400px;
          margin: 0 auto;
        }
        #nostr-login-button {
          background: #8e30eb;
          color: #fff;
          font-size: 1.3rem;
          font-weight: bold;
          border: none;
          border-radius: 10px;
          padding: 1rem 2.5rem;
          cursor: pointer;
          box-shadow: 0 0 10px #ff0077aa;
          transition: all 0.3s ease;
        }
        #nostr-login-button:hover {
          background: #e6006d;
          box-shadow: 0 0 20px #ff0077cc;
          transform: scale(1.05);
        }
        </style>
        <?php
        return ob_get_clean();
    }
}
