<?php
/**
 * Template Name: product Subscription
 */

sk_redirect_login();
sk_redirect_if_not_seller();

get_header();

sk_frontend_dashboard_scripts();
?>

<?php sk_get_template( SK_CORE_DIR . '/templates/dashboard-nav.php', array( 'active_menu' => 'subscription' ) ); ?>

<div id="primary" class="content-area col-md-9">
    <div id="content" class="site-content" role="main">

    <?php while (have_posts()) : the_post(); ?>

        <?php get_template_part( 'content', 'page' ); ?>

    <?php endwhile; // end of the loop. ?>

    </div><!-- #content .site-content -->
</div><!-- #primary .content-area -->

<?php get_footer(); ?>
