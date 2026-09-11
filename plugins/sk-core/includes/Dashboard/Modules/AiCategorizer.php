<?php

namespace SK\Core\Dashboard\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * AI Categorizer — automatic product category suggestions via Claude API.
 *
 * Ported from plugin: sk-ai-categorizer
 */
class AiCategorizer {

	/** Settings section of its own, right after Produktbewerbung. */
	const SECTION = 'sk_ai_categorizer';

	public function __construct() {
		self::migrate_legacy_options();
		self::migrate_section();

		add_filter( 'sk_settings_sections', [ $this, 'add_section' ], 22 );
		add_filter( 'sk_settings_fields', [ $this, 'add_fields' ], 22 );
		add_filter( 'sk_save_settings_value', [ __CLASS__, 'strip_api_key' ], 10, 2 );
		add_action( 'wp_ajax_skai_suggest', [ $this, 'handle_suggest' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	// ── Admin Settings ─────────────────────────────────────────────────────────

	/**
	 * The old scalar options (Settings → KI Kategorisierung) migrated into
	 * the sk_product_advertisement section once, then dropped.
	 */
	private static function migrate_legacy_options(): void {
		$legacy_keys = [ 'skai_enabled', 'skai_api_key', 'skai_model', 'skai_auto_apply' ];

		if ( null === get_option( 'skai_enabled', null ) ) {
			return;
		}

		$section = get_option( self::SECTION );
		$section = is_array( $section ) ? $section : [];

		if ( ! isset( $section['skai_enabled'] ) ) {
			$section['skai_enabled']    = get_option( 'skai_enabled', 0 ) ? 'on' : 'off';
			self::store_api_key( (string) get_option( 'skai_api_key', '' ) );
			$section['skai_model']      = get_option( 'skai_model', 'claude-haiku-4-5-20251001' );
			$section['skai_auto_apply'] = get_option( 'skai_auto_apply', 0 ) ? 'on' : 'off';
			update_option( self::SECTION, $section );
		}

		foreach ( $legacy_keys as $key ) {
			delete_option( $key );
		}
	}

	/**
	 * The settings used to sit inside the Produktbewerbung section; they are
	 * carried over into the section of their own once.
	 */
	private static function migrate_section(): void {
		if ( is_array( get_option( self::SECTION, null ) ) ) {
			return;
		}

		$old = get_option( 'sk_product_advertisement' );
		$old = is_array( $old ) ? $old : [];
		$new = [];

		foreach ( [ 'skai_enabled', 'skai_model', 'skai_auto_apply' ] as $key ) {
			if ( isset( $old[ $key ] ) ) {
				$new[ $key ] = $old[ $key ];
				unset( $old[ $key ] );
			}
		}

		// The key itself moves into the encrypted option, see api_key().
		self::store_api_key( (string) ( $old['skai_api_key'] ?? '' ) );
		unset( $old['skai_api_key'], $old['skai_header'] );

		update_option( self::SECTION, $new );
		update_option( 'sk_product_advertisement', $old );
	}

	public function add_section( $sections ) {
		$sections[] = [
			'id'                   => self::SECTION,
			'title'                => __( 'KI Kategorisierung', 'sk-core' ),
			'icon_url'             => '',
			'description'          => __( 'Kategorievorschlag über Claude AI', 'sk-core' ),
			'settings_title'       => __( 'KI Kategorisierung', 'sk-core' ),
			'settings_description' => __( 'Analysiert Produkttitel und -beschreibung mit Claude AI und schlägt automatisch die passende Kategorie vor.', 'sk-core' ),
		];

		return $sections;
	}

	/** Option holding the encrypted API key. */
	const KEY_OPTION = 'skai_api_key_encrypted';

	/**
	 * Keeps the API key out of the settings section: a submitted key is
	 * stored encrypted on its own, an empty field leaves the stored one alone.
	 */
	public static function strip_api_key( $value, $option_name ) {
		if ( self::SECTION === $option_name && is_array( $value ) && array_key_exists( 'skai_api_key', $value ) ) {
			self::store_api_key( (string) $value['skai_api_key'] );
			$value['skai_api_key'] = '';
		}

		return $value;
	}

	public static function store_api_key( string $key ): void {
		$key = trim( $key );

		if ( '' === $key ) {
			return;
		}

		$encrypted = \SK\Core\Secret::encrypt( $key, \SK\Core\Secret::API_KEY );

		if ( '' !== $encrypted ) {
			update_option( self::KEY_OPTION, $encrypted );
		}
	}

	/**
	 * The API key, decrypted.
	 *
	 * A key still sitting in the settings section in plain text is moved into
	 * the encrypted option the first time it is read, and blanked there.
	 */
	public static function api_key(): string {
		$key = \SK\Core\Secret::from_option( self::KEY_OPTION, \SK\Core\Secret::API_KEY );

		if ( '' !== $key ) {
			return $key;
		}

		$section = get_option( 'sk_product_advertisement' );
		$plain   = is_array( $section ) ? trim( (string) ( $section['skai_api_key'] ?? '' ) ) : '';

		if ( '' === $plain ) {
			return '';
		}

		self::store_api_key( $plain );
		$section['skai_api_key'] = '';
		update_option( 'sk_product_advertisement', $section );

		return $plain;
	}

	public function add_fields( $fields ) {
		$fields[ self::SECTION ] = [
			'skai_enabled' => [
				'name'    => 'skai_enabled',
				'label'   => __( 'KI-Kategorisierung aktivieren', 'sk-core' ),
				'type'    => 'switcher',
				'default' => 'off',
			],
			'skai_api_key' => [
				'name'        => 'skai_api_key',
				'label'       => __( 'Claude API Key', 'sk-core' ),
				'type'        => 'text',
				'default'     => '',
				'placeholder' => self::api_key() !== '' ? 'sk-ant-******** (gespeichert)' : 'sk-ant-…',
				'desc'        => self::api_key() !== ''
					? __( 'Der Schlüssel ist verschlüsselt gespeichert. Neuen Schlüssel eingeben zum Ändern, leer lassen zum Beibehalten.', 'sk-core' )
					: __( 'API Key von console.anthropic.com. Wird verschlüsselt gespeichert.', 'sk-core' ),
			],
			'skai_model' => [
				'name'    => 'skai_model',
				'label'   => __( 'Claude Modell', 'sk-core' ),
				'type'    => 'select',
				'options' => [
					'claude-haiku-4-5-20251001' => __( 'Claude Haiku 4.5 (schnell, günstig — empfohlen)', 'sk-core' ),
					'claude-sonnet-4-6'         => __( 'Claude Sonnet 4.6 (besser, teurer)', 'sk-core' ),
					'claude-opus-4-6'           => __( 'Claude Opus 4.6 (bestes Modell)', 'sk-core' ),
				],
				'default' => 'claude-haiku-4-5-20251001',
			],
			'skai_auto_apply' => [
				'name'    => 'skai_auto_apply',
				'label'   => __( 'Automatisch anwenden', 'sk-core' ),
				'type'    => 'switcher',
				'default' => 'off',
				'desc'    => __( 'Kategorie direkt eintragen, ohne Bestätigung durch den Anbieter. Wenn deaktiviert, wird nur ein Vorschlag angezeigt.', 'sk-core' ),
			],
		];

		return $fields;
	}

	// ── Frontend Assets ────────────────────────────────────────────────────────

	public function enqueue_assets(): void {
		if ( sk_get_option( 'skai_enabled', self::SECTION, 'off' ) !== 'on' ) {
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
			'autoApply'       => sk_get_option( 'skai_auto_apply', self::SECTION, 'off' ) === 'on',
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

		if ( sk_get_option( 'skai_enabled', self::SECTION, 'off' ) !== 'on' ) {
			wp_send_json_error( [ 'message' => 'disabled' ] );
		}

		$api_key = self::api_key();
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
		$model  = sk_get_option( 'skai_model', self::SECTION, 'claude-haiku-4-5-20251001' );
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
