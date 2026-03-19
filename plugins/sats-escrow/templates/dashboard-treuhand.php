<?php
if (!defined('ABSPATH')) {
    exit;
}

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
    ?>

    <div class="sk-dashboard-content sk-dashboard-content--treuhand">
        <?php
            /**
             * Hook: sk_dashboard_content_inside_before
             *
             * @since 2.4
             */
            do_action('sk_dashboard_content_inside_before');
        ?>

        <?php
        if (isset($treuhand_data) && is_array($treuhand_data)) {
            if (function_exists('sk_get_template_part')) {
                sk_get_template_part('global/sk-notice');
            }
            include WEO_DIR.'templates/sk-treuhand-tabs.php';
        } elseif (isset($treuhand_content)) {
            echo $treuhand_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        ?>

        <?php
            /**
             * Hook: sk_dashboard_content_inside_after
             *
             * @since 2.4
             */
            do_action('sk_dashboard_content_inside_after');
        ?>
    </div>

    <?php
        /**
         * Hook: sk_dashboard_content_after
         *
         * @since 2.4
         */
        do_action('sk_dashboard_content_after');
    ?>
</div>

<?php do_action('sk_dashboard_wrap_end');
