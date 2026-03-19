<?php
/**
 *  SK Dashboard Template
 *
 *  SK Dahsboard Announcement widget template
 *
 *
 *
 * @var $notices          \SK\Pro\Announcement\Single[]|WP_Error
 * @var $announcement_url string
 */
?>
<div class="dashboard-widget sk-announcement-widget">
    <div class="widget-title">
        <i class="fas fa-bullhorn" aria-hidden="true"></i> <?php esc_html_e( 'Latest Announcement', 'sk' ); ?>

        <span class="pull-right">
            <a href="<?php echo $announcement_url; ?>"><?php esc_html_e( 'See All', 'sk' ); ?></a>
        </span>
    </div>
    <?php if ( is_wp_error( $notices ) || empty( $notices ) ) : ?>
        <div class="sk-no-announcement">
            <div class="annoument-no-wrapper">
                <i class="fas fa-bell sk-announcement-icon"></i>
                <p><?php esc_html_e( 'No announcement found', 'sk' ); ?></p>
            </div>
        </div>
    <?php elseif ( $notices ) : ?>
        <ul class="list-unstyled">
            <?php foreach ( $notices as $notice ) : ?>
                <?php
                $notice_url = trailingslashit( sk_get_navigation_url( 'single-announcement' ) . $notice->get_notice_id() );
                ?>
                <li>
                    <div class="sk-dashboard-announce-content sk-left">
                        <a href="<?php echo $notice_url; ?>"><h3><?php echo $notice->get_title(); ?></h3></a>
                        <?php echo wp_trim_words( $notice->get_content(), 6, '...' ); ?>
                    </div>
                    <div class="sk-dashboard-announce-date sk-right <?php echo ( $notice->get_read_status() === 'unread' ) ? 'sk-dashboard-announce-unread' : 'sk-dashboard-announce-read'; ?>">
                        <div class="announce-day"><?php echo sk_format_date( $notice->get_date(), 'd' ); ?></div>
                        <div class="announce-month"><?php echo sk_format_date( $notice->get_date(), 'F' ); ?></div>
                        <div class="announce-year"><?php echo sk_format_date( $notice->get_date(), 'Y' ); ?></div>
                    </div>
                    <div class="sk-clearfix"></div>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</div> <!-- .products -->
