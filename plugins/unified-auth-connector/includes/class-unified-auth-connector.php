<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 */
class Unified_Auth_Connector {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     */
    public function __construct() {
        $this->version = UNIFIED_AUTH_CONNECTOR_VERSION;
        $this->plugin_name = 'unified-auth-connector';

        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        require_once UNIFIED_AUTH_CONNECTOR_PATH . 'includes/class-account-linker.php';
        require_once UNIFIED_AUTH_CONNECTOR_PATH . 'includes/class-lnurl-auth-integration.php';
        require_once UNIFIED_AUTH_CONNECTOR_PATH . 'includes/class-nostr-login-integration.php';
        require_once UNIFIED_AUTH_CONNECTOR_PATH . 'includes/class-nostr-profile-sync.php';
        require_once UNIFIED_AUTH_CONNECTOR_PATH . 'includes/class-account-merger.php';
        require_once UNIFIED_AUTH_CONNECTOR_PATH . 'admin/class-sk-dashboard.php';
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     */
    private function define_admin_hooks() {
        // Admin settings page is registered in run_unified_auth_connector() so it
        // is always available regardless of whether the plugin is enabled or not.
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     */
    private function define_public_hooks() {
        // Initialize account linker
        $account_linker = new UAC_Account_Linker();

        // Initialize LNURL-Auth integration
        $lnurl_integration = new UAC_LNURL_Auth_Integration($account_linker);
        add_filter('lnurl_auth_user_id', array($lnurl_integration, 'check_linked_account'), 10, 2);

        // Initialize Nostr Login integration
        $nostr_integration = new UAC_Nostr_Login_Integration($account_linker);
        add_filter('nostr_login_user_id', array($nostr_integration, 'check_linked_account'), 10, 2);

        // Initialize Dokan dashboard integration
        if (class_exists('SK_Lite')) {
            $sk_dashboard = new UAC_Dokan_Dashboard($account_linker);
            add_filter('sk_get_dashboard_nav', array($sk_dashboard, 'add_dashboard_menu'), 20);
            add_filter('sk_query_var_filter', array($sk_dashboard, 'add_query_var'));
            add_filter('sk_dashboard_nav_active', array($sk_dashboard, 'set_active_menu'), 10, 3);
            add_action('sk_load_custom_template', array($sk_dashboard, 'load_template'));
            add_action('wp_ajax_uac_link_nostr', array($sk_dashboard, 'ajax_link_nostr'));
            add_action('wp_ajax_uac_link_lnurl', array($sk_dashboard, 'ajax_link_lnurl'));
            add_action('wp_ajax_uac_unlink_auth', array($sk_dashboard, 'ajax_unlink_auth'));
            add_action('wp_ajax_uac_verify_lnurl_link', array($sk_dashboard, 'ajax_verify_lnurl_link'));
            add_action('wp_ajax_uac_set_sync_preference', array($sk_dashboard, 'ajax_set_sync_preference'));
            add_action('wp_ajax_uac_manual_sync', array($sk_dashboard, 'ajax_manual_sync'));

            // Initialize Nostr profile sync to Dokan
            $nostr_profile_sync = new UAC_Nostr_Profile_Sync();
        }

        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     */
    public function enqueue_styles() {
        if (sk_is_seller_dashboard()) {
            $css_file = UNIFIED_AUTH_CONNECTOR_PATH . 'assets/css/unified-auth-connector.css';
            wp_enqueue_style(
                $this->plugin_name,
                UNIFIED_AUTH_CONNECTOR_URL . 'assets/css/unified-auth-connector.css',
                array(),
                file_exists($css_file) ? filemtime($css_file) : $this->version,
                'all'
            );
        }
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     */
    public function enqueue_scripts() {
        if (sk_is_seller_dashboard()) {
            $js_file = UNIFIED_AUTH_CONNECTOR_PATH . 'assets/js/unified-auth-connector.js';
            wp_enqueue_script(
                $this->plugin_name,
                UNIFIED_AUTH_CONNECTOR_URL . 'assets/js/unified-auth-connector.js',
                array('jquery'),
                file_exists($js_file) ? filemtime($js_file) : $this->version,
                false
            );

            wp_localize_script(
                $this->plugin_name,
                'uacData',
                array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('uac_nonce'),
                    'isSkVendor' => (class_exists('SK_Lite') && function_exists('sk_is_user_seller') && sk_is_user_seller(get_current_user_id())),
                    'i18n' => array(
                        'linking' => 'Verknüpfe...',
                        'unlinking' => 'Trenne...',
                        'error' => 'Ein Fehler ist aufgetreten. Bitte versuche es erneut.',
                        'confirm_unlink' => 'Bist Du sicher, dass Du diese Authentifizierungsmethode trennen möchtest?',
                        'confirm_sync' => 'Möchtest Du Dein Nostr-Profil (Name, Bild, Banner, Biografie) jetzt mit Deinem SK-Shop synchronisieren?',
                    )
                )
            );
        }
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     */
    public function run() {
        // All hooks are already registered in constructor
    }
}
