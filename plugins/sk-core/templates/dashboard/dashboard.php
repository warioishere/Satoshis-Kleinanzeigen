<?php
/**
 *  SK Dashboard Template
 *
 *  SK Main Dashboard template for Fron-end
 *
 *
 */
?>
<?php do_action( 'sk_dashboard_wrap_start' ); ?>

<div class="sk-dashboard-wrap">
    <?php

    /**
     *  Added sk_dashboard_content_before hook
     *
     * @hooked get_dashboard_side_navigation
     *
     */
    do_action( 'sk_dashboard_content_before' );
    ?>

    <div class="sk-dashboard-content">

        <?php

        /**
         *  Added sk_dashboard_content_before hook
         *
         *
         */
        do_action( 'sk_dashboard_content_inside_before' );
        ?>

        <article class="dashboard-content-area">

            <?php

            /**
             *  Added sk_dashboard_before_widgets hook
             *
             * @hooked Notices::output_welcome_box
             *
             */
            do_action( 'sk_dashboard_before_widgets' );
            ?>

        </article><!-- .dashboard-content-area -->

        <?php

        /**
         *  Added sk_dashboard_content_inside_after hook
         *
         */
        do_action( 'sk_dashboard_content_inside_after' );
        ?>


    </div><!-- .sk-dashboard-content -->

    <?php

    /**
     *  Added sk_dashboard_content_after hook
     *
     */
    do_action( 'sk_dashboard_content_after' );
    ?>

</div><!-- .sk-dashboard-wrap -->

<?php do_action( 'sk_dashboard_wrap_end' ); ?>
