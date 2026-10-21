<?php

namespace SK\Modules\Auth;

defined( 'ABSPATH' ) || exit;

/**
 * Recovery code — a way back into an account whose login method is gone.
 *
 * Every login method here hangs on a key the user holds: the Lightning
 * wallet, the Nostr key, the Bitcoin address with its password. Lose that
 * and the account is unreachable. The recovery code is a second key, made
 * by us, shown once, and stored only as a hash.
 *
 * A code is 20 characters from a 31-character alphabet, about 99 bits of
 * randomness. That is far beyond guessing, which is why a plain SHA-256
 * is enough to store it and why the code alone identifies the account:
 * the hash is looked up directly, no user name needed.
 *
 * Using a code logs the user in, ends every other session and uses the
 * code up. The user then links a new login method and makes a new code.
 */
class RecoveryCode {

	const META_HASH    = 'sk_recovery_code_hash';
	const META_CREATED = 'sk_recovery_code_created';
	const META_SNOOZE  = 'sk_recovery_banner_snoozed';

	/** No 0/O, 1/I/L — characters people misread when copying by hand. */
	const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
	const LENGTH   = 20;

	const MAX_ATTEMPTS = 5;
	const LOCKOUT      = 900;
	const SNOOZE       = 30 * DAY_IN_SECONDS;

	/** Error of a failed login attempt in this request, shown by the form. */
	private static string $error = '';

	public static function init(): void {
		add_action( 'wp_ajax_sk_recovery_generate', [ __CLASS__, 'ajax_generate' ] );
		add_action( 'wp_ajax_sk_recovery_snooze', [ __CLASS__, 'ajax_snooze' ] );
		add_action( 'template_redirect', [ __CLASS__, 'handle_login' ] );
		add_action( 'sk_dashboard_content_inside_before', [ __CLASS__, 'render_banner' ], 5 );

		add_action( 'edit_user_profile', [ __CLASS__, 'render_admin_section' ] );
		add_action( 'admin_post_sk_recovery_admin_generate', [ __CLASS__, 'handle_admin_generate' ] );
	}

	// ── Code ───────────────────────────────────────────────────────────────

	/**
	 * Make a new code for a user. Replaces any previous one.
	 *
	 * @return string The code, formatted in groups of five. Not stored anywhere.
	 */
	public static function generate( int $user_id ): string {
		$max  = strlen( self::ALPHABET ) - 1;
		$code = '';

		for ( $i = 0; $i < self::LENGTH; $i++ ) {
			$code .= self::ALPHABET[ random_int( 0, $max ) ];
		}

		update_user_meta( $user_id, self::META_HASH, self::hash( $code ) );
		update_user_meta( $user_id, self::META_CREATED, time() );
		delete_user_meta( $user_id, self::META_SNOOZE );

		return implode( '-', str_split( $code, 5 ) );
	}

	public static function has_code( int $user_id ): bool {
		return '' !== (string) get_user_meta( $user_id, self::META_HASH, true );
	}

	public static function created( int $user_id ): int {
		return (int) get_user_meta( $user_id, self::META_CREATED, true );
	}

	/**
	 * Find the account a code belongs to and use the code up.
	 *
	 * @return int User ID, or 0 when the code matches no account.
	 */
	public static function redeem( string $input ): int {
		$code = self::normalize( $input );

		if ( '' === $code ) {
			return 0;
		}

		$users = get_users( [
			'meta_key'   => self::META_HASH,
			'meta_value' => self::hash( $code ),
			'number'     => 2,
			'fields'     => 'ID',
		] );

		if ( 1 !== count( $users ) ) {
			return 0;
		}

		$user_id = (int) $users[0];

		delete_user_meta( $user_id, self::META_HASH );
		delete_user_meta( $user_id, self::META_CREATED );

		return $user_id;
	}

	/**
	 * Upper case, dashes and spaces gone. Empty when it can't be a code.
	 */
	private static function normalize( string $input ): string {
		$code = preg_replace( '/[^A-Z0-9]/', '', strtoupper( $input ) );

		if ( strlen( $code ) !== self::LENGTH || strspn( $code, self::ALPHABET ) !== self::LENGTH ) {
			return '';
		}

		return $code;
	}

	private static function hash( string $code ): string {
		return hash( 'sha256', $code );
	}

	// ── Login ──────────────────────────────────────────────────────────────

	/**
	 * Form on the login page. Rendered inside the [sk_login] panels.
	 */
	public static function render_login_form(): string {
		ob_start();
		?>
		<form method="post" class="btclogin-form sk-recovery-login">
			<?php wp_nonce_field( 'sk_recovery_login', '_sk_recovery_nonce' ); ?>
			<input type="hidden" name="sk_recovery_action" value="login" />

			<p class="sk-recovery-login-intro">
				<?php esc_html_e( 'Wallet oder Schlüssel verloren? Mit deinem Notfall-Schlüssel kommst du wieder in dein Konto. Danach verknüpfst du eine neue Anmeldemethode.', 'sk-core' ); ?>
			</p>

			<?php if ( '' !== self::$error ) : ?>
				<div class="btclogin-error"><?php echo esc_html( self::$error ); ?></div>
			<?php endif; ?>

			<div class="btclogin-field">
				<label for="sk-recovery-code-input"><?php esc_html_e( 'Notfall-Schlüssel', 'sk-core' ); ?></label>
				<input type="text" id="sk-recovery-code-input" name="sk_recovery_code"
				       placeholder="XXXXX-XXXXX-XXXXX-XXXXX" autocomplete="off" autocapitalize="characters"
				       spellcheck="false" required />
			</div>

			<button type="submit" class="btclogin-submit"><?php esc_html_e( 'Anmelden', 'sk-core' ); ?></button>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	public static function has_login_error(): bool {
		return '' !== self::$error;
	}

	/**
	 * Handle the form before any output, so the redirect can still happen.
	 */
	public static function handle_login(): void {
		if ( ( $_POST['sk_recovery_action'] ?? '' ) !== 'login' || is_user_logged_in() ) {
			return;
		}

		// As with the Bitcoin login: logged-out visitors share one nonce, so
		// only the origin check stops a forged login.
		$same_origin = ! function_exists( 'sk_is_same_origin_request' ) || sk_is_same_origin_request();

		if ( ! $same_origin || ! wp_verify_nonce( $_POST['_sk_recovery_nonce'] ?? '', 'sk_recovery_login' ) ) {
			self::$error = __( 'Die Seite war zu lange offen. Bitte lade sie neu und versuche es nochmal.', 'sk-core' );
			return;
		}

		$key = 'sk_recovery_i_' . md5( function_exists( 'sk_get_client_ip' ) ? sk_get_client_ip() : 'unknown' );

		if ( (int) get_transient( $key ) >= self::MAX_ATTEMPTS ) {
			self::$error = __( 'Zu viele Fehlversuche. Bitte in 15 Minuten erneut versuchen.', 'sk-core' );
			return;
		}

		$user_id = self::redeem( (string) wp_unslash( $_POST['sk_recovery_code'] ?? '' ) );

		if ( ! $user_id ) {
			set_transient( $key, (int) get_transient( $key ) + 1, self::LOCKOUT );
			self::$error = __( 'Dieser Notfall-Schlüssel ist ungültig oder wurde schon benutzt.', 'sk-core' );
			return;
		}

		delete_transient( $key );

		// Whoever holds the lost wallet may still be logged in with it.
		\WP_Session_Tokens::get_instance( $user_id )->destroy_all();

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		$target = function_exists( 'sk_get_navigation_url' ) ? sk_get_navigation_url( 'auth-connector' ) : '';

		wp_safe_redirect( add_query_arg( 'sk_recovered', '1', $target ?: home_url( '/dashboard/' ) ) );
		exit;
	}

	// ── Frontend pieces ────────────────────────────────────────────────────

	/**
	 * Script and styles for making a code — shared by the settings page,
	 * the onboarding and the banner.
	 */
	public static function enqueue_assets(): void {
		$base = SK_AUTH_PATH . '/assets';

		self::enqueue_style();
		wp_enqueue_script(
			'sk-recovery-code',
			SK_AUTH_ASSETS . '/js/recovery-code.js',
			[ 'jquery' ],
			(string) filemtime( $base . '/js/recovery-code.js' ),
			true
		);
		wp_localize_script( 'sk-recovery-code', 'skRecovery', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'sk_recovery' ),
			'i18n'    => [
				'working' => __( 'Wird erstellt…', 'sk-core' ),
				'copied'  => __( 'Kopiert', 'sk-core' ),
				'error'   => __( 'Konnte nicht erstellt werden. Bitte versuche es nochmal.', 'sk-core' ),
			],
		] );
	}

	/**
	 * Styles alone — enough for the login form.
	 */
	public static function enqueue_style(): void {
		wp_enqueue_style(
			'sk-recovery-code',
			SK_AUTH_ASSETS . '/css/recovery-code.css',
			[],
			(string) filemtime( SK_AUTH_PATH . '/assets/css/recovery-code.css' )
		);
	}

	/**
	 * The "make a code" block: button, then the code shown once.
	 *
	 * @param bool $regenerate Whether a code exists and would be replaced.
	 */
	public static function render_generator( bool $regenerate ): void {
		self::enqueue_assets();

		$label   = $regenerate ? __( 'Neuen Notfall-Schlüssel erstellen', 'sk-core' ) : __( 'Notfall-Schlüssel erstellen', 'sk-core' );
		$confirm = $regenerate ? __( 'Der bisherige Notfall-Schlüssel wird damit ungültig. Fortfahren?', 'sk-core' ) : '';
		?>
		<div class="sk-recovery">
			<button type="button" class="sk-btn sk-btn-btc sk-recovery-generate" data-confirm="<?php echo esc_attr( $confirm ); ?>">
				<i class="fas fa-life-ring"></i> <?php echo esc_html( $label ); ?>
			</button>
			<div class="sk-recovery-result" hidden>
				<p class="sk-recovery-warning">
					<i class="fas fa-exclamation-triangle"></i>
					<?php esc_html_e( 'Schreib ihn jetzt auf und bewahre ihn getrennt von deiner Wallet auf. Wir zeigen ihn nur dieses eine Mal. Wer ihn hat, kommt in dein Konto.', 'sk-core' ); ?>
				</p>
				<code class="sk-recovery-value"></code>
				<button type="button" class="sk-btn sk-recovery-copy">
					<i class="fas fa-copy"></i> <?php esc_html_e( 'Kopieren', 'sk-core' ); ?>
				</button>
			</div>
			<p class="sk-recovery-status" role="status"></p>
		</div>
		<?php
	}

	/**
	 * Dashboard banner for accounts without a code.
	 */
	public static function render_banner(): void {
		$user_id = get_current_user_id();

		if ( ! $user_id || self::has_code( $user_id ) || self::login_methods( $user_id ) > 1 ) {
			return;
		}

		// The onboarding asks for it itself.
		if ( get_user_meta( $user_id, 'uob_show_onboarding', true ) === 'yes' ) {
			return;
		}

		if ( (int) get_user_meta( $user_id, self::META_SNOOZE, true ) > time() ) {
			return;
		}

		// On the settings page the generator itself is right there.
		global $wp;
		if ( isset( $wp->query_vars['settings'] ) && 'store' === $wp->query_vars['settings'] ) {
			return;
		}

		self::enqueue_assets();

		$settings_url = sk_get_navigation_url( 'settings/store' ) . '#sk-recovery';
		?>
		<div class="sk-alert sk-alert-warning sk-recovery-banner">
			<div class="sk-recovery-banner-text">
				<strong><i class="fas fa-life-ring"></i> <?php esc_html_e( 'Kein Notfall-Schlüssel', 'sk-core' ); ?></strong>
				<p><?php esc_html_e( 'Geht deine Wallet oder dein Schlüssel verloren, kommst du ohne Notfall-Schlüssel nicht mehr in dein Konto. Er ist in einer Minute erstellt.', 'sk-core' ); ?></p>
			</div>
			<a class="sk-btn sk-btn-btc sk-btn-sm" href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Jetzt erstellen', 'sk-core' ); ?></a>
			<button type="button" class="sk-recovery-snooze" title="<?php esc_attr_e( 'Später', 'sk-core' ); ?>" aria-label="<?php esc_attr_e( 'Später', 'sk-core' ); ?>">
				<i class="fas fa-times"></i>
			</button>
		</div>
		<?php
	}

	/**
	 * How many independent ways into the account there are.
	 *
	 * A Nostr identity we generated does not count: we hold its private
	 * key, and few users ever move it into a signer they could log in with.
	 */
	private static function login_methods( int $user_id ): int {
		$keys = [ 'uac_linked_nostr_pubkey', 'lnurl-auth-bjm-id', 'uac_linked_lnurl_node_key', 'btc_address' ];

		if ( get_user_meta( $user_id, 'sk_nostr_identity_source', true ) !== 'generated' ) {
			$keys[] = 'nostr_public_key';
		}

		// Distinct values: a merge can leave the same key in two fields.
		$values = array_map( fn( $key ) => (string) get_user_meta( $user_id, $key, true ), $keys );

		return count( array_unique( array_filter( $values ) ) );
	}

	// ── AJAX ───────────────────────────────────────────────────────────────

	public static function ajax_generate(): void {
		check_ajax_referer( 'sk_recovery', 'nonce' );

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error( [ 'message' => __( 'Du musst angemeldet sein.', 'sk-core' ) ], 401 );
		}

		nocache_headers();
		wp_send_json_success( [ 'code' => self::generate( $user_id ) ] );
	}

	/**
	 * "Later" on the banner: quiet for a month, then it asks again.
	 */
	public static function ajax_snooze(): void {
		check_ajax_referer( 'sk_recovery', 'nonce' );

		$user_id = get_current_user_id();

		if ( $user_id ) {
			update_user_meta( $user_id, self::META_SNOOZE, time() + self::SNOOZE );
		}

		wp_send_json_success();
	}

	// ── Admin ──────────────────────────────────────────────────────────────

	/**
	 * Admins can make a code for someone who lost access and has none.
	 * The admin sees it once and hands it over on a verified channel.
	 */
	public static function render_admin_section( \WP_User $user ): void {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		$transient = 'sk_recovery_admin_' . get_current_user_id() . '_' . $user->ID;
		$fresh     = (string) get_transient( $transient );
		delete_transient( $transient );

		$created = self::created( $user->ID );
		?>
		<h2><?php esc_html_e( 'Notfall-Schlüssel', 'sk-core' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Status', 'sk-core' ); ?></th>
				<td>
					<?php if ( '' !== $fresh ) : ?>
						<p><code style="font-size:16px;"><?php echo esc_html( $fresh ); ?></code></p>
						<p class="description"><?php esc_html_e( 'Wird nur jetzt angezeigt. Gib ihn nur über einen Kanal weiter, auf dem du die Person sicher erkennst.', 'sk-core' ); ?></p>
					<?php elseif ( $created ) : ?>
						<?php
						/* translators: %s: date the code was made. */
						printf( esc_html__( 'Vorhanden seit %s.', 'sk-core' ), esc_html( wp_date( get_option( 'date_format' ), $created ) ) );
						?>
					<?php else : ?>
						<?php esc_html_e( 'Keiner vorhanden.', 'sk-core' ); ?>
					<?php endif; ?>
					<p>
						<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sk_recovery_admin_generate&user_id=' . $user->ID ), 'sk_recovery_admin_' . $user->ID ) ); ?>"
						   onclick="return confirm('<?php echo esc_js( __( 'Neuen Notfall-Schlüssel erstellen? Ein vorhandener wird ungültig.', 'sk-core' ) ); ?>');">
							<?php esc_html_e( 'Neuen Notfall-Schlüssel erstellen', 'sk-core' ); ?>
						</a>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	public static function handle_admin_generate(): void {
		$user_id = (int) ( $_GET['user_id'] ?? 0 );

		check_admin_referer( 'sk_recovery_admin_' . $user_id );

		if ( ! $user_id || ! get_userdata( $user_id ) || ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'sk-core' ), 403 );
		}

		set_transient( 'sk_recovery_admin_' . get_current_user_id() . '_' . $user_id, self::generate( $user_id ), 5 * MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'user-edit.php?user_id=' . $user_id ) );
		exit;
	}
}
