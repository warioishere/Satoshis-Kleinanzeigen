<?php

use SK\Core\Contracts\Hookable;
use SK\Core\DependencyManagement\Container;

/**
 * SK_Core class
 *
 * @class SK_Core
 *
 * @property SK\Core\Product\Manager $product
 * @property SK\Core\Vendor\Manager $vendor
 * @property SK\Core\BackgroundProcess\Manager $bg_process
 * @property SK\Core\Frontend\Frontend $frontend_manager
 * @property SK\Core\Registration $registration
 */
final class SK_Core {

    /**
     * Plugin version
     *
     * @var string
     */
    public $version;

    /**
     * Instance of self
     *
     * @var SK_Core
     */
    private static $instance = null;

    /**
     * Minimum PHP version required
     *
     * @var string
     */
    private $min_php = '7.4';

    /**
     * Holds various class instances
     *
     *
     * @var array
     */
    private $legacy_container = [];

    /**
     * Databse version key
     *
     *
     * @var string
     */
    private $db_version_key = 'sk_theme_version';

    /**
     * Constructor for the SK_Core class
     *
     * Sets up all the appropriate hooks and actions
     * within our plugin.
     */
    private function __construct() {
        $this->define_constants();

        register_activation_hook( SK_CORE_FILE, [ $this, 'activate' ] );
        register_deactivation_hook( SK_CORE_FILE, [ $this, 'deactivate' ] );

        add_action( 'before_woocommerce_init', [ $this, 'declare_woocommerce_feature_compatibility' ] );
        add_action( 'woocommerce_loaded', [ $this, 'init_plugin' ] );
        add_action( 'woocommerce_flush_rewrite_rules', [ $this, 'flush_rewrite_rules' ] );

    }

    /**
     * Initializes the SK_Core() class
     *
     * Checks for an existing SK_Core instance
     * and if it doesn't find one, create it.
     */
    public static function init() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Magic getter to bypass referencing objects
     *
     *
     * @param string $prop
     *
     * @return object Class Instance
     */
    public function __get( $prop ) {
        if ( array_key_exists( $prop, $this->legacy_container ) ) {
            return $this->legacy_container[ $prop ];
        }

        if ( $this->get_container()->has( $prop ) ) {
            return $this->get_container()->get( $prop );
        }
    }

    /**
     * Check if the PHP version is supported
     *
     * @return bool
     */
    public function is_supported_php() {
        if ( version_compare( PHP_VERSION, $this->min_php, '<=' ) ) {
            return false;
        }

        return true;
    }

    /**
     * Get the plugin path.
     *
     * @return string
     */
    public function plugin_path() {
        return untrailingslashit( plugin_dir_path( SK_CORE_FILE ) );
    }

    /**
     * Get the template path.
     *
     * @return string
     */
    public function template_path() {
        return apply_filters( 'sk_template_path', 'sk/' );
    }

    /**
     * Placeholder for activation function
     *
     * Nothing being called here yet.
     */
    public function activate() {
        if ( ! $this->has_woocommerce() ) {
            set_transient( 'sk_wc_missing_notice', true );
        }

        if ( ! $this->is_supported_php() ) {
            require_once WC_ABSPATH . 'includes/wc-notice-functions.php';

            /* translators: 1: Required PHP Version 2: Running php version */
            wc_print_notice( sprintf( __( 'The Minimum PHP Version Requirement for <b>SK</b> is %1$s. You are Running PHP %2$s', 'sk-core' ), $this->min_php, phpversion() ), 'error' );
            exit;
        }

        require_once __DIR__ . '/includes/functions.php';
        require_once __DIR__ . '/includes/functions-compatibility.php';

        \SK\Core\Dashboard\ModuleLoader::maybe_create_tables();

        $installer = new \SK\Core\Install\Installer();
        $installer->do_install();

        // rewrite rules during sk activation
        if ( $this->has_woocommerce() ) {
            $this->flush_rewrite_rules();
        }
    }


    /**
     * Flush rewrite rules after sk is activated or woocommerce is activated
     *
     */
    public function flush_rewrite_rules() {
        // fix rewrite rules
        $this->get_container()->get( 'rewrite' )->register_rule();
        flush_rewrite_rules();
    }

    /**
     * Placeholder for deactivation function
     *
     * Nothing being called here yet.
     */
    public function deactivate() {
        delete_transient( 'sk_wc_missing_notice', true );
    }

    /**
     * Initialize plugin for localization
     *
     * @uses load_plugin_textdomain()
     */
    public function localization_setup() {
        load_plugin_textdomain( 'sk-core', false, dirname( plugin_basename( SK_CORE_FILE ) ) . '/languages/' );
    }

    /**
     * Define all constants
     *
     * @return void
     */
    public function define_constants() {
        $this->version = get_file_data( SK_CORE_FILE, [ 'Version' => 'Version' ] )['Version'] ?? '0.0.0';
        defined( 'SK_CORE_VERSION' ) || define( 'SK_CORE_VERSION', $this->version );
        defined( 'SK_CORE_DIR' ) || define( 'SK_CORE_DIR', __DIR__ );
        defined( 'SK_CORE_INC_DIR' ) || define( 'SK_CORE_INC_DIR', __DIR__ . '/includes' );
        defined( 'SK_CORE_LIB_DIR' ) || define( 'SK_CORE_LIB_DIR', __DIR__ . '/lib' );
        defined( 'SK_CORE_ASSETS' ) || define( 'SK_CORE_ASSETS', plugins_url( 'assets', SK_CORE_FILE ) );

        // give a way to turn off loading styles and scripts from parent theme
        defined( 'SK_CORE_LOAD_STYLE' ) || define( 'SK_CORE_LOAD_STYLE', true );
        defined( 'SK_CORE_LOAD_SCRIPTS' ) || define( 'SK_CORE_LOAD_SCRIPTS', true );
    }

    /**
     * Add High Performance Order Storage Support
     *
     *
     * @return void
     */
    public function declare_woocommerce_feature_compatibility() {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SK_CORE_FILE, true );
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', SK_CORE_FILE, true );
        }
    }

    /**
     * Load the plugin after WP User Frontend is loaded
     *
     * @return void
     */
    public function init_plugin() {
        $this->includes();
        $this->init_hooks();

        do_action( 'sk_loaded' );
    }

    /**
     * Initialize the actions
     *
     * @return void
     */
    public function init_hooks() {
        // Localize our plugin
        add_action( 'init', [ $this, 'localization_setup' ] );

        // initialize the classes
        add_action( 'init', [ $this, 'init_classes' ], 4 );
        add_action( 'init', [ $this, 'wpdb_table_shortcuts' ], 1 );

        add_action( 'plugins_loaded', [ $this, 'after_plugins_loaded' ] );

        add_filter( 'plugin_action_links_' . plugin_basename( SK_CORE_FILE ), [ $this, 'plugin_action_links' ] );

        add_action( 'widgets_init', [ $this, 'register_widgets' ] );

        $hooks = $this->get_container()->get( Hookable::class );
        foreach ( $hooks as $hook ) {
            $hook->register_hooks();
        }
    }

    /**
     * Include all the required files
     *
     * @return void
     */
    public function includes() {
        require_once SK_CORE_INC_DIR . '/functions.php';
        require_once SK_CORE_INC_DIR . '/Product/functions.php';
        require_once SK_CORE_INC_DIR . '/functions-compatibility.php';
        require_once SK_CORE_INC_DIR . '/wc-functions.php';

        require_once SK_CORE_INC_DIR . '/wc-template.php';

        if ( is_admin() ) {
            require_once SK_CORE_INC_DIR . '/Admin/functions.php';
        } else {
            require_once SK_CORE_INC_DIR . '/template-tags.php';
        }

    }

    /**
     * Init all the classes
     *
     * @return void
     */
    public function init_classes() {
        $common_services = $this->get_container()->get( 'common-service' );

        if ( is_admin() ) {
            $admin_services = $this->get_container()->get( 'admin-service' );
        } else {
            $frontend_services = $this->get_container()->get( 'frontend-service' );
        }

        $container_services = $this->get_container()->get( 'container-service' );

        $this->legacy_container = apply_filters( 'sk_get_class_container', $this->legacy_container );

        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            $ajax_services = $this->get_container()->get( 'ajax-service' );
        }

        new \SK\Core\Dashboard\ModuleLoader();
    }

    /**
     * Load table prefix for orders table
     *
     *
     * @return void
     */
    public function wpdb_table_shortcuts() {
        global $wpdb;

        $wpdb->sk_announcement   = $wpdb->prefix . 'sk_announcement';
    }

    /**
     * Executed after all plugins are loaded
     *
     * 
     *
     *
     * @return void
     */
    public function after_plugins_loaded() {
        // Initiate background processes
        $processes = get_option( 'sk_background_processes', [] );

        if ( ! empty( $processes ) ) {
            $update = false;
            foreach ( $processes as $processor => $file ) {
                if ( file_exists( $file ) ) {
                    include_once $file;
                    new $processor();
                } else {
                    $update = true;
                    unset( $processes[ $processor ] );
                }
            }
            if ( $update ) {
                update_option( 'sk_background_processes', $processes );
            }
        }
    }

    /**
     * Register widgets
     *
     *
     * @return void
     */
    public function register_widgets() {
        $this->get_container()->get( 'widgets' );
    }

    /**
     * Returns if the plugin is in PRO version
     *
     *
     * @return bool
     */
    public function is_pro_exists() {
        return apply_filters( 'sk_is_pro_exists', false );
    }

    /**
     * Plugin action links
     *
     * @param array $links
     *
     *
     * @return array
     */
    public function plugin_action_links( $links ) {
        $links[] = '<a href="' . admin_url( 'admin.php?page=sk&tab=settings' ) . '">' . __( 'Settings', 'sk-core' ) . '</a>';

        return $links;
    }

    /**
     * Check whether woocommerce is installed and active
     *
     *
     * @return bool
     */
    public function has_woocommerce() {
        return class_exists( 'WooCommerce' );
    }

    /**
     * Check whether woocommerce is installed
     *
     *
     * @return bool
     */
    public function is_woocommerce_installed() {
        return in_array( 'woocommerce/woocommerce.php', array_keys( get_plugins() ), true );
    }

    /**
     * Get db version key
     *
     *
     * @return string
     */
    public function get_db_version_key() {
        return $this->db_version_key;
    }

    /**
     * Retrieve the container instance.
     *
     *
     * @return Container
     */
    public function get_container(): Container {
		return sk_get_container();
    }

}
