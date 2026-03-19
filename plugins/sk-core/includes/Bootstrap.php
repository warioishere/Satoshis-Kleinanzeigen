<?php

namespace SK\Core;

use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\StoreApi;
use SK\Core\Shipping\Blocks\ExtendEndpoint;

/**
 * Pro Feature Bootstrap
 *
 * Initializes all pro-level features that were previously in sk-pro.
 * Called from sk-core-class.php after sk_loaded.
 *
 */
class Bootstrap {

    private $container = [];

    public function __construct() {
        // Load pro functions early — needed by classes registered in ServiceProviders
        require_once \SK_CORE_INC_DIR . '/extended-functions.php';

        add_action( 'sk_loaded', [ $this, 'init_updater' ], 1 );
        add_action( 'sk_loaded', [ $this, 'init_plugin' ] );

        add_action(
            'woocommerce_blocks_loaded', function () {
                if ( class_exists( StoreApi::class ) && class_exists( ExtendSchema::class ) ) {
                    $extend = StoreApi::container()->get( ExtendSchema::class );
                    ExtendEndpoint::init( $extend );
                }
            }
        );
    }

    public function __get( $prop ) {
        if ( array_key_exists( $prop, $this->container ) ) {
            return $this->container[ $prop ];
        }
        trigger_error( sprintf( 'Undefined property: %s', self::class . '::$' . $prop ) );
    }

    public function __isset( $prop ) {
        return array_key_exists( $prop, $this->container );
    }

    public function init_plugin() {
        $this->includes();
        $this->load_actions();
        $this->load_filters();

        $modules = new ModuleManager();
        $modules->load_active_modules();
        $this->container['module'] = $modules;
    }

    public function includes() {
        // extended-functions.php already required in constructor
        require_once \SK_CORE_INC_DIR . '/functions-wc.php';
    }

    public function load_actions() {
        add_action( 'init', [ $this, 'init_classes' ], 10 );
        add_action( 'init', [ $this, 'init_shipping_class' ], 1 );
        add_action( 'init', [ $this, 'register_scripts' ], 10 );

        add_action( 'sk_enqueue_scripts', [ $this, 'enqueue_scripts' ], 11 );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ], 20 );

        // flush_rewrite_rules is handled by SK_Core — no duplicate registration needed
    }

    public function load_filters() {
        add_filter( 'sk_rest_api_class_map', [ $this, 'rest_api_class_map' ] );
        add_filter( 'sk_is_pro_exists', '__return_true', 99 );
        add_filter( 'sk_query_var_filter', [ $this, 'load_query_var' ], 10 );
        add_filter( 'sk_widgets', [ $this, 'register_widgets' ] );

        add_filter( 'woocommerce_email_classes', [ $this, 'load_sk_emails' ], 36 );
        add_filter( 'sk_email_list', [ $this, 'set_email_template_directory' ], 15 );
        add_filter( 'sk_email_actions', [ $this, 'register_email_actions' ] );
    }

    public function init_classes() {
        new Blocks\ExtendedManager();
        new SettingsApi\Manager();

        if ( is_admin() ) {
            new Admin\ExtendedAdmin();
            new Admin\Ajax();
            new Admin\ShortcodesButton();
        }

        $this->container['announcement']         = sk_get_container()->get( Announcement\Announcement::class );

        if ( ! isset( $this->container['store'] ) ) {
            $this->container['store'] = sk_get_container()->get( Store::class );
        }

        $this->container['shortcodes']           = new Shortcodes\ExtendedShortcodes();
        $this->container['product']              = new Product\ExtendedManager();
        $this->container['products']             = new Products();
        $this->container['review']               = sk_get_container()->get( Review::class );
        $this->container['shipment']             = new Shipping\ShippingStatus();
        $this->container['bg_process']           = new BackgroundProcess\ExtendedManager();
        $this->container['store_category']       = sk_get_container()->get( StoreCategory::class );
        $this->container['digital_product']      = sk_get_container()->get( DigitalProduct::class );

        if ( is_user_logged_in() ) {
            new Dashboard\ExtendedDashboard();
            $this->container['store_settings'] = new StoreSettings();
        }

        $this->container = apply_filters( 'sk_pro_get_class_container', $this->container );

        new ExtendedAssets();

        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            new Ajax();
        }
    }

    public function init_shipping_class() {
        $this->container['shipping_hooks'] = new Shipping\ExtendedHooks();
    }

    public function init_updater() {
        $this->container['license'] = new class {
            public function is_valid(): bool { return true; }
            public function has_license_key(): bool { return true; }
            public function get_plan(): string { return 'business'; }
            public function get_expiry_days(): int { return 9999; }
            public function refresh_license(): void {}
            public function register_license_routes(): void {}
            public function get_license_source_id(): string { return ''; }
            public function get_license_url(): string { return ''; }
        };
    }

    public function register_scripts() {
        [ $suffix, $version ] = sk_get_script_suffix_and_version();
        $jquery_blockui = Assets::get_wc_handler( 'jquery-blockui' );

        wp_register_script( 'serializejson', WC()->plugin_url() . '/assets/js/jquery-serializejson/jquery.serializejson' . $suffix . '.js', [ 'jquery' ], $version, true );
        wp_register_script( $jquery_blockui, WC()->plugin_url() . '/assets/js/jquery-blockui/jquery.blockUI.min.js', [ 'jquery' ], $version, true );
        wp_register_script( 'sk_pro_admin', \SK_CORE_ASSETS . '/js/sk-pro-admin.js', [], $version, true );
    }

    public function enqueue_scripts() {
        if (
            ( sk_is_seller_dashboard() || ( get_query_var( 'edit' ) && is_singular( 'product' ) ) )
            || sk_is_store_page()
            || sk_is_store_review_page()
            || is_account_page()
            || sk_is_store_listing()
            || apply_filters( 'sk_forced_load_scripts', false )
        ) {
            $jquery_blockui = Assets::get_wc_handler( 'jquery-blockui' );
            wp_enqueue_script( 'serializejson' );
            wp_enqueue_script( $jquery_blockui );
            // wp_enqueue_script( 'sk-pro-script' ); // DISABLED — replaced by modular sk-*.js
        }


        if ( get_query_var( 'account-migration' ) ) {
            wp_enqueue_script( 'sk-vendor-registration' );
        }
    }

    public function admin_enqueue_scripts( $hook ) {
        $jquery_blockui = Assets::get_wc_handler( 'jquery-blockui' );
        wp_enqueue_script( $jquery_blockui );
        wp_enqueue_script( 'sk_pro_admin' );

        $sk_admin = apply_filters(
            'sk_admin_localize_param', [
                'ajaxurl'                 => admin_url( 'admin-ajax.php' ),
                'nonce'                   => wp_create_nonce( 'sk-admin-nonce' ),
                'activating'              => __( 'Activating', 'sk' ),
                'deactivating'            => __( 'Deactivating', 'sk' ),
            ]
        );
        wp_localize_script( 'sk_pro_admin', 'sk_admin', $sk_admin );
    }

    public function register_widgets( $widgets ) {
        $widgets['feature_seller'] = Widgets\FeatureSeller::class;
        return $widgets;
    }

    public function rest_api_class_map( $class_map ) {
        return REST\ExtendedManager::register_rest_routes( $class_map );
    }

    public function load_query_var( $query_vars ) {
        $query_vars[] = 'reviews';
        $query_vars[] = 'announcement';
        $query_vars[] = 'single-announcement';
        $query_vars[] = 'sk-registration';
        return $query_vars;
    }

    public function flush_rewrite_rules() {
        if ( ! isset( $this->container['store'] ) ) {
            $this->container['store'] = sk_get_container()->get( Store::class );
        }
        add_filter( 'sk_query_var_filter', [ $this, 'load_query_var' ], 10 );
        sk()->rewrite->register_rule();
        flush_rewrite_rules();
    }

    public function load_sk_emails( $wc_emails ) {
        $wc_emails['SK_Email_Announcement']           = new Emails\Announcement();
        $wc_emails['SK_Email_Updated_Product']        = new Emails\UpdatedProduct();
        $wc_emails['SK_Email_Vendor_Enable']          = new Emails\VendorEnable();
        $wc_emails['SK_Email_Vendor_Disable']         = new Emails\VendorDisable();
        $wc_emails['SK_Email_Shipping_Status']        = new Emails\ShippingStatus();
        $wc_emails['SK_Email_Marked_Order_Received']  = new Emails\MarkedOrderReceive();
        return $wc_emails;
    }

    public function set_email_template_directory( $sk_emails ) {
        $sk_pro_emails = [
            'announcement.php',
            'product-updated-pending.php',
            'vendor-disabled.php',
            'vendor-enabled.php',
            'shipping-status.php',
            'marked-order-receive.php',
        ];
        return array_merge( $sk_pro_emails, $sk_emails );
    }

    public function register_email_actions( $actions ) {
        $actions[] = 'sk_vendor_enabled';
        $actions[] = 'sk_vendor_disabled';
        $actions[] = 'sk_after_announcement_saved';
        $actions[] = 'sk_rma_requested';
        $actions[] = 'sk_marked_order_as_receive';
        $actions[] = 'sk_edited_product_pending_notification';
        $actions[] = 'sk_order_shipping_status_tracking_notify';
        $actions[] = 'sk_pro_process_announcement_background_process';
        return $actions;
    }
}
