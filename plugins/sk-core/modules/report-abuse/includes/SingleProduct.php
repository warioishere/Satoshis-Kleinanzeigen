<?php

namespace SK\Modules\ReportAbuse;

class SingleProduct {

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        add_action( 'woocommerce_single_product_summary', [ self::class, 'add_report_button' ], 100 );
        add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue_scripts' ] );
        add_action( 'init', [ self::class, 'register_scripts' ] );
    }

    /**
     * Add report button
     *
     *
     * @return void
     */
    public static function add_report_button() {
        $label = apply_filters( 'sk_report_abuse_button_label', esc_html__( 'Report Abuse', 'sk' ) );

        $args = [
            'label' => $label,
        ];

        sk_report_abuse_template( 'report-button', $args );
    }

    /**
     * Register scripts
     *
     */
    public static function register_scripts() {
        list( $suffix, $version ) = sk_get_script_suffix_and_version();

        wp_register_script( 'sk-report-abuse', SK_REPORT_ABUSE_ASSETS . '/js/sk-report-abuse.js', [], $version, true );
        wp_register_style( 'sk-report-abuse-css', plugins_url( 'assets/css/sk-report-abuse.css', dirname( __FILE__, 2 ) . '/module.php' ), [], time() );
    }

    /**
     * Enqueue scripts
     *
     *
     * @return void
     */
    public static function enqueue_scripts() {
        if ( is_product() ) {
            $product = wc_get_product();

            wp_enqueue_script( 'sk-report-abuse' );
            wp_enqueue_style( 'sk-report-abuse-css' );

            // Get abuse reasons form `sk_report_abuse`.
            $options                  = (array) get_option( 'sk_report_abuse', [] );
            $options['abuse_reasons'] = $options['abuse_reasons'] ?? [];

            foreach ( $options['abuse_reasons'] as $key => $status ) {
                $options['abuse_reasons'][$key]['value'] = apply_filters('sk_pro_abuse_report_reason', $status['value'] );
            }

            wp_localize_script(
                'sk-report-abuse',
                'skReportAbuse',
                array_merge(
                    $options, [
                        'is_user_logged_in' => is_user_logged_in(),
                        'nonce'             => wp_create_nonce( 'sk_report_abuse' ),
                        'product_id'        => $product->get_id(),
                    ]
                )
            );
        }
    }
}
