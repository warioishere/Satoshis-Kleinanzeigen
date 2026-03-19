<?php
/**
 * SK Vendor Chat Dashboard Template
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user_id = get_current_user_id();

// Get active and archived chats
$active_chats   = dvc_get_user_chats( $current_user_id, false );
$archived_chats = dvc_get_user_chats( $current_user_id, true );

// Get current view
$view    = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : 'active';
$chat_id = isset( $_GET['chat_id'] ) ? intval( $_GET['chat_id'] ) : 0;

// If viewing a specific chat
if ( $chat_id && dvc_is_chat_participant( $chat_id, $current_user_id ) ) {
	dvc_mark_chat_as_read( $chat_id, $current_user_id );
}
?>

<div class="sk-dashboard-wrap">
	<?php do_action( 'sk_dashboard_content_before' ); ?>

	<div class="sk-dashboard-content sk-vendor-chat-dashboard">
		<?php do_action( 'sk_dashboard_content_inside_before' ); ?>

		<div class="sk-review-page-header">
			<h2><i class="fas fa-comments"></i> <?php esc_html_e( 'Nachrichten', 'sk-vendor-chat' ); ?></h2>
		</div>

		<div class="dvc-chat-container">
			<!-- Sidebar with chat list -->
			<div class="dvc-chat-sidebar">
				<!-- Tabs -->
				<div class="dvc-chat-tabs">
					<button class="dvc-tab-btn <?php echo $view === 'active' ? 'active' : ''; ?>" data-tab="active">
						<?php esc_html_e( 'Aktiv', 'sk-vendor-chat' ); ?>
						<?php if ( count( $active_chats ) > 0 ) : ?>
							<span class="dvc-count">(<?php echo count( $active_chats ); ?>)</span>
						<?php endif; ?>
					</button>
					<button class="dvc-tab-btn <?php echo $view === 'archived' ? 'active' : ''; ?>" data-tab="archived">
						<?php esc_html_e( 'Archiviert', 'sk-vendor-chat' ); ?>
						<?php if ( count( $archived_chats ) > 0 ) : ?>
							<span class="dvc-count">(<?php echo count( $archived_chats ); ?>)</span>
						<?php endif; ?>
					</button>
				</div>

				<!-- Active chats list -->
				<div class="dvc-chat-list" id="dvc-active-list" style="<?php echo $view !== 'active' ? 'display:none;' : ''; ?>">
					<?php if ( empty( $active_chats ) ) : ?>
						<div class="dvc-empty-state">
							<i class="fas fa-inbox"></i>
							<p><?php esc_html_e( 'Keine aktiven Chats', 'sk-vendor-chat' ); ?></p>
							<small><?php esc_html_e( 'Starte einen Chat von einer Produktseite', 'sk-vendor-chat' ); ?></small>
						</div>
					<?php else : ?>
						<?php foreach ( $active_chats as $chat ) : ?>
							<?php
							$other_user_id = dvc_get_other_participant( $chat->ID, $current_user_id );
							$other_user    = get_userdata( $other_user_id );
							$vendor_info   = sk_get_store_info( $other_user_id );
							$display_name  = isset( $vendor_info['store_name'] ) && ! empty( $vendor_info['store_name'] )
								? $vendor_info['store_name']
								: $other_user->display_name;
							$product_id    = get_post_meta( $chat->ID, '_dvc_product_id', true );
							$product_title = get_the_title( $product_id );
							$messages      = dvc_get_chat_messages( $chat->ID );
							$last_message  = ! empty( $messages ) ? end( $messages ) : null;
							$unread        = dvc_has_unread_messages( $chat->ID, $current_user_id );
							?>
							<div class="dvc-chat-item <?php echo $chat_id == $chat->ID ? 'active' : ''; ?> <?php echo $unread ? 'unread' : ''; ?>"
								data-chat-id="<?php echo esc_attr( $chat->ID ); ?>">
								<div class="dvc-chat-item-avatar">
									<?php echo get_avatar( $other_user_id, 40 ); ?>
								</div>
								<div class="dvc-chat-item-content">
									<div class="dvc-chat-item-header">
										<strong><?php echo esc_html( $display_name ); ?></strong>
										<?php if ( $last_message ) : ?>
											<span class="dvc-chat-time">
												<?php echo human_time_diff( $last_message['timestamp'], current_time( 'timestamp' ) ); ?>
											</span>
										<?php endif; ?>
									</div>
									<div class="dvc-chat-item-product">
										<small><?php echo esc_html( $product_title ); ?></small>
									</div>
									<?php if ( $last_message ) : ?>
										<div class="dvc-chat-item-preview">
											<?php
											$preview = wp_trim_words( $last_message['message'], 8, '...' );
											echo esc_html( $preview );
											?>
										</div>
									<?php endif; ?>
								</div>
								<?php if ( $unread ) : ?>
									<div class="dvc-unread-indicator"></div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<!-- Archived chats list -->
				<div class="dvc-chat-list" id="dvc-archived-list" style="<?php echo $view !== 'archived' ? 'display:none;' : ''; ?>">
					<?php if ( empty( $archived_chats ) ) : ?>
						<div class="dvc-empty-state">
							<i class="fas fa-archive"></i>
							<p><?php esc_html_e( 'Keine archivierten Chats', 'sk-vendor-chat' ); ?></p>
						</div>
					<?php else : ?>
						<?php foreach ( $archived_chats as $chat ) : ?>
							<?php
							$other_user_id = dvc_get_other_participant( $chat->ID, $current_user_id );
							$other_user    = get_userdata( $other_user_id );
							$vendor_info   = sk_get_store_info( $other_user_id );
							$display_name  = isset( $vendor_info['store_name'] ) && ! empty( $vendor_info['store_name'] )
								? $vendor_info['store_name']
								: $other_user->display_name;
							$product_id    = get_post_meta( $chat->ID, '_dvc_product_id', true );
							$product_title = get_the_title( $product_id );
							$messages      = dvc_get_chat_messages( $chat->ID );
							$last_message  = ! empty( $messages ) ? end( $messages ) : null;
							?>
							<div class="dvc-chat-item <?php echo $chat_id == $chat->ID ? 'active' : ''; ?>"
								data-chat-id="<?php echo esc_attr( $chat->ID ); ?>">
								<div class="dvc-chat-item-avatar">
									<?php echo get_avatar( $other_user_id, 40 ); ?>
								</div>
								<div class="dvc-chat-item-content">
									<div class="dvc-chat-item-header">
										<strong><?php echo esc_html( $display_name ); ?></strong>
										<?php if ( $last_message ) : ?>
											<span class="dvc-chat-time">
												<?php echo human_time_diff( $last_message['timestamp'], current_time( 'timestamp' ) ); ?>
											</span>
										<?php endif; ?>
									</div>
									<div class="dvc-chat-item-product">
										<small><?php echo esc_html( $product_title ); ?></small>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

			<!-- Chat window -->
			<div class="dvc-chat-window">
				<?php if ( $chat_id && dvc_is_chat_participant( $chat_id, $current_user_id ) ) : ?>
					<?php
					$other_user_id = dvc_get_other_participant( $chat_id, $current_user_id );
					$other_user    = get_userdata( $other_user_id );
					$vendor_info   = sk_get_store_info( $other_user_id );
					$display_name  = isset( $vendor_info['store_name'] ) && ! empty( $vendor_info['store_name'] )
						? $vendor_info['store_name']
						: $other_user->display_name;
					$product_id    = get_post_meta( $chat_id, '_dvc_product_id', true );
					$product_title = get_the_title( $product_id );
					$product_url   = get_permalink( $product_id );
					$messages      = dvc_get_chat_messages( $chat_id );
					$is_archived   = in_array( $current_user_id, get_post_meta( $chat_id, '_dvc_archived_by', true ) ?: [] );
					?>

					<!-- Chat header -->
					<div class="dvc-chat-header">
						<div class="dvc-chat-header-info">
							<?php echo get_avatar( $other_user_id, 40 ); ?>
							<div>
								<strong><?php echo esc_html( $display_name ); ?></strong>
								<div class="dvc-chat-product-link">
									<a href="<?php echo esc_url( $product_url ); ?>" target="_blank">
										<i class="fas fa-box"></i>
										<?php echo esc_html( $product_title ); ?>
									</a>
								</div>
							</div>
						</div>
						<div class="dvc-chat-actions">
							<?php if ( $is_archived ) : ?>
								<button class="dvc-action-btn dvc-unarchive-btn" data-chat-id="<?php echo esc_attr( $chat_id ); ?>" title="<?php esc_attr_e( 'Wiederherstellen', 'sk-vendor-chat' ); ?>">
									<i class="fas fa-box-open"></i>
								</button>
							<?php else : ?>
								<button class="dvc-action-btn dvc-archive-btn" data-chat-id="<?php echo esc_attr( $chat_id ); ?>" title="<?php esc_attr_e( 'Archivieren', 'sk-vendor-chat' ); ?>">
									<i class="fas fa-archive"></i>
								</button>
							<?php endif; ?>
							<button class="dvc-action-btn dvc-delete-btn" data-chat-id="<?php echo esc_attr( $chat_id ); ?>" title="<?php esc_attr_e( 'Löschen', 'sk-vendor-chat' ); ?>">
								<i class="fas fa-trash"></i>
							</button>
						</div>
					</div>

					<!-- Messages area -->
					<div class="dvc-messages-area" id="dvc-messages-area" data-current-user-id="<?php echo esc_attr( $current_user_id ); ?>">
						<?php if ( empty( $messages ) ) : ?>
							<div class="dvc-empty-chat">
								<i class="fas fa-comments"></i>
								<p><?php esc_html_e( 'Noch keine Nachrichten', 'sk-vendor-chat' ); ?></p>
							</div>
						<?php else : ?>
							<?php foreach ( $messages as $message ) : ?>
								<?php
								$is_own_message  = $message['user_id'] == $current_user_id;
								$message_user    = get_userdata( $message['user_id'] );
								$msg_store_info  = sk_get_store_info( $message['user_id'] );
								$msg_name        = ! empty( $msg_store_info['store_name'] ) ? $msg_store_info['store_name'] : ( $message_user ? $message_user->display_name : '' );
								?>
								<div class="dvc-message <?php echo $is_own_message ? 'own' : 'other'; ?>">
									<div class="dvc-message-avatar">
										<?php echo get_avatar( $message['user_id'], 32 ); ?>
									</div>
									<div class="dvc-message-content">
										<div class="dvc-message-header">
											<strong><?php echo esc_html( $msg_name ); ?></strong>
											<span class="dvc-message-time">
												<?php echo date_i18n( 'd.m.Y H:i', $message['timestamp'] ); ?>
											</span>
										</div>
										<div class="dvc-message-text">
											<?php echo nl2br( esc_html( $message['message'] ) ); ?>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>

					<!-- Message input -->
					<div class="dvc-message-input-area">
						<form class="dvc-send-message-form" data-chat-id="<?php echo esc_attr( $chat_id ); ?>">
							<textarea
								name="message"
								class="dvc-message-input"
								placeholder="<?php esc_attr_e( 'Nachricht schreiben...', 'sk-vendor-chat' ); ?>"
								rows="3"
								required
							></textarea>
							<button type="submit" class="dvc-send-btn">
								<i class="fas fa-paper-plane"></i>
								<?php esc_html_e( 'Senden', 'sk-vendor-chat' ); ?>
							</button>
						</form>
					</div>

				<?php else : ?>
					<!-- No chat selected -->
					<div class="dvc-no-chat-selected">
						<i class="fas fa-comments"></i>
						<h3><?php esc_html_e( 'Wähle einen Chat aus', 'sk-vendor-chat' ); ?></h3>
						<p><?php esc_html_e( 'Klicke auf einen Chat in der Seitenleiste, um die Unterhaltung anzuzeigen.', 'sk-vendor-chat' ); ?></p>
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
			<h3><?php esc_html_e( 'Neue Nachricht', 'sk-vendor-chat' ); ?></h3>
			<button class="dvc-modal-close">&times;</button>
		</div>
		<div class="dvc-modal-body">
			<form id="dvc-start-chat-form">
				<input type="hidden" name="vendor_id" id="dvc-vendor-id">
				<input type="hidden" name="product_id" id="dvc-product-id">
				<div class="dvc-form-group">
					<label><?php esc_html_e( 'Deine Nachricht:', 'sk-vendor-chat' ); ?></label>
					<textarea
						name="message"
						id="dvc-chat-message"
						rows="5"
						placeholder="<?php esc_attr_e( 'Schreibe deine Nachricht...', 'sk-vendor-chat' ); ?>"
						required
					></textarea>
				</div>
				<div class="dvc-modal-actions">
					<button type="button" class="dvc-btn-secondary dvc-modal-close">
						<?php esc_html_e( 'Abbrechen', 'sk-vendor-chat' ); ?>
					</button>
					<button type="submit" class="dvc-btn-primary">
						<i class="fas fa-paper-plane"></i>
						<?php esc_html_e( 'Nachricht senden', 'sk-vendor-chat' ); ?>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

