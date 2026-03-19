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
         * @hooked show_seller_dashboard_notice
         *
         */
        do_action( 'sk_dashboard_content_inside_before' );
        ?>

        <article class="dashboard-content-area">

            <?php

            /**
             *  Added sk_dashboard_before_widgets hook
             *
             * @hooked sk_show_profile_progressbar
             *
             */
            do_action( 'sk_dashboard_before_widgets' );
            ?>

            <div class="sk-w6 sk-dash-left">

                <?php

                /**
                 *  Added sk_dashboard_left_widgets hook
                 *
                 * @hooked get_big_counter_widgets
                 * @hooked get_orders_widgets
                 * @hooked get_products_widgets
                 *
                 */
                do_action( 'sk_dashboard_left_widgets' );
                ?>

            </div> <!-- .col-md-6 -->

            <div class="sk-w6 sk-dash-right">
                <?php
                /**
                 *  Added sk_dashboard_right_widgets hook
                 *
                 * @hooked get_sales_report_chart_widget
                 *
                 */
                do_action( 'sk_dashboard_right_widgets' );
                ?>

            </div>

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
