<?php

namespace SK\Modules\ReportAbuse;

class AdminSingleProduct {

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        add_action( 'init', [ self::class, 'register_scripts' ] );
        add_action( 'add_meta_boxes', [ self::class, 'add_abuse_report_meta_box' ] );
    }

    /**
     * Add metabox in product editing page
     *
     *
     * @return void
     */
    public static function add_abuse_report_meta_box() {
        add_meta_box( 'sk_report_abuse_reports', __( 'Abuse Reports', 'sk' ), [ self::class, 'meta_box' ], 'product', 'normal', 'core' );
    }

    /**
     * Register scripts
     *
     */
    public static function register_scripts() {
        list( $suffix, $version ) = sk_get_script_suffix_and_version();


        if ( 'off' === sk_get_option( 'disable_sk_fontawesome', 'sk_appearance', 'off' ) ) {
            wp_enqueue_style( 'sk-fontawesome' );
        }
    }

    /**
     * Abuse Reports metabox
     *
     *
     * @param \WP_Post $post
     *
     * @return void
     */
    public static function meta_box( $post ) {
        $reports = sk_report_abuse_get_reports( [
            'product_id' => $post->ID,
        ] );

        sk_report_abuse_template( 'report-abuse-admin-single-product', [
            'reports'     => $reports,
            'date_format' => get_option( 'date_format', 'F j, Y' ),
            'time_format' => get_option( 'time_format', 'g:i a' ),
        ] );

    }
}
