<?php

namespace SK\Core\Dashboard\Modules;

use SK\Core\Dashboard\ChatMessages;
use SK\Core\Dashboard\DashboardModule;

/**
 * Vendor Chat module — ported from sk-vendor-chat plugin.
 *
 * Registers the vendor_chat CPT, adds the Nachrichten dashboard nav item,
 * handles all AJAX endpoints (start/send/get/delete/archive/unarchive),
 * outputs the product-page chat modal, and adds the chat icon to the
 * contact-icons collection.
 *
 */
class VendorChat extends DashboardModule {

	public function config(): ?array {
		if ( ! self::is_enabled() ) {
			return null;
		}
		return [
			'slug'          => 'vendor-chat',
			'title'         => __( 'Nachrichten', 'sk-core' ),
			'icon'          => '<i class="fas fa-comment-dots"></i>',
			'pos'           => 56,
			'permission'    => 'sk_view_overview_menu',
			'template'      => 'dashboard/vendor-chat/dashboard-vendor-chat',
			// Data and the mark-as-read side effect run here, before the
			// template loads; the template only renders what this returns.
			'template_args' => [ $this, 'dashboard_view_data' ],
		];
	}

	protected function register_extras(): void {
		// Registered whatever the setting says: existing chats have to keep
		// cleaning up their message rows, and the settings page is what
		// switches the feature back on.
		add_action( 'init',               [ $this, 'register_cpt' ] );
		add_action( 'init',               [ ChatMessages::class, 'maybe_install' ] );
		// Custom table rows are not covered by post deletion.
		add_action( 'before_delete_post', [ $this, 'delete_chat_messages' ] );
		add_action( 'admin_menu',         [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init',         [ $this, 'register_settings' ] );

		// Everything below is the running chat. With the feature switched off
		// the dashboard entry and the product-page icon already disappeared,
		// but the six write endpoints stayed reachable and every frontend page
		// still shipped the script together with a valid nonce.
		if ( ! self::is_enabled() ) {
			return;
		}

		// Badge runs AFTER Registry injects at 50 so it can modify the entry.
		add_filter( 'sk_get_dashboard_nav',         [ $this, 'add_notification_badge' ], 60 );
		add_action( 'wp_enqueue_scripts',           [ $this, 'enqueue_assets' ] );
		add_action( 'wp_footer',                    [ $this, 'output_modal' ] );
		add_filter( 'dkp_contact_icons_collection', [ $this, 'add_chat_icon' ], 10, 4 );

		// AJAX handlers — logged-in users only
		add_action( 'wp_ajax_dvc_start_chat',     [ $this, 'ajax_start_chat' ] );
		add_action( 'wp_ajax_dvc_send_message',   [ $this, 'ajax_send_message' ] );
		add_action( 'wp_ajax_dvc_get_messages',   [ $this, 'ajax_get_messages' ] );
		add_action( 'wp_ajax_dvc_delete_chat',    [ $this, 'ajax_delete_chat' ] );
		add_action( 'wp_ajax_dvc_archive_chat',   [ $this, 'ajax_archive_chat' ] );
		add_action( 'wp_ajax_dvc_unarchive_chat', [ $this, 'ajax_unarchive_chat' ] );
	}

	// =========================================================================
	// CPT
	// =========================================================================

	/**
	 * Register vendor_chat custom post type.
	 *
	 */
	public function register_cpt() {
		register_post_type( 'vendor_chat', [
			'labels'              => [
				'name'               => __( 'Vendor Chats', 'sk-core' ),
				'singular_name'      => __( 'Vendor Chat', 'sk-core' ),
				'add_new'            => __( 'Add New Chat', 'sk-core' ),
				'add_new_item'       => __( 'Add New Chat', 'sk-core' ),
				'edit_item'          => __( 'Edit Chat', 'sk-core' ),
				'new_item'           => __( 'New Chat', 'sk-core' ),
				'view_item'          => __( 'View Chat', 'sk-core' ),
				'search_items'       => __( 'Search Chats', 'sk-core' ),
				'not_found'          => __( 'No chats found', 'sk-core' ),
				'not_found_in_trash' => __( 'No chats found in trash', 'sk-core' ),
			],
			'public'              => false,
			'has_archive'         => false,
			'publicly_queryable'  => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_admin_bar'   => false,
			'rewrite'             => false,
			'supports'            => [ 'title', 'author' ],
			'show_in_rest'        => false,
			'menu_icon'           => 'dashicons-format-chat',
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'exclude_from_search' => true,
		] );
	}

	// =========================================================================
	// Dashboard nav
	// =========================================================================

	/**
	 * Append unread notification badge to the nav title.
	 *
	 *
	 * @param array $nav
	 * @return array
	 */
	public function add_notification_badge( $nav ) {
		if ( ! is_user_logged_in() || ! isset( $nav['vendor-chat'] ) ) {
			return $nav;
		}

		$unread = $this->get_unread_count( get_current_user_id() );
		if ( $unread > 0 ) {
			$nav['vendor-chat']['title'] .= ' <span class="dvc-notification-badge">' . $unread . '</span>';
		}

		return $nav;
	}

	// (Nav activation + template dispatch handled by DashboardRegistry via config()).

	// =========================================================================
	// Dashboard view data
	// =========================================================================

	/**
	 * Everything the Nachrichten dashboard renders.
	 *
	 * Registered as 'template_args', so it runs before the template is
	 * included. Includes the mark-as-read side effect of opening a chat.
	 *
	 * @param array $query_vars
	 * @return array
	 */
	public function dashboard_view_data( $query_vars = [] ): array {
		$user_id = get_current_user_id();

		$view    = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : 'active';
		$chat_id = isset( $_GET['chat_id'] ) ? intval( $_GET['chat_id'] ) : 0;

		$active_chats   = $this->get_user_chats( $user_id, false );
		$archived_chats = $this->get_user_chats( $user_id, true );

		// One query each for the previews and the unread markers, instead of
		// loading every message of every chat for the lists.
		$list_chat_ids = array_map(
			static function ( $chat ) {
				return (int) $chat->ID;
			},
			array_merge( $active_chats, $archived_chats )
		);
		$last_messages = ChatMessages::last_messages( $list_chat_ids );
		$unread_counts = ChatMessages::unread_counts( $list_chat_ids, $user_id );

		$open_chat = null;

		if ( $chat_id && $this->can_view_chat( $chat_id, $user_id ) ) {
			// Read after the counts above, so the chat being opened still
			// carries its unread marker in the list on this one render.
			$this->mark_as_read( $chat_id, $user_id );
			$open_chat = $this->open_chat_data( $chat_id, $user_id );
		}

		return [
			'current_user_id' => $user_id,
			'view'            => $view,
			'chat_id'         => $chat_id,
			'active_count'    => count( $active_chats ),
			'archived_count'  => count( $archived_chats ),
			// Previews only for the active list — the archived list does not
			// show one, and building it costs a payment-card lookup per chat.
			'active_rows'     => $this->chat_rows( $active_chats, $user_id, $last_messages, $unread_counts, true ),
			'archived_rows'   => $this->chat_rows( $archived_chats, $user_id, $last_messages, $unread_counts, false ),
			'open_chat'       => $open_chat,
		];
	}

	/**
	 * May this user open the chat? Participant, and not deleted by them.
	 *
	 * @param int $chat_id
	 * @param int $user_id
	 * @return bool
	 */
	public function can_view_chat( $chat_id, $user_id ) {
		return $this->is_participant( $chat_id, $user_id )
			&& ! self::is_deleted_for_user( $chat_id, $user_id );
	}

	/**
	 * One sidebar entry per chat.
	 *
	 * @param \WP_Post[] $chats
	 * @param int        $user_id
	 * @param array      $last_messages [ chat_id => message ]
	 * @param array      $unread_counts [ chat_id => count ]
	 * @param bool       $with_preview  Build the message preview line.
	 * @return array
	 */
	private function chat_rows( array $chats, $user_id, array $last_messages, array $unread_counts, $with_preview ): array {
		$rows = [];

		foreach ( $chats as $chat ) {
			$id            = (int) $chat->ID;
			$other_user_id = $this->get_other_participant( $id, $user_id );
			$product_id    = get_post_meta( $id, '_dvc_product_id', true );
			$last_message  = $last_messages[ $id ] ?? null;

			$preview = null;
			if ( $with_preview && $last_message ) {
				$prepared = self::prepare_message( $last_message, $id );
				$preview  = $prepared['text'];
			}

			$rows[] = [
				'id'            => $id,
				'other_user_id' => $other_user_id,
				'display_name'  => $this->display_name_for( $other_user_id ),
				'product_title' => get_the_title( $product_id ),
				'timestamp'     => $last_message ? (int) $last_message['timestamp'] : null,
				'preview'       => $preview,
				'unread'        => ! empty( $unread_counts[ $id ] ),
			];
		}

		return $rows;
	}

	/**
	 * Header, actions and message history of the opened chat.
	 *
	 * Only called once the caller has confirmed the user may view it.
	 *
	 * @param int $chat_id
	 * @param int $user_id
	 * @return array
	 */
	private function open_chat_data( $chat_id, $user_id ): array {
		$chat_id       = (int) $chat_id;
		$other_user_id = $this->get_other_participant( $chat_id, $user_id );
		$product_id    = get_post_meta( $chat_id, '_dvc_product_id', true );
		$archived_by   = get_post_meta( $chat_id, '_dvc_archived_by', true ) ?: [];

		$messages = [];
		foreach ( $this->get_messages( $chat_id ) as $message ) {
			// Payment markers never reach the reader as raw text; the card is
			// rebuilt from verified data (see prepare_message).
			$prepared = self::prepare_message( $message, $chat_id );

			$messages[] = [
				'user_id'   => $message['user_id'],
				'is_own'    => $message['user_id'] == $user_id,
				'name'      => $this->display_name_for( $message['user_id'] ),
				'timestamp' => $message['timestamp'],
				'text'      => $prepared['text'],
				'card'      => $prepared['card'],
			];
		}

		return [
			'id'            => $chat_id,
			'other_user_id' => $other_user_id,
			'display_name'  => $this->display_name_for( $other_user_id ),
			'product_title' => get_the_title( $product_id ),
			'product_url'   => get_permalink( $product_id ),
			'is_archived'   => in_array( $user_id, (array) $archived_by ),
			'messages'      => $messages,
		];
	}

	/**
	 * Store name if the user runs a shop, display name otherwise.
	 *
	 * @param int $user_id
	 * @return string
	 */
	private function display_name_for( $user_id ) {
		$store_info = sk_get_store_info( $user_id );

		if ( ! empty( $store_info['store_name'] ) ) {
			return $store_info['store_name'];
		}

		$user = get_userdata( $user_id );

		return $user ? $user->display_name : '';
	}

	// =========================================================================
	// Assets
	// =========================================================================

	/**
	 * Enqueue CSS + JS on every frontend page (chat modal needed on product pages).
	 *
	 */
	public function enqueue_assets() {
		$css_url = SK_CORE_ASSETS . '/css/sk-vendor-chat.css';
		$js_url  = SK_CORE_ASSETS . '/js/sk-vendor-chat.js';
		$css_path = SK_CORE_DIR . '/assets/css/sk-vendor-chat.css';
		$js_path  = SK_CORE_DIR . '/assets/js/sk-vendor-chat.js';

		$css_ver = file_exists( $css_path ) ? filemtime( $css_path ) : SK_CORE_VERSION;
		$js_ver  = file_exists( $js_path )  ? filemtime( $js_path )  : SK_CORE_VERSION;

		// CSS is in sk-theme.css (consolidated).
		wp_enqueue_script( 'sk-vendor-chat', $js_url, [ 'jquery' ], $js_ver, true );

		wp_localize_script( 'sk-vendor-chat', 'dvcAjax', [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'dvc_ajax_nonce' ),
		] );
	}

	// =========================================================================
	// Product-page modal
	// =========================================================================

	/**
	 * Output chat modals in wp_footer (product/shop/archive pages only).
	 *
	 */
	public function output_modal() {
		if ( ! self::is_enabled() ) {
			return;
		}

		if ( ! ( is_product() || is_shop() || is_product_category() || is_product_tag() || sk_is_store_page() ) ) {
			return;
		}

		$is_logged_in = is_user_logged_in();
		?>
		<div id="dvc-login-required-modal" class="dvc-modal" style="display: none;">
			<div class="dvc-modal-content">
				<div class="dvc-modal-header">
					<h3><?php _e( 'Anmeldung erforderlich', 'sk-core' ); ?></h3>
					<button type="button" class="dvc-modal-close" aria-label="<?php esc_attr_e( 'Schließen', 'sk-core' ); ?>">
						<i class="fas fa-times"></i>
					</button>
				</div>
				<div class="dvc-modal-body">
					<div class="dvc-message-info" style="margin: 0;">
						<p style="margin: 0;">
							<?php _e( 'Um den Chat nutzen zu können, musst du angemeldet sein.', 'sk-core' ); ?>
						</p>
					</div>
					<div class="dvc-modal-actions" style="margin-top: 1.5rem;">
						<a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" class="dvc-btn-primary">
							<i class="fas fa-sign-in-alt"></i>
							<?php _e( 'Anmelden', 'sk-core' ); ?>
						</a>
					</div>
				</div>
			</div>
		</div>

		<?php if ( $is_logged_in ) : ?>
		<div id="dvc-chat-modal" class="dvc-modal" style="display: none;">
			<div class="dvc-modal-content">
				<div class="dvc-modal-header">
					<h3><?php _e( 'Chat starten', 'sk-core' ); ?></h3>
					<button type="button" class="dvc-modal-close" aria-label="<?php esc_attr_e( 'Schließen', 'sk-core' ); ?>">
						<i class="fas fa-times"></i>
					</button>
				</div>
				<div class="dvc-modal-body">
					<form id="dvc-start-chat-form">
						<input type="hidden" id="dvc-vendor-id"  name="vendor_id">
						<input type="hidden" id="dvc-product-id" name="product_id">
						<div class="dvc-form-group">
							<label for="dvc-chat-message"><?php _e( 'Nachricht', 'sk-core' ); ?></label>
							<textarea
								id="dvc-chat-message"
								name="message"
								rows="5"
								placeholder="<?php esc_attr_e( 'Schreibe deine Nachricht...', 'sk-core' ); ?>"
								required
							></textarea>
						</div>
						<div class="dvc-modal-actions">
							<button type="submit" class="dvc-btn-primary">
								<i class="fas fa-paper-plane"></i>
								<?php _e( 'Senden', 'sk-core' ); ?>
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php endif; ?>
		<?php
	}

	// =========================================================================
	// Admin settings
	// =========================================================================

	/**
	 * Add options page under Settings menu.
	 *
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'SK Vendor Chat Einstellungen', 'sk-core' ),
			__( 'Vendor Chat', 'sk-core' ),
			'manage_options',
			'sk-vendor-chat-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Register dvc_enabled setting.
	 *
	 */
	public function register_settings() {
		register_setting( 'dvc_settings', 'dvc_enabled' );
	}

	/**
	 * Render the admin settings page.
	 *
	 */
	public function render_settings_page() {
		wp_enqueue_style( 'sk-vendor-chat-settings' );

		if ( isset( $_POST['dvc_save_settings'] ) && check_admin_referer( 'dvc_settings_nonce' ) ) {
			$enabled = isset( $_POST['dvc_enabled'] ) ? 'yes' : 'no';
			update_option( 'dvc_enabled', $enabled );
			echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Einstellungen gespeichert.', 'sk-core' ) . '</p></div>';
		}

		$enabled      = get_option( 'dvc_enabled', 'no' ) === 'yes';
		$total_chats  = wp_count_posts( 'vendor_chat' );
		$active_chats = isset( $total_chats->publish ) ? $total_chats->publish : 0;
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div style="max-width:1200px;">
				<form method="post" action="">
					<?php wp_nonce_field( 'dvc_settings_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="dvc_enabled"><?php _e( 'Chat-System Status', 'sk-core' ); ?></label>
							</th>
							<td>
								<label class="dvc-toggle-switch">
									<input type="checkbox" id="dvc_enabled" name="dvc_enabled" value="yes" <?php checked( $enabled, true ); ?>>
									<span class="dvc-toggle-slider"></span>
								</label>
								<p class="description">
									<?php if ( $enabled ) : ?>
										<strong style="color:#46b450;">✓ <?php _e( 'Chat-System ist aktiviert', 'sk-core' ); ?></strong><br>
										<?php _e( 'Das Chat-Icon wird bei allen Produkten angezeigt (für eingeloggte Nutzer).', 'sk-core' ); ?>
									<?php else : ?>
										<strong style="color:#dc3232;">✗ <?php _e( 'Chat-System ist deaktiviert', 'sk-core' ); ?></strong><br>
										<?php _e( 'Das Chat-Icon wird nicht angezeigt. Bestehende Chats bleiben erhalten.', 'sk-core' ); ?>
									<?php endif; ?>
								</p>
							</td>
						</tr>
					</table>
					<p class="submit">
						<button type="submit" name="dvc_save_settings" class="button button-primary button-large">
							<?php _e( 'Einstellungen speichern', 'sk-core' ); ?>
						</button>
					</p>
				</form>
				<hr>
				<div style="background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px;margin-top:20px;">
					<h2><?php _e( 'Statistiken', 'sk-core' ); ?></h2>
					<p><strong><?php _e( 'Aktive Chats:', 'sk-core' ); ?></strong> <?php echo esc_html( $active_chats ); ?></p>
					<p><strong><?php _e( 'Plugin Version:', 'sk-core' ); ?></strong> <?php echo esc_html( SK_CORE_VERSION ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	// =========================================================================
	// Contact icon
	// =========================================================================

	/**
	 * Inject chat icon into the contact-icons collection.
	 *
	 *
	 * @param array $icons
	 * @param int   $vendor_id
	 * @param int   $product_id
	 * @param string $context
	 * @return array
	 */
	public function add_chat_icon( $icons, $vendor_id, $product_id = 0, $context = '' ) {
		if ( ! self::is_enabled() ) {
			return $icons;
		}

		$current_user_id = get_current_user_id();
		$is_logged_in    = is_user_logged_in();

		// Don't show to the vendor on their own product
		if ( $is_logged_in && $current_user_id == $vendor_id ) {
			return $icons;
		}

		$icons[] = [
			'href'  => '#',
			'title' => __( 'Chat starten', 'sk-core' ),
			'class' => 'fa-solid fa-comments dvc-start-chat-icon',
			'key'   => 'chat',
			'data'  => [
				'vendor-id'  => $vendor_id,
				'product-id' => $product_id,
				'logged-in'  => $is_logged_in ? '1' : '0',
			],
		];

		return $icons;
	}

	// =========================================================================
	// AJAX handlers
	// =========================================================================

	/**
	 * AJAX: start a new chat or add a message to an existing one.
	 *
	 */
	public function ajax_start_chat() {
		check_ajax_referer( 'dvc_ajax_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'Du musst angemeldet sein.', 'sk-core' ) ] );
		}

		$current_user_id = get_current_user_id();
		$vendor_id       = isset( $_POST['vendor_id'] )  ? intval( $_POST['vendor_id'] )  : 0;
		$product_id      = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		$message         = isset( $_POST['message'] )
			? self::sanitize_user_message( sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) )
			: '';

		if ( ! $vendor_id || ! $product_id ) {
			wp_send_json_error( [ 'message' => __( 'Ungültige Anfrage.', 'sk-core' ) ] );
		}

		if ( empty( $message ) ) {
			wp_send_json_error( [ 'message' => __( 'Bitte gib eine Nachricht ein.', 'sk-core' ) ] );
		}

		$existing_chat = $this->get_chat_between_users( $current_user_id, $vendor_id, $product_id );

		if ( $existing_chat ) {
			$this->add_message_to_chat( $existing_chat->ID, $current_user_id, $message );
			wp_send_json_success( [
				'message' => __( 'Nachricht gesendet!', 'sk-core' ),
				'chat_id' => $existing_chat->ID,
			] );
		} else {
			$chat_title = sprintf( __( 'Chat über: %s', 'sk-core' ), get_the_title( $product_id ) );
			$chat_id    = wp_insert_post( [
				'post_type'   => 'vendor_chat',
				'post_title'  => $chat_title,
				'post_status' => 'publish',
				'post_author' => $current_user_id,
			] );

			if ( is_wp_error( $chat_id ) ) {
				wp_send_json_error( [ 'message' => __( 'Fehler beim Erstellen des Chats.', 'sk-core' ) ] );
			}

			update_post_meta( $chat_id, '_dvc_participant_1', $current_user_id );
			update_post_meta( $chat_id, '_dvc_participant_2', $vendor_id );
			update_post_meta( $chat_id, '_dvc_product_id',    $product_id );
			update_post_meta( $chat_id, '_dvc_archived_by',   [] );

			$this->add_message_to_chat( $chat_id, $current_user_id, $message );

			wp_send_json_success( [
				'message' => __( 'Chat erstellt und Nachricht gesendet!', 'sk-core' ),
				'chat_id' => $chat_id,
			] );
		}
	}

	/**
	 * AJAX: send a message to an existing chat.
	 *
	 */
	public function ajax_send_message() {
		check_ajax_referer( 'dvc_ajax_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'Du musst angemeldet sein.', 'sk-core' ) ] );
		}

		$current_user_id = get_current_user_id();
		$chat_id         = isset( $_POST['chat_id'] ) ? intval( $_POST['chat_id'] ) : 0;
		$message         = isset( $_POST['message'] )
			? self::sanitize_user_message( sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) )
			: '';

		if ( ! $chat_id || empty( $message ) ) {
			wp_send_json_error( [ 'message' => __( 'Ungültige Anfrage.', 'sk-core' ) ] );
		}

		if ( ! $this->is_participant( $chat_id, $current_user_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Du bist kein Teilnehmer dieses Chats.', 'sk-core' ) ] );
		}

		$this->add_message_to_chat( $chat_id, $current_user_id, $message );

		$other_user_id = $this->get_other_participant( $chat_id, $current_user_id );
		if ( $other_user_id ) {

			// Mirror to Nostr DM if both users have Nostr identities.
			if ( class_exists( 'SK\Modules\Auth\NostrIdentity' ) && class_exists( 'SK\Modules\NostrMarket\Bridge\ChatBridge' ) ) {
				$recipient_pubkey = \SK\Modules\Auth\NostrIdentity::get_public_key( $other_user_id );
				if ( $recipient_pubkey && \SK\Modules\Auth\NostrIdentity::has_identity( $current_user_id ) ) {
					register_shutdown_function( function () use ( $recipient_pubkey, $message, $current_user_id ) {
						\SK\Modules\NostrMarket\Bridge\ChatBridge::send_dm( $recipient_pubkey, $message, $current_user_id );
					} );
				}
			}
		}

		wp_send_json_success( [ 'message' => __( 'Nachricht gesendet!', 'sk-core' ) ] );
	}

	/**
	 * AJAX: get messages for a chat.
	 *
	 */
	public function ajax_get_messages() {
		check_ajax_referer( 'dvc_ajax_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'Du musst angemeldet sein.', 'sk-core' ) ] );
		}

		$current_user_id = get_current_user_id();
		$chat_id         = isset( $_POST['chat_id'] ) ? intval( $_POST['chat_id'] ) : 0;

		if ( ! $chat_id ) {
			wp_send_json_error( [ 'message' => __( 'Ungültige Anfrage.', 'sk-core' ) ] );
		}

		if ( ! $this->is_participant( $chat_id, $current_user_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Du bist kein Teilnehmer dieses Chats.', 'sk-core' ) ] );
		}

		$this->mark_as_read( $chat_id, $current_user_id );

		// Enrich messages with display_name + avatar for JS rendering, and
		// replace payment markers with server-verified card data.
		$messages = $this->get_messages( $chat_id );
		foreach ( $messages as &$msg ) {
			$user = get_userdata( $msg['user_id'] );
			$store_info = sk_get_store_info( $msg['user_id'] );
			$msg['display_name'] = ( ! empty( $store_info['store_name'] ) )
				? $store_info['store_name']
				: ( $user ? $user->display_name : '' );
			$msg['avatar'] = get_avatar_url( $msg['user_id'], [ 'size' => 32 ] );

			$prepared       = self::prepare_message( $msg, $chat_id );
			$msg['message'] = $prepared['text'];
			$msg['card']    = $prepared['card'];
		}
		unset( $msg );

		wp_send_json_success( [ 'messages' => $messages ] );
	}

	/**
	 * AJAX: permanently delete a chat.
	 *
	 */
	public function ajax_delete_chat() {
		check_ajax_referer( 'dvc_ajax_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'Du musst angemeldet sein.', 'sk-core' ) ] );
		}

		$current_user_id = get_current_user_id();
		$chat_id         = isset( $_POST['chat_id'] ) ? intval( $_POST['chat_id'] ) : 0;

		if ( ! $chat_id ) {
			wp_send_json_error( [ 'message' => __( 'Ungültige Anfrage.', 'sk-core' ) ] );
		}

		if ( ! $this->is_participant( $chat_id, $current_user_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Du bist kein Teilnehmer dieses Chats.', 'sk-core' ) ] );
		}

		$this->delete_chat_for_user( $chat_id, $current_user_id );

		wp_send_json_success( [ 'message' => __( 'Chat gelöscht.', 'sk-core' ) ] );
	}

	/**
	 * AJAX: archive a chat for the current user.
	 *
	 */
	public function ajax_archive_chat() {
		check_ajax_referer( 'dvc_ajax_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'Du musst angemeldet sein.', 'sk-core' ) ] );
		}

		$current_user_id = get_current_user_id();
		$chat_id         = isset( $_POST['chat_id'] ) ? intval( $_POST['chat_id'] ) : 0;

		if ( ! $chat_id ) {
			wp_send_json_error( [ 'message' => __( 'Ungültige Anfrage.', 'sk-core' ) ] );
		}

		if ( ! $this->is_participant( $chat_id, $current_user_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Du bist kein Teilnehmer dieses Chats.', 'sk-core' ) ] );
		}

		$this->archive_chat( $chat_id, $current_user_id );
		wp_send_json_success( [ 'message' => __( 'Chat archiviert.', 'sk-core' ) ] );
	}

	/**
	 * AJAX: unarchive a chat for the current user.
	 *
	 */
	public function ajax_unarchive_chat() {
		check_ajax_referer( 'dvc_ajax_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'Du musst angemeldet sein.', 'sk-core' ) ] );
		}

		$current_user_id = get_current_user_id();
		$chat_id         = isset( $_POST['chat_id'] ) ? intval( $_POST['chat_id'] ) : 0;

		if ( ! $chat_id ) {
			wp_send_json_error( [ 'message' => __( 'Ungültige Anfrage.', 'sk-core' ) ] );
		}

		if ( ! $this->is_participant( $chat_id, $current_user_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Du bist kein Teilnehmer dieses Chats.', 'sk-core' ) ] );
		}

		$this->unarchive_chat( $chat_id, $current_user_id );
		wp_send_json_success( [ 'message' => __( 'Chat wiederhergestellt.', 'sk-core' ) ] );
	}

	// =========================================================================
	// Helper methods (also exposed as standalone functions at EOF)
	// =========================================================================

	/**
	 * Check if chat system is enabled.
	 *
	 *
	 * @return bool
	 */
	/**
	 * Is the chat feature switched on?
	 *
	 * Static because other modules gate behaviour on it — a vendor without
	 * public contact details is still reachable while the chat is running.
	 */
	public static function is_enabled(): bool {
		return get_option( 'dvc_enabled', 'no' ) === 'yes';
	}

	/**
	 * Strip payment markers from user supplied message text.
	 *
	 * Payment cards ([lightning_invoice], [onchain_payment], …) are rendered
	 * from verified data by PaymentCard. Nobody may type one and have it show
	 * up as a real invoice, QR code or payment confirmation.
	 *
	 *
	 * @param string $message
	 * @return string
	 */
	public static function sanitize_user_message( $message ) {
		$message = (string) $message;

		// Superset of the markers PaymentCard knows, so new card types stay covered.
		$pattern = '#\[/?(?:lightning_[a-z_]+|onchain_[a-z_]+)\]#i';

		if ( ! preg_match( $pattern, $message ) ) {
			return $message;
		}

		// Whole blocks first, then any leftover tag.
		$message = preg_replace( '#\[(lightning_[a-z_]+|onchain_[a-z_]+)\].*?\[/\1\]#is', '', $message );
		$message = preg_replace( $pattern, '', (string) $message );

		// Collapse the gap the removed block left behind, like
		// PaymentCard::strip_markers() does. Only reached when a marker was
		// present, so ordinary messages keep their spacing.
		$message = preg_replace( '/[ \t]+/', ' ', (string) $message );

		return trim( (string) $message );
	}

	/**
	 * Build the render data for one chat message.
	 *
	 * Returns the text to display plus — only if the message references a
	 * payment that really belongs to this chat — the verified card data. Raw
	 * marker text never reaches the reader.
	 *
	 *
	 * @param array $message
	 * @param int   $chat_id
	 * @return array
	 */
	public static function prepare_message( $message, $chat_id ) {
		$message = (array) $message;
		$text    = isset( $message['message'] ) ? (string) $message['message'] : '';

		if ( ! class_exists( '\SK\Modules\Payments\Chat\PaymentCard' ) ) {
			$stripped = self::sanitize_user_message( $text );
			return [
				'text' => $stripped !== '' ? $stripped : __( '⚡ Zahlungsnachricht', 'sk-core' ),
				'card' => null,
			];
		}

		$card = \SK\Modules\Payments\Chat\PaymentCard::build( $message, (int) $chat_id );

		return [
			'text' => $card
				? __( '⚡ Zahlungsnachricht', 'sk-core' )
				: \SK\Modules\Payments\Chat\PaymentCard::to_display_text( $text ),
			'card' => $card,
		];
	}

	/**
	 * Find an existing chat between two users for a specific product.
	 *
	 *
	 * @param int $user1_id
	 * @param int $user2_id
	 * @param int $product_id
	 * @return \WP_Post|null
	 */
	public function get_chat_between_users( $user1_id, $user2_id, $product_id ) {
		$chats = get_posts( [
			'post_type'   => 'vendor_chat',
			'post_status' => 'publish',
			'meta_query'  => [
				'relation' => 'AND',
				[
					'key'     => '_dvc_product_id',
					'value'   => $product_id,
					'compare' => '=',
				],
				[
					'relation' => 'OR',
					[
						'relation' => 'AND',
						[ 'key' => '_dvc_participant_1', 'value' => $user1_id, 'compare' => '=' ],
						[ 'key' => '_dvc_participant_2', 'value' => $user2_id, 'compare' => '=' ],
					],
					[
						'relation' => 'AND',
						[ 'key' => '_dvc_participant_1', 'value' => $user2_id, 'compare' => '=' ],
						[ 'key' => '_dvc_participant_2', 'value' => $user1_id, 'compare' => '=' ],
					],
				],
			],
		] );

		return ! empty( $chats ) ? $chats[0] : null;
	}

	/**
	 * Append a message to a chat.
	 *
	 *
	 * @param int    $chat_id
	 * @param int    $user_id
	 * @param string $message
	 */
	public function add_message_to_chat( $chat_id, $user_id, $message ) {
		// Never let user text carry a payment marker (see sanitize_user_message).
		$message = self::sanitize_user_message( $message );

		ChatMessages::append( (int) $chat_id, (int) $user_id, $message );
	}

	/**
	 * Return all messages for a chat.
	 *
	 *
	 * @param int $chat_id
	 * @return array
	 */
	public function get_messages( $chat_id ) {
		return ChatMessages::all( (int) $chat_id );
	}

	/**
	 * Check if a user is a participant in a chat.
	 *
	 *
	 * @param int $chat_id
	 * @param int $user_id
	 * @return bool
	 */
	public function is_participant( $chat_id, $user_id ) {
		$p1 = get_post_meta( $chat_id, '_dvc_participant_1', true );
		$p2 = get_post_meta( $chat_id, '_dvc_participant_2', true );
		return ( $p1 == $user_id || $p2 == $user_id );
	}

	/**
	 * Return the other participant's user ID.
	 *
	 *
	 * @param int $chat_id
	 * @param int $user_id
	 * @return int|null
	 */
	public function get_other_participant( $chat_id, $user_id ) {
		$p1 = get_post_meta( $chat_id, '_dvc_participant_1', true );
		$p2 = get_post_meta( $chat_id, '_dvc_participant_2', true );

		if ( $p1 == $user_id ) {
			return $p2;
		} elseif ( $p2 == $user_id ) {
			return $p1;
		}

		return null;
	}

	/**
	 * Return all chats for a user, filtered by archived state.
	 *
	 *
	 * @param int  $user_id
	 * @param bool $archived
	 * @return \WP_Post[]
	 */
	public function get_user_chats( $user_id, $archived = false ) {
		$all_chats = get_posts( [
			'post_type'      => 'vendor_chat',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'meta_value_num',
			'meta_key'       => '_dvc_last_message_time',
			'order'          => 'DESC',
			'meta_query'     => [
				'relation' => 'OR',
				[ 'key' => '_dvc_participant_1', 'value' => $user_id, 'compare' => '=' ],
				[ 'key' => '_dvc_participant_2', 'value' => $user_id, 'compare' => '=' ],
			],
		] );

		$filtered = [];
		foreach ( $all_chats as $chat ) {
			if ( self::is_deleted_for_user( $chat->ID, $user_id ) ) {
				continue;
			}

			$archived_by = get_post_meta( $chat->ID, '_dvc_archived_by', true );
			$archived_by = is_array( $archived_by ) ? $archived_by : [];
			$is_archived = in_array( $user_id, $archived_by );

			if ( $archived === $is_archived ) {
				$filtered[] = $chat;
			}
		}

		return $filtered;
	}

	/**
	 * Hide a chat for one user.
	 *
	 * Deleting used to remove the post for both sides, which let a scammer wipe
	 * the whole payment conversation from their counterpart's dashboard. The
	 * chat is now only hidden, and removed for good once both participants have
	 * deleted it.
	 *
	 *
	 * @param int $chat_id
	 * @param int $user_id
	 */
	public function delete_chat_for_user( $chat_id, $user_id ) {
		$deleted_by   = get_post_meta( $chat_id, '_dvc_deleted_by', true );
		$deleted_by   = is_array( $deleted_by ) ? array_map( 'intval', $deleted_by ) : [];
		$deleted_by[] = (int) $user_id;
		$deleted_by   = array_values( array_unique( $deleted_by ) );

		update_post_meta( $chat_id, '_dvc_deleted_by', $deleted_by );

		$p1 = (int) get_post_meta( $chat_id, '_dvc_participant_1', true );
		$p2 = (int) get_post_meta( $chat_id, '_dvc_participant_2', true );

		if ( in_array( $p1, $deleted_by, true ) && in_array( $p2, $deleted_by, true ) ) {
			wp_delete_post( $chat_id, true );
		}
	}

	/**
	 * Drop a chat's messages when its post is deleted.
	 *
	 * Post meta goes away with the post, rows in our own table do not.
	 *
	 *
	 * @param int $post_id
	 */
	public function delete_chat_messages( $post_id ) {
		if ( get_post_type( $post_id ) !== 'vendor_chat' ) {
			return;
		}

		$post_id = (int) $post_id;

		ChatMessages::forget_read_marker( $post_id, [
			(int) get_post_meta( $post_id, '_dvc_participant_1', true ),
			(int) get_post_meta( $post_id, '_dvc_participant_2', true ),
		] );

		ChatMessages::delete_for_chat( $post_id );
	}

	/**
	 * Has this user deleted the chat from their own dashboard?
	 *
	 *
	 * @param int $chat_id
	 * @param int $user_id
	 * @return bool
	 */
	public static function is_deleted_for_user( $chat_id, $user_id ) {
		$deleted_by = get_post_meta( $chat_id, '_dvc_deleted_by', true );
		$deleted_by = is_array( $deleted_by ) ? array_map( 'intval', $deleted_by ) : [];

		return in_array( (int) $user_id, $deleted_by, true );
	}

	/**
	 * Add user to a chat's archived_by list.
	 *
	 *
	 * @param int $chat_id
	 * @param int $user_id
	 */
	public function archive_chat( $chat_id, $user_id ) {
		$archived_by   = get_post_meta( $chat_id, '_dvc_archived_by', true );
		$archived_by   = is_array( $archived_by ) ? $archived_by : [];
		$archived_by[] = $user_id;
		$archived_by   = array_unique( $archived_by );
		update_post_meta( $chat_id, '_dvc_archived_by', $archived_by );
	}

	/**
	 * Remove user from a chat's archived_by list.
	 *
	 *
	 * @param int $chat_id
	 * @param int $user_id
	 */
	public function unarchive_chat( $chat_id, $user_id ) {
		$archived_by = get_post_meta( $chat_id, '_dvc_archived_by', true );
		$archived_by = is_array( $archived_by ) ? $archived_by : [];
		$archived_by = array_values( array_diff( $archived_by, [ $user_id ] ) );
		update_post_meta( $chat_id, '_dvc_archived_by', $archived_by );
	}

	/**
	 * Number of unread messages across all of a user's chats.
	 *
	 * Derived from the messages table and the per-chat read markers, so nothing
	 * has to be tracked per received message.
	 *
	 *
	 * @param int $user_id
	 * @return int
	 */
	public function get_unread_count( $user_id ) {
		$chat_ids = ChatMessages::chat_ids_for_participant( (int) $user_id );

		if ( empty( $chat_ids ) ) {
			return 0;
		}

		return (int) array_sum( ChatMessages::unread_counts( $chat_ids, (int) $user_id ) );
	}

	/**
	 * Mark everything currently in the chat as read for this user.
	 *
	 *
	 * @param int $chat_id
	 * @param int $user_id
	 */
	public function mark_as_read( $chat_id, $user_id ) {
		ChatMessages::mark_read( (int) $chat_id, (int) $user_id );
	}
}

