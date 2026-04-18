<?php
/**
 * Dokan Authentication Connector Dashboard Template
 *
 * @package Unified_Auth_Connector
 */

if (!defined('ABSPATH')) {
    exit;
}

// Start Dokan dashboard wrapper
do_action('sk_dashboard_wrap_start');
?>

<div class="sk-dashboard-wrap">
    <?php
    /**
     * Hook: sk_dashboard_content_before
     *
     * @since 2.4
     */
    do_action('sk_dashboard_content_before');
    do_action('sk_auth_connector_content_before');
    ?>

    <div class="sk-dashboard-content sk-auth-connector-content">
        <?php
        /**
         * Hook: sk_dashboard_content_inside_before
         *
         * @since 2.4
         */
        do_action('sk_dashboard_content_inside_before');
        do_action('sk_auth_connector_inside_before');
        ?>

        <?php
        // Get the Dokan Dashboard instance and render the page
        $account_linker = new UAC_Account_Linker();
        $sk_dashboard = new SK_Auth_Dashboard($account_linker);
        $sk_dashboard->render_auth_page();
        ?>

        <?php
        /**
         * Hook: sk_dashboard_content_inside_after
         *
         * @since 2.4
         */
        do_action('sk_dashboard_content_inside_after');
        do_action('sk_auth_connector_inside_after');
        ?>
    </div>

    <?php
    /**
     * Hook: sk_dashboard_content_after
     *
     * @since 2.4
     */
    do_action('sk_dashboard_content_after');
    do_action('sk_auth_connector_content_after');
    ?>
</div>

<?php
// End Dokan dashboard wrapper
do_action('sk_dashboard_wrap_end');
