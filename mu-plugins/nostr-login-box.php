<?php
/**
 * Plugin Name: MU – Nostr Login Box
 * Description: Fügt den Nostr Login Shortcode und Einstellungsseite hinzu.
 * Version: 1.0.0
 * Author: Wario
 */

// ── Sync nostr_public_key → sk_profile_settings['nostr'] (npub) ──────────────
// Fires whenever the nostr-login plugin sets/updates a user's nostr_public_key.
// Converts the hex pubkey to npub bech32 and writes it into the vendor's
// contact-details profile settings if not already set.
function sk_sync_nostr_npub_to_profile( $meta_id, $user_id, $meta_key, $hex_pubkey ) {
    if ( $meta_key !== 'nostr_public_key' ) return;
    if ( empty( $hex_pubkey ) ) return;

    // Load the nostr-php Key class from the nostr-login plugin vendor directory
    $key_class = WP_PLUGIN_DIR . '/nostr-login/vendor/swentel/nostr-php/src/Key/Key.php';
    if ( ! file_exists( $key_class ) ) return;
    require_once $key_class;

    // Also need the bech32 library it depends on
    $autoload = WP_PLUGIN_DIR . '/nostr-login/vendor/autoload.php';
    if ( file_exists( $autoload ) ) {
        require_once $autoload;
    }

    try {
        $key  = new \swentel\nostr\Key\Key();
        $npub = $key->convertPublicKeyToBech32( $hex_pubkey );
    } catch ( \Throwable $e ) {
        return;
    }

    if ( empty( $npub ) || strpos( $npub, 'npub' ) !== 0 ) return;

    $settings = get_user_meta( $user_id, 'sk_profile_settings', true );
    if ( ! is_array( $settings ) ) $settings = [];

    // Only write if not already set to something
    if ( empty( $settings['nostr'] ) ) {
        $settings['nostr'] = $npub;
        update_user_meta( $user_id, 'sk_profile_settings', $settings );
    }
}
add_action( 'added_user_meta',   'sk_sync_nostr_npub_to_profile', 10, 4 );
add_action( 'updated_user_meta', 'sk_sync_nostr_npub_to_profile', 10, 4 );

// Also covers existing users logging in again: nostr_public_key is already set
// so the meta hooks above never fire. set_auth_cookie fires for every login.
add_action( 'set_auth_cookie', function ( $auth_cookie, $expire, $expiration, $user_id ) {
    $hex_pubkey = get_user_meta( $user_id, 'nostr_public_key', true );
    if ( empty( $hex_pubkey ) ) return; // not a nostr user

    $settings = get_user_meta( $user_id, 'sk_profile_settings', true );
    if ( ! empty( $settings['nostr'] ) ) return; // already filled in

    sk_sync_nostr_npub_to_profile( null, $user_id, 'nostr_public_key', $hex_pubkey );
}, 10, 4 );

// Nostr Login

add_shortcode('nostr_login_box', function () {
    $enabled = get_option('show_nostr_login_box', true);
    if (!$enabled) return '';

    ob_start();
    ?>
    <div class="nostr-login-box" style="text-align:center;padding:1rem 0;">
      <button id="nostr-login-button" class="sk-btn" style="font-size:16px;padding:12px 24px;">🔑 Mit Nostr einloggen</button>
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
              ["u", "<?php echo admin_url('admin-ajax.php'); ?>"],
              ["method", "post"]
            ],
            content: "Login via Nostr",
            pubkey
          });

          const formData = new URLSearchParams();
          formData.append("action", "nostr_login");
          formData.append("nonce", "<?php echo wp_create_nonce('nostr-login-nonce'); ?>");
          formData.append("metadata", JSON.stringify(metadata));
          formData.append("authtoken", btoa(JSON.stringify(authEvent)));

          const response = await fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded"
            },
            body: formData
          });

          const result = await response.json();

          if (result.success) {
            window.location.href = result.redirect || '/shop';
          } else {
            alert("Login fehlgeschlagen: " + (result.data?.message || "Unbekannter Fehler"));
          }

        } catch (err) {
          console.error("Fehler beim Nostr-Login", err);
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
      font-family: sans-serif;
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
});

// === Menüpunkt im WP-Admin unter "Einstellungen" hinzufügen ===
add_action('admin_menu', function () {
    add_options_page(
        'Nostr Login Einstellungen',
        'Nostr Login Box Anzeigen',
        'manage_options',
        'nostr-login-settings',
        'render_nostr_login_settings_page'
    );
});

// === Option registrieren ===
add_action('admin_init', function () {
    register_setting('nostr_login_settings_group', 'show_nostr_login_box');
    add_settings_section('nostr_login_main', '', null, 'nostr-login-settings');
    add_settings_field(
        'show_nostr_login_box',
        'Nostr Login-Box anzeigen?',
        function () {
            $value = get_option('show_nostr_login_box', true);
            echo '<input type="hidden" name="show_nostr_login_box" value="0">';
            echo '<label><input type="checkbox" name="show_nostr_login_box" value="1" ' . checked(1, $value, false) . '> Aktiviert</label>';
        },
        'nostr-login-settings',
        'nostr_login_main'
    );
});

// === HTML-Ausgabe der Seite ===
function render_nostr_login_settings_page() {
    ?>
    <div class="wrap">
        <h1>Nostr Login Einstellungen</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('nostr_login_settings_group');
            do_settings_sections('nostr-login-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}
