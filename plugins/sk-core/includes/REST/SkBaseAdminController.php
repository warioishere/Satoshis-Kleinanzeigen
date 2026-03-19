<?php

namespace SK\Core\REST;

/**
* Admin REST Controller for SK
*
*/
abstract class SkBaseAdminController extends SkBaseController {
    /**
     * Endpoint namespace.
     *
     * @var string
     */
    protected $namespace = 'sk/v1/admin';

    /**
     * Check if user has admin permission.
     *
     *
     * @return bool
     */
    public function check_permission() {
        return current_user_can( 'manage_woocommerce' );
    }
}
