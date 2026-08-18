<?php

namespace SK\Core\Dashboard;

/**
 * Dashboard Template Class.
 *
 * A template for frontend dashboard rendering items
 *
 */
class ExtendedDashboard {

    /**
     * Current seller.
     *
     * @var int
     */
    protected $user_id;

    /**
     * Constructor
     *
     * @uses add_action()
     */
    public function __construct() {
        $this->user_id = sk_get_current_user_id();

        add_action( 'sk_dashboard_content_inside_before', [ $this, 'show_seller_dashboard_notice' ], 10 );
    }

    /**
     * Get Seller Dashboard Notice
     *
     * @return void
     */
    public function show_seller_dashboard_notice() {
        if ( ! sk_is_seller_enabled( $this->user_id ) ) {
            sk_seller_not_enabled_notice();
        }
    }
}
