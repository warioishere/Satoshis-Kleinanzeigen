<?php
/**
 * Template Name: product Subscription
 */

?>

<?php do_action( 'sk_dashboard_wrap_start' ); ?>

<div class="sk-dashboard-wrap">

    <?php

    /**
     *  sk_dashboard_content_before hook
     *  sk_subcription_content_before hook
     *
     *  @hooked get_dashboard_side_navigation
     *
     */
    do_action( 'sk_dashboard_content_before' );
    do_action( 'sk_subcription_content_before' );
    ?>

    <div class="sk-dashboard-content">

        <?php

        /**
         *  sk_subscription_content_inside_before hook
         *
         */
        do_action( 'sk_subscription_content_inside_before' );

        echo do_shortcode( '[dps_product_pack]' );

        /**
         *  sk_subscription_content_inside_after hook
         *
         */
        do_action( 'sk_subscription_content_inside_after' );
        ?>


    </div><!-- #primary .content-area -->

    <?php
    /**
     *  sk_dashboard_content_after hook
     *  sk_subscription_content_after hook
     *
     */
    do_action( 'sk_dashboard_content_after' );
    do_action( 'sk_subscription_content_after' );
    ?>

</div>

<?php do_action( 'sk_dashboard_wrap_end' ); ?>
