<?php
/**
 * SK Dahsbarod Review Main Template
 *
 *
 */
?>

<?php do_action( 'sk_dashboard_wrap_start' ); ?>

<div class="sk-dashboard-wrap">

    <?php

        /**
         *  sk_dashboard_content_before hook
         *  sk_dashboard_review_content_before hook
         *
         *  @hooked get_dashboard_side_navigation
         *
         */
        do_action( 'sk_dashboard_content_before' );
        do_action( 'sk_dashboard_review_content_before' );
    ?>

    <div class="sk-dashboard-content sk-reviews-content">

        <?php

            /**
             *  sk_review_content_inside_before hook
             *
             */
            do_action( 'sk_review_content_inside_before' );
        ?>


        <article class="sk-reviews-area">

            <?php
                /**
                 * sk_review_content_area_header hook
                 *
                 * @hooked sk_review_header_render
                 *
                 */
                do_action( 'sk_review_content_area_header' );


                /**
                 * sk_review_content hook
                 *
                 */
                do_action( 'sk_review_content' );

            ?>

        </article>

    </div><!-- .sk-dashboard-content -->

    <?php

        /**
         *  sk_dashboard_content_after hook
         *  sk_dashboard_review_content_after hook
         *
         */
        do_action( 'sk_dashboard_content_after' );
        do_action( 'sk_dashboard_review_content_after' );
    ?>

</div><!-- .sk-dashboard-wrap -->

<?php do_action( 'sk_dashboard_wrap_end' ); ?>