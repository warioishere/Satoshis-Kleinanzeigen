<?php

namespace SK\Modules\FollowStore;

use SkFollowStoreRestController;

final class Module {

    /**
     * Module version
     *
     *
     * @var string
     */
    public $version;

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        $this->version = sk_assets_version( __DIR__ . '/assets' );
        $this->define_constants();
        $this->includes();
        $this->load_hooks();
        $this->instances();
    }

    /**
     * Module constants
     *
     *
     * @return void
     */
    private function define_constants() {
        define( 'SK_FOLLOW_STORE_VERSION', $this->version );
        define( 'SK_FOLLOW_STORE_FILE', __FILE__ );
        define( 'SK_FOLLOW_STORE_PATH', dirname( SK_FOLLOW_STORE_FILE ) );
        define( 'SK_FOLLOW_STORE_INCLUDES', SK_FOLLOW_STORE_PATH . '/includes' );
        define( 'SK_FOLLOW_STORE_URL', plugins_url( '', SK_FOLLOW_STORE_FILE ) );
        define( 'SK_FOLLOW_STORE_ASSETS', SK_FOLLOW_STORE_URL . '/assets' );
        define( 'SK_FOLLOW_STORE_VIEWS', SK_FOLLOW_STORE_PATH . '/views' );
    }

    /**
     * Include module related files
     *
     *
     * @return void
     */
    private function includes() {
        require_once SK_FOLLOW_STORE_INCLUDES . '/functions.php';
        require_once SK_FOLLOW_STORE_INCLUDES . '/class-sk-follow-store-install.php';
        require_once SK_FOLLOW_STORE_INCLUDES . '/class-sk-follow-store-scripts.php';
        require_once SK_FOLLOW_STORE_INCLUDES . '/class-sk-follow-store-ajax.php';
        require_once SK_FOLLOW_STORE_INCLUDES . '/class-sk-follow-store-follow-button.php';
        require_once SK_FOLLOW_STORE_INCLUDES . '/class-sk-follow-store-my-account.php';
        require_once SK_FOLLOW_STORE_INCLUDES . '/class-sk-follow-store-vendor-dashboard.php';
        require_once SK_FOLLOW_STORE_INCLUDES . '/class-sk-follow-store-cron.php';
        require_once SK_FOLLOW_STORE_INCLUDES . '/class-sk-follow-store-email-loader.php';
        require_once SK_FOLLOW_STORE_INCLUDES . '/FollowStoreCache.php';
    }

    /**
     * Create module related class instances
     *
     *
     * @return void
     */
    private function instances() {
        new \SK_Follow_Store_Install();
        new \SK_Follow_Store_Scripts();
        new \SK_Follow_Store_Ajax();
        new \SK_Follow_Store_Follow_Button();
        new \SK_Follow_Store_My_Account();
        new \SK_Follow_Store_Vendor_Dashboard();
        new \SK_Follow_Store_Cron();
        new \SK_Follow_Store_Email_Loader();
        new FollowStoreCache();
    }

    /**
     * Load hooks for this modules
     *
     *
     * @return void
     */
    public function load_hooks() {
        // Activation and Deactivation hook
        add_action( 'sk_activated_module_follow_store', [ $this, 'activate' ] );
        add_action( 'sk_deactivated_module_follow_store', [ $this, 'deactivate' ] );

        // flush rewrite rules
        add_action( 'woocommerce_flush_rewrite_rules', [ $this, 'flush_rewrite_rules' ] );
        add_filter( 'sk_rest_api_class_map', [ $this, 'rest_api_class_map' ] );
        add_action( 'plugins_loaded', [ $this, 'load_background_class' ] );
    }

    /**
     * Plugin activation hook
     *
     *
     * @return void
     */
    public function activate() {
        $this->flush_rewrite_rules();
    }

    /**
     * Plugin deactivation hook
     *
     *
     * @return void
     */
    public function deactivate() {
        $this->flush_rewrite_rules();
    }

    /**
     * Flush rewrite rules
     *
     *
     * @return void
     */
    public function flush_rewrite_rules() {
        sk()->rewrite->register_rule();
        flush_rewrite_rules( true );
    }

    public function rest_api_class_map( $class_map ) {
        $class_map[ SK_FOLLOW_STORE_PATH . '/includes/class-sk-follow-store-rest-controller.php' ] = SkFollowStoreRestController::class;

        return $class_map;
    }

    public function load_background_class() {
        $processor_file = SK_FOLLOW_STORE_INCLUDES . '/class-sk-follow-store-send-updates.php';
        if ( ! class_exists( 'SK_Follow_Store_Send_Updates' ) ) {
            require_once $processor_file;
        }

        global $sk_follow_store_updates_bg;
        $sk_follow_store_updates_bg = new \SK_Follow_Store_Send_Updates();
    }
}
