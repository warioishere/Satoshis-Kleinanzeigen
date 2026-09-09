<?php

namespace SK\Modules\ReportAbuse;

class Ajax {

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        add_filter( 'sk_ajax_login_user_response', [ self::class, 'add_nonce_to_ajax_reponse' ] );

        add_action( 'wp_ajax_nopriv_sk_report_abuse_get_form', [ self::class, 'get_form' ] );
        add_action( 'wp_ajax_sk_report_abuse_get_form', [ self::class, 'get_form' ] );
        add_action( 'wp_ajax_nopriv_sk_report_abuse_submit_form', [ self::class, 'submit_form' ] );
        add_action( 'wp_ajax_sk_report_abuse_submit_form', [ self::class, 'submit_form' ] );
    }

    /**
     * Add nonce to login form popup response
     *
     *
     * @param array $response
     *
     * @return array
     */
    public static function add_nonce_to_ajax_reponse( $response ) {
        $response['sk_report_abuse_nonce'] = wp_create_nonce( 'sk_report_abuse' );
        return $response;
    }

    /**
     * Get report form
     *
     *
     * @return void
     */
    public static function get_form() {
        check_ajax_referer( 'sk_report_abuse' );

        ob_start();
        sk_report_abuse_template( 'report-form-popup' );
        $popup_html = ob_get_clean();

        wp_send_json_success(
            [
                'html'  => $popup_html,
                'title' => esc_html__( 'Report Abuse', 'sk-core' ),
            ]
        );
    }

    /**
     * Check a submitted reason against the reasons configured in the settings.
     *
     * @param string $reason
     *
     * @return bool
     */
    private static function is_valid_reason( $reason ) {
        $option = sk_report_abuse_get_option();

        foreach ( (array) $option['abuse_reasons'] as $abuse_reason ) {
            if ( isset( $abuse_reason['value'] ) && $abuse_reason['value'] === $reason ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Submit report form
     *
     *
     * @return void
     */
    public static function submit_form() {
        check_ajax_referer( 'sk_report_abuse' );

        if ( empty( $_POST['form_data'] ) ) {
            wp_send_json_error( [
                'message' => esc_html__( 'Missing form_data.', 'sk-core' ),
            ], 400 );
        }

        // The frontend posts form_data as a JSON blob. Older callers used a
        // urlencoded query string — accept both, otherwise wp_parse_args()
        // silently swallows the whole JSON into one key and every field
        // ends up empty ("Missing reason param." on every submit).
        $raw     = wp_unslash( $_POST['form_data'] );
        $decoded = json_decode( $raw, true );

        if ( ! is_array( $decoded ) ) {
            $decoded = [];
            wp_parse_str( $raw, $decoded );
        }

        $args = wp_parse_args( $decoded, [
            'reason'         => '',
            'product_id'     => 0,
            'customer_name'  => '',
            'customer_email' => '',
            'description'    => '',
        ] );

        $args['reason']         = sanitize_text_field( $args['reason'] );
        $args['product_id']     = absint( $args['product_id'] );
        $args['customer_name']  = sanitize_text_field( $args['customer_name'] );
        $args['customer_email'] = sanitize_email( $args['customer_email'] );
        $args['description']    = sanitize_textarea_field( $args['description'] );

        // Only accept reasons that are actually configured in the settings.
        if ( ! empty( $args['reason'] ) && ! self::is_valid_reason( $args['reason'] ) ) {
            wp_send_json_error( [
                'message' => esc_html__( 'Invalid reason.', 'sk-core' ),
            ], 400 );
        }

        $customer_id = get_current_user_id();

        if ( $customer_id ) {
            $args['customer_id'] = $customer_id;
        }

        $report = sk_report_abuse_create_report( $args );

        if ( is_wp_error( $report ) ) {
            wp_send_json_error( [
                'message' => $report->get_error_message(),
            ], 400 );
        }

        // Call WC_Emails once
        wc()->mailer();

        do_action( 'sk_report_abuse_send_admin_email', $report );

        $response = [
            'message' => esc_html__( 'Your report has been submitted. Thank you for your response.', 'sk-core' ),
            'report'  => $report,
        ];

        wp_send_json_success( $response, 200 );
    }
}
