<?php

namespace SK\Core;

use SK\Core\Traits\ChainableContainer;

/**
 * SK Pro Modules
 *
 * @property Modules\VendorVerification\Module $vendor_verification Vendor Verification.
 * @property Modules\ProductQA\Module $product_qa Product Qa Module.
 * @property Modules\PayPalMarketplace\Module $paypal_marketplace PayPal.
 * @property Modules\OrderMinMax\Module $order_min_max Order Min Max Module.
 * @property Modules\StoreSupport\Module $store_support Store Support Module.
 */
class ModuleManager {

    use ChainableContainer;

    /**
     * The wp option key which contains active module ids
     *
     *
     * @var string
     */
    const ACTIVE_MODULES_DB_KEY = 'sk_pro_active_modules';

    /**
     * Active module ids
     *
     *
     * @var array
     */
    private $active_modules = [];

    /**
     * Contains all module informations
     *
     *
     * @var array
     */
    private $sk_pro_modules = [];

    /**
     * Tells us if modules activated or not
     *
     *
     * @var bool
     */
    private static $modules_activated = false;

    /**
     * Update db option containing active module ids
     *
     *
     * @param array $value
     *
     * @return bool
     */
    protected function update_db_option( $value ) {
        return update_option( self::ACTIVE_MODULES_DB_KEY, $value );
    }

    /**
     * Load active modules
     *
     *
     * @param array $newly_activated_modules Useful after module activation
     *
     * @return void
     */
    public function load_active_modules( $newly_activated_modules = [], $force = false ) {
        if ( self::$modules_activated ) {
            return;
        }

        $active_modules    = $this->get_active_modules( $force );
        $sk_pro_modules = $this->get_all_modules();
        $activated_modules = [];

        foreach ( $active_modules as $module_id ) {
            if ( ! isset( $sk_pro_modules[ $module_id ] ) ) {
                continue;
            }

            $module = $sk_pro_modules[ $module_id ];

            // store this module as activated modules
            if ( file_exists( $module['module_file'] ) ) {
                $activated_modules[] = $module_id;
            }

            if ( ! isset( $this->container[ $module_id ] ) && file_exists( $module['module_file'] ) ) {
                require_once $module['module_file'];

                $module_class = $module['module_class'];
                $this->container[ $module_id ] = new $module_class(); // @phpstan-ignore-line

                if ( in_array( $module_id, $newly_activated_modules, true ) ) {
                    /**
                     * Module activation hook
                     *
                     *
                     * @param object $module Module class instance
                     */
                    do_action( 'sk_activated_module_' . $module_id, $this->container[ $module_id ] );
                }
            }
        }

        // store activated module as active module
        if ( $activated_modules !== $active_modules ) {
            update_option( self::ACTIVE_MODULES_DB_KEY, $activated_modules );
        }
        self::$modules_activated = true;
    }

    /**
     * Disable doing it wrong trigger error for load_textdomain_just_in_time
     * @see https://make.wordpress.org/core/2024/10/21/i18n-improvements-6-7/
     *
     * @param bool $doing_it
     * @param string $function_name
     * @return bool
     */
    public function disable_doing_it_trigger_error( $doing_it, $function_name ) {
		if ( '_load_textdomain_just_in_time' === $function_name ) {
            return false;
        }

        return $doing_it;
    }

    /**
     * List of SK Pro modules
     *
     *
     * @return array
     */
    public function get_all_modules() {
        add_filter( 'doing_it_wrong_trigger_error', [ $this, 'disable_doing_it_trigger_error' ], 10, 2 );

        if ( ! $this->sk_pro_modules ) {
            $thumbnail_dir = SK_CORE_ASSETS . '/images/modules';

            $this->sk_pro_modules = apply_filters(
                'sk_pro_modules', [
                    'product_votes' => [
                        'id'           => 'product_votes',
                        'name'         => __( 'Product Votes (Hot/Cold)', 'sk-core' ),
                        'description'  => __( 'Qualifizierte User können Inserate als Heiß/Kalt bewerten — Anti-Scam-Signal.', 'sk-core' ),
                        'thumbnail'    => $thumbnail_dir . '/follow-store.svg',
                        'module_file'  => SK_CORE_DIR . '/modules' . '/product-votes/module.php',
                        'module_class' => 'SK\Modules\ProductVotes\Module',
                        'categories'   => [ 'Product Management' ],
                    ],
                    'follow_store' => [
                        'id'           => 'follow_store',
                        'name'         => __( 'Follow Store', 'sk-core' ),
                        'description'  => __( 'Send emails to customers when their favorite store updates.', 'sk-core' ),
                        'thumbnail'    => $thumbnail_dir . '/follow-store.svg',
                        'module_file'  => SK_CORE_DIR . '/modules' . '/follow-store/module.php',
                        'module_class' => 'SK\Modules\FollowStore\Module',
                        'categories'   => [ 'Store Management' ],
                    ],
                    'geolocation' => [
                        'id'           => 'geolocation',
                        'name'         => __( 'Geolocation', 'sk-core' ),
                        'description'  => __( 'Search Products and Vendors by geolocation.', 'sk-core' ),
                        'thumbnail'    => $thumbnail_dir . '/geolocation.svg',
                        'module_file'  => SK_CORE_DIR . '/modules' . '/geolocation/module.php',
                        'module_class' => 'SK\Modules\Geolocation\Module',
                        'categories'   => [ 'Store Management', 'Product Management' ],
                    ],
                    'live_search' => [
                        'id'           => 'live_search',
                        'name'         => __( 'Live Search', 'sk-core' ),
                        'description'  => __( 'Live product search for WooCommerce store.', 'sk-core' ),
                        'thumbnail'    => $thumbnail_dir . '/ajax-live-search.svg',
                        'module_file'  => SK_CORE_DIR . '/modules' . '/live-search/module.php',
                        'module_class' => 'SK\Modules\LiveSearch\Module',
                        'categories'   => [ 'Product Management' ],
                    ],
                    'report_abuse' => [
                        'id'           => 'report_abuse',
                        'name'         => __( 'Report Abuse', 'sk-core' ),
                        'description'  => __( 'Let customers report fraudulent or fake products.', 'sk-core' ),
                        'thumbnail'    => $thumbnail_dir . '/report-abuse.svg',
                        'module_file'  => SK_CORE_DIR . '/modules' . '/report-abuse/module.php',
                        'module_class' => 'SK\Modules\ReportAbuse\Module',
                        'categories'   => [ 'Store Management' ],
                    ],
                    'store_reviews' => [
                        'id'           => 'store_reviews',
                        'name'         => __( 'Store Reviews', 'sk-core' ),
                        'description'  => __( 'A plugin that allows customers to rate the sellers.', 'sk-core' ),
                        'thumbnail'    => $thumbnail_dir . '/vendor-review.svg',
                        'module_file'  => SK_CORE_DIR . '/modules' . '/store-reviews/module.php',
                        'module_class' => 'SK\Modules\StoreReviews\Module',
                        'categories'   => [ 'Store Management' ],
                    ],
                    'product_advertising' => [
                        'id'           => 'product_advertising',
                        'name'         => __( 'Product Advertising', 'sk-core' ),
                        'description'  => __( 'Admin can earn more by allowing vendors to advertise their products and give them the right exposure.', 'sk-core' ),
                        'thumbnail'    => $thumbnail_dir . '/product-adv.svg',
                        'module_file'  => SK_CORE_DIR . '/modules' . '/product-adv/module.php',
                        'module_class' => 'SK\Modules\ProductAdvertisement\Module',
                    ],
                    'product_subscription' => [
                        'id'           => 'product_subscription',
                        'name'         => __( 'Vendor Subscription', 'sk-core' ),
                        'description'  => __( 'Subscription pack add-on for SK vendors.', 'sk-core' ),
                        'thumbnail'    => $thumbnail_dir . '/subscription.svg',
                        'module_file'  => SK_CORE_DIR . '/modules' . '/subscription/module.php',
                        'module_class' => 'SK\Modules\ProductSubscription\Module',
                        'categories'   => [ 'Store Management' ],
                    ],
                    'sk_payments' => [
                        'id'           => 'sk_payments',
                        'name'         => __( 'Lightning Payments', 'sk-core' ),
                        'description'  => __( 'Non-custodial Lightning-Zahlungen für den Marketplace.', 'sk-core' ),
                        'thumbnail'    => $thumbnail_dir . '/follow-store.svg',
                        'module_file'  => SK_CORE_DIR . '/modules' . '/sk-payments/module.php',
                        'module_class' => 'SK\Modules\Payments\Module',
                        'categories'   => [ 'Store Management' ],
                    ],
                    'sk_reputation' => [
                        'id'           => 'sk_reputation',
                        'name'         => __( 'Lightning Reputation', 'sk-core' ),
                        'description'  => __( 'Sybil-resistentes Reputationssystem basierend auf Lightning-Zahlungen.', 'sk-core' ),
                        'thumbnail'    => $thumbnail_dir . '/follow-store.svg',
                        'module_file'  => SK_CORE_DIR . '/modules' . '/sk-reputation/module.php',
                        'module_class' => 'SK\Modules\Reputation\Module',
                        'categories'   => [ 'Store Management' ],
                    ],
                    'sk_zaps' => [
                        'id'           => 'sk_zaps',
                        'name'         => __( 'SK Zaps', 'sk-core' ),
                        'description'  => __( 'Lightning Zap Buttons auf Store- und Produktseiten.', 'sk-core' ),
                        'thumbnail'    => $thumbnail_dir . '/follow-store.svg',
                        'module_file'  => SK_CORE_DIR . '/modules' . '/sk-zaps/module.php',
                        'module_class' => 'SK\Modules\Zaps\Module',
                        'categories'   => [ 'Store Management' ],
                    ],
                    'sk_nostr_market' => [
                        'id'           => 'sk_nostr_market',
                        'name'         => __( 'SK Nostr Market', 'sk-core' ),
                        'description'  => __( 'NIP-15 Nostr Marketplace — Produkte auf Nostr Marketplaces publishen.', 'sk-core' ),
                        'thumbnail'    => $thumbnail_dir . '/follow-store.svg',
                        'module_file'  => SK_CORE_DIR . '/modules' . '/sk-nostr-market/module.php',
                        'module_class' => 'SK\Modules\NostrMarket\Module',
                        'categories'   => [ 'Store Management' ],
                    ],
                    'sk_notifications' => [
                        'id'           => 'sk_notifications',
                        'name'         => __( 'SK Notifications', 'sk-core' ),
                        'description'  => __( 'Produkte auf Nostr und Telegram posten.', 'sk-core' ),
                        'thumbnail'    => $thumbnail_dir . '/follow-store.svg',
                        'module_file'  => SK_CORE_DIR . '/modules' . '/sk-notifications/module.php',
                        'module_class' => 'SK\Modules\Notifications\Module',
                        'categories'   => [ 'Store Management' ],
                    ],
                    'sk_auth' => [
                        'id'           => 'sk_auth',
                        'name'         => __( 'SK Auth', 'sk-core' ),
                        'description'  => __( 'Bitcoin, Lightning und Nostr Login.', 'sk-core' ),
                        'thumbnail'    => $thumbnail_dir . '/follow-store.svg',
                        'module_file'  => SK_CORE_DIR . '/modules' . '/sk-auth/module.php',
                        'module_class' => 'SK\Modules\Auth\Module',
                        'categories'   => [ 'Store Management' ],
                    ],
                    'sk_anti_fraud' => [
                        'id'           => 'sk_anti_fraud',
                        'name'         => __( 'Anti-Fraud', 'sk-core' ),
                        'description'  => __( 'Fingerprinting, Käufer-Warnungen, Verkaufslimits, Report Auto-Suspend.', 'sk-core' ),
                        'thumbnail'    => $thumbnail_dir . '/follow-store.svg',
                        'module_file'  => SK_CORE_DIR . '/modules' . '/sk-anti-fraud/module.php',
                        'module_class' => 'SK\Modules\AntiFraud\Module',
                        'categories'   => [ 'Store Management' ],
                    ],
                    'sk_feed' => [
                        'id'           => 'sk_feed',
                        'name'         => __( 'Vendor Feed', 'sk-core' ),
                        'description'  => __( 'Social Feed für Vendor-Beiträge, Likes & Kommentare.', 'sk-core' ),
                        'thumbnail'    => $thumbnail_dir . '/follow-store.svg',
                        'module_file'  => SK_CORE_DIR . '/modules' . '/sk-feed/module.php',
                        'module_class' => 'SK\Modules\Feed\Module',
                        'categories'   => [ 'Store Management' ],
                    ],
                    'sk_sponsors' => [
                        'id'           => 'sk_sponsors',
                        'name'         => __( 'Sponsoren', 'sk-core' ),
                        'description'  => __( 'Sponsorenplätze der Startseite mit Klickmessung über /go/.', 'sk-core' ),
                        'thumbnail'    => $thumbnail_dir . '/follow-store.svg',
                        'module_file'  => SK_CORE_DIR . '/modules' . '/sk-sponsors/module.php',
                        'module_class' => 'SK\Modules\Sponsors\Module',
                        'categories'   => [ 'Store Management' ],
                    ],
                ]
            );
        }

        remove_filter( 'doing_it_wrong_trigger_error', [ $this, 'disable_doing_it_trigger_error' ], 10 );

        return $this->sk_pro_modules;
    }

    /**
     * Set SK Pro modules
     *
     *
     * @param array $modules
     *
     * @return void
     */
    public function set_modules( $modules ) {
        $this->sk_pro_modules = $modules;
    }

    /**
     * Get a list of module ids
     *
     *
     * @return array
     */
    public function get_all_module_ids() {
        static $module_ids = [];

        if ( ! $module_ids ) {
            $modules = $this->get_all_modules();
            $module_ids = array_keys( $modules );
        }

        return $module_ids;
    }

    /**
     * Get SK Pro active modules
     *
     *
     * @return array
     */
    public function get_active_modules( $force = false ) {
        if ( $this->active_modules ) {
            return $this->active_modules;
        }

        $this->active_modules = get_option( self::ACTIVE_MODULES_DB_KEY, [] );

        if ( empty( $this->active_modules ) ) {
            return [];
        } if ( isset( $this->active_modules[0] ) && preg_match( '/php$/', $this->active_modules[0] ) ) {
            $old_convention_name_map = $this->get_compatibility_naming_map();
            $mapped_active_modules   = [];
            $test = [];

            foreach ( $this->active_modules as $module_file_name ) {
                if ( isset( $old_convention_name_map[ $module_file_name ] ) ) {
                    $mapped_active_modules[] = $old_convention_name_map[ $module_file_name ];
                }
            }

            sort( $mapped_active_modules );

            $this->update_db_option( $mapped_active_modules );

            $this->active_modules = $mapped_active_modules;
        }

        return $this->active_modules;
    }


    /**
     * Backward compatible module naming map
     *
     *
     * @return array
     */
    public function get_compatibility_naming_map() {
        return [
            'booking/booking.php'                                               => 'booking',
            'elementor/elementor.php'                                           => 'elementor',
            'export-import/export-import.php'                                   => 'export_import',
            'follow-store/follow-store.php'                                     => 'follow_store',
            'geolocation/geolocation.php'                                       => 'geolocation',
            'live-search/live-search.php'                                       => 'live_search',
            'product-enquiry/enquiry.php'                                       => 'product_enquiry',
            'report-abuse/report-abuse.php'                                     => 'report_abuse',
            'rma/rma.php'                                                       => 'rma',
            'seller-vacation/seller-vacation.php'                               => 'seller_vacation',
            'shipstation/shipstation.php'                                       => 'shipstation',
            'simple-auction/auction.php'                                        => 'auction',
            'single-product-multiple-vendor/single-product-multiple-vendor.php' => 'spmv',
            'store-reviews/store-reviews.php'                                   => 'store_reviews',
            'store-support/store-support.php'                                   => 'store_support',
            'stripe/gateway-stripe.php'                                         => 'stripe',
            'subscription/product-subscription.php'                             => 'product_subscription',
            'vendor-analytics/vendor-analytics.php'                             => 'vendor_analytics',
            'vendor-staff/vendor-staff.php'                                     => 'vendor_staff',
            'wholesale/wholesale.php'                                           => 'wholesale',
        ];
    }

    /**
     * Activate SK Pro modules
     *
     *
     * @param array $modules
     *
     * @return array
     */
    public function activate_modules( $modules, $force = false ) {
        $active_modules = $this->get_active_modules();

        $this->active_modules = array_unique( array_merge( $active_modules, $modules ) );

        $this->update_db_option( $this->active_modules );

        self::$modules_activated = false;

        $this->load_active_modules( $modules, $force );

        return $this->active_modules;
    }

    /**
     * Deactivate SK Pro modules
     *
     *
     * @param array $modules
     *
     * @return array
     */
    public function deactivate_modules( $modules ) {
        $active_modules = $this->get_active_modules();

        foreach ( $modules as $module_id ) {
            $active_modules = array_diff( $active_modules, [ $module_id ] );
        }

        $active_modules = array_values( $active_modules );

        $this->active_modules = $active_modules;

        $this->update_db_option( $this->active_modules );

        add_action(
            'shutdown', function () use ( $modules ) {
                foreach ( $modules as $module_id ) {
                    /**
                     * Module deactivation hook
                     *
                     *
                     * @param object $module deactivated module class instance
                     */
                    do_action( 'sk_deactivated_module_' . $module_id, sk_ext()->module->$module_id );
                }
            }
        );

        return $this->active_modules;
    }

    /**
     * Checks if a module is active or not
     *
     *
     * @param string $module_id
     *
     * @return bool
     */
    public function is_active( $module_id ) {
        $active_modules = $this->get_active_modules();

        if ( in_array( $module_id, $active_modules, true ) ) {
            return true;
        }

        return false;
    }

}
