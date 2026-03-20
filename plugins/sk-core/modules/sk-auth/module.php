<?php

namespace SK\Modules\Auth;

defined( 'ABSPATH' ) || exit;

// Global functions (no namespace) — loaded before the class.
require_once __DIR__ . '/includes/global-functions.php';

/**
 * SK Auth — Unified authentication module.
 *
 * Consolidates three login methods into sk-core:
 *   - BTC Login: Bitcoin address + password (by SK)
 *   - LNURL Auth: Lightning wallet QR login (original: Joel Stuedle, https://github.com/joel-st/lnurl-auth-for-wordpress)
 *   - Nostr Login: NIP-07/NIP-98 browser extension login (original: Yeghro, https://github.com/Yeghro/YEGHRO_NostrLogin)
 *   - Nostr Login Box: Frontend widget + npub sync (by SK)
 *   - Nostr Import: Post/comment import from Nostr (original: Yeghro)
 *
 * All vendor dependencies are loaded centrally via sk-core/lib/autoload.php.
 */
final class Module {

    public $version = '1.0.0';

    public function __construct() {
        $this->define_constants();

        // Settings always load (visible in admin even when methods are disabled).
        require_once SK_AUTH_INCLUDES . '/AuthSettings.php';
        new AuthSettings();

        $this->load_lnurl_auth();
        $this->load_nostr_login();
        $this->load_btc_login();
        $this->load_connector();
        $this->register_shortcodes();
    }

    private function define_constants() {
        define( 'SK_AUTH_VERSION', $this->version );
        define( 'SK_AUTH_FILE', __FILE__ );
        define( 'SK_AUTH_PATH', dirname( SK_AUTH_FILE ) );
        define( 'SK_AUTH_INCLUDES', SK_AUTH_PATH . '/includes' );
        define( 'SK_AUTH_URL', plugins_url( '', SK_AUTH_FILE ) );
        define( 'SK_AUTH_ASSETS', SK_AUTH_URL . '/assets' );
        define( 'SK_AUTH_TEMPLATES', SK_AUTH_PATH . '/templates' );

        // LNURL Auth compatibility constants.
        define( 'SK_AUTH_LNURL_PREFIX', 'lnurl-auth' );
    }

    /**
     * LNURL Auth — Lightning wallet login.
     * Original plugin by Joel Stuedle (https://github.com/joel-st/lnurl-auth-for-wordpress).
     */
    private function load_lnurl_auth() {
        require_once SK_AUTH_INCLUDES . '/LnurlHelpers.php';
        require_once SK_AUTH_INCLUDES . '/LnurlTransients.php';
        require_once SK_AUTH_INCLUDES . '/LnurlAssets.php';
        require_once SK_AUTH_INCLUDES . '/LnurlLogin.php';
        require_once SK_AUTH_INCLUDES . '/LnurlSettings.php';
        require_once SK_AUTH_INCLUDES . '/LnurlPlugin.php';

        $plugin = Lnurl\Plugin::get_instance( SK_AUTH_FILE );
        if ( $plugin ) {
            $plugin->run();
        }
    }

    /**
     * Nostr Login — NIP-07 browser extension login.
     * Original plugin by Yeghro (https://github.com/Yeghro/YEGHRO_NostrLogin).
     */
    private function load_nostr_login() {
        require_once SK_AUTH_INCLUDES . '/NostrLogin.php';
        require_once SK_AUTH_INCLUDES . '/NostrImport.php';
        require_once SK_AUTH_INCLUDES . '/NostrLoginBox.php';

        $login = new Nostr_Login_Handler();
        $login->init();

        $import = new Nostr_Import_Handler();
        $import->init();

        NostrLoginBox::init();
    }

    /**
     * BTC Login — Bitcoin address + password login.
     * By SK.
     */
    private function load_btc_login() {
        require_once SK_AUTH_INCLUDES . '/BtcLogin.php';
        new BtcLogin();
    }

    /**
     * Unified Auth Connector — Account linking, merging, profile sync.
     * Links LNURL-Auth and Nostr Login to a single WordPress account.
     * By SK.
     */
    private function load_connector() {
        if ( get_option( 'uac_enabled', 'no' ) !== 'yes' ) {
            return;
        }

        require_once SK_AUTH_INCLUDES . '/Connector/AccountLinker.php';
        require_once SK_AUTH_INCLUDES . '/Connector/AccountMerger.php';
        require_once SK_AUTH_INCLUDES . '/Connector/LnurlIntegration.php';
        require_once SK_AUTH_INCLUDES . '/Connector/NostrIntegration.php';
        require_once SK_AUTH_INCLUDES . '/Connector/NostrProfileSync.php';
        require_once SK_AUTH_INCLUDES . '/Connector/Dashboard.php';

        $account_linker = new \UAC_Account_Linker();

        $lnurl_integration = new \UAC_LNURL_Auth_Integration( $account_linker );
        $nostr_integration = new \UAC_Nostr_Login_Integration( $account_linker );

        if ( class_exists( 'SK_Core' ) ) {
            $sk_dashboard = new \UAC_Dokan_Dashboard( $account_linker );
            add_filter( 'sk_get_dashboard_nav', [ $sk_dashboard, 'add_dashboard_menu' ], 20 );
            add_filter( 'sk_query_var_filter', [ $sk_dashboard, 'add_query_var' ] );
            add_filter( 'sk_dashboard_nav_active', [ $sk_dashboard, 'set_active_menu' ], 10, 3 );
            add_action( 'sk_load_custom_template', [ $sk_dashboard, 'load_template' ] );
            add_action( 'wp_ajax_uac_link_nostr', [ $sk_dashboard, 'ajax_link_nostr' ] );
            add_action( 'wp_ajax_uac_link_lnurl', [ $sk_dashboard, 'ajax_link_lnurl' ] );
            add_action( 'wp_ajax_uac_unlink_auth', [ $sk_dashboard, 'ajax_unlink_auth' ] );
            add_action( 'wp_ajax_uac_verify_lnurl_link', [ $sk_dashboard, 'ajax_verify_lnurl_link' ] );
            add_action( 'wp_ajax_uac_set_sync_preference', [ $sk_dashboard, 'ajax_set_sync_preference' ] );
            add_action( 'wp_ajax_uac_manual_sync', [ $sk_dashboard, 'ajax_manual_sync' ] );

            new \UAC_Nostr_Profile_Sync();
        }

        // Enqueue assets on vendor dashboard.
        add_action( 'wp_enqueue_scripts', function () {
            if ( ! function_exists( 'sk_is_seller_dashboard' ) || ! sk_is_seller_dashboard() ) {
                return;
            }
            wp_enqueue_style( 'unified-auth-connector', SK_AUTH_ASSETS . '/css/unified-auth-connector.css', [], SK_AUTH_VERSION );
            wp_enqueue_script( 'unified-auth-connector', SK_AUTH_ASSETS . '/js/unified-auth-connector.js', [ 'jquery' ], SK_AUTH_VERSION, true );
            wp_localize_script( 'unified-auth-connector', 'uacData', [
                'ajaxurl'      => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( 'uac_nonce' ),
                'isSkVendor'   => ( function_exists( 'sk_is_user_seller' ) && sk_is_user_seller( get_current_user_id() ) ),
                'i18n'         => [
                    'linking'        => 'Verknüpfe...',
                    'unlinking'      => 'Trenne...',
                    'error'          => 'Ein Fehler ist aufgetreten. Bitte versuche es erneut.',
                    'confirm_unlink' => 'Bist Du sicher, dass Du diese Authentifizierungsmethode trennen möchtest?',
                    'confirm_sync'   => 'Möchtest Du Dein Nostr-Profil jetzt mit Deinem SK-Shop synchronisieren?',
                ],
            ] );
        } );
    }

    /**
     * Register the unified [sk_login] shortcode.
     * Tabbed UI: Lightning, Bitcoin, Nostr.
     */
    private function register_shortcodes() {
        add_shortcode( 'sk_login', [ $this, 'render_sk_login' ] );
        add_shortcode( 'lnurl_auth_conditional', [ $this, 'render_sk_login' ] );
    }

    public function render_sk_login() {
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            $dashboard_url = function_exists( 'sk_get_navigation_url' ) ? sk_get_navigation_url( 'dashboard' ) : home_url( '/dashboard/' );
            return '<p style="text-align:center;">Hallo <strong>' . esc_html( $user->display_name ) . '</strong>, du bist bereits eingeloggt. <a href="' . esc_url( $dashboard_url ) . '">Zum Dashboard</a></p>';
        }

        ob_start();
        ?>
        <div class="sk-login-tabs">
            <div class="sk-login-tab-buttons">
                <button class="sk-login-tab-btn active" data-tab="lightning">Lightning</button>
                <button class="sk-login-tab-btn" data-tab="bitcoin">Bitcoin</button>
                <button class="sk-login-tab-btn" data-tab="nostr">Nostr</button>
            </div>
            <div class="sk-login-tab-content">
                <div class="sk-login-panel active" id="sk-login-lightning">
                    <?php echo do_shortcode( '[lnurl_auth]' ); ?>
                    <div id="lnurl-copy-button-container"></div>
                </div>
                <div class="sk-login-panel" id="sk-login-bitcoin">
                    <?php echo do_shortcode( '[btc_login]' ); ?>
                </div>
                <div class="sk-login-panel" id="sk-login-nostr">
                    <?php echo do_shortcode( '[nostr_login_box]' ); ?>
                </div>
            </div>
        </div>
        <style>
        .sk-login-tabs { max-width: 480px; margin: 0 auto; }
        .sk-login-tab-buttons {
            display: flex; gap: 0; border-bottom: 2px solid #2b3240;
            margin-bottom: 1.5rem;
        }
        .sk-login-tab-btn {
            flex: 1; padding: 12px 16px; border: none; background: #181e27;
            color: #8b949e; font-size: 15px; font-weight: 600; cursor: pointer;
            border-bottom: 3px solid transparent; transition: all 0.2s;
        }
        .sk-login-tab-btn:first-child { border-radius: 8px 0 0 0; }
        .sk-login-tab-btn:last-child { border-radius: 0 8px 0 0; }
        .sk-login-tab-btn:hover { color: #e2e8f0; background: #1f2733; }
        .sk-login-tab-btn.active {
            color: #f7931a; border-bottom-color: #f7931a; background: #1f2733;
        }
        .sk-login-panel { display: none; text-align: center; padding: 1rem 0; }
        .sk-login-panel.active { display: block; }
        @media (max-width: 480px) {
            .sk-login-tab-btn { font-size: 13px; padding: 10px 8px; }
        }
        </style>
        <script>
        (function() {
            var btns = document.querySelectorAll('.sk-login-tab-btn');
            var panels = document.querySelectorAll('.sk-login-panel');
            btns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    btns.forEach(function(b) { b.classList.remove('active'); });
                    panels.forEach(function(p) { p.classList.remove('active'); });
                    btn.classList.add('active');
                    document.getElementById('sk-login-' + btn.dataset.tab).classList.add('active');
                });
            });
            /* LNURL copy button */
            var retries = 0, maxRetries = 100;
            var iv = setInterval(function() {
                var link = document.querySelector('.lnurl-auth-permalink a[href^="lightning:"]');
                var container = document.getElementById('lnurl-copy-button-container');
                if (link && container && !document.getElementById('lnurl-copy-button')) {
                    var lnurl = link.getAttribute('href').replace(/^lightning:/, '').trim();
                    if (lnurl) {
                        var btn = document.createElement('button');
                        btn.id = 'lnurl-copy-button';
                        btn.textContent = 'LNURL kopieren';
                        btn.className = 'sk-btn';
                        btn.style.marginTop = '1em';
                        btn.onclick = function() {
                            navigator.clipboard.writeText(lnurl).then(function() {
                                btn.textContent = 'Kopiert!';
                                setTimeout(function() { btn.textContent = 'LNURL kopieren'; }, 2000);
                            });
                        };
                        container.appendChild(btn);
                    }
                    clearInterval(iv);
                }
                if (++retries >= maxRetries) clearInterval(iv);
            }, 100);
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
