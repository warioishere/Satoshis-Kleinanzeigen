<?php
/**
 * Vendor Dashboard — Feed Posts (Beiträge).
 * Follows the exact same pattern as follow-store/views/vendor-dashboard.php.
 *
 * @var array $posts      Array of WP_Post objects.
 * @var int   $post_count Number of posts.
 */

defined( 'ABSPATH' ) || exit;

do_action( 'sk_dashboard_wrap_start' );
?>

<div class="sk-dashboard-wrap">
	<?php do_action( 'sk_dashboard_content_before' ); ?>

	<div class="sk-dashboard-content">

		<!-- Page Header -->
		<div class="sk-feed-page-header">
			<h2>
				<i class="fas fa-rss"></i>
				<?php esc_html_e( 'Beiträge', 'sk-core' ); ?>
				<?php if ( $post_count > 0 ) : ?>
					<span class="sk-feed-total"><?php echo esc_html( $post_count ); ?></span>
				<?php endif; ?>
			</h2>
		</div>

		<!-- Compose Form -->
		<div class="sk-feed-compose">
			<h3 class="sk-feed-section-title"><?php esc_html_e( 'Beitrag erstellen', 'sk-core' ); ?></h3>
			<form class="sk-feed-compose-form" id="sk-feed-compose-form" enctype="multipart/form-data">
				<div class="sk-form-group">
					<textarea
						class="sk-form-control"
						name="content"
						id="sk-feed-content"
						rows="4"
						maxlength="2000"
						placeholder="<?php esc_attr_e( 'Was gibt\'s Neues?', 'sk-core' ); ?>"
						required
					></textarea>
					<div class="sk-feed-char-count"><span id="sk-feed-chars">0</span>/2000</div>
				</div>

				<div class="sk-feed-image-preview" id="sk-feed-image-preview" style="display:none;">
					<img id="sk-feed-preview-img" src="" alt="" />
					<button type="button" class="sk-feed-remove-image" id="sk-feed-remove-image"><i class="fas fa-times"></i></button>
				</div>

				<div class="sk-feed-compose-actions">
					<label class="sk-feed-upload-btn" for="sk-feed-image-input">
						<i class="fas fa-image"></i> <?php esc_html_e( 'Bild', 'sk-core' ); ?>
					</label>
					<input type="file" id="sk-feed-image-input" name="image" accept="image/*" style="display:none;" />
					<button type="submit" class="sk-btn sk-btn-btc" id="sk-feed-submit">
						<i class="fas fa-paper-plane"></i> <?php esc_html_e( 'Posten', 'sk-core' ); ?>
					</button>
				</div>
			</form>
		</div>

		<!-- Post List -->
		<h3 class="sk-feed-section-title"><?php esc_html_e( 'Meine Beiträge', 'sk-core' ); ?></h3>

		<?php if ( empty( $posts ) ) : ?>

			<div class="sk-feed-empty">
				<i class="fas fa-rss"></i>
				<p><?php esc_html_e( 'Du hast noch keine Beiträge erstellt.', 'sk-core' ); ?></p>
			</div>

		<?php else : ?>

			<div class="sk-feed-dashboard-list" id="sk-feed-dashboard-list">
				<?php foreach ( $posts as $feed_post ) :
					$thumb_url  = get_the_post_thumbnail_url( $feed_post->ID, 'thumbnail' );
					$time_ago   = human_time_diff( get_post_time( 'U', false, $feed_post ), current_time( 'timestamp' ) );
					$like_count = \SK\Modules\Feed\Likes::get_count( $feed_post->ID );
					$comments   = (int) get_comments_number( $feed_post->ID );
					$is_hidden  = $feed_post->post_status === 'pending';
				?>
					<div class="sk-feed-dashboard-item<?php echo $is_hidden ? ' sk-feed-dashboard-item--hidden' : ''; ?>" data-post-id="<?php echo esc_attr( $feed_post->ID ); ?>">
						<?php if ( $thumb_url ) : ?>
							<div class="sk-feed-dashboard-item-thumb">
								<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" />
							</div>
						<?php endif; ?>
						<div class="sk-feed-dashboard-item-body">
							<div class="sk-feed-dashboard-item-content" data-full-content="<?php echo esc_attr( $feed_post->post_content ); ?>">
								<?php echo wp_trim_words( wp_strip_all_tags( $feed_post->post_content ), 30 ); ?>
							</div>
							<div class="sk-feed-dashboard-item-edit" style="display:none;">
								<textarea class="sk-form-control sk-feed-edit-textarea" rows="3" maxlength="2000"><?php echo esc_textarea( $feed_post->post_content ); ?></textarea>
								<div class="sk-feed-edit-image">
									<?php if ( $thumb_url ) : ?>
										<div class="sk-feed-edit-image-current">
											<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" />
											<button type="button" class="sk-feed-edit-remove-image" title="<?php esc_attr_e( 'Bild entfernen', 'sk-core' ); ?>"><i class="fas fa-times"></i></button>
										</div>
									<?php endif; ?>
									<label class="sk-feed-upload-btn sk-feed-edit-upload-label">
										<i class="fas fa-image"></i> <?php echo $thumb_url ? esc_html__( 'Bild ändern', 'sk-core' ) : esc_html__( 'Bild hinzufügen', 'sk-core' ); ?>
										<input type="file" class="sk-feed-edit-image-input" accept="image/*" style="display:none;" />
									</label>
								</div>
								<div class="sk-feed-edit-actions">
									<button type="button" class="sk-btn sk-btn-btc sk-feed-save-btn" data-post-id="<?php echo esc_attr( $feed_post->ID ); ?>"><?php esc_html_e( 'Speichern', 'sk-core' ); ?></button>
									<button type="button" class="sk-feed-cancel-edit-btn"><?php esc_html_e( 'Abbrechen', 'sk-core' ); ?></button>
								</div>
							</div>
							<div class="sk-feed-dashboard-item-meta">
								<span><i class="far fa-clock"></i> <?php printf( esc_html__( 'vor %s', 'sk-core' ), $time_ago ); ?></span>
								<?php if ( $like_count ) : ?>
									<span><i class="far fa-heart"></i> <?php echo esc_html( $like_count ); ?></span>
								<?php endif; ?>
								<?php if ( $comments ) : ?>
									<span><i class="far fa-comment"></i> <?php echo esc_html( $comments ); ?></span>
								<?php endif; ?>
								<?php if ( $is_hidden ) : ?>
									<span class="sk-feed-status-hidden"><i class="fas fa-eye-slash"></i> <?php esc_html_e( 'Versteckt (gemeldet)', 'sk-core' ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						<div class="sk-feed-dashboard-item-actions">
							<?php $is_pinned = (int) get_post_meta( $feed_post->ID, '_sk_feed_pinned', true ); ?>
							<button type="button" class="sk-feed-pin-btn<?php echo $is_pinned ? ' active' : ''; ?>" data-post-id="<?php echo esc_attr( $feed_post->ID ); ?>" title="<?php echo $is_pinned ? esc_attr__( 'Loslösen', 'sk-core' ) : esc_attr__( 'Anpinnen', 'sk-core' ); ?>">
								<i class="fas fa-thumbtack"></i>
							</button>
							<button type="button" class="sk-feed-edit-btn" data-post-id="<?php echo esc_attr( $feed_post->ID ); ?>" title="<?php esc_attr_e( 'Bearbeiten', 'sk-core' ); ?>">
								<i class="fas fa-pen"></i>
							</button>
							<button type="button" class="sk-feed-delete-btn" data-post-id="<?php echo esc_attr( $feed_post->ID ); ?>" title="<?php esc_attr_e( 'Löschen', 'sk-core' ); ?>">
								<i class="fas fa-trash-alt"></i>
							</button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

		<?php endif; ?>

	</div><!-- .sk-dashboard-content -->
</div><!-- .sk-dashboard-wrap -->

<?php do_action( 'sk_dashboard_wrap_end' ); ?>
