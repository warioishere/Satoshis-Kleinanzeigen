<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

do_action( 'sk_dashboard_wrap_start' );
?>

<div class="sk-dashboard-wrap">
    <?php
        /**
         * Hook: sk_dashboard_content_before
         *
         */
        do_action( 'sk_dashboard_content_before' );
    ?>

    <div class="sk-dashboard-content sk-dashboard-content--gesuche">
        <?php
            /**
             * Hook: sk_dashboard_content_inside_before
             *
             */
            do_action( 'sk_dashboard_content_inside_before' );
        ?>

        <?php echo dg_render_gesuche_dashboard(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

        <?php
            /**
             * Hook: sk_dashboard_content_inside_after
             *
             */
            do_action( 'sk_dashboard_content_inside_after' );
        ?>
    </div>

    <?php
        /**
         * Hook: sk_dashboard_content_after
         *
         */
        do_action( 'sk_dashboard_content_after' );
    ?>
</div>

<?php do_action( 'sk_dashboard_wrap_end' );
