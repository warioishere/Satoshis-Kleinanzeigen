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
     *  Adding sk_dashboard_content_before hook
     *
     * @hooked get_dashboard_side_navigation
     *
     */
    do_action( 'sk_dashboard_content_before' );
    ?>

    <div class="sk-dashboard-content">

        <?php

        /**
         *  Adding sk_dashboard_content_before hook
         *
         *
         */
        do_action( 'sk_dashboard_content_inside_before' );
        ?>

        <article class="dashboard-content-area">

            <?php

            /**
             *  Loading no permission error template
             *
             */
            sk_get_template_part(
                'global/sk-error', '', [
                    'deleted' => false,
                    'message' => __( 'You have no permission to view this page', 'sk-core' ),
                ]
            );
            ?>

        </article><!-- .dashboard-content-area -->

        <?php

        /**
         *  Adding sk_dashboard_content_inside_after hook
         *
         */
        do_action( 'sk_dashboard_content_inside_after' );
        ?>


    </div><!-- .sk-dashboard-content -->

    <?php

    /**
     *  Adding sk_dashboard_content_after hook
     *
     */
    do_action( 'sk_dashboard_content_after' );
    ?>

</div><!-- .sk-dashboard-wrap -->

<?php do_action( 'sk_dashboard_wrap_end' ); ?>
