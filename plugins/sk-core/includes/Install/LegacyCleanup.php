<?php

namespace SK\Core\Install;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * One-time removal of options left behind by the Dokan plugin and by dashboard
 * features that no longer exist.
 *
 * Nothing in wp-content reads any of these any more — every prefix below was
 * checked against all plugins, themes and mu-plugins before being listed here.
 * Once this has run everywhere the class can be dropped.
 */
class LegacyCleanup {

    /**
     * Bump when the list below changes so the cleanup runs again.
     *
     * @var string
     */
    const VERSION = '1';

    /**
     * Options removed by exact name.
     *
     * @var string[]
     */
    private const EXACT = [
        // Settings of a delivery-time feature that came with Dokan.
        '_dokan_delivery_slot_settings',
        'dismiss_dokan_admin_logo_update_notice',
        'sk_pointer_toplevel_page_dokan',

        // Two generations of dashboard performance settings, both superseded by
        // sk_page_cache_enabled and read by nothing.
        'satoshis_dokan_dashboard_spa_cache_exclusions',
        'satoshis_dokan_dashboard_spa_cache_ttl',
        'satoshis_dokan_dashboard_spa_scope',

        // Turbo navigation, removed along with its prefetching.
        'sk_turbo_navigation_enabled',

        // WooCommerce email settings whose email classes are gone. The shipping
        // method settings are deliberately not in this list: their zone rows are
        // still present and are a separate decision.
        'woocommerce_dokan_new_seller_settings',
        'woocommerce_dokan_product_review_settings',
        'woocommerce_dokan_product_shipping_settings',
        'woocommerce_dokan_vendor_completed_order_settings',
        'woocommerce_dokan_vendor_new_order_settings',
    ];

    /**
     * Options removed by prefix.
     *
     * @var string[]
     */
    private const PREFIXES = [
        // Queues of the geolocation background process, stalled since the module
        // was removed. Several hundred rows.
        'wp_Dokan_Geolocation_',

        // Performance toggles of the removed Dokan dashboard layer.
        'sk_dokan_perf_',

        // Widget instances — none of these widgets is registered any more and none
        // is placed in a sidebar.
        'widget_dokan-',
        'widget_dokan_',

        // Cache-busting markers of Dokan's transient cache. These carry no timeout
        // and would sit here forever.
        '_transient_dokan_cache_',
        '_transient_timeout_dokan_cache_',
    ];

    /**
     * Delete the leftovers once.
     *
     * @return int Number of options removed.
     */
    public static function maybe_run(): int {
        if ( self::VERSION === get_option( 'sk_legacy_cleanup_done' ) ) {
            return 0;
        }

        global $wpdb;

        $removed = 0;

        foreach ( self::EXACT as $name ) {
            // delete_option() rather than a DELETE, so the option cache and the
            // autoload cache are updated too.
            if ( delete_option( $name ) ) {
                ++$removed;
            }
        }

        foreach ( self::PREFIXES as $prefix ) {
            $names = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $wpdb->esc_like( $prefix ) . '%'
                )
            );

            foreach ( $names as $name ) {
                if ( delete_option( $name ) ) {
                    ++$removed;
                }
            }
        }

        update_option( 'sk_legacy_cleanup_done', self::VERSION );

        return $removed;
    }
}
