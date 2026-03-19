<?php
/**
 * SK No Announcement Found Template
 *
 *
 */
?>
<article class="sk-notice-single-notice-area">
    <header class="sk-dashboard-header sk-clearfix">
        <span class="left-header-content">
            <h1 class="entry-title"><?php _e( 'Notice', 'sk' ); ?></h1>
        </span>
    </header>
    <div class="sk-error">
        <?php echo sprintf( "<p>%s <a href='%s'>%s</a></p", __( 'No Notice found; ', 'sk' ), sk_get_navigation_url('announcement'), __( 'Back to all Notice', 'sk' ) ) ?>
    </div>
</article>
