<?php
namespace SK\Modules\ProductAdvertisement\Admin;

use SK\Modules\ProductAdvertisement\Helper;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class Install
 *
 *
 */
class Install {

    /**
     * Install constructor.
     *
     *
     * @return void
     */
    public function __construct() {
        $this->create_table();
        $this->create_advertisement_product();
        if ( $this->schedule_cron() ) {
            //early call expire cron
            do_action( 'sk_product_advertisement_daily_at_midnight_cron' );
        }
    }

    /**
     * This method will create required table
     *
     *
     * @return void
     */
    private function create_table() {
        global $wpdb;

        $sql = "CREATE TABLE `{$wpdb->prefix}sk_advertised_products` (
                    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `product_id` bigint(20) UNSIGNED NOT NULL,
                    `created_via` ENUM('order','admin','subscription','free') NOT NULL DEFAULT 'admin',
                    `order_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
                    `price` decimal(19,4) NOT NULL DEFAULT 0.0000,
                    `expires_at` int(10) UNSIGNED NOT NULL DEFAULT 0,
                    `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
                    `added` int(10) UNSIGNED NOT NULL DEFAULT 0,
                    `updated` int(10) UNSIGNED NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    KEY product_id (product_id),
                    KEY order_id (order_id),
                    KEY expires_at (expires_at),
                    KEY status (status),
                    KEY expires_at_status (expires_at,status)
                ) ENGINE=InnoDB {$wpdb->get_charset_collate()};
                ";

        include_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( $sql );
    }

    /**
     * This method will create advertisement base product
     *
     *
     * @return void
     */
    private function create_advertisement_product() {
        Helper::create_advertisement_base_product();
    }

    /**
     * Schedule crom for midnight
     *
     *
     * @return bool
     */
    public function schedule_cron() {
        if ( wp_next_scheduled( 'sk_product_advertisement_daily_at_midnight_cron' ) ) {
            return false;
        }

        // schedule cron at next midnight local time
        $timestamp = sk_current_datetime()->modify( 'tomorrow midnight' )->getTimestamp();

        return false !== wp_schedule_event(
            $timestamp,
            'daily',
            'sk_product_advertisement_daily_at_midnight_cron'
        );
    }
}
