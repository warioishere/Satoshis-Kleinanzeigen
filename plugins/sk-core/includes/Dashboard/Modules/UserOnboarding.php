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
		add_action( 'wp_ajax_sk_create_nostr_identity', [ $this, 'ajax_create_nostr_identity' ] );
		add_action( 'wp_ajax_sk_delete_nostr_identity', [ $this, 'ajax_delete_nostr_identity' ] );
		add_action( 'wp_ajax_sk_get_nostr_nsec', [ $this, 'ajax_get_nostr_nsec' ] );
		add_action( 'deleted_user', [ $this, 'cleanup' ] );
		add_action( 'admin_menu', [ $this, 'add_admin_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );

		// Dashboard banner for existing users who missed onboarding.
		// content_before feuert INNERHALB von .sk-dashboard-wrap, also direkt neben
		// Sidebar und Inhalt. Der Banner wurde dadurch zum dritten Flex-Kind und
		// nahm dem Inhalt die Breite (1440px: Sidebar 220 | Banner 639 | Inhalt 476).
		// inside_before rendert ihn oberhalb des Inhalts, wie den Kontakt-Hinweis
		// aus ContactDetails.
		add_action( 'sk_dashboard_content_inside_before', [ $this, 'nostr_identity_banner' ] );
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

		// Nicht SK_CORE_VERSION: die bleibt zwischen Releases stehen, waehrend
		// sich die Datei aendert — der Browser behielt dann die alte. Wie die
		// uebrigen Assets ueber den juengsten Zeitstempel im assets-Ordner.
		$assets_version = function_exists( 'sk_assets_version' )
			? sk_assets_version( SK_CORE_DIR . '/assets' )
			: SK_CORE_VERSION;

		wp_enqueue_style( 'sk-onboarding', plugins_url( 'assets/css/sk-onboarding.css', SK_CORE_FILE ), [], $assets_version );
		wp_enqueue_script( 'sk-onboarding', plugins_url( 'assets/js/sk-onboarding.js', SK_CORE_FILE ), [ 'jquery' ], $assets_version, true );
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

		?>
		<div id="uob-modal" class="uob-modal">
			<div class="uob-modal-content">
				<div class="uob-progress">
					<span class="uob-dot active" data-slide="0"></span>
					<span class="uob-dot" data-slide="1"></span>
					<span class="uob-dot" data-slide="2"></span>
					<span class="uob-dot" data-slide="3"></span>
					<span class="uob-dot" data-slide="4"></span>
					<span class="uob-dot" data-slide="5"></span>
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
								<strong><?php _e( 'Anbieter anschreiben', 'sk-core' ); ?></strong>
								<span><?php _e( 'Direkt per Chat auf dem Inserat — ohne Umweg über fremde Dienste', 'sk-core' ); ?></span>
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
								<strong><?php _e( 'Shop-Profil erstellen', 'sk-core' ); ?></strong>
								<span><?php _e( 'Dashboard → Shop Info. Anfragen erreichen dich per Chat; eigene Kontaktwege sind freiwillig', 'sk-core' ); ?></span>
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
					<p><?php _e( 'Der Chat ist der direkte Weg zum Anbieter — er steht auf jedem Inserat und du brauchst dafür nichts weiter als dein Konto hier.', 'sk-core' ); ?></p>
					<div class="uob-contact-icons">
						<div class="uob-contact-method uob-contact-method--primary">
							<i class="fas fa-comments uob-icon-chat"></i>
							<span><?php _e( 'Chat', 'sk-core' ); ?></span>
						</div>
					</div>
					<p class="uob-contact-note"><?php _e( 'Manche Anbieter geben zusätzlich eigene Kontaktwege an:', 'sk-core' ); ?></p>
					<div class="uob-contact-icons uob-contact-icons--secondary">
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
					<p class="uob-tip"><i class="fas fa-lightbulb"></i> <?php _e( 'Tipp: Deine Chats findest du im Dashboard unter "Nachrichten"', 'sk-core' ); ?></p>
					<p class="uob-tip"><i class="fas fa-lightbulb"></i> <?php printf( __( 'Tipp: Folge uns auf <a href="%s" target="_blank" rel="noopener">Nostr</a> oder tritt dem offiziellen <a href="%s" target="_blank" rel="noopener">Telegram Kanal</a> bei', 'sk-core' ), 'https://primal.net/p/nprofile1qqsg3fglunsprjgg0z2efc0qpcshrjkvyksfk9lracjawpuzs0quy8cqxrg92', 'https://t.me/satoshiskleinanzeige' ); ?></p>
				</div>

				<!-- Slide 5: Nostr Identity -->
				<?php $has_nostr = ! empty( get_user_meta( get_current_user_id(), 'nostr_public_key', true ) ); ?>
				<div class="uob-slide" data-slide="4" <?php if ( $has_nostr ) echo 'data-skip="true"'; ?>>
					<div class="uob-slide-icon"><i class="fas fa-key"></i></div>
					<h2><?php _e( 'Nostr-Identität erstellen', 'sk-core' ); ?></h2>
					<p><?php _e( 'Wir empfehlen dir, eine Nostr-Identität zu erstellen. Damit werden deine Inserate kryptographisch signiert und deine Reputation nachweisbar.', 'sk-core' ); ?></p>
					<ul class="uob-feature-list">
						<li>
							<i class="fas fa-shield-alt"></i>
							<div>
								<strong><?php _e( 'Scam-Schutz', 'sk-core' ); ?></strong>
								<span><?php _e( 'Deine Reputation ist an deine Identität gebunden', 'sk-core' ); ?></span>
							</div>
						</li>
						<li>
							<i class="fas fa-bolt"></i>
							<div>
								<strong><?php _e( 'Lightning Zaps', 'sk-core' ); ?></strong>
								<span><?php _e( 'Erhalte Lightning-Zahlungen direkt auf dein Profil', 'sk-core' ); ?></span>
							</div>
						</li>
						<li>
							<i class="fas fa-check-double"></i>
							<div>
								<strong><?php _e( 'Verifizierbar', 'sk-core' ); ?></strong>
								<span><?php _e( 'Deine Inserate sind kryptographisch beweisbar deine', 'sk-core' ); ?></span>
							</div>
						</li>
					</ul>
					<?php if ( ! $has_nostr ) : ?>
						<div class="uob-nostr-actions" style="margin-top:16px;text-align:center;">
							<button type="button" class="uob-btn uob-btn-primary" id="uob-create-nostr">
								<i class="fas fa-key"></i> <?php _e( 'Ja, Nostr-Identität erstellen', 'sk-core' ); ?>
							</button>
							<div id="uob-nostr-status" style="margin-top:8px;font-size:13px;color:#5a6a7e;"></div>
						</div>
					<?php else : ?>
						<p style="text-align:center;color:#5cb85c;font-weight:600;"><i class="fas fa-check-circle"></i> <?php _e( 'Nostr-Identität bereits vorhanden!', 'sk-core' ); ?></p>
					<?php endif; ?>
				</div>

				<!-- Slide 6: Get Started -->
				<div class="uob-slide" data-slide="5">
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

		// "Später" auf dem Nostr-Banner — nur Banner dismissen, Onboarding-State nicht verändern.
		if ( ! empty( $_POST['dismiss_nostr'] ) ) {
			update_user_meta( $user_id, 'sk_nostr_banner_dismissed', 1 );
			wp_send_json_success( [ 'message' => 'Banner dismissed.' ] );
		}

		delete_user_meta( $user_id, 'uob_show_onboarding' );
		update_user_meta( $user_id, 'uob_onboarding_completed', 'yes' );

		wp_send_json_success( [ 'message' => 'Onboarding completed.' ] );
	}

	/**
	 * AJAX: Create Nostr identity for current user.
	 */
	public function ajax_create_nostr_identity(): void {
		check_ajax_referer( 'uob_ajax_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => 'Not logged in.' ] );
		}

		$user_id = get_current_user_id();

		// Already has identity?
		if ( \SK\Modules\Auth\NostrIdentity::has_identity( $user_id ) ) {
			wp_send_json_success( [
				'message' => __( 'Nostr-Identität existiert bereits.', 'sk-core' ),
				'npub'    => \SK\Modules\Auth\NostrIdentity::get_npub( $user_id ),
			] );
		}

		try {
			$pubkey = \SK\Modules\Auth\NostrIdentity::create_for_user( $user_id );
			$npub   = \SK\Modules\Auth\NostrIdentity::get_npub( $user_id );

			wp_send_json_success( [
				'message' => __( 'Nostr-Identität erstellt!', 'sk-core' ),
				'npub'    => $npub,
				'pubkey'  => $pubkey,
			] );
		} catch ( \Throwable $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	/**
	 * AJAX: Delete the generated Nostr identity for current user, so they
	 * can re-link with their own browser-extension key via the auth-connector.
	 */
	/**
	 * Hand the Nostr private key to its owner, on request only.
	 *
	 * The key used to be embedded in the page via wp_localize_script, which put it
	 * into the HTML of every visit to the connector page — readable by any other
	 * script on that page and by anything that caches markup. It is now fetched
	 * here, and only ever for the logged in user themselves: the id comes from the
	 * session, never from the request, so there is nothing to tamper with.
	 */
	public function ajax_get_nostr_nsec(): void {
		check_ajax_referer( 'uob_ajax_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'Du musst angemeldet sein.', 'sk-core' ) ], 401 );
		}

		$nsec = \SK\Modules\Auth\NostrIdentity::get_nsec( get_current_user_id() );

		if ( empty( $nsec ) ) {
			wp_send_json_error( [ 'message' => __( 'Fuer diesen Account ist kein Nostr-Schluessel hinterlegt.', 'sk-core' ) ], 404 );
		}

		nocache_headers();
		wp_send_json_success( [ 'nsec' => $nsec ] );
	}

	public function ajax_delete_nostr_identity(): void {
		check_ajax_referer( 'uob_ajax_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => 'Not logged in.' ] );
		}

		$user_id = get_current_user_id();
		\SK\Modules\Auth\NostrIdentity::delete_for_user( $user_id );

		wp_send_json_success( [
			'message' => __( 'Nostr-Identität gelöscht. Du kannst jetzt einen eigenen Nostr-Account über deine Browser-Extension verknüpfen.', 'sk-core' ),
		] );
	}

	/**
	 * Skript und Konfiguration fuer das Anlegen einer Nostr-Identitaet.
	 *
	 * Wird vom Banner und von der Nostr/LN-Link-Seite gebraucht — beide rufen
	 * denselben Endpunkt `sk_create_nostr_identity` mit demselben Nonce.
	 */
	public static function enqueue_nostr_script(): void {
		$js_path = SK_CORE_DIR . '/assets/js/dashboard/nostr-banner.js';

		wp_enqueue_script(
			'sk-nostr-banner',
			SK_CORE_ASSETS . '/js/dashboard/nostr-banner.js',
			[ 'jquery' ],
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : SK_CORE_VERSION,
			true
		);

		wp_localize_script(
			'sk-nostr-banner',
			'uobAjax',
			[
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'uob_ajax_nonce' ),
			]
		);
	}

	/**
	 * Dashboard banner for existing users without Nostr identity.
	 */
	public function nostr_identity_banner(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Auf der Nostr/LN-Link-Seite waere der Banner doppelt: dort steht die
		// Aktion selbst. isset() statt get_query_var(), weil die Rewrite-Regel
		// die Query-Var auf '' setzt und get_query_var() das nicht von "nicht
		// gesetzt" unterscheiden kann.
		global $wp;
		if ( isset( $wp->query_vars['auth-connector'] ) ) {
			return;
		}

		$user_id = get_current_user_id();

		// Only show if onboarding completed but no Nostr identity.
		if ( get_user_meta( $user_id, 'uob_onboarding_completed', true ) !== 'yes' ) {
			return;
		}
		if ( \SK\Modules\Auth\NostrIdentity::has_pubkey( $user_id ) ) {
			return;
		}
		// User hat manuell einen npub in den Store-Settings eingetragen — auch ohne
		// verknüpften Account eine bewusste Nostr-Präsenz, nicht mehr nerven.
		$profile_settings = get_user_meta( $user_id, 'sk_profile_settings', true );
		if ( is_array( $profile_settings ) && ! empty( $profile_settings['nostr'] ) ) {
			return;
		}
		if ( get_user_meta( $user_id, 'sk_nostr_banner_dismissed', true ) ) {
			return;
		}
		// Nur nennen, was auf dieser Installation auch wirklich laeuft. Die
		// Nostr-Module lassen sich einzeln abschalten — auf Live sind
		// sk_nostr_market, sk_zaps und sk_reputation aus, ein Versprechen
		// "deine Inserate erscheinen auf Nostr" waere dort schlicht falsch.
		$benefits = [];

		if ( sk_module_active( 'sk_nostr_market' ) ) {
			$benefits[] = __( 'Inserate auch auf Nostr', 'sk-core' );
		}
		if ( sk_module_active( 'sk_feed' ) ) {
			$benefits[] = __( 'Beiträge auch auf Nostr', 'sk-core' );
		}
		if ( sk_module_active( 'sk_nostr_market' ) ) {
			$benefits[] = __( 'Chat-Nachrichten auch als Nostr-DM', 'sk-core' );
		}
		if ( sk_module_active( 'sk_zaps' ) ) {
			$benefits[] = __( 'Zaps empfangen', 'sk-core' );
		}
		$benefits[] = __( 'Anmeldung mit Nostr', 'sk-core' );

		$connector_url = sk_get_navigation_url( 'auth-connector' );
		?>
		<div class="sk-alert sk-alert-info sk-nostr-banner" id="sk-nostr-banner" style="margin-bottom:16px;display:flex;align-items:center;gap:12px;">
			<div style="flex:1;">
				<strong><i class="fas fa-key"></i> <?php _e( 'Nostr-Identität empfohlen', 'sk-core' ); ?></strong>
				<p style="margin:4px 0 0;font-size:13px;">
					<?php
					printf(
						/* translators: %s: Aufzaehlung der Funktionen, die eine Nostr-Identitaet freischaltet. */
						esc_html__( 'Damit möglich: %s.', 'sk-core' ),
						esc_html( wp_sprintf_l( '%l', $benefits ) )
					);
					?>
				</p>
				<p style="margin:4px 0 0;font-size:13px;">
					<?php
					if ( $connector_url ) {
						printf(
							/* translators: %s: Link zur Seite Nostr/LN Link. */
							wp_kses( __( 'Du hast schon einen Nostr-Account? Dann verknüpfe diesen unter <a href="%s">Nostr/LN Link</a>, statt hier einen neuen anzulegen.', 'sk-core' ), [ 'a' => [ 'href' => [] ] ] ),
							esc_url( $connector_url )
						);
					} else {
						esc_html_e( 'Du hast schon einen Nostr-Account? Dann verknüpfe diesen unter Nostr/LN Link, statt hier einen neuen anzulegen.', 'sk-core' );
					}
					?>
					<?php esc_html_e( 'Ein hier erstellter Schlüssel gehört dir: du kannst ihn exportieren und überall im Nostr-Netz verwenden, nicht nur hier.', 'sk-core' ); ?>
				</p>
			</div>
			<button type="button" class="sk-btn sk-btn-btc sk-btn-sm" id="sk-nostr-banner-create">
				<i class="fas fa-key"></i> <?php _e( 'Erstellen', 'sk-core' ); ?>
			</button>
			<button type="button" class="sk-btn sk-btn-sm" id="sk-nostr-banner-dismiss" style="background:none;border:none;color:#8b949e;font-size:18px;" title="<?php esc_attr_e( 'Später', 'sk-core' ); ?>">
				<i class="fas fa-times"></i>
			</button>
		</div>
		<?php
		self::enqueue_nostr_script();
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
