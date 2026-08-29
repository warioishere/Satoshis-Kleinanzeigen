<?php
/**
 * SK Settings Main Template
 *
 *
 */
?>

<?php do_action( 'sk_dashboard_wrap_start' ); ?>

<div class="sk-dashboard-wrap">

    <?php

    /**
     *  Adding sk_dashboard_content_before hook
     *  sk_dashboard_settings_store_content_before hook
     *
     * @hooked get_dashboard_side_navigation
     *
     */
    do_action( 'sk_dashboard_content_before' );
    do_action( 'sk_dashboard_settings_content_before' );
    ?>

    <div class="sk-dashboard-content sk-settings-content sk-dashboard-content--settings">
        <?php

        /**
         *  Adding sk_settings_content_inside_before hook
         *
         */
        do_action( 'sk_settings_content_inside_before' );
        ?>
        <article class="sk-settings-area">

            <?php
            /**
             * Adding sk_review_content_area_header hook
             *
             * @hooked sk_settings_content_area_header
             *
             */
            do_action( 'sk_settings_content_area_header' );


            /**
             * Adding sk_settings_content hook
             *
             * @hooked render_settings_content_hook
             */
            do_action( 'sk_settings_content' );
            ?>

            <!--settings updated content ends-->
        </article>
    </div><!-- .sk-dashboard-content -->
</div><!-- .sk-dashboard-wrap -->

<?php do_action( 'sk_dashboard_wrap_end' ); ?>
