<?php
/**
 * SK Single Announement Content Template
 *
 *
 *
 * @var $notice Single
 */

use SK\Core\Announcement\Single;

?>
<article class="sk-notice-single-notice-area">
    <header class="sk-dashboard-header sk-clearfix">
        <span class="left-header-content">
            <h2 class="entry-title"><?php echo $notice->get_title(); ?></h2>
        </span>
    </header>
    <span class="sk-single-announcement-date"><i class="far fa-calendar-alt"></i> <?php echo sk_format_date( $notice->get_date() ); ?></span>

    <div class="entry-content">
        <?php echo wp_kses_post( wpautop( $notice->get_content() ) ); ?>
    </div>

    <div class="sk-announcement-link">
        <a href="<?php echo esc_url( sk_get_navigation_url( 'announcement' ) ); ?>" class="sk-btn sk-btn-theme"><?php esc_html_e( 'Back to all Notice', 'sk-core' ); ?></a>
    </div>
</article>
