<?php

namespace SK\Core\Dashboard\Modules;

use SK\Core\Dashboard\ChatMessages;

defined( 'ABSPATH' ) || exit;

/**
 * Inbox in the site header, as an element of the Kadence header builder.
 *
 * Deliberately not a hard-coded menu item: Kadence assembles the header from
 * elements that can be dragged into rows and columns in the Customizer —
 * logo, search, cart. The inbox joins that list so its position can be
 * decided on real devices, not here.
 *
 * On mobile that isn't a nicety. The top row already carries a logo up to
 * 233 px wide and a 66 px profile picture: on a 360 px wide screen that
 * leaves 31 px, and on 320 px nothing at all. The second row, by contrast,
 * has over 200 px free — which column is the right one there is a matter of
 * taste, and therefore belongs in the Customizer.
 *
 * The same identifier appears in both lists, the way Kadence itself does it
 * for search; the desktop and mobile headers render the same template.
 */
class InboxHeaderItem {

	/** Identifier of the header element. */
	const ITEM = 'sk-inbox';

	/**
	 * Section behind the gear icon on the element.
	 *
	 * Kadence keeps its sections under a short key and prepends
	 * 'kadence_customizer_' when registering — the reference from the
	 * element list must carry the long name.
	 */
	const SECTION_KEY = 'sk_inbox';
	const SECTION     = 'kadence_customizer_sk_inbox';

	/** Only show when there's actually something unread. */
	const OPTION_ONLY_UNREAD = 'sk_header_inbox_only_unread';

	/**
	 * Outer margin, all four sides — one setting per viewport.
	 *
	 * Kadence doesn't make a control device-aware by giving it its own
	 * toggle; instead a separate control is shown per viewport. Which one
	 * is decided by the switcher at the bottom of the header builder via
	 * the '__device' context.
	 */
	const OPTION_MARGIN = [
		'desktop' => 'sk_header_inbox_margin',
		'tablet'  => 'sk_header_inbox_margin_tablet',
		'mobile'  => 'sk_header_inbox_margin_mobile',
	];

	public function __construct() {
		add_filter( 'kadence_theme_customizer_control_choices', [ $this, 'register_choice' ] );
		add_filter( 'kadence_theme_customizer_sections', [ $this, 'register_section' ] );

		/*
		 * Priority 5: Kadence loads its option files at 1 and builds the
		 * controls at 10. In between is the window where custom settings
		 * can slot in.
		 */
		add_action( 'customize_register', [ $this, 'register_settings' ], 5 );

		add_action( 'wp_enqueue_scripts', [ $this, 'inline_styles' ], 30 );

		/*
		 * Kadence renders a header element via get_template_part(). That
		 * call fires this hook first and then looks for the file in the
		 * theme — it's missing there, so our output is used instead. That
		 * way no file is needed in the theme, which would have to survive
		 * a theme update.
		 */
		add_action( 'get_template_part_template-parts/header/' . self::ITEM, [ $this, 'render' ] );
	}

	/**
	 * Offer the element in the header builder — for desktop and mobile.
	 *
	 * @param array $choices
	 * @return array
	 */
	public function register_choice( $choices ) {
		if ( ! is_array( $choices ) ) {
			return $choices;
		}

		$eintrag = [
			'name'    => __( 'Postfach', 'sk-core' ),
			'section' => self::SECTION,
		];

		foreach ( [ 'header_desktop_items', 'header_mobile_items' ] as $liste ) {
			if ( isset( $choices[ $liste ] ) && is_array( $choices[ $liste ] ) ) {
				$choices[ $liste ][ self::ITEM ] = $eintrag;
			}
		}

		return $choices;
	}

	/**
	 * Register the section in Kadence's directory.
	 *
	 * @param array $sections
	 * @return array
	 */
	public function register_section( $sections ) {
		if ( ! is_array( $sections ) ) {
			return $sections;
		}

		$sections[ self::SECTION_KEY ] = [
			'title'    => __( 'Postfach', 'sk-core' ),
			'panel'    => 'header',
			'priority' => 20,
		];

		return $sections;
	}

	/**
	 * The element's settings: one "General" tab, one "Design" tab.
	 *
	 * Built like the theme's search — a single section where the tabs only
	 * toggle which controls are visible. That saves the second section the
	 * cart needs for the same purpose.
	 */
	public function register_settings(): void {
		if ( ! class_exists( '\Kadence\Theme_Customizer' ) ) {
			return;
		}

		ob_start();
		?>
		<div class="kadence-compontent-tabs nav-tab-wrapper wp-clearfix">
			<a href="#" class="nav-tab kadence-general-tab kadence-compontent-tabs-button nav-tab-active" data-tab="general">
				<span><?php esc_html_e( 'Allgemein', 'sk-core' ); ?></span>
			</a>
			<a href="#" class="nav-tab kadence-design-tab kadence-compontent-tabs-button" data-tab="design">
				<span><?php esc_html_e( 'Design', 'sk-core' ); ?></span>
			</a>
		</div>
		<?php
		$reiter = ob_get_clean();

		\Kadence\Theme_Customizer::add_settings( [
			'sk_header_inbox_tabs'   => [
				'control_type' => 'kadence_blank_control',
				'section'      => self::SECTION_KEY,
				'settings'     => false,
				'priority'     => 1,
				'description'  => $reiter,
			],
			self::OPTION_ONLY_UNREAD => [
				'control_type' => 'kadence_switch_control',
				'section'      => self::SECTION_KEY,
				'sanitize'     => 'kadence_sanitize_toggle',
				'priority'     => 6,
				'default'      => 0,
				'label'        => __( 'Nur bei ungelesenen Nachrichten zeigen', 'sk-core' ),
				'context'      => [
					[
						'setting' => '__current_tab',
						'value'   => 'general',
					],
				],
			],
		] );

		// A separate control per viewport. Only the one whose viewport is
		// selected below is ever visible — so none needs its own toggle,
		// and the label for the four sides stays the same.
		$abstaende = [];

		foreach ( self::OPTION_MARGIN as $ansicht => $schluessel ) {
			$abstaende[ $schluessel ] = [
				'control_type' => 'kadence_measure_control',
				'section'      => self::SECTION_KEY,
				'priority'     => 10,
				'default'      => [
					'size'   => [ '', '', '', '' ],
					'unit'   => 'px',
					'locked' => false,
				],
				'label'        => __( 'Aussenabstand', 'sk-core' ),
				'context'      => [
					[
						'setting' => '__current_tab',
						'value'   => 'design',
					],
					[
						'setting' => '__device',
						'value'   => $ansicht,
					],
				],
				'live_method'  => [
					[
						'type'     => 'css',
						'selector' => '.sk-header-inbox .sk-inbox-link',
						'property' => 'margin',
						'pattern'  => '$',
						'key'      => 'measure',
					],
				],
				'input_attrs'  => [
					'min'        => [ 'px' => 0, 'em' => 0, 'rem' => 0 ],
					'max'        => [ 'px' => 100, 'em' => 6, 'rem' => 6 ],
					'step'       => [ 'px' => 1, 'em' => 0.01, 'rem' => 0.01 ],
					'units'      => [ 'px', 'em', 'rem' ],
					'responsive' => false,
				],
			];
		}

		\Kadence\Theme_Customizer::add_settings( $abstaende );
	}

	/**
	 * Read a value from the theme settings.
	 *
	 * @param string $key
	 * @param mixed  $fallback
	 * @return mixed
	 */
	private function setting( string $key, $fallback ) {
		return function_exists( 'Kadence\kadence' ) ? \Kadence\kadence()->option( $key, $fallback ) : $fallback;
	}

	/**
	 * The configured outer margin of a viewport as a CSS value.
	 *
	 * Deliberately computed here instead of via the theme's render_measure():
	 * that's an internal of the style component, and this class is meant to
	 * survive a theme update.
	 *
	 * @param string $ansicht desktop | tablet | mobile
	 */
	private function margin_css( string $ansicht ): string {
		if ( ! isset( self::OPTION_MARGIN[ $ansicht ] ) ) {
			return '';
		}

		$mass = $this->setting( self::OPTION_MARGIN[ $ansicht ], [] );

		if ( ! is_array( $mass ) || empty( $mass['size'] ) || ! is_array( $mass['size'] ) ) {
			return '';
		}

		$seiten = array_slice( array_pad( $mass['size'], 4, '' ), 0, 4 );

		// Nothing set: then don't output a rule either.
		$gesetzt = array_filter( $seiten, static function ( $wert ) {
			return is_numeric( $wert );
		} );

		if ( empty( $gesetzt ) ) {
			return '';
		}

		/*
		 * Only let real units through. Just filtering out characters wasn't
		 * enough: a malformed value would then not turn into anything
		 * dangerous, but into "5pxbodydisplaynone" — a rule the browser
		 * silently discards.
		 */
		$erlaubt = [ 'px', 'em', 'rem', '%', 'vh', 'vw' ];
		$einheit = isset( $mass['unit'] ) ? strtolower( trim( (string) $mass['unit'] ) ) : 'px';
		$einheit = in_array( $einheit, $erlaubt, true ) ? $einheit : 'px';

		$teile = [];

		foreach ( $seiten as $wert ) {
			$teile[] = is_numeric( $wert ) ? ( 0 + $wert ) . $einheit : '0';
		}

		return implode( ' ', $teile );
	}

	/**
	 * Add the margin to the stylesheet, one rule per viewport.
	 *
	 * Each viewport gets its own condition, including desktop. That's the
	 * difference from the theme, which always outputs the desktop value and
	 * layers the others on top: there, mobile inherits the desktop value as
	 * long as nothing is set. Here, each viewport is meant to stand on its
	 * own — otherwise a margin set on desktop would drag along into mobile
	 * even though nothing was set there.
	 *
	 * The ranges only overlap for tablet and mobile; that's why mobile
	 * comes last and wins there. The breakpoints come from the same filters
	 * Kadence uses, so a shifted boundary applies here too.
	 */
	public function inline_styles(): void {
		if ( ! wp_style_is( 'sk-theme', 'enqueued' ) ) {
			return;
		}

		$auswahl = '.sk-header-inbox .sk-inbox-link';

		$ansichten = [
			'desktop' => apply_filters( 'kadence_desktop_media_query', '(min-width: 1025px)' ),
			'tablet'  => apply_filters( 'kadence_tablet_media_query', '(max-width: 1024px)' ),
			'mobile'  => apply_filters( 'kadence_mobile_media_query', '(max-width: 767px)' ),
		];

		$css = '';

		foreach ( $ansichten as $ansicht => $bedingung ) {
			$margin = $this->margin_css( $ansicht );

			if ( '' === $margin ) {
				continue;
			}

			$css .= '@media ' . $bedingung . '{' . $auswahl . '{margin:' . $margin . ';}}';
		}

		if ( '' !== $css ) {
			wp_add_inline_style( 'sk-theme', $css );
		}
	}

	/**
	 * URL of the chat overview.
	 */
	private function chat_url(): string {
		return function_exists( 'sk_get_navigation_url' )
			? sk_get_navigation_url( 'vendor-chat' )
			: home_url( '/dashboard/vendor-chat/' );
	}

	public function render(): void {
		if ( ! is_user_logged_in() || ! class_exists( ChatMessages::class ) ) {
			return;
		}

		$ungelesen = ChatMessages::unread_total( get_current_user_id() );

		if ( 0 === $ungelesen && $this->setting( self::OPTION_ONLY_UNREAD, 0 ) ) {
			return;
		}

		$titel = $ungelesen > 0
			? sprintf(
				/* translators: %d: number of unread messages. */
				_n( '%d ungelesene Nachricht', '%d ungelesene Nachrichten', $ungelesen, 'sk-core' ),
				$ungelesen
			)
			: __( 'Postfach', 'sk-core' );

		?>
		<div class="site-header-item site-header-focus-item sk-header-inbox" data-section="<?php echo esc_attr( self::SECTION ); ?>">
			<a class="sk-inbox-link<?php echo $ungelesen > 0 ? ' has-unread' : ''; ?>"
			   href="<?php echo esc_url( $this->chat_url() ); ?>"
			   aria-label="<?php echo esc_attr( $titel ); ?>"
			   title="<?php echo esc_attr( $titel ); ?>">
				<i class="fas fa-envelope" aria-hidden="true"></i>
				<?php if ( $ungelesen > 0 ) : ?>
					<span class="sk-inbox-count"><?php echo esc_html( $ungelesen > 99 ? '99+' : (string) $ungelesen ); ?></span>
				<?php endif; ?>
			</a>
		</div><!-- .sk-header-inbox -->
		<?php
	}
}
