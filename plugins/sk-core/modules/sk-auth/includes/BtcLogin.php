<?php

namespace SK\Modules\Auth;

defined( 'ABSPATH' ) || exit;

/**
 * BTC Login — Registration & Login with Bitcoin address + password.
 *
 * Ported from plugin: btc-login
 */
class BtcLogin {

	public function __construct() {
		add_shortcode( 'btc_login', [ $this, 'render_shortcode' ] );
	}

	/**
	 * Validate a Bitcoin address format.
	 *
	 * Supports: Legacy (1...), P2SH (3...), Bech32 (bc1q...), Taproot (bc1p...).
	 */
	public static function is_valid_address( string $address ): bool {
		// Legacy (P2PKH): starts with 1, 25-34 chars.
		if ( preg_match( '/^1[a-km-zA-HJ-NP-Z1-9]{25,34}$/', $address ) ) {
			return true;
		}
		// P2SH: starts with 3, 25-34 chars.
		if ( preg_match( '/^3[a-km-zA-HJ-NP-Z1-9]{25,34}$/', $address ) ) {
			return true;
		}
		// Bech32 (Segwit): starts with bc1q, 42 chars.
		if ( preg_match( '/^bc1q[a-z0-9]{38,}$/', strtolower( $address ) ) ) {
			return true;
		}
		// Bech32m (Taproot): starts with bc1p, 62 chars.
		if ( preg_match( '/^bc1p[a-z0-9]{58}$/', strtolower( $address ) ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Shortcode: [btc_login]
	 */
	public function render_shortcode( $atts ) {
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			return '<div class="btclogin-logged-in">'
				. '<p>Eingeloggt als <strong>' . esc_html( $user->display_name ) . '</strong></p>'
				. '<a href="' . esc_url( wp_logout_url( home_url() ) ) . '">Ausloggen</a>'
				. '</div>';
		}

		$error = '';
		$tab   = isset( $_POST['btclogin_tab'] ) ? sanitize_text_field( $_POST['btclogin_tab'] ) : ( isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'login' );

		// The nonce alone cannot stop login CSRF: logged-out visitors all share the
		// same nonce, so an attacker can fetch a valid one. The request origin can.
		$same_origin = ! function_exists( 'sk_is_same_origin_request' ) || sk_is_same_origin_request();

		if ( isset( $_POST['btclogin_action'] )
			&& $same_origin
			&& wp_verify_nonce( $_POST['_btclogin_nonce'] ?? '', 'btclogin_nonce' ) ) {
			$error = $this->handle_form( $tab );
			if ( $error === null ) {
				return ''; // redirect happened
			}
		}

		wp_enqueue_style( 'sk-btc-login', SK_AUTH_ASSETS . '/css/sk-btc-login.css', [], SK_AUTH_VERSION );

		ob_start();
		?>
		<div class="btclogin-form-wrap">
			<div class="btclogin-tabs">
				<button type="button" class="btclogin-tab <?php echo $tab === 'login' ? 'active' : ''; ?>" data-tab="login">Anmelden</button>
				<button type="button" class="btclogin-tab <?php echo $tab === 'register' ? 'active' : ''; ?>" data-tab="register">Registrieren</button>
			</div>

			<?php if ( $error ) : ?>
				<div class="btclogin-error"><?php echo esc_html( $error ); ?></div>
			<?php endif; ?>

			<!-- Login Form -->
			<form method="post" class="btclogin-form" id="btclogin-login" style="<?php echo $tab !== 'login' ? 'display:none;' : ''; ?>">
				<?php wp_nonce_field( 'btclogin_nonce', '_btclogin_nonce' ); ?>
				<input type="hidden" name="btclogin_action" value="login" />
				<input type="hidden" name="btclogin_tab" value="login" />

				<div class="btclogin-field">
					<label for="btclogin-address-login">Bitcoin-Adresse</label>
					<input type="text" id="btclogin-address-login" name="btc_address"
						   placeholder="bc1q... / 1... / 3..." autocomplete="off" required />
				</div>

				<div class="btclogin-field">
					<label for="btclogin-password-login">Passwort</label>
					<input type="password" id="btclogin-password-login" name="btc_password" required />
				</div>

				<button type="submit" class="btclogin-submit">Anmelden</button>
			</form>

			<!-- Register Form -->
			<form method="post" class="btclogin-form" id="btclogin-register" style="<?php echo $tab !== 'register' ? 'display:none;' : ''; ?>">
				<?php wp_nonce_field( 'btclogin_nonce', '_btclogin_nonce' ); ?>
				<input type="hidden" name="btclogin_action" value="register" />
				<input type="hidden" name="btclogin_tab" value="register" />

				<div class="btclogin-field">
					<label for="btclogin-address-reg">Bitcoin-Adresse</label>
					<input type="text" id="btclogin-address-reg" name="btc_address"
						   placeholder="bc1q... / 1... / 3..." autocomplete="off" required />
				</div>

				<div class="btclogin-field">
					<label for="btclogin-password-reg">Passwort</label>
					<input type="password" id="btclogin-password-reg" name="btc_password"
						   minlength="8" required />
				</div>

				<div class="btclogin-field">
					<label for="btclogin-password-confirm">Passwort bestätigen</label>
					<input type="password" id="btclogin-password-confirm" name="btc_password_confirm"
						   minlength="8" required />
				</div>

				<button type="submit" class="btclogin-submit">Registrieren</button>
			</form>
		</div>

		<script>
		(function() {
			document.querySelectorAll('.btclogin-tab').forEach(function(tab) {
				tab.addEventListener('click', function() {
					document.querySelectorAll('.btclogin-tab').forEach(function(t) { t.classList.remove('active'); });
					this.classList.add('active');
					var target = this.getAttribute('data-tab');
					document.getElementById('btclogin-login').style.display = target === 'login' ? '' : 'none';
					document.getElementById('btclogin-register').style.display = target === 'register' ? '' : 'none';
				});
			});
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Handle login/register form submission.
	 *
	 * @return string|null Error message, or null on successful redirect.
	 */
	private function handle_form( string $tab ): ?string {
		$address  = sanitize_text_field( wp_unslash( $_POST['btc_address'] ?? '' ) );
		$password = $_POST['btc_password'] ?? '';

		if ( empty( $address ) || empty( $password ) ) {
			return 'Bitte Bitcoin-Adresse und Passwort eingeben.';
		}

		if ( ! self::is_valid_address( $address ) ) {
			return 'Ungültige Bitcoin-Adresse. Unterstützt: 1..., 3..., bc1q..., bc1p...';
		}

		if ( $_POST['btclogin_action'] === 'register' ) {
			return $this->handle_register( $address, $password );
		}

		return $this->handle_login( $address, $password );
	}

	/**
	 * Handle registration.
	 */
	private function handle_register( string $address, string $password ): ?string {
		$password_confirm = $_POST['btc_password_confirm'] ?? '';

		if ( $password !== $password_confirm ) {
			return 'Passwörter stimmen nicht überein.';
		}

		if ( strlen( $password ) < 8 ) {
			return 'Passwort muss mindestens 8 Zeichen lang sein.';
		}

		$existing = get_users( [ 'meta_key' => 'btc_address', 'meta_value' => $address, 'number' => 1 ] );
		if ( ! empty( $existing ) ) {
			return 'Diese Bitcoin-Adresse ist bereits registriert.';
		}

		$username = 'satoshi-' . wp_generate_password( 5, false, false );
		while ( username_exists( $username ) ) {
			$username = 'satoshi-' . wp_generate_password( 5, false, false );
		}

		$user_id = wp_create_user( $username, $password, $username . '@btc.local' );

		if ( is_wp_error( $user_id ) ) {
			return $user_id->get_error_message();
		}

		update_user_meta( $user_id, 'btc_address', $address );
		update_user_meta( $user_id, 'sk_password_set', 1 );
		wp_update_user( [ 'ID' => $user_id, 'display_name' => $username ] );

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		wp_safe_redirect( apply_filters( 'btclogin_register_redirect', home_url() ) );
		exit;
	}


	/** Failed password attempts allowed per address and per IP before locking out. */
	const MAX_LOGIN_ATTEMPTS = 5;

	/** How long a lockout lasts. */
	const LOGIN_LOCKOUT = 900;

	/**
	 * Is this address or IP currently locked out?
	 *
	 * The Bitcoin address is a public identifier, so without a limit the login
	 * form is an offline-quality brute force oracle against the password.
	 */
	private static function login_blocked( string $address ): bool {
		foreach ( self::login_keys( $address ) as $key ) {
			if ( (int) get_transient( $key ) >= self::MAX_LOGIN_ATTEMPTS ) {
				return true;
			}
		}

		return false;
	}

	private static function register_failed_login( string $address ): void {
		foreach ( self::login_keys( $address ) as $key ) {
			set_transient( $key, (int) get_transient( $key ) + 1, self::LOGIN_LOCKOUT );
		}
	}

	private static function clear_failed_logins( string $address ): void {
		foreach ( self::login_keys( $address ) as $key ) {
			delete_transient( $key );
		}
	}

	/**
	 * @return string[] Counter keys: one per address, one per client IP.
	 */
	private static function login_keys( string $address ): array {
		$ip = function_exists( 'sk_get_client_ip' ) ? sk_get_client_ip() : '';

		return [
			'sk_btclogin_a_' . md5( strtolower( $address ) ),
			'sk_btclogin_i_' . md5( $ip !== '' ? $ip : 'unknown' ),
		];
	}

	/**
	 * Handle login.
	 */
	private function handle_login( string $address, string $password ): ?string {
		if ( self::login_blocked( $address ) ) {
			return 'Zu viele Fehlversuche. Bitte in 15 Minuten erneut versuchen.';
		}

		$users = get_users( [ 'meta_key' => 'btc_address', 'meta_value' => $address, 'number' => 1 ] );

		if ( empty( $users ) ) {
			self::register_failed_login( $address );
			return 'Bitcoin-Adresse oder Passwort falsch.';
		}

		$user = wp_authenticate( $users[0]->user_login, $password );

		if ( ! $user || is_wp_error( $user ) ) {
			self::register_failed_login( $address );
			return 'Bitcoin-Adresse oder Passwort falsch.';
		}

		self::clear_failed_logins( $address );

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );

		wp_safe_redirect( apply_filters( 'btclogin_login_redirect', home_url() ) );
		exit;
	}
}
