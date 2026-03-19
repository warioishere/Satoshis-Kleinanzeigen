<?php

namespace SK\Core\Dashboard\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * AI Categorizer — automatic product category suggestions via Claude API.
 *
 * Ported from plugin: sk-ai-categorizer
 */
class AiCategorizer {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'wp_ajax_skai_suggest', [ $this, 'handle_suggest' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	// ── Admin Settings ─────────────────────────────────────────────────────────

	public function add_menu(): void {
		add_options_page(
			'KI Kategorisierung',
			'KI Kategorisierung',
			'manage_options',
			'sk-ai-categorizer',
			[ $this, 'render_settings_page' ]
		);
	}

	public function register_settings(): void {
		register_setting( 'skai_settings', 'skai_enabled', [ 'type' => 'boolean', 'sanitize_callback' => 'absint', 'default' => 0 ] );
		register_setting( 'skai_settings', 'skai_api_key', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ] );
		register_setting( 'skai_settings', 'skai_model', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'claude-haiku-4-5-20251001' ] );
		register_setting( 'skai_settings', 'skai_auto_apply', [ 'type' => 'boolean', 'sanitize_callback' => 'absint', 'default' => 0 ] );
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$saved = isset( $_GET['settings-updated'] );
		?>
		<div class="wrap">
			<h1>KI Kategorisierung</h1>
			<p style="color:#666;max-width:600px">Analysiert Produkttitel und -beschreibung mit Claude AI und schlägt automatisch die passende Kategorie vor.</p>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p>Einstellungen gespeichert.</p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'skai_settings' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Plugin aktiv</th>
						<td>
							<label>
								<input type="checkbox" name="skai_enabled" value="1" <?php checked( get_option( 'skai_enabled', 0 ), 1 ); ?>>
								KI-Kategorisierung aktivieren
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="skai_api_key">Claude API Key</label></th>
						<td>
							<input type="password" id="skai_api_key" name="skai_api_key"
								   value="<?php echo esc_attr( get_option( 'skai_api_key', '' ) ); ?>"
								   class="regular-text" autocomplete="new-password">
							<p class="description">
								API Key von <a href="https://console.anthropic.com/" target="_blank" rel="noopener">console.anthropic.com</a>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="skai_model">Claude Modell</label></th>
						<td>
							<select id="skai_model" name="skai_model">
								<?php
								$models = [
									'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (schnell, günstig — empfohlen)',
									'claude-sonnet-4-6'         => 'Claude Sonnet 4.6 (besser, teurer)',
									'claude-opus-4-6'           => 'Claude Opus 4.6 (bestes Modell)',
								];
								$current = get_option( 'skai_model', 'claude-haiku-4-5-20251001' );
								foreach ( $models as $value => $label ) {
									printf(
										'<option value="%s" %s>%s</option>',
										esc_attr( $value ),
										selected( $current, $value, false ),
										esc_html( $label )
									);
								}
								?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">Automatisch anwenden</th>
						<td>
							<label>
								<input type="checkbox" name="skai_auto_apply" value="1" <?php checked( get_option( 'skai_auto_apply', 0 ), 1 ); ?>>
								Kategorie direkt eintragen (ohne Bestätigung durch Verkäufer)
							</label>
							<p class="description">Wenn deaktiviert, wird nur ein Vorschlag angezeigt — der Verkäufer muss ihn manuell übernehmen.</p>
						</td>
					</tr>
				</table>

				<?php
				$key   = get_option( 'skai_api_key', '' );
				$model = get_option( 'skai_model', 'claude-haiku-4-5-20251001' );
				if ( $key ) :
					$test_result = $this->test_connection( $key, $model );
				?>
				<h2 style="margin-top:30px">Verbindungstest</h2>
				<?php if ( true === $test_result ) : ?>
					<div class="notice notice-success inline"><p>Verbindung zu Claude API erfolgreich.</p></div>
				<?php else : ?>
					<div class="notice notice-error inline"><p>Fehler: <?php echo esc_html( $test_result ); ?></p></div>
				<?php endif; ?>
				<?php endif; ?>

				<?php submit_button( 'Einstellungen speichern' ); ?>
			</form>
		</div>
		<?php
	}

	private function test_connection( string $api_key, string $model ): bool|string {
		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
			'timeout' => 10,
			'headers' => [
				'x-api-key'        => $api_key,
				'anthropic-version' => '2023-06-01',
				'content-type'     => 'application/json',
			],
			'body' => wp_json_encode( [
				'model'      => $model,
				'max_tokens' => 5,
				'messages'   => [ [ 'role' => 'user', 'content' => 'Hi' ] ],
			] ),
		] );

		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 === $code ) {
			return true;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['error']['message'] ?? "HTTP $code";
	}

	// ── Frontend Assets ────────────────────────────────────────────────────────

	public function enqueue_assets(): void {
		if ( ! get_option( 'skai_enabled', 0 ) ) {
			return;
		}
		if ( ! function_exists( 'sk_is_seller_dashboard' ) || ! sk_is_seller_dashboard() ) {
			return;
		}

		global $wp;
		$vars           = $wp->query_vars ?? [];
		$on_product_page = isset( $vars['new-product'] )
			|| isset( $vars['products'] )
			|| ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['product_id'] ) );

		if ( ! $on_product_page ) {
			return;
		}

		wp_enqueue_script(
			'sk-ai-cat',
			plugins_url( 'assets/js/sk-ai-cat.js', SK_CORE_FILE ),
			[ 'jquery' ],
			SK_CORE_VERSION,
			true
		);

		wp_localize_script( 'sk-ai-cat', 'skAiCat', [
			'ajaxurl'         => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( 'skai_suggest' ),
			'autoApply'       => (bool) get_option( 'skai_auto_apply', 0 ),
			'uncategorizedId' => (function () {
				$t = get_term_by( 'slug', 'unkategorisiert', 'product_cat' );
				return $t ? (int) $t->term_id : 15;
			})(),
			'strings' => [
				'loading'    => __( 'Kategorie wird analysiert...', 'sk-core' ),
				'suggestion' => __( 'KI-Vorschlag:', 'sk-core' ),
				'apply'      => __( 'Übernehmen', 'sk-core' ),
				'applied'    => __( 'Kategorie übernommen', 'sk-core' ),
				'dismiss'    => __( 'Ignorieren', 'sk-core' ),
				'error'      => __( 'Kein Vorschlag verfügbar.', 'sk-core' ),
			],
		] );

		wp_add_inline_style( 'sk-theme', '
			#skai-box{display:none;margin-top:8px}
			#skai-box.visible{display:block}
			.skai-content{display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-size:13px;color:#c9d4e0}
			.skai-content .skai-label{opacity:.75}
			.skai-content strong{color:#fff}
			#skai-apply{background:#f05025;color:#fff;border:none;border-radius:4px;padding:5px 12px;cursor:pointer;font-size:12px;font-weight:600}
			#skai-apply:hover{background:#d94420}
			#skai-dismiss{background:none;border:none;color:#6b7f96;cursor:pointer;font-size:16px;line-height:1;padding:0 4px}
			#skai-dismiss:hover{color:#fff}
			.skai-spinner{display:inline-block;width:14px;height:14px;border:2px solid #3a4f66;border-top-color:#f05025;border-radius:50%;animation:skai-spin .7s linear infinite}
			@keyframes skai-spin{to{transform:rotate(360deg)}}
		' );
	}

	// ── AJAX Handler ───────────────────────────────────────────────────────────

	public function handle_suggest(): void {
		check_ajax_referer( 'skai_suggest', 'nonce' );

		if ( ! get_option( 'skai_enabled', 0 ) ) {
			wp_send_json_error( [ 'message' => 'disabled' ] );
		}

		$api_key = get_option( 'skai_api_key', '' );
		if ( empty( $api_key ) ) {
			wp_send_json_error( [ 'message' => 'no_api_key' ] );
		}

		$title       = sanitize_text_field( $_POST['title'] ?? '' );
		$description = sanitize_textarea_field( $_POST['description'] ?? '' );

		if ( strlen( $title ) < 3 ) {
			wp_send_json_error( [ 'message' => 'title_too_short' ] );
		}

		if ( ! class_exists( '\SK\Core\ProductCategory\Helper' ) ) {
			wp_send_json_error( [ 'message' => 'no_helper' ] );
		}

		$tree = \SK\Core\ProductCategory\Helper::get_product_categories_tree();
		if ( empty( $tree ) ) {
			wp_send_json_error( [ 'message' => 'no_categories' ] );
		}

		$flat = $this->flatten_tree( $tree );
		if ( empty( $flat ) ) {
			wp_send_json_error( [ 'message' => 'no_categories' ] );
		}

		$category_list = implode( "\n", array_map(
			static fn( $c ) => "ID {$c['term_id']}: {$c['path']}",
			$flat
		) );

		$prompt = "Produkttitel: {$title}";
		if ( ! empty( $description ) ) {
			$desc_short = mb_substr( wp_strip_all_tags( $description ), 0, 300 );
			$prompt    .= "\nBeschreibung: {$desc_short}";
		}
		$prompt .= "\n\nVerfügbare Kategorien:\n{$category_list}";

		$result = $this->call_claude( $api_key, $prompt );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		$valid_ids = array_column( $flat, 'term_id' );
		if ( ! in_array( $result['term_id'], $valid_ids, true ) ) {
			wp_send_json_error( [ 'message' => 'invalid_term_id' ] );
		}

		wp_send_json_success( $result );
	}

	private function call_claude( string $api_key, string $prompt ): array|\WP_Error {
		$model  = get_option( 'skai_model', 'claude-haiku-4-5-20251001' );
		$system = 'Du bist ein Kategorisierungs-Assistent für einen Bitcoin-Marktplatz (Kleinanzeigen). '
			. 'Wähle die am besten passende Kategorie aus der Liste für das gegebene Produkt. '
			. 'Antworte NUR mit einem JSON-Objekt: {"term_id": <zahl>, "term_name": "<name>", "path": "<Eltern > Kind>"}. '
			. 'Keine Erklärung, kein Text außer dem JSON.';

		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
			'timeout' => 20,
			'headers' => [
				'x-api-key'        => $api_key,
				'anthropic-version' => '2023-06-01',
				'content-type'     => 'application/json',
			],
			'body' => wp_json_encode( [
				'model'      => $model,
				'max_tokens' => 100,
				'system'     => $system,
				'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
			] ),
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$msg = $body['error']['message'] ?? "HTTP $code";
			return new \WP_Error( 'claude_error', $msg );
		}

		$text = $body['content'][0]['text'] ?? '';

		if ( preg_match( '/\{[^}]+\}/s', $text, $m ) ) {
			$data = json_decode( $m[0], true );
			if ( isset( $data['term_id'], $data['term_name'] ) ) {
				return [
					'term_id'   => (int) $data['term_id'],
					'term_name' => sanitize_text_field( $data['term_name'] ),
					'path'      => sanitize_text_field( $data['path'] ?? $data['term_name'] ),
				];
			}
		}

		return new \WP_Error( 'parse_error', 'Could not parse Claude response' );
	}

	private function flatten_tree( array $tree, string $parent_path = '' ): array {
		$flat = [];
		foreach ( $tree as $cat ) {
			$path = $parent_path ? "{$parent_path} > {$cat['label']}" : $cat['label'];
			if ( ! empty( $cat['children'] ) ) {
				$flat = array_merge( $flat, $this->flatten_tree( $cat['children'], $path ) );
			} else {
				$flat[] = [ 'term_id' => (int) $cat['term_id'], 'path' => $path ];
			}
		}
		return $flat;
	}
}
