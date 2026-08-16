<?php

namespace SK\Core\Dashboard\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Simple Feedback — form via [feedback_box] shortcode with admin page.
 *
 * Ported from plugin: sats-feedback (WP Simple Feedback)
 */
class Feedback {

	const CPT      = 'wp_simple_feedback';
	const NONCE    = 'wpsf_nonce';
	const OPTS_KEY = 'wpsf_options';

	public function __construct() {
		add_action( 'init', [ $this, 'register_cpt' ] );
		add_shortcode( 'feedback_box', [ $this, 'shortcode_box' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_wpsf_submit', [ $this, 'handle_submit' ] );
		add_action( 'wp_ajax_nopriv_wpsf_submit', [ $this, 'handle_submit' ] );
		add_action( 'wp_dashboard_setup', [ $this, 'register_dashboard_widget' ] );
	}

	/**
	 * Admin URL of the Feedback tab inside the SK dashboard.
	 */
	public static function admin_url( string $sub = 'entries' ): string {
		return admin_url( 'admin.php?page=sk&tab=feedback&sub=' . $sub );
	}

	// ── CPT ────────────────────────────────────────────────────────────────────

	public function register_cpt(): void {
		register_post_type( self::CPT, [
			'labels'   => [
				'name'          => __( 'Feedbacks', 'sk-core' ),
				'singular_name' => __( 'Feedback', 'sk-core' ),
			],
			'public'   => false,
			'show_ui'  => false,
			'supports' => [ 'editor', 'title' ],
		] );
	}

	// ── Assets ─────────────────────────────────────────────────────────────────

	public function enqueue_assets(): void {
		wp_enqueue_style( 'sk-feedback', plugins_url( 'assets/css/sk-feedback.css', SK_CORE_FILE ), [], SK_CORE_VERSION );

		$js_path = SK_CORE_DIR . '/assets/js/dashboard/feedback-box.js';
		wp_register_script(
			'sk-feedback-box',
			SK_CORE_ASSETS . '/js/dashboard/feedback-box.js',
			[],
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : SK_CORE_VERSION,
			true
		);
		wp_localize_script( 'sk-feedback-box', 'wpsfFeedback', [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'i18n'    => [
				'sending' => __( 'Senden...', 'sk-core' ),
				'error'   => __( 'Es ist ein Fehler aufgetreten.', 'sk-core' ),
			],
		] );
	}

	// ── Admin ──────────────────────────────────────────────────────────────────

	public static function get_defaults(): array {
		return [
			'title'         => 'Dein Feedback',
			'description'   => 'Wir freuen uns über kurzes, ehrliches Feedback.',
			'rate_limit'    => 60,
			'require_login' => 0,
		];
	}

	public static function get_options(): array {
		return wp_parse_args( get_option( self::OPTS_KEY, [] ), self::get_defaults() );
	}

	/**
	 * Sanitize a raw settings array coming from the admin form.
	 */
	public static function sanitize_options( array $opts ): array {
		$defaults = self::get_defaults();

		return [
			'title'         => sanitize_text_field( $opts['title'] ?? $defaults['title'] ),
			'description'   => wp_kses_post( $opts['description'] ?? $defaults['description'] ),
			'rate_limit'    => max( 0, intval( $opts['rate_limit'] ?? $defaults['rate_limit'] ) ),
			'require_login' => ! empty( $opts['require_login'] ) ? 1 : 0,
		];
	}
	// ── Dashboard Widget ───────────────────────────────────────────────────────

	public function register_dashboard_widget(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget( 'wpsf_dashboard_widget', __( 'Letztes Feedback', 'sk-core' ), [ $this, 'dashboard_widget_render' ] );
	}

	public function dashboard_widget_render(): void {
		$q = new \WP_Query( [
			'post_type'      => self::CPT,
			'posts_per_page' => 5,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );
		if ( $q->have_posts() ) {
			echo '<ul>';
			while ( $q->have_posts() ) {
				$q->the_post();
				$date = esc_html( get_the_date( 'Y-m-d H:i' ) );
				echo '<li><strong>' . $date . ':</strong> ' . esc_html( wp_trim_words( get_the_title() . ' – ' . wp_strip_all_tags( get_the_content() ), 18 ) ) . '</li>';
			}
			echo '</ul>';
			wp_reset_postdata();
		} else {
			echo '<p>' . esc_html__( 'Noch kein Feedback vorhanden.', 'sk-core' ) . '</p>';
		}
		echo '<p><a class="button button-primary" href="' . esc_url( self::admin_url( 'entries' ) ) . '">' . esc_html__( 'Alle anzeigen', 'sk-core' ) . '</a></p>';
	}

	// ── Shortcode ──────────────────────────────────────────────────────────────

	public function shortcode_box( $atts = [] ): string {
		$opts = self::get_options();
		if ( $opts['require_login'] && ! is_user_logged_in() ) {
			return '<div class="wpsf-notice">' . esc_html__( 'Bitte einloggen, um Feedback zu senden.', 'sk-core' ) . '</div>';
		}
		wp_enqueue_script( 'sk-feedback-box' );

		ob_start();
		?>
		<div class="wpsf-box">
			<h3 class="wpsf-title"><?php echo esc_html( $opts['title'] ); ?></h3>
			<div class="wpsf-description"><?php echo wp_kses_post( wpautop( $opts['description'] ) ); ?></div>
			<form class="wpsf-form" method="post">
				<textarea name="message" rows="5" placeholder="<?php echo esc_attr__( 'Dein Feedback...', 'sk-core' ); ?>" required></textarea>
				<input type="text" name="website" class="wpsf-honeypot" autocomplete="off" tabindex="-1" aria-hidden="true" />
				<input type="hidden" name="action" value="wpsf_submit"/>
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( self::NONCE ) ); ?>"/>
				<button type="submit" class="button"><?php echo esc_html__( 'Absenden', 'sk-core' ); ?></button>
				<div class="wpsf-msg" aria-live="polite"></div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	// ── AJAX Handler ───────────────────────────────────────────────────────────

	public function handle_submit(): void {
		header( 'Content-Type: application/json; charset=utf-8' );

		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', self::NONCE ) ) {
			status_header( 400 );
			echo wp_json_encode( [ 'ok' => false, 'msg' => 'Ungültige Anfrage.' ] );
			wp_die();
		}

		$opts = self::get_options();

		// Rate limit per IP.
		$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
		$key = 'wpsf_rate_' . md5( $ip );
		$last = get_transient( $key );
		if ( $last && time() - intval( $last ) < intval( $opts['rate_limit'] ) ) {
			status_header( 429 );
			echo wp_json_encode( [ 'ok' => false, 'msg' => 'Bitte warte kurz, bevor du erneut sendest.' ] );
			wp_die();
		}
		set_transient( $key, time(), max( 5, intval( $opts['rate_limit'] ) ) );

		// Honeypot.
		if ( ! empty( trim( $_POST['website'] ?? '' ) ) ) {
			echo wp_json_encode( [ 'ok' => true, 'msg' => 'Danke!' ] );
			wp_die();
		}

		$message = trim( wp_unslash( $_POST['message'] ?? '' ) );
		if ( empty( $message ) ) {
			status_header( 400 );
			echo wp_json_encode( [ 'ok' => false, 'msg' => 'Bitte gib eine Nachricht ein.' ] );
			wp_die();
		}

		$user_id = get_current_user_id();
		$title   = $user_id
			? sprintf( __( 'Feedback von %s', 'sk-core' ), wp_get_current_user()->user_login )
			: __( 'Feedback', 'sk-core' );

		$post_id = wp_insert_post( [
			'post_type'    => self::CPT,
			'post_title'   => wp_strip_all_tags( $title ),
			'post_content' => wp_kses_post( $message ),
			'post_status'  => 'publish',
			'meta_input'   => [
				'_wpsf_user_id' => $user_id,
				'_wpsf_ip'      => $ip,
			],
		], true );

		if ( is_wp_error( $post_id ) ) {
			status_header( 500 );
			echo wp_json_encode( [ 'ok' => false, 'msg' => 'Fehler beim Speichern.' ] );
			wp_die();
		}

		$this->send_admin_notification( $post_id, $title, $message, $user_id, $ip );

		echo wp_json_encode( [ 'ok' => true, 'msg' => 'Danke für dein Feedback!' ] );
		wp_die();
	}

	private function send_admin_notification( int $post_id, string $title, string $message, int $user_id, string $ip ): void {
		$to = get_option( 'admin_email' );
		if ( ! $to ) {
			return;
		}

		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$user      = $user_id ? get_userdata( $user_id ) : null;
		$user_line = $user ? sprintf( '%s (#%d, %s)', $user->user_login, $user_id, $user->user_email ) : __( 'Gast (nicht eingeloggt)', 'sk-core' );
		$edit_link = self::admin_url( 'entries' );

		$subject = sprintf( '[%s] %s', $site_name, $title );
		$body    = sprintf(
			"%s\n\n%s\n\n---\n%s %s\n%s %s\n%s %s\n%s %s",
			$title,
			wp_strip_all_tags( $message ),
			__( 'Benutzer:', 'sk-core' ),
			$user_line,
			__( 'IP:', 'sk-core' ),
			$ip,
			__( 'Zeit:', 'sk-core' ),
			current_time( 'Y-m-d H:i:s' ),
			__( 'Im Admin öffnen:', 'sk-core' ),
			$edit_link
		);

		wp_mail( $to, $subject, $body );
	}
}
