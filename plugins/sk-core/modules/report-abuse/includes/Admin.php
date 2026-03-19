<?php

namespace SK\Modules\ReportAbuse;

class Admin {

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        add_action( 'sk_admin_menu', [ self::class, 'add_admin_menu' ] );
        add_action( 'init', [ self::class, 'register_scripts' ] );
        add_action( 'sk_after_saving_settings', [ $this, 'after_save_settings' ], 10, 3 );
        add_filter( 'sk_admin_dashboard_pages_settings', [ $this, 'load_most_reported_vendors' ] );
    }

    /**
     * Add submenu
     *
     *
     * @param string $capability
     *
     * @return void
     */
    public static function add_admin_menu( $capability ) {
        if ( current_user_can( $capability ) ) {
            global $submenu;

            $title = esc_html__( 'Abuse Reports', 'sk' );
            $slug  = 'sk';

            $submenu[ $slug ][] = [ $title, $capability, 'admin.php?page=' . $slug . '&tab=abuse-reports' ];
        }
    }

    /**
     * Register scripts
     *
     */
    public static function register_scripts() {
        list( $suffix, $version ) = sk_get_script_suffix_and_version();

        wp_register_style( 'woocommerce_select2', WC()->plugin_url() . '/assets/css/select2.css', [], WC_VERSION );
    }

    /**
     * After Save Admin Settings.
     *
     *
     * @param string $option_name Option Key (Section Key).
     * @param array $option_value Option value.
     * @param array $old_options Option Previous value.
     *
     * @return void
     */
    public function after_save_settings( $option_name, $option_value, $old_options ) {
        if ( 'sk_report_abuse' !== $option_name ) {
            return;
        }

        foreach ( $option_value['abuse_reasons'] as $key => $status ) {
            do_action( 'sk_pro_register_abuse_report_reason', $status['value'] );
        }
    }

    /**
     * Load most reported vendors data into localized data for the admin dashboard.
     *
     *
     * @param array $localized_data
     *
     * @return array
     */
    public function load_most_reported_vendors( array $localized_data ): array {
        $localized_data['show_most_reported_vendors'] = true;

        return $localized_data;
    }
}
