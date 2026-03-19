<?php

namespace SK\Core\Dashboard\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * User Onboarding — welcome modal for first-time users.
 *
 * Ported from plugin: user-onboarding
 */
class UserOnboarding {

	public function __construct() {
		add_action( 'user_register', [ $this, 'mark_for_onboarding' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_footer', [ $this, 'render_modal' ] );
		add_action( 'wp_ajax_uob_complete_onboarding', [ $this, 'ajax_complete' ] );
		add_action( 'deleted_user', [ $this, 'cleanup' ] );
		add_action( 'admin_menu', [ $this, 'add_admin_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	// ── Helpers ────────────────────────────────────────────────────────────

	private function is_enabled(): bool {
		return get_option( 'uob_enabled', 'no' ) === 'yes';
	}

	private function should_show( int $user_id ): bool {
		return get_user_meta( $user_id, 'uob_show_onboarding', true ) === 'yes';
	}

	// ── Registration Hook ──────────────────────────────────────────────────

	public function mark_for_onboarding( $user_id ): void {
		if ( $this->is_enabled() ) {
			update_user_meta( $user_id, 'uob_show_onboarding', 'yes' );
		}
	}

	// ── Assets ─────────────────────────────────────────────────────────────

	public function enqueue_assets(): void {
		if ( ! $this->is_enabled() || ! is_user_logged_in() ) {
			return;
		}
		if ( ! $this->should_show( get_current_user_id() ) ) {
			return;
		}

		wp_enqueue_style( 'sk-onboarding', plugins_url( 'assets/css/sk-onboarding.css', SK_CORE_FILE ), [], SK_CORE_VERSION );
		wp_enqueue_script( 'sk-onboarding', plugins_url( 'assets/js/sk-onboarding.js', SK_CORE_FILE ), [ 'jquery' ], SK_CORE_VERSION, true );
		wp_localize_script( 'sk-onboarding', 'uobAjax', [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'uob_ajax_nonce' ),
		] );
	}

	// ── Modal Output ───────────────────────────────────────────────────────

	public function render_modal(): void {
		if ( ! $this->is_enabled() || ! is_user_logged_in() ) {
			return;
		}
		if ( ! $this->should_show( get_current_user_id() ) ) {
			return;
		}

		$user       = wp_get_current_user();
		$first_name = $user->first_name ?: $user->display_name;

		if ( $first_name && stripos( $first_name, 'satoshi-' ) === 0 ) {
			$first_name = '';
		}

		$chat_enabled = function_exists( 'dvc_is_enabled' ) && dvc_is_enabled();
		?>
		<div id="uob-modal" class="uob-modal">
			<div class="uob-modal-content">
				<div class="uob-progress">
					<span class="uob-dot active" data-slide="0"></span>
					<span class="uob-dot" data-slide="1"></span>
					<span class="uob-dot" data-slide="2"></span>
					<span class="uob-dot" data-slide="3"></span>
					<span class="uob-dot" data-slide="4"></span>
				</div>

				<button type="button" class="uob-close" aria-label="<?php esc_attr_e( 'Schließen', 'sk-core' ); ?>">
					<i class="fas fa-times"></i>
				</button>

				<!-- Slide 1: Welcome -->
				<div class="uob-slide active" data-slide="0">
					<div class="uob-slide-icon"><i class="fas fa-hand-peace"></i></div>
					<h2><?php printf( __( 'Willkommen%s!', 'sk-core' ), $first_name ? ', ' . esc_html( $first_name ) : '' ); ?></h2>
					<p><?php _e( 'Schön, dass du hier bist! Wir zeigen dir in wenigen Schritten, wie SatoshisKleinanzeigen funktioniert.', 'sk-core' ); ?></p>
					<p class="uob-subtitle"><?php _e( 'Der Bitcoin-Marktplatz für Deutschland, Österreich und die Schweiz', 'sk-core' ); ?></p>
				</div>

				<!-- Slide 2: How to Buy -->
				<div class="uob-slide" data-slide="1">
					<div class="uob-slide-icon"><i class="fas fa-shopping-cart"></i></div>
					<h2><?php _e( 'Kaufen', 'sk-core' ); ?></h2>
					<ul class="uob-feature-list">
						<li>
							<i class="fas fa-search"></i>
							<div>
								<strong><?php _e( 'Anzeigen durchsuchen', 'sk-core' ); ?></strong>
								<span><?php _e( 'Finde Produkte in verschiedenen ', 'sk-core' ); ?><a href="<?php echo esc_url( home_url( '/kategorien/' ) ); ?>" target="_blank" rel="noopener"><?php _e( 'Kategorien', 'sk-core' ); ?></a><?php _e( ' oder dem ', 'sk-core' ); ?><a href="<?php echo esc_url( home_url( '/shop/' ) ); ?>" target="_blank" rel="noopener"><?php _e( 'Marktplatz', 'sk-core' ); ?></a></span>
							</div>
						</li>
						<li>
							<i class="fas fa-comments"></i>
							<div>
								<strong><?php _e( 'Verkäufer kontaktieren', 'sk-core' ); ?></strong>
								<span><?php echo $chat_enabled ? __( 'Per Chat, Telegram, Email oder Telefon', 'sk-core' ) : __( 'Per Telegram, Email, Telefon oder Nostr', 'sk-core' ); ?></span>
							</div>
						</li>
						<li>
							<i class="fab fa-bitcoin"></i>
							<div>
								<strong><?php _e( 'Mit Bitcoin bezahlen', 'sk-core' ); ?></strong>
								<span><?php _e( 'Bezahlmethode untereinander ausmachen', 'sk-core' ); ?></span>
							</div>
						</li>
					</ul>
				</div>

				<!-- Slide 3: How to Sell -->
				<div class="uob-slide" data-slide="2">
					<h2><?php _e( 'Verkaufen', 'sk-core' ); ?></h2>
					<ul class="uob-feature-list">
						<li>
							<i class="fas fa-user-circle"></i>
							<div>
								<strong><?php echo $chat_enabled ? __( 'Shop-Profil erstellen und Kontaktdaten hinterlegen (Optional)', 'sk-core' ) : __( 'Shop-Profil und Kontaktdaten erstellen', 'sk-core' ); ?></strong>
								<span><?php _e( 'Dashboard → Shop Info', 'sk-core' ); ?></span>
							</div>
						</li>
						<li>
							<i class="fas fa-plus-circle"></i>
							<div>
								<strong><?php _e( 'Anzeigen erstellen', 'sk-core' ); ?></strong>
								<span><?php _e( 'Dashboard → Angebote → Neues Produkt hinzufügen', 'sk-core' ); ?></span>
							</div>
						</li>
						<li>
							<i class="fas fa-check-circle"></i>
							<div>
								<strong><?php _e( 'Produkt vervollständigen', 'sk-core' ); ?></strong>
								<span><?php _e( 'Produktbild, Preis, Versand und Beschreibung hinzufügen, absenden, fertig!', 'sk-core' ); ?></span>
							</div>
						</li>
					</ul>
				</div>

				<!-- Slide 4: Communication -->
				<div class="uob-slide" data-slide="3">
					<div class="uob-slide-icon"><i class="fas fa-comments"></i></div>
					<h2><?php _e( 'Kommunikation', 'sk-core' ); ?></h2>
					<p><?php _e( 'Verkäufer können verschiedene Kontaktmethoden anbieten:', 'sk-core' ); ?></p>
					<div class="uob-contact-icons">
						<?php if ( $chat_enabled ) : ?>
						<div class="uob-contact-method">
							<i class="fas fa-comments uob-icon-chat"></i>
							<span><?php _e( 'Chat', 'sk-core' ); ?></span>
						</div>
						<?php endif; ?>
						<div class="uob-contact-method">
							<i class="fab fa-telegram uob-icon-telegram"></i>
							<span><?php _e( 'Telegram', 'sk-core' ); ?></span>
						</div>
						<div class="uob-contact-method">
							<i class="fas fa-envelope uob-icon-email"></i>
							<span><?php _e( 'E-Mail', 'sk-core' ); ?></span>
						</div>
						<div class="uob-contact-method">
							<i class="fas fa-phone uob-icon-phone"></i>
							<span><?php _e( 'Telefon', 'sk-core' ); ?></span>
						</div>
						<div class="uob-contact-method">
							<i class="fas fa-bolt uob-icon-nostr"></i>
							<span><?php _e( 'Nostr', 'sk-core' ); ?></span>
						</div>
					</div>
					<?php if ( $chat_enabled ) : ?>
					<p class="uob-tip"><i class="fas fa-lightbulb"></i> <?php _e( 'Tipp: Deine Chats findest du im Dashboard unter "Nachrichten"', 'sk-core' ); ?></p>
					<?php else : ?>
					<p class="uob-tip"><i class="fas fa-lightbulb"></i> <?php _e( 'Tipp: Kontaktiere Verkäufer direkt über deren bevorzugte Kommunikationswege', 'sk-core' ); ?></p>
					<?php endif; ?>
					<p class="uob-tip"><i class="fas fa-lightbulb"></i> <?php printf( __( 'Tipp: Folge uns auf <a href="%s" target="_blank" rel="noopener">Nostr</a> oder tritt dem offiziellen <a href="%s" target="_blank" rel="noopener">Telegram Kanal</a> bei', 'sk-core' ), 'https://primal.net/p/nprofile1qqsg3fglunsprjgg0z2efc0qpcshrjkvyksfk9lracjawpuzs0quy8cqxrg92', 'https://t.me/satoshiskleinanzeige' ); ?></p>
				</div>

				<!-- Slide 5: Get Started -->
				<div class="uob-slide" data-slide="4">
					<div class="uob-slide-icon"><i class="fas fa-rocket"></i></div>
					<h2><?php _e( 'Los geht\'s!', 'sk-core' ); ?></h2>
					<p><?php _e( 'Du bist startklar! Hier sind deine nächsten Schritte:', 'sk-core' ); ?></p>
					<div class="uob-action-cards">
						<a href="<?php echo esc_url( home_url( '/shop/' ) ); ?>" class="uob-action-card" target="_blank" rel="noopener">
							<i class="fas fa-search"></i>
							<strong><?php _e( 'Anzeigen durchstöbern', 'sk-core' ); ?></strong>
						</a>
						<a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" class="uob-action-card" target="_blank" rel="noopener">
							<i class="fas fa-tachometer-alt"></i>
							<strong><?php _e( 'Zum Dashboard', 'sk-core' ); ?></strong>
						</a>
						<a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>" class="uob-action-card" target="_blank" rel="noopener">
							<i class="fas fa-question-circle"></i>
							<strong><?php _e( 'FAQ besuchen', 'sk-core' ); ?></strong>
						</a>
					</div>
				</div>

				<div class="uob-navigation">
					<button type="button" class="uob-btn uob-btn-skip"><?php _e( 'Überspringen', 'sk-core' ); ?></button>
					<div class="uob-nav-buttons">
						<button type="button" class="uob-btn uob-btn-prev" style="display:none;">
							<i class="fas fa-arrow-left"></i> <?php _e( 'Zurück', 'sk-core' ); ?>
						</button>
						<button type="button" class="uob-btn uob-btn-next uob-btn-primary">
							<?php _e( 'Weiter', 'sk-core' ); ?> <i class="fas fa-arrow-right"></i>
						</button>
						<button type="button" class="uob-btn uob-btn-finish uob-btn-primary" style="display:none;">
							<?php _e( 'Verstanden!', 'sk-core' ); ?> <i class="fas fa-check"></i>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	// ── AJAX ───────────────────────────────────────────────────────────────

	public function ajax_complete(): void {
		check_ajax_referer( 'uob_ajax_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => 'Not logged in.' ] );
		}

		$user_id = get_current_user_id();
		delete_user_meta( $user_id, 'uob_show_onboarding' );
		update_user_meta( $user_id, 'uob_onboarding_completed', 'yes' );

		wp_send_json_success( [ 'message' => 'Onboarding completed.' ] );
	}

	// ── Cleanup ────────────────────────────────────────────────────────────

	public function cleanup( $user_id ): void {
		delete_user_meta( $user_id, 'uob_show_onboarding' );
		delete_user_meta( $user_id, 'uob_onboarding_completed' );
	}

	// ── Admin Settings ─────────────────────────────────────────────────────

	public function add_admin_page(): void {
		add_options_page(
			__( 'User Onboarding', 'sk-core' ),
			__( 'User Onboarding', 'sk-core' ),
			'manage_options',
			'user-onboarding-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	public function register_settings(): void {
		register_setting( 'uob_settings', 'uob_enabled' );
	}

	public function render_settings_page(): void {
		if ( isset( $_POST['uob_save_settings'] ) && check_admin_referer( 'uob_settings_nonce' ) ) {
			$enabled = isset( $_POST['uob_enabled'] ) ? 'yes' : 'no';
			update_option( 'uob_enabled', $enabled );
			echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Einstellungen gespeichert.', 'sk-core' ) . '</p></div>';
		}

		$enabled = get_option( 'uob_enabled', 'no' ) === 'yes';
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="post" action="">
				<?php wp_nonce_field( 'uob_settings_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="uob_enabled"><?php _e( 'Onboarding-System Status', 'sk-core' ); ?></label></th>
						<td>
							<label>
								<input type="checkbox" id="uob_enabled" name="uob_enabled" value="yes" <?php checked( $enabled ); ?>>
								<?php _e( 'Onboarding-System aktivieren', 'sk-core' ); ?>
							</label>
							<p class="description">
								<?php _e( 'Neue Benutzer sehen nach der Registrierung ein interaktives Onboarding-Modal.', 'sk-core' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Einstellungen speichern', 'sk-core' ), 'primary', 'uob_save_settings' ); ?>
			</form>
		</div>
		<?php
	}
}
