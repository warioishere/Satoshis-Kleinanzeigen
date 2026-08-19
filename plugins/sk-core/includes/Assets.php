<?php

namespace SK\Core;

use Automattic\WooCommerce\Internal\Admin\WCAdminAssets;
use SK\Core\ProductCategory\Helper as CategoryHelper;
use SK\Core\Utilities\OrderUtil;

class Assets {

    /**
     * The constructor
     */
    public function __construct() {
        add_action( 'init', [ $this, 'register_all_scripts' ], 10 );
        add_filter( 'sk_localized_args', [ $this, 'conditional_localized_args' ] );
        add_filter( 'script_loader_src', [ $this, 'version_asset_src' ] );
        add_filter( 'style_loader_src', [ $this, 'version_asset_src' ] );

        if ( is_admin() ) {
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ], 10 );
            add_action( 'admin_enqueue_scripts', [ $this, 'load_sk_global_scripts' ], 5 );
        } else {
            add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_front_scripts' ] );
            add_action( 'wp_enqueue_scripts', [ $this, 'load_sk_global_scripts' ], 5 );
            add_action( 'init', [ $this, 'register_wc_admin_scripts' ] );
        }
    }

    public static function get_wc_handler( $handler ): string {
        // map legacy handlers to new ones
        $supported_handlers = [ 'jquery-blockui', 'jquery-tiptip' ];
        // Return original handler if not in our supported list
        if ( ! in_array( $handler, $supported_handlers, true ) ) {
            return $handler;
        }
        // For WC 10.3.0+, use 'wc-' prefix
        if ( version_compare( WC()->version, '10.3.0', '>=' ) ) {
            return 'wc-' . $handler;
        }
        return $handler;
    }

    /**
     * Load global SK helper scripts and localization data.
     */
    public function load_sk_global_scripts() {
        wp_enqueue_script( 'sk-util-helper' );

        $localize_data = apply_filters(
            'sk_helper_localize_script',
            [
                'i18n_date_format'       => wc_date_format(),
                'i18n_time_format'       => wc_time_format(),
                'week_starts_day'        => intval( get_option( 'start_of_week', 0 ) ),
                'daterange_picker_local' => [
                    'toLabel'          => __( 'To', 'sk-core' ),
                    'firstDay'         => intval( get_option( 'start_of_week', 0 ) ),
                    'fromLabel'        => __( 'From', 'sk-core' ),
                    'separator'        => __( ' - ', 'sk-core' ),
                    'weekLabel'        => __( 'W', 'sk-core' ),
                    'applyLabel'       => __( 'Apply', 'sk-core' ),
                    'cancelLabel'      => __( 'Clear', 'sk-core' ),
                    'customRangeLabel' => __( 'Custom', 'sk-core' ),
                    'daysOfWeek'       => [
                        __( 'Su', 'sk-core' ),
                        __( 'Mo', 'sk-core' ),
                        __( 'Tu', 'sk-core' ),
                        __( 'We', 'sk-core' ),
                        __( 'Th', 'sk-core' ),
                        __( 'Fr', 'sk-core' ),
                        __( 'Sa', 'sk-core' ),
                    ],
                    'monthNames'       => [
                        __( 'January', 'sk-core' ),
                        __( 'February', 'sk-core' ),
                        __( 'March', 'sk-core' ),
                        __( 'April', 'sk-core' ),
                        __( 'May', 'sk-core' ),
                        __( 'June', 'sk-core' ),
                        __( 'July', 'sk-core' ),
                        __( 'August', 'sk-core' ),
                        __( 'September', 'sk-core' ),
                        __( 'October', 'sk-core' ),
                        __( 'November', 'sk-core' ),
                        __( 'December', 'sk-core' ),
                    ],
                ],
                'sweetalert_local'       => [
                    'cancelButtonText'     => __( 'Cancel', 'sk-core' ),
                    'closeButtonText'      => __( 'Close', 'sk-core' ),
                    'confirmButtonText'    => __( 'OK', 'sk-core' ),
                    'denyButtonText'       => __( 'No', 'sk-core' ),
                    'closeButtonAriaLabel' => __( 'Close this dialog', 'sk-core' ),
                ],
            ]
        );

        wp_localize_script( 'sk-util-helper', 'sk_helper', $localize_data );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts( $hook ) {
        global $post, $wp_version, $typenow;

        if ( 'toplevel_page_sk' === $hook ) {
            wp_enqueue_style( 'sk-admin-css' );
            wp_enqueue_style( 'sk-fontawesome' );
        }

        if ( 'sk_page_sk-modules' === $hook ) {
            wp_enqueue_style( 'sk-admin-css' );
            wp_enqueue_script( 'underscore' );
            wp_enqueue_media();
        }

        if ( 'product' === $typenow ) {
            wp_enqueue_style( 'sk-admin-product' );
        }
    }

    public function get_localized_price() {
        return [
            'precision' => wc_get_price_decimals(),
            'symbol'    => html_entity_decode( get_woocommerce_currency_symbol() ),
            'decimal'   => esc_attr( wc_get_price_decimal_separator() ),
            'thousand'  => esc_attr( wc_get_price_thousand_separator() ),
            'position'  => esc_attr( get_option( 'woocommerce_currency_pos' ) ),
            'format'    => esc_attr( str_replace( [ '%1$s', '%2$s' ], [ '%s', '%v' ], get_woocommerce_price_format() ) ), // For accounting JS
        ];
    }

    /**
     * Register all SK scripts and styles
     */
    public function register_all_scripts() {
        $styles  = $this->get_styles();
        $scripts = $this->get_scripts();

        $this->register_styles( $styles );
        $this->register_scripts( $scripts );

        do_action( 'sk_register_scripts' );

        wp_add_inline_script(
            'sk-util-helper',
            'window.sk=window.sk||{};',
            'before'
        );

    }

    /**
     * Version an asset by its modification time so a deploy busts the browser cache.
     *
     * Falls back to the plugin version for files that are not on disk.
     *
     * @param string $file Absolute path to the asset file.
     *
     * @return string
     */
    public static function asset_version( string $file ): string {
        static $cache = [];

        if ( ! isset( $cache[ $file ] ) ) {
            $cache[ $file ] = file_exists( $file ) ? (string) filemtime( $file ) : SK_CORE_VERSION;
        }

        return $cache[ $file ];
    }

    /**
     * Stamp every asset of this plugin with its file modification time.
     *
     * Registrations all over the plugin pass SK_CORE_VERSION, which never changes
     * between deploys, so browsers keep serving their cached copy for months.
     *
     * @param string $src Asset URL as WordPress is about to print it.
     *
     * @return string
     */
    public function version_asset_src( $src ) {
        static $base_url = null;

        if ( ! is_string( $src ) || '' === $src ) {
            return $src;
        }

        if ( null === $base_url ) {
            $base_url = untrailingslashit( plugins_url( '', SK_CORE_FILE ) );
        }

        $path = strtok( $src, '?' );

        if ( 0 !== strpos( $path, $base_url . '/' ) ) {
            return $src;
        }

        $file = SK_CORE_DIR . substr( $path, strlen( $base_url ) );

        if ( ! file_exists( $file ) ) {
            return $src;
        }

        return add_query_arg( 'ver', self::asset_version( $file ), $src );
    }

    /**
     * Get registered styles
     *
     * @return array
     */
    public function get_styles() {
        $dir = SK_CORE_DIR . '/assets/';

        $styles = [
            'sk-theme'                   => [
                'src'     => SK_CORE_ASSETS . '/css/sk-theme.css',
                'deps'    => [],
                'version' => self::asset_version( $dir . 'css/sk-theme.css' ),
            ],
            'sk-store-settings'          => [
                'src'  => SK_CORE_ASSETS . '/css/sk-store-settings.css',
                'deps' => [],
            ],
            'sk-map-picker'              => [
                'src'  => SK_CORE_ASSETS . '/css/sk-map-picker.css',
                'deps' => [],
            ],
            'sk-gesuch-single'           => [
                'src'  => SK_CORE_ASSETS . '/css/sk-gesuch-single.css',
                'deps' => [],
            ],
            'sk-gesuche-list'            => [
                'src'  => SK_CORE_ASSETS . '/css/sk-gesuche-list.css',
                'deps' => [],
            ],
            'sk-admin-user-profile'      => [
                'src'  => SK_CORE_ASSETS . '/css/sk-admin-user-profile.css',
                'deps' => [],
            ],
            'sk-seo-audit'               => [
                'src'  => SK_CORE_ASSETS . '/css/sk-seo-audit.css',
                'deps' => [],
            ],
            'sk-vendor-chat-settings'    => [
                'src'  => SK_CORE_ASSETS . '/css/sk-vendor-chat-settings.css',
                'deps' => [],
            ],
            'sk-empty-slider'            => [
                'src'  => SK_CORE_ASSETS . '/css/sk-empty-slider.css',
                'deps' => [],
            ],
            'sk-contact-hint'            => [
                'src'  => SK_CORE_ASSETS . '/css/sk-contact-hint.css',
                'deps' => [],
            ],
            'sk-currency-icon'           => [
                'src'  => SK_CORE_ASSETS . '/css/sk-currency-icon.css',
                'deps' => [],
            ],
            'sk-tinymce'                 => [
                'src'     => site_url( '/wp-includes/css/editor.css' ),
                'deps'    => [],
                'version' => time(),
            ],
            'jquery-ui'                     => [
                'src' => SK_CORE_ASSETS . '/vendors/jquery-ui/jquery-ui-1.10.0.custom.css',
            ],
            'sk-fontawesome'             => [
                'src' => SK_CORE_ASSETS . '/vendors/font-awesome/css/font-awesome.min.css',
            ],
            'sk-modal'                   => [
                'src'     => SK_CORE_ASSETS . '/vendors/izimodal/iziModal.min.css',
                'version' => self::asset_version( $dir . 'vendors/izimodal/iziModal.min.css' ),
            ],
            'sk-select2-css'             => [
                'src' => SK_CORE_ASSETS . '/vendors/select2/select2.css',
            ],
            'sk-date-range-picker'       => [
                'src' => SK_CORE_ASSETS . '/vendors/date-range-picker/daterangepicker.min.css',
            ],
            'sk-admin-css'               => [
                'src'     => SK_CORE_ASSETS . '/css/admin.css',
                'version' => self::asset_version( $dir . 'css/admin.css' ),
            ],
            'sk-product-category-ui-css' => [
                'src'     => SK_CORE_ASSETS . '/css/sk-product-category-ui.css',
                'version' => self::asset_version( $dir . 'css/sk-product-category-ui.css' ),
            ],
            'sk-admin-product'           => [
                'src'     => SK_CORE_ASSETS . '/css/sk-admin-product-style.css',
                'version' => self::asset_version( $dir . 'css/sk-admin-product-style.css' ),
            ],
            'sk-notices'                 => [
                'src'     => SK_CORE_ASSETS . '/css/notices.css',
                'version' => self::asset_version( $dir . 'css/notices.css' ),
            ],
        ];

        return $styles;
    }

    /**
     * Get all registered scripts
     *
     * @return array
     */
    public function get_scripts() {
        global $wp_version;
        $jquery_tiptip = self::get_wc_handler( 'jquery-tiptip' );

        $frontend_asset_file     = SK_CORE_DIR . '/assets/js/frontend.asset.php';
        $frontend_shipping_asset = file_exists( $frontend_asset_file ) ? require $frontend_asset_file : [ 'dependencies' => [], 'version' => SK_CORE_VERSION ];

        $suffix         = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
        $asset_url      = SK_CORE_ASSETS;
        $asset_path     = SK_CORE_DIR . '/assets/';
        $bootstrap_deps = [ 'wp-i18n', 'wp-hooks' ];

        $scripts = [
            'sk-store-listing'       => [
                'src'  => $asset_url . '/js/modules/sk-store-listing.js',
                'deps' => [ 'jquery' ],
            ],
            'sk-dashboard-nav'       => [
                'src'  => $asset_url . '/js/dashboard/nav-mobile-menu.js',
                'deps' => [],
            ],
            'sk-mapbox-with-search'  => [
                'src'  => $asset_url . '/js/maps/mapbox-with-search.js',
                'deps' => [ 'jquery' ],
            ],
            'sk-store-settings-form' => [
                'src'  => $asset_url . '/js/settings/store-form.js',
                'deps' => [ 'jquery' ],
            ],
            'sk-store-delete-account' => [
                'src'  => $asset_url . '/js/settings/store-delete-account.js',
                'deps' => [ 'jquery' ],
            ],
            'sk-announcement'        => [
                'src'  => $asset_url . '/js/dashboard/announcement.js',
                'deps' => [ 'jquery', 'sk-util-helper' ],
            ],
            'sk-admin-user-profile-fields' => [
                'src'  => $asset_url . '/js/admin/user-profile.js',
                'deps' => [ 'jquery' ],
            ],
            'sk-author-quick-edit'   => [
                'src'  => $asset_url . '/js/admin/author-quick-edit.js',
                'deps' => [ 'jquery' ],
            ],
            'sk-seo-audit-bulk'      => [
                'src'  => $asset_url . '/js/admin/seo-audit.js',
                'deps' => [],
            ],
            'sk-products-listing'    => [
                'src'  => $asset_url . '/js/products/products-listing.js',
                'deps' => [],
            ],
            'sk-php-dashboard-modules' => [
                'src'  => $asset_url . '/js/admin/php-dashboard/modules.js',
                'deps' => [],
            ],
            'sk-php-dashboard-settings' => [
                'src'  => $asset_url . '/js/admin/php-dashboard/settings.js',
                'deps' => [],
            ],
            'sk-gesuch-copy-link'    => [
                'src'  => $asset_url . '/js/gesuche/copy-link.js',
                'deps' => [],
            ],
            'sk-store-map-mapbox'    => [
                'src'  => $asset_url . '/js/maps/store-map-mapbox.js',
                'deps' => [ 'jquery' ],
            ],
            'sk-tinymce'             => [
                'src'  => site_url( '/wp-includes/js/tinymce/tinymce.min.js' ),
                'deps' => [],
            ],
            'sk-modal'               => [
                'src'  => $asset_url . '/vendors/izimodal/iziModal.min.js',
                'deps' => [ 'jquery' ],
            ],
            'sk-form-validate'       => [
                'src'  => $asset_url . '/vendors/form-validate/form-validate.js',
                'deps' => [ 'jquery' ],
            ],
            'sk-select2-js'          => [
                'src'  => $asset_url . '/vendors/select2/select2.full.min.js',
                'deps' => [ 'jquery' ],
            ],
            'sk-date-range-picker'   => [
                'src'  => $asset_url . '/vendors/date-range-picker/daterangepicker.min.js',
                'deps' => [ 'jquery', 'moment', 'sk-util-helper' ],
            ],
            'speaking-url'              => [
                'src'  => $asset_url . '/vendors/speakingurl/speakingurl.min.js',
                'deps' => [ 'jquery' ],
            ],
            'product-category-ui'    => [
                'src'      => $asset_url . '/js/product-category-ui.js',
                'deps'     => [ 'jquery' ],
                'version'  => self::asset_version( $asset_path . 'js/product-category-ui.js' ),
                'in_footer' => true,
            ],
            'sk-global-utils'        => [
                'src'     => $asset_url . '/js/modules/sk-global-utils.js',
                'deps'    => [ 'jquery', 'sk-util-helper' ],
                'version' => self::asset_version( $asset_path . 'js/modules/sk-global-utils.js' ),
            ],
            'sk-product-edit'        => [
                'src'     => $asset_url . '/js/modules/sk-product-edit.js',
                'deps'    => [ 'jquery', 'jquery-ui-sortable', 'sk-util-helper', 'sk-select2-js', 'wp-hooks' ],
                'version' => self::asset_version( $asset_path . 'js/modules/sk-product-edit.js' ),
            ],
            'sk-review-manage'       => [
                'src'     => $asset_url . '/js/modules/sk-review-manage.js',
                'deps'    => [ 'jquery' ],
                'version' => self::asset_version( $asset_path . 'js/modules/sk-review-manage.js' ),
            ],
            'sk-sweetalert2'         => [
                'src'     => $asset_url . '/vendors/sweetalert2/sweetalert2.all.min.js',
                'deps'    => [ 'sk-modal', 'wp-i18n' ],
                'version' => self::asset_version( $asset_path . 'vendors/sweetalert2/sweetalert2.all.min.js' ),
            ],
            'sk-util-helper'         => [
                'src'       => $asset_url . '/js/helper.js',
                'deps'      => [ 'jquery', 'sk-sweetalert2', 'moment' ],
                'version'   => self::asset_version( $asset_path . 'js/helper.js' ),
                'in_footer' => false,
            ],
        ];

        $require_dompurify = version_compare( WC()->version, '10.0.2', '>' );

        if ( $require_dompurify && ! wp_script_is( 'dompurify', 'registered' ) ) {
            $scripts['dompurify'] = [
                'src'  => WC()->plugin_url() . '/assets/js/dompurify/purify' . $suffix . '.js',
                'deps' => [],
            ];
        }

        if ( ! wp_script_is( $jquery_tiptip, 'registered' ) ) {
            $scripts[ $jquery_tiptip ] = [
                'src'  => WC()->plugin_url() . '/assets/js/jquery-tiptip/jquery.tipTip' . $suffix . '.js',
                'deps' => $require_dompurify ? [ 'jquery', 'dompurify' ] : [ 'jquery' ],
            ];
        }

        return $scripts;
    }

    /**
     * Registers WooCommerce Admin scripts for the React-based SK Vendor dashboard.
     *
     * This function ensures that the necessary WooCommerce Admin assets are registered
     * for use in the SK Vendor dashboard. It temporarily suppresses "doing it wrong"
     * warnings during the registration process.
     *
     * @return void
     */
    public function register_wc_admin_scripts() {
        // Register WooCommerce Admin Assets for the React-base SK Vendor ler dashboard.
        if ( ! function_exists( 'get_current_screen' ) ) {
            require_once ABSPATH . '/wp-admin/includes/screen.php';
        }

        add_filter( 'doing_it_wrong_trigger_error', [ $this, 'desable_doing_it_wrong_error' ] );

        $wc_instance = WCAdminAssets::get_instance();
        $wc_instance->register_scripts();

        remove_filter( 'doing_it_wrong_trigger_error', [ $this, 'desable_doing_it_wrong_error' ] );
    }

    /**
     * Disable "doing it wrong" error
     *
     * @return bool
     */
    public function desable_doing_it_wrong_error() {
        return false;
    }

    /**
     * Enqueue front-end scripts
     */
    public function enqueue_front_scripts() {
        if ( ! function_exists( 'WC' ) ) {
            return;
        }

        // load sk style on every pages. requires for shortcodes in other pages
        if ( SK_CORE_LOAD_STYLE ) {
            wp_enqueue_style( 'sk-theme' );
            wp_enqueue_style( 'sk-modal' );
            if ( 'off' === sk_get_option( 'disable_sk_fontawesome', 'sk_appearance', 'off' ) ) {
                wp_enqueue_style( 'sk-fontawesome' );
            }

        }

        $vendor = sk()->vendor->get( sk_get_current_user_id() );

        $default_script = [
            'ajaxurl'                      => admin_url( 'admin-ajax.php' ),
            'nonce'                        => wp_create_nonce( 'sk_reviews' ),
            'order_nonce'                  => wp_create_nonce( 'sk_view_order' ),
            'product_edit_nonce'           => wp_create_nonce( 'sk_edit_product_nonce' ),
            'ajax_loader'                  => SK_CORE_ASSETS . '/images/ajax-loader.gif',
            'seller'                       => [
                'available'    => __( 'Available', 'sk-core' ),
                'notAvailable' => __( 'Not Available', 'sk-core' ),
            ],
            'delete_confirm'               => __( 'Are you sure?', 'sk-core' ),
            'wrong_message'                => __( 'Something went wrong. Please try again.', 'sk-core' ),
            'rounding_precision'           => wc_get_rounding_precision(),
            'mon_decimal_point'            => wc_get_price_decimal_separator(),
            'currency_format_num_decimals' => wc_get_price_decimals(),
            'currency_format_symbol'       => get_woocommerce_currency_symbol(),
            'currency_format_decimal_sep'  => esc_attr( wc_get_price_decimal_separator() ),
            'currency_format_thousand_sep' => esc_attr( wc_get_price_thousand_separator() ),
            'currency_format'              => esc_attr( str_replace( [ '%1$s', '%2$s' ], [ '%s', '%v' ], get_woocommerce_price_format() ) ), // For accounting JS
            'round_at_subtotal'            => get_option( 'woocommerce_tax_round_at_subtotal', 'no' ),
            'product_types'                => apply_filters( 'sk_product_types', [ 'simple' ] ),
            'loading_img'                  => SK_CORE_ASSETS . '/images/loading.gif',
            'store_product_search_nonce'   => wp_create_nonce( 'sk_store_product_search_nonce' ),
            'i18n_download_permission'     => __( 'Are you sure you want to revoke access to this download?', 'sk-core' ),
            'i18n_download_access'         => __( 'Could not grant access - the user may already have permission for this file or billing email is not set. Ensure the billing email is set, and the order has been saved.', 'sk-core' ),
            /**
             * Filter of maximun a vendor can add tags.
             *
             *
             * @param integer default -1
             */
            'maximum_tags_select_length'   => apply_filters( 'sk_product_tags_select_max_length', - 1 ),  // Filter of maximun a vendor can add tags
            'modal_header_color'           => 'var(--sk-button-background-color, #7047EB)',
        ];

        $localize_script     = apply_filters( 'sk_localized_args', $default_script );
        $vue_localize_script = apply_filters(
            'sk_frontend_localize_script', [
                'rest'            => [
                    'root'    => esc_url_raw( get_rest_url() ),
                    'nonce'   => wp_create_nonce( 'wp_rest' ),
                    'version' => 'sk/v1',
                ],
                'api'             => null,
                'libs'            => [],
                'routeComponents' => [ 'default' => null ],
                'urls'            => [
                    'assetsUrl'    => SK_CORE_ASSETS,
                    'dashboardUrl' => sk_get_navigation_url(),
                    'storeUrl'     => sk_get_store_url( sk_get_current_user_id() ),
                ],
            ]
        );

        $localize_data = array_merge( $localize_script, $vue_localize_script );

        wp_localize_script( 'sk-util-helper', 'sk', $localize_data );

        self::load_form_validate_script();

        // Mobile category toggle for product category widget.
        wp_enqueue_script(
            'sk-category-toggle',
            plugins_url( 'assets/js/category-toggle.js', SK_CORE_FILE ),
            [],
            self::asset_version( SK_CORE_DIR . '/assets/js/category-toggle.js' ),
            true
        );

        // Vendor card clickable on single product pages.
        if ( is_product() ) {
            wp_enqueue_script(
                'vendor-card-stretched-link',
                plugins_url( 'assets/js/vendor-card-stretched-link.js', SK_CORE_FILE ),
                [],
                self::asset_version( SK_CORE_DIR . '/assets/js/vendor-card-stretched-link.js' ),
                true
            );
        }

        // load only in sk dashboard and product edit page
        if ( ( sk_is_seller_dashboard() || ( get_query_var( 'edit' ) && is_singular( 'product' ) ) ) || apply_filters( 'sk_forced_load_scripts', false ) ) {
            $this->sk_dashboard_scripts();
        }

        // Load category ui css and product-edit JS on product add/edit pages.
        global $wp;
        if ( ( sk_is_seller_dashboard() && isset( $wp->query_vars['products'] ) ) || ( isset( $wp->query_vars['products'], $_GET['product_id'] ) ) || ( sk_is_seller_dashboard() && isset( $wp->query_vars['new-product'] ) ) ) { // phpcs:ignore
            CategoryHelper::enqueue_and_localize_sk_multistep_category();
            wp_enqueue_script( 'sk-product-edit' );
        }

        // AJAX store tab switching on vendor pages.
        if ( sk_is_store_page() || sk_is_store_review_page() ) {
            wp_enqueue_script(
                'sk-store-tabs',
                plugins_url( 'assets/js/sk-store-tabs.js', SK_CORE_FILE ),
                [],
                self::asset_version( SK_CORE_DIR . '/assets/js/sk-store-tabs.js' ),
                true
            );
        }

        // store and my account page
        if (
            sk_is_store_page()
            || sk_is_store_review_page()
            || is_account_page()
            || is_product()
            || sk_is_store_listing()
        ) {
            if ( SK_CORE_LOAD_STYLE ) {
                wp_enqueue_style( 'sk-select2-css' );
            }

            if ( SK_CORE_LOAD_SCRIPTS ) {
                // Only the store pages can carry the store-location widget.
                if ( sk_is_store_page() || sk_is_store_listing() ) {
                    $this->load_map_scripts();
                }

                wp_enqueue_script( 'jquery-ui-sortable' );
                wp_enqueue_script( 'jquery-ui-datepicker' );
                wp_enqueue_script( 'sk-select2-js' );

                if ( sk_is_store_listing() ) {
                    wp_enqueue_script( 'sk-store-listing' );
                }
            }
        }

        $localize_data['datepicker'] = [
            'monthNames'       => [
                __( 'January', 'sk-core' ),
                __( 'February', 'sk-core' ),
                __( 'March', 'sk-core' ),
                __( 'April', 'sk-core' ),
                __( 'May', 'sk-core' ),
                __( 'June', 'sk-core' ),
                __( 'July', 'sk-core' ),
                __( 'August', 'sk-core' ),
                __( 'September', 'sk-core' ),
                __( 'October', 'sk-core' ),
                __( 'November', 'sk-core' ),
                __( 'December', 'sk-core' ),
            ],
        ];
        $localize_data['sweetalert_local'] = [
            'cancelButtonText'     => __( 'Cancel', 'sk-core' ),
            'closeButtonText'      => __( 'Close', 'sk-core' ),
            'confirmButtonText'    => __( 'OK', 'sk-core' ),
            'denyButtonText'       => __( 'No', 'sk-core' ),
            'closeButtonAriaLabel' => __( 'Close this dialog', 'sk-core' ),
        ];

        wp_localize_script( 'sk-util-helper', 'sk_helper', $localize_data );
    }

    /**
     * Load form validate script args
     *
     */
    public static function load_form_validate_script() {
        $form_validate_messages = [
            'required'        => __( 'This field is required', 'sk-core' ),
            'remote'          => __( 'Please fix this field.', 'sk-core' ),
            'email'           => __( 'Please enter a valid email address.', 'sk-core' ),
            'url'             => __( 'Please enter a valid URL.', 'sk-core' ),
            'date'            => __( 'Please enter a valid date.', 'sk-core' ),
            'dateISO'         => __( 'Please enter a valid date (ISO).', 'sk-core' ),
            'number'          => __( 'Please enter a valid number.', 'sk-core' ),
            'digits'          => __( 'Please enter only digits.', 'sk-core' ),
            'creditcard'      => __( 'Please enter a valid credit card number.', 'sk-core' ),
            'equalTo'         => __( 'Please enter the same value again.', 'sk-core' ),
            'maxlength_msg'   => __( 'Please enter no more than {0} characters.', 'sk-core' ),
            'minlength_msg'   => __( 'Please enter at least {0} characters.', 'sk-core' ),
            'rangelength_msg' => __( 'Please enter a value between {0} and {1} characters long.', 'sk-core' ),
            'range_msg'       => __( 'Please enter a value between {0} and {1}.', 'sk-core' ),
            'max_msg'         => __( 'Please enter a value less than or equal to {0}.', 'sk-core' ),
            'min_msg'         => __( 'Please enter a value greater than or equal to {0}.', 'sk-core' ),
        ];

        wp_localize_script( 'sk-form-validate', 'SkValidateMsg', apply_filters( 'sk_validate_msg_args', $form_validate_messages ) );
    }

    /**
     * Load SK Dashboard Scripts
     *
     *
     * @global type $wp
     */
    public function sk_dashboard_scripts() {
        global $wp;

        if ( SK_CORE_LOAD_STYLE ) {
            wp_enqueue_style( 'jquery-ui' );
            wp_enqueue_style( 'woocommerce-general' );
            wp_enqueue_style( 'sk-select2-css' );

            if (
                isset( $wp->query_vars['products'] ) ||
                isset( $wp->query_vars['products-search'] )
            ) {
                wp_enqueue_style( 'sk-modal' );
            }

            if (
                isset( $wp->query_vars['products'] ) ||
                isset( $wp->query_vars['orders'] ) ||
                isset( $wp->query_vars['coupons'] ) ||
                isset( $wp->query_vars['reports'] ) ||
                ( isset( $wp->query_vars['settings'] ) && $wp->query_vars['settings'] === 'store' )
            ) {
                wp_enqueue_style( 'sk-date-range-picker' );
            }
        }

        if ( SK_CORE_LOAD_SCRIPTS ) {
            self::load_form_validate_script();

            // Maps appear on the store settings form and in the product location box
            // of the product editor. Everywhere else the 1.7 MB bundle is dead weight.
            // The product editor condition mirrors the geolocation module's own
            // enqueue, whose script declares sk-maps as a dependency — without it
            // WordPress drops that script and the location picker stops working.
            $is_store_settings = isset( $wp->query_vars['settings'] ) && 'store' === $wp->query_vars['settings'];
            $is_product_editor = isset( $wp->query_vars['products'] )
                && isset( $_GET['action'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                && 'edit' === sanitize_text_field( wp_unslash( $_GET['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

            if ( $is_store_settings || $is_product_editor ) {
                $this->load_map_scripts();
            }

            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'jquery-ui' );
            wp_enqueue_script( 'jquery-ui-autocomplete' );
            wp_enqueue_script( 'jquery-ui-datepicker' );
            wp_enqueue_script( 'underscore' );
            wp_enqueue_script( 'post' );

            // Same pages as the matching style above — only these show a date range.
            if (
                isset( $wp->query_vars['products'] ) ||
                isset( $wp->query_vars['orders'] ) ||
                isset( $wp->query_vars['coupons'] ) ||
                isset( $wp->query_vars['reports'] ) ||
                ( isset( $wp->query_vars['settings'] ) && $wp->query_vars['settings'] === 'store' )
            ) {
                wp_enqueue_script( 'sk-date-range-picker' );
            }

            wp_enqueue_script( 'sk-select2-js' );
            // Provides sk_show_delete_prompt / sk_bulk_delete_prompt used by delete/trash links
            // on products list, orders, coupons etc. — depends on sk-util-helper (sk_sweetalert).
            wp_enqueue_script( 'sk-global-utils' );
            // Handles Approve/Spam/Trash action-link clicks on the reviews page.
            if ( isset( $wp->query_vars['reviews'] ) ) {
                wp_enqueue_script( 'sk-review-manage' );
            }
            wp_enqueue_media();
        }
    }

    /**
     * Load the Mapbox scripts and styles.
     */
    public function load_map_scripts() {
        $access_token = sk_get_option( 'mapbox_access_token', 'sk_appearance', null );

        if ( $access_token ) {
            wp_enqueue_style( 'sk-mapbox-gl', SK_CORE_ASSETS . '/vendor/mapbox/mapbox-gl.css', [], SK_CORE_VERSION );
            wp_enqueue_style( 'sk-mapbox-gl-geocoder', SK_CORE_ASSETS . '/vendor/mapbox/mapbox-gl-geocoder.css', [ 'sk-mapbox-gl' ], SK_CORE_VERSION );

            wp_enqueue_script( 'sk-maps', SK_CORE_ASSETS . '/vendor/mapbox/mapbox-gl.js', [], SK_CORE_VERSION, true );
            // Mapbox GL calls fetch( new Request( url ) ) for its telemetry, so the
            // URL has to be read off the Request object — a typeof string check
            // never matches and the call goes out. Stays inline and runs 'before',
            // because it has to be in place before the bundle issues anything.
            wp_add_inline_script( 'sk-maps', '(function(){var E="events.mapbox.com";function u(v){try{return typeof v==="string"?v:(v&&v.url?v.url:(v?String(v):""))}catch(e){return ""}}function b(v){return u(v).indexOf(E)!==-1}var O=XMLHttpRequest.prototype.open,S=XMLHttpRequest.prototype.send;XMLHttpRequest.prototype.open=function(m,v){this._skBlocked=b(v);return O.apply(this,arguments)};XMLHttpRequest.prototype.send=function(){if(this._skBlocked)return;return S.apply(this,arguments)};var F=window.fetch;if(F){var FB=F.bind(window);window.fetch=function(v){if(!b(v))return FB.apply(null,arguments);try{return Promise.resolve(new Response(null,{status:204}))}catch(e){return FB.apply(null,arguments)}}}if(navigator.sendBeacon){var B=navigator.sendBeacon.bind(navigator);navigator.sendBeacon=function(v,d){if(b(v))return true;return B(v,d)}}})();', 'before' );
            wp_enqueue_script( 'sk-mapbox-suggestions', SK_CORE_ASSETS . '/js/suggestions-polyfill.js', [], SK_CORE_VERSION, true );
            wp_enqueue_script( 'sk-mapbox-gl-geocoder', SK_CORE_ASSETS . '/vendor/mapbox/mapbox-gl-geocoder.min.js', [ 'sk-maps' ], SK_CORE_VERSION, true );
        }
    }

    /**
     * Filter 'sk' localize script's arguments
     *
     *
     * @param array $default_args
     *
     * @return $default_args
     */
    public function conditional_localized_args( $default_args ) {
        if ( sk_is_seller_dashboard()
            || ( get_query_var( 'edit' ) && is_singular( 'product' ) )
            || sk_is_store_page()
            || is_account_page()
            || is_product()
            || sk_is_store_listing()
            || apply_filters( 'sk_force_load_extra_args', false )
        ) {
            $general_settings = get_option( 'sk_general', [] );

            $decimal         = wc_get_price_decimal_separator();
            $banner_width    = sk_get_vendor_store_banner_width();
            $banner_height   = sk_get_vendor_store_banner_height();
            $has_flex_width  = ! empty( $general_settings['store_banner_flex_width'] ) ? $general_settings['store_banner_flex_width'] : true;
            $has_flex_height = ! empty( $general_settings['store_banner_flex_height'] ) ? $general_settings['store_banner_flex_height'] : true;

            $custom_args = [
                'i18n_choose_featured_img'                 => __( 'Upload featured image', 'sk-core' ),
                'i18n_choose_file'                         => __( 'Choose a file', 'sk-core' ),
                'i18n_choose_gallery'                      => __( 'Add Images to Product Gallery', 'sk-core' ),
                'i18n_choose_featured_img_btn_text'        => __( 'Set featured image', 'sk-core' ),
                'i18n_choose_file_btn_text'                => __( 'Insert file URL', 'sk-core' ),
                'i18n_choose_gallery_btn_text'             => __( 'Add to gallery', 'sk-core' ),
                'duplicates_attribute_messg'               => __( 'Sorry, this attribute option already exists, Try a different one.', 'sk-core' ),
                'variation_unset_warning'                  => __( 'Warning! This product will not have any variations if this option is not checked.', 'sk-core' ),
                'new_attribute_prompt'                     => __( 'Enter a name for the new attribute term:', 'sk-core' ),
                'remove_attribute'                         => __( 'Remove this attribute?', 'sk-core' ),
                'sk_placeholder_img_src'                => wc_placeholder_img_src(),
                'add_variation_nonce'                      => wp_create_nonce( 'add-variation' ),
                'link_variation_nonce'                     => wp_create_nonce( 'link-variations' ),
                'delete_variations_nonce'                  => wp_create_nonce( 'delete-variations' ),
                'load_variations_nonce'                    => wp_create_nonce( 'load-variations' ),
                'save_variations_nonce'                    => wp_create_nonce( 'save-variations' ),
                'bulk_edit_variations_nonce'               => wp_create_nonce( 'bulk-edit-variations' ),
                /* translators: %d: max linked variation. */
                'i18n_link_all_variations'                 => esc_js( sprintf( __( 'Are you sure you want to link all variations? This will create a new variation for each and every possible combination of variation attributes (max %d per run).', 'sk-core' ), defined( 'WC_MAX_LINKED_VARIATIONS' ) ? WC_MAX_LINKED_VARIATIONS : 50 ) ),
                'i18n_enter_a_value'                       => esc_js( __( 'Enter a value', 'sk-core' ) ),
                'i18n_enter_menu_order'                    => esc_js( __( 'Variation menu order (determines position in the list of variations)', 'sk-core' ) ),
                'i18n_enter_a_value_fixed_or_percent'      => esc_js( __( 'Enter a value (fixed or %)', 'sk-core' ) ),
                'i18n_delete_all_variations'               => esc_js( __( 'Are you sure you want to delete all variations? This cannot be undone.', 'sk-core' ) ),
                'i18n_last_warning'                        => esc_js( __( 'Last warning, are you sure?', 'sk-core' ) ),
                'i18n_choose_image'                        => esc_js( __( 'Choose an image', 'sk-core' ) ),
                'i18n_set_image'                           => esc_js( __( 'Set variation image', 'sk-core' ) ),
                'i18n_variation_added'                     => esc_js( __( 'variation added', 'sk-core' ) ),
                'i18n_variations_added'                    => esc_js( __( 'variations added', 'sk-core' ) ),
                'i18n_no_variations_added'                 => esc_js( __( 'No variations added', 'sk-core' ) ),
                'i18n_remove_variation'                    => esc_js( __( 'Are you sure you want to remove this variation?', 'sk-core' ) ),
                'i18n_scheduled_sale_start'                => esc_js( __( 'Sale start date (YYYY-MM-DD format or leave blank)', 'sk-core' ) ),
                'i18n_scheduled_sale_end'                  => esc_js( __( 'Sale end date (YYYY-MM-DD format or leave blank)', 'sk-core' ) ),
                'i18n_edited_variations'                   => esc_js( __( 'Save changes before changing page?', 'sk-core' ) ),
                'i18n_variation_count_single'              => esc_js( __( '%qty% variation', 'sk-core' ) ),
                'i18n_variation_count_plural'              => esc_js( __( '%qty% variations', 'sk-core' ) ),
                'i18n_no_result_found'                     => esc_js( __( 'No Result Found', 'sk-core' ) ),
                'i18n_sales_price_error'                   => esc_js( __( 'Please insert value less than the regular price!', 'sk-core' ) ),
                /* translators: %s: decimal */
                'i18n_decimal_error'                       => sprintf( __( 'Please enter with one decimal point (%s) without thousand separators.', 'sk-core' ), $decimal ),
                /* translators: %s: price decimal separator */
                'i18n_mon_decimal_error'                   => sprintf( __( 'Please enter with one monetary decimal point (%s) without thousand separators and currency symbols.', 'sk-core' ), wc_get_price_decimal_separator() ),
                'i18n_country_iso_error'                   => __( 'Please enter in country code with two capital letters.', 'sk-core' ),
                'i18n_sale_less_than_regular_error'        => __( 'Please enter in a value less than the regular price.', 'sk-core' ),
                'i18n_delete_product_notice'               => __( 'This product has produced sales and may be linked to existing orders. Are you sure you want to delete it?', 'sk-core' ),
                'i18n_remove_personal_data_notice'         => __( 'This action cannot be reversed. Are you sure you wish to erase personal data from the selected orders?', 'sk-core' ),
                'decimal_point'                            => $decimal,
                'mon_decimal_point'                        => wc_get_price_decimal_separator(),
                'variations_per_page'                      => absint( apply_filters( 'sk_product_variations_per_page', 10 ) ),
                'store_banner_dimension'                   => [
                    'width'       => $banner_width,
                    'height'      => $banner_height,
                    'flex-width'  => $has_flex_width,
                    'flex-height' => $has_flex_height,
                ],
                'selectAndCrop'                            => __( 'Select and Crop', 'sk-core' ),
                'chooseImage'                              => __( 'Choose Image', 'sk-core' ),
                'product_title_required'                   => __( 'Product title is required', 'sk-core' ),
                'product_category_required'                => __( 'Product category is required', 'sk-core' ),
                'product_created_response'                 => __( 'Product created successfully', 'sk-core' ),
                'search_products_nonce'                    => wp_create_nonce( 'search-products' ),
                'search_products_tags_nonce'               => wp_create_nonce( 'search-products-tags' ),
                'search_products_brands_nonce'             => wp_create_nonce( 'search-products-brands' ),
                'search_customer_nonce'                    => wp_create_nonce( 'search-customer' ),
                'i18n_matches_1'                           => __( 'One result is available, press enter to select it.', 'sk-core' ),
                'i18n_matches_n'                           => __( '%qty% results are available, use up and down arrow keys to navigate.', 'sk-core' ),
                'i18n_no_matches'                          => __( 'No matches found', 'sk-core' ),
                'i18n_ajax_error'                          => __( 'Loading failed', 'sk-core' ),
                'i18n_input_too_short_1'                   => __( 'Please enter 1 or more characters', 'sk-core' ),
                'i18n_input_too_short_n'                   => __( 'Please enter %qty% or more characters', 'sk-core' ),
                'i18n_input_too_long_1'                    => __( 'Please delete 1 character', 'sk-core' ),
                'i18n_input_too_long_n'                    => __( 'Please delete %qty% characters', 'sk-core' ),
                'i18n_selection_too_long_1'                => __( 'You can only select 1 item', 'sk-core' ),
                'i18n_selection_too_long_n'                => __( 'You can only select %qty% items', 'sk-core' ),
                'i18n_load_more'                           => __( 'Loading more results&hellip;', 'sk-core' ),
                'i18n_searching'                           => __( 'Searching&hellip;', 'sk-core' ),
                'i18n_calculating'                         => __( 'Calculating', 'sk-core' ),
                'i18n_ok_text'                             => __( 'OK', 'sk-core' ),
                'i18n_cancel_text'                         => __( 'Cancel', 'sk-core' ),
                'i18n_attribute_label'                     => __( 'Attribute Name', 'sk-core' ),
                'i18n_date_format'                         => get_option( 'date_format' ),
                'sk_banner_added_alert_msg'             => __( 'Are you sure? You have uploaded banner but didn\'t click the Update Settings button!', 'sk-core' ),
                'update_settings'                          => __( 'Update Settings', 'sk-core' ),
                'search_downloadable_products_nonce'       => wp_create_nonce( 'search-downloadable-products' ),
                'search_downloadable_products_placeholder' => __( 'Please enter 3 or more characters', 'sk-core' ),
            ];

            $default_args = array_merge( $default_args, $custom_args );
        }

        return $default_args;
    }

    /**
     * Get file prefix
     *
     * @return string
     */
    public function get_prefix() {
        $prefix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

        return $prefix;
    }

    /**
     * Register scripts
     *
     * @param array $scripts
     *
     * @return void
     */
    public function register_scripts( $scripts ) {
        foreach ( $scripts as $handle => $script ) {
            $deps      = isset( $script['deps'] ) ? $script['deps'] : false;
            $in_footer = isset( $script['in_footer'] ) ? $script['in_footer'] : true;
            $version   = isset( $script['version'] ) ? $script['version'] : SK_CORE_VERSION;

            wp_register_script( $handle, $script['src'], $deps, $version, $in_footer );
        }
    }

    /**
     * Register styles
     *
     * @param array $styles
     *
     * @return void
     */
    public function register_styles( $styles ) {
        foreach ( $styles as $handle => $style ) {
            $deps    = isset( $style['deps'] ) ? $style['deps'] : false;
            $version = isset( $style['version'] ) ? $style['version'] : SK_CORE_VERSION;

            wp_register_style( $handle, $style['src'], $deps, $version );
        }
    }

    /**
     * Enqueue the scripts
     *
     * @param array $scripts
     *
     * @return void
     */
    public function enqueue_scripts( $scripts ) {
        foreach ( $scripts as $handle => $script ) {
            wp_enqueue_script( $handle );
        }
    }

    /**
     * Enqueue styles
     *
     * @param array $styles
     *
     * @return void
     */
    public function enqueue_styles( $styles ) {
        foreach ( $styles as $handle => $script ) {
            wp_enqueue_style( $handle );
        }
    }

    /**
     * Admin localized scripts
     *
     *
     * @return array
     */
    public function get_admin_localized_scripts() {
        $general_settings = get_option( 'sk_general', [] );
        $banner_width     = sk_get_option( 'store_banner_width', 'sk_appearance', 625 );
        $banner_height    = sk_get_option( 'store_banner_height', 'sk_appearance', 300 );
        $has_flex_width   = ! empty( $general_settings['store_banner_flex_width'] ) ? $general_settings['store_banner_flex_width'] : true;
        $has_flex_height  = ! empty( $general_settings['store_banner_flex_height'] ) ? $general_settings['store_banner_flex_height'] : true;
        $decimal          = wc_get_price_decimal_separator();

        return apply_filters(
            'sk_admin_localize_script', [
                'ajaxurl'                           => admin_url( 'admin-ajax.php' ),
                'nonce'                             => wp_create_nonce( 'sk_admin' ),
                'rest'                              => [
                    'root'    => esc_url_raw( get_rest_url() ),
                    'nonce'   => wp_create_nonce( 'wp_rest' ),
                    'version' => 'sk/v1',
                ],
                'api'                               => null,
                'libs'                              => [],
                'routeComponents'                   => [ 'default' => null ],
                'currency'                          => $this->get_localized_price(),
                'proNag'                            => sk()->is_pro_exists() ? 'hide' : get_option( 'sk_hide_pro_nag', 'show' ),
                'hasPro'                            => sk()->is_pro_exists(),
                'showPromoBanner'                   => empty( Helper::sk_get_promo_notices() ),
                'hasNewVersion'                     => Helper::sk_has_new_version(),
                'proVersion'                        => sk()->is_pro_exists() ? sk_ext()->version : '',
                'urls'                              => [
                    'adminRoot'         => admin_url(),
                    'siteUrl'           => home_url( '/' ),
                    'storePrefix'       => sk_get_option( 'custom_store_url', 'sk_general', 'store' ),
                    'assetsUrl'         => SK_CORE_ASSETS,
                    'buynowpro'         => sk_pro_buynow_url(),
                    'upgradeToPro'      => 'https://sk.co/wordpress/upgrade-to-pro/?utm_source=plugin&utm_medium=wp-admin&utm_campaign=sk-lite',
                    'dummy_data'        => SK_CORE_ASSETS . '/dummy-data/sk_dummy_data.csv',
                    'adminOrderListUrl' => OrderUtil::get_admin_order_list_url(),
                    'adminOrderEditUrl' => OrderUtil::get_admin_order_edit_url(),
                ],
                'states'                            => WC()->countries->get_allowed_country_states(),
                'countries'                         => WC()->countries->get_allowed_countries(),
                'current_time'                      => current_time( 'mysql' ),
                'store_banner_dimension'            => [
                    'width'       => $banner_width,
                    'height'      => $banner_height,
                    'flex-width'  => $has_flex_width,
                    'flex-height' => $has_flex_height,
                ],
                'ajax_loader'                       => SK_CORE_ASSETS . '/images/spinner-2x.gif',
                /* translators: %s: decimal */
                'i18n_decimal_error'                => sprintf( __( 'Please enter with one decimal point (%s) without thousand separators.', 'sk-core' ), $decimal ),
                /* translators: %s: price decimal separator */
                'i18n_mon_decimal_error'            => sprintf( __( 'Please enter with one monetary decimal point (%s) without thousand separators and currency symbols.', 'sk-core' ), wc_get_price_decimal_separator() ),
                'i18n_country_iso_error'            => __( 'Please enter in country code with two capital letters.', 'sk-core' ),
                'i18n_sale_less_than_regular_error' => __( 'Please enter in a value less than the regular price.', 'sk-core' ),
                'i18n_delete_product_notice'        => __( 'This product has produced sales and may be linked to existing orders. Are you sure you want to delete it?', 'sk-core' ),
                'i18n_remove_personal_data_notice'  => __( 'This action cannot be reversed. Are you sure you wish to erase personal data from the selected orders?', 'sk-core' ),
                'decimal_point'                     => $decimal,
                'mon_decimal_point'                 => wc_get_price_decimal_separator(),
                'i18n_date_format'                  => wc_date_format(),
            ]
        );
    }

}
