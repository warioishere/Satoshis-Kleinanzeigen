<?php

namespace SK\Modules\ReportAbuse;

final class Module {

    /**
     * Plugin constructor
     *
     *
     * @return void
     */
    public function __construct() {
        $this->define_constants();
        $this->includes();
        $this->instances();

        add_action( 'sk_activated_module_report_abuse', [ self::class, 'activate' ] );
    }

    /**
     * Module constants
     *
     *
     * @return void
     */
    private function define_constants() {
        define( 'SK_REPORT_ABUSE_FILE' , __FILE__ );
        define( 'SK_REPORT_ABUSE_PATH' , dirname( SK_REPORT_ABUSE_FILE ) );
        define( 'SK_REPORT_ABUSE_INCLUDES' , SK_REPORT_ABUSE_PATH . '/includes' );
        define( 'SK_REPORT_ABUSE_URL' , plugins_url( '', SK_REPORT_ABUSE_FILE ) );
        define( 'SK_REPORT_ABUSE_ASSETS' , SK_REPORT_ABUSE_URL . '/assets' );
        define( 'SK_REPORT_ABUSE_VIEWS', SK_REPORT_ABUSE_PATH . '/views' );
    }

    /**
     * Include module related files
     *
     *
     * @return void
     */
    private function includes() {
        require_once SK_REPORT_ABUSE_INCLUDES . '/functions.php';
        require_once SK_REPORT_ABUSE_INCLUDES . '/ReportAbuseCache.php';
        require_once SK_REPORT_ABUSE_INCLUDES . '/AdminSettings.php';
        require_once SK_REPORT_ABUSE_INCLUDES . '/Ajax.php';
        require_once SK_REPORT_ABUSE_INCLUDES . '/SingleProduct.php';
        require_once SK_REPORT_ABUSE_INCLUDES . '/EmailLoader.php';
        require_once SK_REPORT_ABUSE_INCLUDES . '/Admin.php';
        require_once SK_REPORT_ABUSE_INCLUDES . '/Rest.php';
        require_once SK_REPORT_ABUSE_INCLUDES . '/AdminSingleProduct.php';
    }

    /**
     * Create module related class instances
     *
     *
     * @return void
     */
    private function instances() {
        new \SK\Modules\ReportAbuse\ReportAbuseCache();
        new \SK\Modules\ReportAbuse\AdminSettings();
        new \SK\Modules\ReportAbuse\Ajax();
        new \SK\Modules\ReportAbuse\SingleProduct();
        new \SK\Modules\ReportAbuse\EmailLoader();
        new \SK\Modules\ReportAbuse\Admin();
        new \SK\Modules\ReportAbuse\Rest();
        new \SK\Modules\ReportAbuse\AdminSingleProduct();
    }

    /**
     * Executes on module activation
     *
     *
     * @return void
     */
    public static function activate() {
        $option = (array) get_option( 'sk_report_abuse', [] );

        if ( empty( $option['abuse_reasons'] ) ) {
            $option['abuse_reasons'] = [
                [
                    'id'    => 'report_as_spam',
                    'value' => esc_html__( 'This content is spam', 'sk-core' ),
                ],
                [
                    'id'    => 'report_as_adult',
                    'value' => esc_html__( 'This content should marked as adult', 'sk-core' ),
                ],
                [
                    'id'    => 'report_as_abusive',
                    'value' => esc_html__( 'This content is abusive', 'sk-core' ),
                ],
                [
                    'id'    => 'report_as_violent',
                    'value' => esc_html__( 'This content is violent', 'sk-core' ),
                ],
                [
                    'id'    => 'report_as_risk_of_hurting',
                    'value' => esc_html__( 'This content suggests the author might be risk of hurting themselves', 'sk-core' ),
                ],
                [
                    'id'    => 'report_as_infringes_copyright',
                    'value' => esc_html__( 'This content infringes upon my copyright', 'sk-core' ),
                ],
                [
                    'id'    => 'report_as_contains_private_info',
                    'value' => esc_html__( 'This content contains my private information', 'sk-core' ),
                ],
                [
                    'id' => 'other',
                    'value' => esc_html__( 'Other', 'sk-core' )
                ],
            ];

            foreach ( $option['abuse_reasons'] as $key => $status ) {
                do_action( 'sk_pro_register_abuse_report_reason', $status['value'] );
            }

            update_option( 'sk_report_abuse', $option, false );
        }

        self::create_tables();
    }

    /**
     * Create module related tables
     *
     *
     * @return void
     */
    private static function create_tables() {
        global $wpdb;

        $collate = '';

        if ( $wpdb->has_cap( 'collation' ) ) {
            if ( ! empty($wpdb->charset ) ) {
                $collate .= "AUTO_INCREMENT=1 DEFAULT CHARACTER SET $wpdb->charset";
            }

            if ( ! empty($wpdb->collate ) ) {
                $collate .= " AUTO_INCREMENT=1 COLLATE $wpdb->collate";
            }
        }

        include_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $request_table = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}sk_report_abuse_reports` (
          `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `reason` VARCHAR(191) NOT NULL,
          `product_id` BIGINT(20) NOT NULL,
          `vendor_id` BIGINT(20) NOT NULL,
          `customer_id` BIGINT(20) DEFAULT NULL,
          `customer_name` VARCHAR(191) DEFAULT NULL,
          `customer_email` VARCHAR(100) DEFAULT NULL,
          `description` TEXT DEFAULT NULL,
          `reported_at` DATETIME NOT NULL,
          INDEX `reason` (`reason`),
          INDEX `product_id` (`product_id`),
          INDEX `vendor_id` (`vendor_id`)
        ) $collate";

        dbDelta( $request_table );
    }
}
