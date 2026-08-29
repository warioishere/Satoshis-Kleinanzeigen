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
 *                                  Enthaelt other_url: Profil des Gegenuebers.
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
							<?php
							// Bild und Name fuehren auf das Profil des
							// Gegenuebers — von hier aus will man wissen,
							// mit wem man es zu tun hat.
							$dvc_profil = $open_chat['other_url'] ?? '';
							?>
							<?php if ( $dvc_profil !== '' ) : ?>
								<a class="dvc-chat-partner" href="<?php echo esc_url( $dvc_profil ); ?>"><?php echo get_avatar( $open_chat['other_user_id'], 40 ); ?></a>
							<?php else : ?>
								<?php echo get_avatar( $open_chat['other_user_id'], 40 ); ?>
							<?php endif; ?>
							<div>
								<strong>
									<?php if ( $dvc_profil !== '' ) : ?>
										<a class="dvc-chat-partner" href="<?php echo esc_url( $dvc_profil ); ?>"><?php echo esc_html( $open_chat['display_name'] ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $open_chat['display_name'] ); ?>
									<?php endif; ?>
								</strong>
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
							<?php if ( ! empty( $open_chat['blocked_by_me'] ) ) : ?>
								<button class="dvc-action-btn dvc-unblock-btn" data-chat-id="<?php echo esc_attr( $open_chat['id'] ); ?>" title="<?php esc_attr_e( 'Blockierung aufheben', 'sk-core' ); ?>">
									<i class="fas fa-user-check"></i>
								</button>
							<?php else : ?>
								<button class="dvc-action-btn dvc-block-btn" data-chat-id="<?php echo esc_attr( $open_chat['id'] ); ?>" title="<?php esc_attr_e( 'Blockieren', 'sk-core' ); ?>">
									<i class="fas fa-user-slash"></i>
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
						<?php if ( ! empty( $open_chat['is_blocked'] ) ) : ?>
							<p class="dvc-blocked-notice">
								<i class="fas fa-user-slash"></i>
								<?php
								echo ! empty( $open_chat['blocked_by_me'] )
									? esc_html__( 'Du hast diesen Nutzer blockiert. In dieser Unterhaltung kann niemand mehr schreiben.', 'sk-core' )
									: esc_html__( 'In dieser Unterhaltung kann nicht mehr geschrieben werden.', 'sk-core' );
								?>
							</p>
						<?php else : ?>
						<form class="dvc-send-message-form" data-chat-id="<?php echo esc_attr( $open_chat['id'] ); ?>">
							<textarea
								name="message"
								class="dvc-message-input"
								placeholder="<?php esc_attr_e( 'Nachricht schreiben...', 'sk-core' ); ?>"
								rows="3"
								maxlength="<?php echo esc_attr( \SK\Core\Dashboard\Modules\VendorChat::MAX_MESSAGE_LENGTH ); ?>"
								required
							></textarea>
							<button type="submit" class="dvc-send-btn">
								<i class="fas fa-paper-plane"></i>
								<?php esc_html_e( 'Senden', 'sk-core' ); ?>
							</button>
						</form>
						<?php endif; ?>
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

<?php do_action( 'sk_dashboard_wrap_end' ); ?>
