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
        wp_send_json_success( [ 'logged_in' => false ] );
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
        <div class="nostr-login-box" style="text-align:center;padding:1rem 0;">
          <button id="nostr-login-button" class="sk-btn" style="font-size:16px;padding:12px 24px;">Mit Nostr einloggen</button>
          <p style="margin-top:1rem;color:#8b949e;font-size:13px;">Benötigt eine Nostr-Erweiterung (z.B. Alby)</p>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', () => {
          const btn = document.getElementById('nostr-login-button');
          if (!btn) return;

          btn.addEventListener('click', async () => {
            if (!window.nostr) {
              alert("Nostr-kompatible Browsererweiterung nicht gefunden. Bitte z.B. Alby installieren.");
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
                  ["u", "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"],
                  ["method", "post"]
                ],
                content: "Login via Nostr",
                pubkey
              });

              const formData = new URLSearchParams();
              formData.append("action", "nostr_login");
              formData.append("nonce", "<?php echo wp_create_nonce( 'nostr-login-nonce' ); ?>");
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
                  window.location.href = '<?php echo esc_url( function_exists("sk_get_navigation_url") ? sk_get_navigation_url("dashboard") : home_url("/dashboard/") ); ?>';
                  return;
                }
                throw parseErr;
              }

              if (result.success) {
                window.location.href = (result.data && result.data.redirect) || result.redirect || '<?php echo esc_url( function_exists("sk_get_navigation_url") ? sk_get_navigation_url("dashboard") : home_url("/dashboard/") ); ?>';
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
                  window.location.href = checkResult.data.redirect || '<?php echo esc_url( function_exists("sk_get_navigation_url") ? sk_get_navigation_url("dashboard") : home_url("/dashboard/") ); ?>';
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
