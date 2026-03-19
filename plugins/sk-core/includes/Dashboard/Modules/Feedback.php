<?php

namespace SK\Core\Dashboard\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Simple Feedback — form via [feedback_box] shortcode with admin page.
 *
 * Ported from plugin: sats-feedback (WP Simple Feedback)
 */
class Feedback {

	const CPT       = 'wp_simple_feedback';
	const NONCE     = 'wpsf_nonce';
	const OPTS_KEY  = 'wpsf_options';
	const PAGE_SLUG = 'wpsf-admin';

	public function __construct() {
		add_action( 'init', [ $this, 'register_cpt' ] );
		add_shortcode( 'feedback_box', [ $this, 'shortcode_box' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'wp_ajax_wpsf_submit', [ $this, 'handle_submit' ] );
		add_action( 'wp_ajax_nopriv_wpsf_submit', [ $this, 'handle_submit' ] );
		add_action( 'wp_dashboard_setup', [ $this, 'register_dashboard_widget' ] );
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
	}

	// ── Admin ──────────────────────────────────────────────────────────────────

	public function register_admin_page(): void {
		add_menu_page(
			__( 'Feedback', 'sk-core' ),
			__( 'Feedback', 'sk-core' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'admin_page_render' ],
			'dashicons-feedback',
			58
		);
		add_submenu_page( self::PAGE_SLUG, __( 'Eingänge', 'sk-core' ), __( 'Eingänge', 'sk-core' ), 'manage_options', self::PAGE_SLUG . '&tab=entries' );
		add_submenu_page( self::PAGE_SLUG, __( 'Einstellungen', 'sk-core' ), __( 'Einstellungen', 'sk-core' ), 'manage_options', self::PAGE_SLUG . '&tab=settings' );
	}

	public function register_settings(): void {
		register_setting( 'wpsf_settings_group', self::OPTS_KEY, [
			'type'              => 'array',
			'sanitize_callback' => function ( $opts ) {
				return [
					'title'         => sanitize_text_field( $opts['title'] ?? 'Dein Feedback' ),
					'description'   => wp_kses_post( $opts['description'] ?? 'Wir freuen uns über kurzes, ehrliches Feedback.' ),
					'rate_limit'    => max( 0, intval( $opts['rate_limit'] ?? 60 ) ),
					'require_login' => ! empty( $opts['require_login'] ) ? 1 : 0,
				];
			},
			'default' => $this->get_defaults(),
		] );
	}

	private function get_defaults(): array {
		return [
			'title'         => 'Dein Feedback',
			'description'   => 'Wir freuen uns über kurzes, ehrliches Feedback.',
			'rate_limit'    => 60,
			'require_login' => 0,
		];
	}

	private function get_options(): array {
		return wp_parse_args( get_option( self::OPTS_KEY, [] ), $this->get_defaults() );
	}

	public function admin_page_render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab = 'entries';
		if ( isset( $_GET['tab'] ) && in_array( $_GET['tab'], [ 'entries', 'settings' ], true ) ) {
			$active_tab = sanitize_key( $_GET['tab'] );
		} elseif ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'tab=settings' ) !== false ) {
			$active_tab = 'settings';
		}

		$opts = $this->get_options();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Feedback', 'sk-core' ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=entries' ) ); ?>"
				   class="nav-tab <?php echo $active_tab === 'entries' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Eingänge', 'sk-core' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=settings' ) ); ?>"
				   class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Einstellungen', 'sk-core' ); ?>
				</a>
			</h2>

			<?php if ( $active_tab === 'settings' ) : ?>
				<form method="post" action="options.php">
					<?php settings_fields( 'wpsf_settings_group' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="wpsf_title"><?php esc_html_e( 'Titel', 'sk-core' ); ?></label></th>
							<td><input id="wpsf_title" type="text" name="<?php echo esc_attr( self::OPTS_KEY ); ?>[title]" class="regular-text" value="<?php echo esc_attr( $opts['title'] ); ?>"/></td>
						</tr>
						<tr>
							<th scope="row"><label for="wpsf_description"><?php esc_html_e( 'Beschreibung', 'sk-core' ); ?></label></th>
							<td><textarea id="wpsf_description" name="<?php echo esc_attr( self::OPTS_KEY ); ?>[description]" rows="5" class="large-text"><?php echo esc_textarea( $opts['description'] ); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Nur eingeloggte Nutzer', 'sk-core' ); ?></th>
							<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTS_KEY ); ?>[require_login]" value="1" <?php checked( 1, intval( $opts['require_login'] ) ); ?>/> <?php esc_html_e( 'Feedback nur für angemeldete Benutzer erlauben', 'sk-core' ); ?></label></td>
						</tr>
						<tr>
							<th scope="row"><label for="wpsf_rate_limit"><?php esc_html_e( 'Rate Limit (Sekunden)', 'sk-core' ); ?></label></th>
							<td><input id="wpsf_rate_limit" type="number" class="small-text" min="0" name="<?php echo esc_attr( self::OPTS_KEY ); ?>[rate_limit]" value="<?php echo intval( $opts['rate_limit'] ); ?>"/> <span class="description"><?php esc_html_e( 'Minimale Sekunden zwischen Einsendungen pro IP', 'sk-core' ); ?></span></td>
						</tr>
					</table>
					<?php submit_button(); ?>
				</form>
				<p><strong><?php esc_html_e( 'Shortcode:', 'sk-core' ); ?></strong> <code>[feedback_box]</code></p>

			<?php else : ?>
				<?php
				$q = new \WP_Query( [
					'post_type'      => self::CPT,
					'posts_per_page' => 50,
					'post_status'    => 'publish',
					'orderby'        => 'date',
					'order'          => 'DESC',
				] );
				if ( $q->have_posts() ) : ?>
					<table class="widefat striped">
						<thead>
						<tr>
							<th><?php esc_html_e( 'Datum', 'sk-core' ); ?></th>
							<th><?php esc_html_e( 'Titel', 'sk-core' ); ?></th>
							<th><?php esc_html_e( 'Nachricht', 'sk-core' ); ?></th>
							<th><?php esc_html_e( 'Benutzer', 'sk-core' ); ?></th>
							<th><?php esc_html_e( 'IP', 'sk-core' ); ?></th>
						</tr>
						</thead>
						<tbody>
						<?php while ( $q->have_posts() ) : $q->the_post();
							$uid = get_post_meta( get_the_ID(), '_wpsf_user_id', true );
							$ip  = get_post_meta( get_the_ID(), '_wpsf_ip', true );
							?>
							<tr>
								<td><?php echo esc_html( get_the_date( 'Y-m-d H:i' ) ); ?></td>
								<td><?php echo esc_html( get_the_title() ); ?></td>
								<td><?php echo wp_kses_post( wpautop( get_the_content() ) ); ?></td>
								<td><?php
									$userdata = $uid ? get_userdata( $uid ) : null;
									echo $userdata ? esc_html( $userdata->user_login ) : '—';
								?></td>
								<td><?php echo $ip ? esc_html( $ip ) : '—'; ?></td>
							</tr>
						<?php endwhile; wp_reset_postdata(); ?>
						</tbody>
					</table>
				<?php else : ?>
					<p><?php esc_html_e( 'Noch kein Feedback vorhanden.', 'sk-core' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
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
		echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=entries' ) ) . '">' . esc_html__( 'Alle anzeigen', 'sk-core' ) . '</a></p>';
	}

	// ── Shortcode ──────────────────────────────────────────────────────────────

	public function shortcode_box( $atts = [] ): string {
		$opts = $this->get_options();
		if ( $opts['require_login'] && ! is_user_logged_in() ) {
			return '<div class="wpsf-notice">' . esc_html__( 'Bitte einloggen, um Feedback zu senden.', 'sk-core' ) . '</div>';
		}
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
		<script>
		(function(){
			document.addEventListener('submit', function(e){
				var form = e.target.closest('.wpsf-form');
				if (!form) return;
				e.preventDefault();
				var msgEl = form.querySelector('.wpsf-msg');
				var data = new FormData(form);
				msgEl.textContent = '<?php echo esc_js( __( 'Senden...', 'sk-core' ) ); ?>';
				fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
					method: 'POST',
					credentials: 'same-origin',
					body: data
				}).then(function(res){ return res.json(); })
				  .then(function(json){
					msgEl.textContent = json.msg || '';
					if (json.ok) { form.reset(); }
				  })
				  .catch(function(){
					msgEl.textContent = '<?php echo esc_js( __( 'Es ist ein Fehler aufgetreten.', 'sk-core' ) ); ?>';
				  });
			});
		})();
		</script>
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

		$opts = $this->get_options();

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

		echo wp_json_encode( [ 'ok' => true, 'msg' => 'Danke für dein Feedback!' ] );
		wp_die();
	}
}
