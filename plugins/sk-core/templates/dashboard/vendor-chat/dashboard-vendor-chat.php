<?php
/**
 * SK Vendor Chat Dashboard Template
 *
 * Variables come from VendorChat::dashboard_view_data(), registered as
 * 'template_args' in the module config and run before this file is included.
 *
 * @var int        $current_user_id
 * @var string     $view            'active' or 'archived'.
 * @var int        $chat_id         Chat requested via ?chat_id=, 0 if none.
 * @var int        $active_count
 * @var int        $archived_count
 * @var array[]    $active_rows     Sidebar entries, with preview line.
 * @var array[]    $archived_rows   Sidebar entries, without preview line.
 * @var array|null $open_chat       Opened chat, null if none is viewable.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'sk_dashboard_wrap_start' );
?>

<div class="sk-dashboard-wrap">
	<?php do_action( 'sk_dashboard_content_before' ); ?>

	<div class="sk-dashboard-content sk-vendor-chat-dashboard">
		<?php do_action( 'sk_dashboard_content_inside_before' ); ?>

		<div class="sk-review-page-header">
			<h2><i class="fas fa-comments"></i> <?php esc_html_e( 'Nachrichten', 'sk-core' ); ?></h2>
		</div>

		<div class="dvc-chat-container">
			<!-- Sidebar with chat list -->
			<div class="dvc-chat-sidebar">
				<!-- Tabs -->
				<div class="dvc-chat-tabs">
					<button class="dvc-tab-btn <?php echo $view === 'active' ? 'active' : ''; ?>" data-tab="active">
						<?php esc_html_e( 'Aktiv', 'sk-core' ); ?>
						<?php if ( $active_count > 0 ) : ?>
							<span class="dvc-count">(<?php echo $active_count; ?>)</span>
						<?php endif; ?>
					</button>
					<button class="dvc-tab-btn <?php echo $view === 'archived' ? 'active' : ''; ?>" data-tab="archived">
						<?php esc_html_e( 'Archiviert', 'sk-core' ); ?>
						<?php if ( $archived_count > 0 ) : ?>
							<span class="dvc-count">(<?php echo $archived_count; ?>)</span>
						<?php endif; ?>
					</button>
				</div>

				<!-- Active chats list -->
				<div class="dvc-chat-list" id="dvc-active-list" style="<?php echo $view !== 'active' ? 'display:none;' : ''; ?>">
					<?php if ( empty( $active_rows ) ) : ?>
						<div class="dvc-empty-state">
							<i class="fas fa-inbox"></i>
							<p><?php esc_html_e( 'Keine aktiven Chats', 'sk-core' ); ?></p>
							<small><?php esc_html_e( 'Starte einen Chat von einer Produktseite', 'sk-core' ); ?></small>
						</div>
					<?php else : ?>
						<?php foreach ( $active_rows as $row ) : ?>
							<div class="dvc-chat-item <?php echo $chat_id == $row['id'] ? 'active' : ''; ?> <?php echo $row['unread'] ? 'unread' : ''; ?>"
								data-chat-id="<?php echo esc_attr( $row['id'] ); ?>">
								<div class="dvc-chat-item-avatar">
									<?php echo get_avatar( $row['other_user_id'], 40 ); ?>
								</div>
								<div class="dvc-chat-item-content">
									<div class="dvc-chat-item-header">
										<strong><?php echo esc_html( $row['display_name'] ); ?></strong>
										<?php if ( null !== $row['timestamp'] ) : ?>
											<span class="dvc-chat-time">
												<?php echo human_time_diff( $row['timestamp'], current_time( 'timestamp' ) ); ?>
											</span>
										<?php endif; ?>
									</div>
									<div class="dvc-chat-item-product">
										<small><?php echo esc_html( $row['product_title'] ); ?></small>
									</div>
									<?php if ( null !== $row['preview'] ) : ?>
										<div class="dvc-chat-item-preview">
											<?php echo esc_html( wp_trim_words( $row['preview'], 8, '...' ) ); ?>
										</div>
									<?php endif; ?>
								</div>
								<?php if ( $row['unread'] ) : ?>
									<div class="dvc-unread-indicator"></div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<!-- Archived chats list -->
				<div class="dvc-chat-list" id="dvc-archived-list" style="<?php echo $view !== 'archived' ? 'display:none;' : ''; ?>">
					<?php if ( empty( $archived_rows ) ) : ?>
						<div class="dvc-empty-state">
							<i class="fas fa-archive"></i>
							<p><?php esc_html_e( 'Keine archivierten Chats', 'sk-core' ); ?></p>
						</div>
					<?php else : ?>
						<?php foreach ( $archived_rows as $row ) : ?>
							<div class="dvc-chat-item <?php echo $chat_id == $row['id'] ? 'active' : ''; ?>"
								data-chat-id="<?php echo esc_attr( $row['id'] ); ?>">
								<div class="dvc-chat-item-avatar">
									<?php echo get_avatar( $row['other_user_id'], 40 ); ?>
								</div>
								<div class="dvc-chat-item-content">
									<div class="dvc-chat-item-header">
										<strong><?php echo esc_html( $row['display_name'] ); ?></strong>
										<?php if ( null !== $row['timestamp'] ) : ?>
											<span class="dvc-chat-time">
												<?php echo human_time_diff( $row['timestamp'], current_time( 'timestamp' ) ); ?>
											</span>
										<?php endif; ?>
									</div>
									<div class="dvc-chat-item-product">
										<small><?php echo esc_html( $row['product_title'] ); ?></small>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

			<!-- Chat window -->
			<div class="dvc-chat-window">
				<?php if ( $open_chat ) : ?>

					<!-- Chat header -->
					<div class="dvc-chat-header">
						<div class="dvc-chat-header-info">
							<?php echo get_avatar( $open_chat['other_user_id'], 40 ); ?>
							<div>
								<strong><?php echo esc_html( $open_chat['display_name'] ); ?></strong>
								<div class="dvc-chat-product-link">
									<a href="<?php echo esc_url( $open_chat['product_url'] ); ?>" target="_blank">
										<i class="fas fa-box"></i>
										<?php echo esc_html( $open_chat['product_title'] ); ?>
									</a>
								</div>
							</div>
						</div>
						<div class="dvc-chat-actions">
							<?php if ( $open_chat['is_archived'] ) : ?>
								<button class="dvc-action-btn dvc-unarchive-btn" data-chat-id="<?php echo esc_attr( $open_chat['id'] ); ?>" title="<?php esc_attr_e( 'Wiederherstellen', 'sk-core' ); ?>">
									<i class="fas fa-box-open"></i>
								</button>
							<?php else : ?>
								<button class="dvc-action-btn dvc-archive-btn" data-chat-id="<?php echo esc_attr( $open_chat['id'] ); ?>" title="<?php esc_attr_e( 'Archivieren', 'sk-core' ); ?>">
									<i class="fas fa-archive"></i>
								</button>
							<?php endif; ?>
							<button class="dvc-action-btn dvc-delete-btn" data-chat-id="<?php echo esc_attr( $open_chat['id'] ); ?>" title="<?php esc_attr_e( 'Löschen', 'sk-core' ); ?>">
								<i class="fas fa-trash"></i>
							</button>
						</div>
					</div>

					<!-- Messages area -->
					<div class="dvc-messages-area" id="dvc-messages-area" data-current-user-id="<?php echo esc_attr( $current_user_id ); ?>">
						<?php if ( empty( $open_chat['messages'] ) ) : ?>
							<div class="dvc-empty-chat">
								<i class="fas fa-comments"></i>
								<p><?php esc_html_e( 'Noch keine Nachrichten', 'sk-core' ); ?></p>
							</div>
						<?php else : ?>
							<?php foreach ( $open_chat['messages'] as $message ) : ?>
								<div class="dvc-message <?php echo $message['is_own'] ? 'own' : 'other'; ?>"
									<?php if ( $message['card'] ) : ?>
									data-sk-card="<?php echo esc_attr( wp_json_encode( $message['card'] ) ); ?>"
									<?php endif; ?>>
									<div class="dvc-message-avatar">
										<?php echo get_avatar( $message['user_id'], 32 ); ?>
									</div>
									<div class="dvc-message-content">
										<div class="dvc-message-header">
											<strong><?php echo esc_html( $message['name'] ); ?></strong>
											<span class="dvc-message-time">
												<?php echo date_i18n( 'd.m.Y H:i', $message['timestamp'] ); ?>
											</span>
										</div>
										<div class="dvc-message-text">
											<?php echo nl2br( esc_html( $message['text'] ) ); ?>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>

					<!-- Message input -->
					<div class="dvc-message-input-area">
						<form class="dvc-send-message-form" data-chat-id="<?php echo esc_attr( $open_chat['id'] ); ?>">
							<textarea
								name="message"
								class="dvc-message-input"
								placeholder="<?php esc_attr_e( 'Nachricht schreiben...', 'sk-core' ); ?>"
								rows="3"
								required
							></textarea>
							<button type="submit" class="dvc-send-btn">
								<i class="fas fa-paper-plane"></i>
								<?php esc_html_e( 'Senden', 'sk-core' ); ?>
							</button>
						</form>
					</div>

				<?php else : ?>
					<!-- No chat selected -->
					<div class="dvc-no-chat-selected">
						<i class="fas fa-comments"></i>
						<h3><?php esc_html_e( 'Wähle einen Chat aus', 'sk-core' ); ?></h3>
						<p><?php esc_html_e( 'Klicke auf einen Chat in der Seitenleiste, um die Unterhaltung anzuzeigen.', 'sk-core' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<?php do_action( 'sk_dashboard_content_inside_after' ); ?>
	</div>

	<?php do_action( 'sk_dashboard_content_after' ); ?>
</div>

<!-- Chat request modal -->
<div id="dvc-chat-modal" class="dvc-modal" style="display:none;">
	<div class="dvc-modal-content">
		<div class="dvc-modal-header">
			<h3><?php esc_html_e( 'Neue Nachricht', 'sk-core' ); ?></h3>
			<button class="dvc-modal-close">&times;</button>
		</div>
		<div class="dvc-modal-body">
			<form id="dvc-start-chat-form">
				<input type="hidden" name="vendor_id" id="dvc-vendor-id">
				<input type="hidden" name="product_id" id="dvc-product-id">
				<div class="dvc-form-group">
					<label><?php esc_html_e( 'Deine Nachricht:', 'sk-core' ); ?></label>
					<textarea
						name="message"
						id="dvc-chat-message"
						rows="5"
						placeholder="<?php esc_attr_e( 'Schreibe deine Nachricht...', 'sk-core' ); ?>"
						required
					></textarea>
				</div>
				<div class="dvc-modal-actions">
					<button type="button" class="dvc-btn-secondary dvc-modal-close">
						<?php esc_html_e( 'Abbrechen', 'sk-core' ); ?>
					</button>
					<button type="submit" class="dvc-btn-primary">
						<i class="fas fa-paper-plane"></i>
						<?php esc_html_e( 'Nachricht senden', 'sk-core' ); ?>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>


<?php do_action( 'sk_dashboard_wrap_end' ); ?>
