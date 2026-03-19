<?php

namespace SK\Modules\ReportAbuse;

class Rest {

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        add_filter( 'sk_rest_api_class_map', [ $this, 'add_rest_controller' ] );

        // Register the REST API endpoint for most reported vendors.
        add_action( 'rest_api_init', [ $this, 'register_most_reported_vendors' ] );
        add_filter( 'sk_rest_admin_dashboard_monthly_overview_data', [ $this, 'load_monthly_abuse_report_count' ], 10, 2 );
    }

    /**
     * Add module REST Controller
     *
     *
     * @param array $class_map
     */
    public function add_rest_controller( $class_map ) {
        $class_map[ SK_REPORT_ABUSE_INCLUDES . '/RestController.php' ] = "\\SK\\Pro\\Modules\\ReportAbuse\\RestController";

        return $class_map;
    }

	/**
	 * Permission check for admin endpoints
	 *
	 *
	 * @return bool
	 */
	public function admin_permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Register most reported vendors endpoint
	 *
	 *
	 * @return void
	 */
	public function register_most_reported_vendors() {
		register_rest_route(
			'sk/v1/admin', '/dashboard/most-reported-vendors', [
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_most_reported_vendors_data' ],
					'permission_callback' => [ $this, 'admin_permissions_check' ],
					'args'                => [],
				],
			]
		);
	}


	/**
	 * Load monthly abuse report count for the dashboard.
	 *
	 *
	 * @param array $data
	 * @param array $date_range
	 *
	 * @return mixed
	 */
	public function load_monthly_abuse_report_count( $data, $date_range ) {
		global $wpdb;

		$abuse_reports_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
                        SUM(CASE WHEN reported_at >= %s THEN 1 ELSE 0 END) AS current_reports,
                        SUM(CASE WHEN reported_at < %s THEN 1 ELSE 0 END) AS previous_reports
                    FROM {$wpdb->prefix}sk_report_abuse_reports 
                    WHERE reported_at BETWEEN %s AND %s",
				$date_range['current_month_start'],
				$date_range['current_month_start'],
				$date_range['previous_month_start'],
				$date_range['current_month_end']
			)
		);

		$abuse_reports_current  = $abuse_reports_data->current_reports ?? 0;
		$abuse_reports_previous = $abuse_reports_data->previous_reports ?? 0;

		// Apply filters to modify the abuse report count data.
		$data['abuse_reports'] = apply_filters(
			'sk_dashboard_monthly_abuse_report_count',
			[
				'icon'     => 'MessageSquareWarning',
				'current'  => (int) $abuse_reports_current,
				'previous' => (int) $abuse_reports_previous,
				'title'    => esc_html__( 'Abuse Reports', 'sk' ),
				'tooltip'  => esc_html__( 'Total vendors who got reported in the time period', 'sk' ),
                'position' => 100,
			]
		);

		return $data;
	}

	/**
	 * Get most reported vendors data for REST API.
	 *
	 *
	 * @return \WP_REST_Response
	 */
	public function get_most_reported_vendors_data() {
		global $wpdb;

		$result = [];

		// Query to get most reported vendors
		$reported_vendors = $wpdb->get_results(
			"SELECT vendor_id, COUNT(id) as abuse_count
	        FROM {$wpdb->prefix}sk_report_abuse_reports
	        GROUP BY vendor_id
	        ORDER BY abuse_count DESC
	        LIMIT 5",
			ARRAY_A
		);

		// If vendors found, then populate the result array
		if ( ! empty( $reported_vendors ) ) {
			$rank = 0;
			foreach ( $reported_vendors as $vendor ) {
				$vendor_info = sk()->vendor->get( $vendor['vendor_id'] );
				if ( ! $vendor_info->get_id() ) {
					continue;
				}

				$result[] = [
					'rank'         => ++$rank,
					'vendor_id'    => (int) $vendor['vendor_id'],
					'vendor_name'  => $vendor_info->get_shop_name(),
					'abuse_count'  => (int) $vendor['abuse_count'],
					'vendor_url'   => $vendor_info->get_shop_url(),
				];
			}
		}

		return rest_ensure_response(
			apply_filters(
				'sk_admin_dashboard_most_reported_vendors_data',
				$result,
				$reported_vendors
			),
		);
	}
}
