<?php
/**
 * SK Announcement Template
 *
 *
 */

use SK\Core\Announcement\Single;

$current_user_id = get_current_user_id();
$manager         = sk_ext()->announcement->manager;
$pagenum         = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 1;
$per_page        = apply_filters( 'sk_announcement_list_number', 20 );

$args = [
	'vendor_id' => $current_user_id,
	'page'      => $pagenum,
	'per_page'  => $per_page,
	'status'    => 'publish',
	'return'    => 'all',
];

$announcements   = $manager->all( $args );
$pagination_data = $manager->get_pagination_data( $args );

// Pre-load first announcement if available
$first_notice = null;
if ( ! empty( $announcements ) ) {
	$first = $announcements[0];
	$first_notice = $manager->get_notice( $first->get_notice_id() );
	if ( $first_notice instanceof Single && 'unread' === $first_notice->get_read_status() ) {
		$manager->update_read_status( $first->get_notice_id(), 'read' );
		$first_notice = $first_notice->set_read_status( 'read' );
	}
}

wp_enqueue_script( 'sk-announcement' );
wp_localize_script(
	'sk-announcement',
	'skAnnouncement',
	[
		'nonce' => wp_create_nonce( 'sk_announcement_nonce' ),
	]
);
?>

<div class="sk-dashboard-wrap">
	<?php do_action( 'sk_dashboard_content_before' ); ?>

	<div class="sk-dashboard-content sk-announcement-dashboard">
		<?php do_action( 'sk_dashboard_content_inside_before' ); ?>

		<div class="sk-review-page-header">
			<h2><i class="fas fa-bell"></i> <?php esc_html_e( 'Ankündigungen', 'sk' ); ?></h2>
		</div>

		<div class="sk-announcement-container">
			<!-- Sidebar with announcement list -->
			<div class="sk-announcement-sidebar">
				<?php if ( empty( $announcements ) ) : ?>
					<div class="sk-announcement-empty">
						<i class="fas fa-bell"></i>
						<p><?php esc_html_e( 'Keine Ankündigungen', 'sk' ); ?></p>
					</div>
				<?php else : ?>
					<div class="sk-announcement-list">
						<?php foreach ( $announcements as $index => $item ) :
							$is_first  = ( $index === 0 );
							$is_unread = $item->get_read_status() === 'unread';
							?>
							<a href="#"
							   class="sk-announcement-list-item <?php echo $is_first ? 'active' : ''; ?> <?php echo $is_unread ? 'unread' : ''; ?>"
							   data-notice-id="<?php echo esc_attr( $item->get_notice_id() ); ?>">
								<div class="sk-announcement-list-item-header">
									<strong><?php echo esc_html( $item->get_title() ); ?></strong>
									<?php if ( $is_unread ) : ?>
										<span class="sk-announcement-badge"></span>
									<?php endif; ?>
								</div>
								<div class="sk-announcement-list-item-meta">
									<span class="sk-announcement-date">
										<i class="far fa-calendar-alt"></i>
										<?php echo sk_format_date( $item->get_date() ); ?>
									</span>
								</div>
								<div class="sk-announcement-list-item-preview">
									<?php echo wp_trim_words( wp_strip_all_tags( $item->get_content() ), 12, '...' ); ?>
								</div>
							</a>
						<?php endforeach; ?>
					</div>

					<?php if ( $pagination_data['total_pages'] > 1 ) : ?>
						<div class="sk-announcement-pagination">
							<?php
							$base_url   = sk_get_navigation_url( 'announcement' );
							$page_links = paginate_links( [
								'current'   => $pagenum,
								'total'     => $pagination_data['total_pages'],
								'base'      => $base_url . '%_%',
								'format'    => '?pagenum=%#%',
								'add_args'  => false,
								'type'      => 'array',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
							] );
							if ( $page_links ) {
								echo '<ul class="pagination"><li>' . join( '</li><li>', $page_links ) . '</li></ul>';
							}
							?>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<!-- Content area -->
			<div class="sk-announcement-content-area" id="sk-announcement-content-area">
				<?php if ( $first_notice instanceof Single ) : ?>
					<div class="sk-announcement-detail">
						<div class="sk-announcement-detail-header">
							<h3><?php echo esc_html( $first_notice->get_title() ); ?></h3>
							<span class="sk-announcement-detail-date">
								<i class="far fa-calendar-alt"></i>
								<?php echo sk_format_date( $first_notice->get_date() ); ?>
							</span>
						</div>
						<div class="sk-announcement-detail-body">
							<?php echo wp_kses_post( wpautop( $first_notice->get_content() ) ); ?>
						</div>
					</div>
				<?php elseif ( ! empty( $announcements ) ) : ?>
					<div class="sk-announcement-empty-detail">
						<i class="fas fa-bell"></i>
						<p><?php esc_html_e( 'Wähle eine Ankündigung aus', 'sk' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<?php do_action( 'sk_dashboard_content_inside_after' ); ?>
	</div>

	<?php do_action( 'sk_dashboard_content_after' ); ?>
</div>

