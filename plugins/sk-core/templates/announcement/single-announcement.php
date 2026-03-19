<?php
/**
 * SK Announcement Template
 *
 *
 */
?>

<?php do_action( 'sk_dashboard_wrap_start' ); ?>

<div class="sk-dashboard-wrap">

    <?php

        /**
         *  sk_dashboard_content_before hook
         *  sk_dashboard_single_announcement_content_before
         *
         *  @hooked get_dashboard_side_navigation
         *
         */
        do_action( 'sk_dashboard_content_before' );
        do_action( 'sk_dashboard_single_announcement_content_before' );
    ?>

    <div class="sk-dashboard-content sk-notice-listing">

        <?php

            /**
             *  sk_before_single_notice hook
             *
             */
            do_action( 'sk_before_single_notice' );


            /**
             * sk_single_announcement_content hook
             *
             */
            do_action( 'sk_single_announcement_content' );

            /**
             *  sk_after_listing_notice hook
             *
             */
            do_action( 'sk_after_listing_notice' );
        ?>
    </div><!-- #primary .content-area -->

    <?php

        /**
         *  sk_dashboard_content_after hook
         *  sk_dashboard_single_announcement_content_after hook
         *
         */
        do_action( 'sk_dashboard_content_after' );
        do_action( 'sk_dashboard_single_announcement_content_after' );
    ?>

</div><!-- .sk-dashboard-wrap -->

<?php do_action( 'sk_dashboard_wrap_end' ); ?>