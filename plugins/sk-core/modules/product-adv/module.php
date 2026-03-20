<?php
namespace SK\Modules\ProductAdvertisement;

use SK\Core\Traits\ChainableContainer;
use SK\Modules\ProductAdvertisement\Admin\Admin;
use SK\Modules\ProductAdvertisement\Admin\Install;
use SK\Modules\ProductAdvertisement\Admin\Settings;
use SK\Modules\ProductAdvertisement\Admin\VendorSubscription;
use SK\Modules\ProductAdvertisement\Frontend\Cart;
use SK\Modules\ProductAdvertisement\Frontend\Order;
use SK\Modules\ProductAdvertisement\Frontend\Product;
use SK\Modules\ProductAdvertisement\Frontend\Shortcode;
use SK\Modules\ProductAdvertisement\Frontend\ProductWidget;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class Module
 *
 *
 */
final class Module {

    use ChainableContainer;

    /**
     * Cloning is forbidden.
     *
     */
    public function __clone() {
        $message = ' Backtrace: ' . wp_debug_backtrace_summary();
        _doing_it_wrong( __METHOD__, $message . __( 'Cloning is forbidden.', 'sk' ), SK_CORE_VERSION );
    }

    /**
     * Unserializing instances of this class is forbidden.
     *
     */
    public function __wakeup() {
        $message = ' Backtrace: ' . wp_debug_backtrace_summary();
        _doing_it_wrong( __METHOD__, $message . __( 'Unserializing instances of this class is forbidden.', 'sk' ), SK_CORE_VERSION );
    }

    /**
     * Manager constructor.
     *
     *
     * @return void
     */
    public function __construct() {
        $this->define_constants();
        $this->set_controllers();
        $this->init_hooks();

        // Activation and Deactivation hook
        add_action( 'sk_activated_module_product_advertising', [ $this, 'activate' ], 10, 1 );
        add_action( 'sk_deactivated_module_product_advertising', [ $this, 'deactivate' ], 10, 1 );
    }

    /**
     * Define module constants
     *
     *
     * @return void
     */
    private function define_constants() {
        define( 'SK_PRODUCT_ADVERTISEMENT_FILE', __FILE__ );
        define( 'SK_PRODUCT_ADVERTISEMENT_DIR', dirname( SK_PRODUCT_ADVERTISEMENT_FILE ) );
        define( 'SK_PRODUCT_ADV_DIR', dirname( SK_PRODUCT_ADVERTISEMENT_FILE ) ); // Alias for consistency
        define( 'SK_PRODUCT_ADVERTISEMENT_INC', SK_PRODUCT_ADVERTISEMENT_DIR . '/includes/' );
        define( 'SK_PRODUCT_ADVERTISEMENT_ASSETS', plugins_url( 'assets', SK_PRODUCT_ADVERTISEMENT_FILE ) );
        define( 'SK_PRODUCT_ADV_ASSETS', plugins_url( 'assets', SK_PRODUCT_ADVERTISEMENT_FILE ) ); // Alias for consistency
        define( 'SK_PRODUCT_ADVERTISEMENT_TEMPLATE_PATH', SK_PRODUCT_ADVERTISEMENT_DIR . '/templates/' );
    }

    /**
     * Set controllers
     *
     *
     * @return void
     */
    private function set_controllers() {
        $this->container['admin']         = new Admin();
        $this->container['settings']      = new Settings();
        $this->container['hooks']         = new Hooks();
        $this->container['products']      = new Product();
        $this->container['order']         = new Order();
        $this->container['cart']          = new Cart();
        $this->container['subscriptions'] = new VendorSubscription();
        $this->container['cache']         = new AdvertisementCache();

        // Block data modifier
        new BlockData();

        if ( wp_doing_ajax() ) {
            $this->container['ajax'] = new Ajax();
        }

        if ( ! is_admin() ) {
            $this->container['shortcode'] = new Shortcode();
        }
    }

    /**
     * Call all hooks here
     *
     *
     * @return void
     */
    public function init_hooks() {
        // set action hooks
        add_filter( 'sk_rest_api_class_map', [ $this, 'rest_api_class_map' ] ); // include rest api class

        // set template path
        add_filter( 'sk_set_template_path', [ $this, 'load_templates' ], 10, 3 );

        // register script and styles
        add_action( 'init', [ $this, 'register_scripts' ], 10 );

        // register widgets
        add_action( 'sk_widgets', [ $this, 'register_product_advertisement_widget' ] );

        add_filter( 'sk_button_shortcodes', [ $this, 'add_product_advertisement_shortcode_to_block_list' ] );
    }

    /**
     * Add Product Advertisement shortcode to block list.
     *
     *
     * @param array $shortcodes List of shortcodes
     *
     * @return array
     */
    public function add_product_advertisement_shortcode_to_block_list( $shortcodes ) {
        $shortcodes['sk_product_advertisement'] = [
            'title'   => __( 'Product Advertisement', 'sk' ),
            'content' => '[sk_product_advertisement title="" count="" vendor_id="" order="ASC" orderby="product_title"]',
        ];

        return $shortcodes;
    }

    /**
     * Register Product Advertisement Widget
     *
     *
     * @param array $widgets List of widgets to be registered
     *
     * @return array
     */
    public function register_product_advertisement_widget( array $widgets ): array {
        $widgets[ ProductWidget::INSTANCE_KEY ] = ProductWidget::class;
        return $widgets;
    }

    /**
     * Rest api class map
     *
     * @param array $classes
     *
     *
     * @return array
     */
    public function rest_api_class_map( $classes ) {
        $class[ SK_PRODUCT_ADVERTISEMENT_INC . '/REST/AdvertisementController.php' ] = '\SK\Modules\ProductAdvertisement\REST\AdvertisementController';

        return array_merge( $classes, $class );
    }

    /**
     * Set template path for Product Advertisement module
     *
     *
     * @return string
     */
    public function load_templates( $template_path, $template, $args ) {
        if ( ! empty( $args['is_product_advertisement'] ) ) {
            return untrailingslashit( SK_PRODUCT_ADVERTISEMENT_TEMPLATE_PATH );
        }

        return $template_path;
    }

    /**
     * Register all scripts
     *
     *
     * @return void
     * */
    public function register_scripts() {
        list( $suffix, $version ) = sk_get_script_suffix_and_version();

        // register frontend scripts
        wp_register_script( 'sk-product-adv-purchase', SK_PRODUCT_ADVERTISEMENT_ASSETS . '/js/purchase_advertisement' . $suffix . '.js', [ 'jquery', 'sk-sweetalert2' ], $version, true );
    }

    /**
     * This method will be called during module activation
     *
     */
    public function activate( $instance ) {
        new Install();
    }

    /**
     * This method will be called during module deactivation
     *
     */
    public function deactivate( $instance ) {
        // clear schedule
        wp_clear_scheduled_hook( 'sk_product_advertisement_daily_at_midnight_cron' );
    }
}
