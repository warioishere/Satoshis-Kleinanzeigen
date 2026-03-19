<?php

namespace SK\Core\Dashboard;

use SK\Core\Cache;
use SK\Core\Dashboard\Templates\Dashboard as SkDashboard;
use SK\Core\Utilities\ReportUtil;

/**
 * Dashboard Template Class.
 *
 * A template for frontend dashboard rendering items
 *
 */
class ExtendedDashboard extends SkDashboard {

    /**
     * Constructor
     *
     * @uses add_action()
     */
    public function __construct() {
        $this->user_id = sk_get_current_user_id();

        if ( ! ( class_exists( ReportUtil::class ) && ReportUtil::is_analytics_enabled() ) ) {
            add_action( 'sk_dashboard_left_widgets', array( $this, 'get_review_widget' ), 16 );
            add_action( 'sk_dashboard_right_widgets', array( $this, 'get_announcement_widget' ), 12 );
        }
    }

    /**
     * Get Review Widget
     *
     * @return void
     */
    public function get_review_widget() {
        if ( ! apply_filters( 'sk_dashboard_widget_applicable', true, 'reviews' ) ) {
            return;
        }

        if ( ! current_user_can( 'sk_view_overview_menu' ) ) {
            return;
        }

        if ( ! current_user_can( 'sk_view_review_reports' ) ) {
            return;
        }

        // Check if product review is disabled from WooCommerce's setting.
        if ( 'yes' !== get_option( 'woocommerce_enable_reviews' ) ) {
            return;
        }

        sk_get_template_part(
            'dashboard/review-widget', '', array(
				'pro'            => true,
				'comment_counts' => $this->get_comment_counts(),
				'reviews_url'    => sk_get_navigation_url( 'reviews' ),
			)
        );
    }

    /**
     * Get announcement widget
     *
     * @return void
     */
    public function get_announcement_widget() {
        if ( ! apply_filters( 'sk_dashboard_widget_applicable', true, 'announcement' ) ) {
            return;
        }

        if ( ! current_user_can( 'sk_view_overview_menu' ) ) {
            return;
        }

        if ( ! current_user_can( 'sk_view_announcement' ) ) {
            return;
        }

        $announcement = sk_ext()->announcement->manager;
        $args         = [
            'per_page'  => apply_filters( 'sk_dashboard_widget_announcement_list_number', 3 ),
            'vendor_id' => sk_get_current_user_id(),
        ];
        $notices      = $announcement->all( $args );

        sk_get_template_part(
            'dashboard/announcement-widget', '', array(
				'pro'              => true,
				'notices'          => $notices,
				'announcement_url' => sk_get_navigation_url( 'announcement' ),
			)
        );
    }
}
