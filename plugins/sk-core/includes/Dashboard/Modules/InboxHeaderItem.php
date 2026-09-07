<?php

namespace SK\Core\Dashboard\Modules;

use SK\Core\Dashboard\ChatMessages;

defined( 'ABSPATH' ) || exit;

/**
 * Postfach im Seitenkopf, als Element des Kadence-Kopfbaukastens.
 *
 * Bewusst kein fest eingebauter Menuepunkt: Kadence setzt den Kopf aus
 * Elementen zusammen, die sich im Customizer in Reihen und Spalten ziehen
 * lassen — Logo, Suche, Warenkorb. Das Postfach reiht sich dort ein, damit
 * sich seine Position an echten Geraeten entscheiden laesst und nicht hier.
 *
 * Auf dem Handy ist das keine Feinheit. Die obere Reihe traegt bereits das bis
 * zu 233 px breite Logo und das 66 px grosse Profilbild: auf einem 360 px
 * breiten Bildschirm bleiben davon 31 px uebrig, auf 320 px gar nichts. Die
 * zweite Reihe hat dagegen ueber 200 px frei — welche Spalte dort die richtige
 * ist, ist Geschmackssache und gehoert deshalb in den Customizer.
 *
 * Derselbe Bezeichner steht in beiden Listen, wie es Kadence bei der Suche
 * selbst haelt; Desktop- und Handykopf zeigen dieselbe Vorlage.
 */
class InboxHeaderItem {

	/** Bezeichner des Kopf-Elements. */
	const ITEM = 'sk-inbox';

	/**
	 * Bereich hinter dem Zahnrad am Element.
	 *
	 * Kadence fuehrt seine Bereiche unter einem kurzen Schluessel und haengt
	 * beim Registrieren 'kadence_customizer_' davor — der Verweis aus der
	 * Elementliste muss den langen Namen tragen.
	 */
	const SECTION_KEY = 'sk_inbox';
	const SECTION     = 'kadence_customizer_sk_inbox';

	/** Nur zeigen, wenn wirklich etwas ungelesen ist. */
	const OPTION_ONLY_UNREAD = 'sk_header_inbox_only_unread';

	/**
	 * Aussenabstand, alle vier Seiten — je Ansicht eine eigene Einstellung.
	 *
	 * Kadence macht einen Regler nicht dadurch geraeteabhaengig, dass er eine
	 * eigene Umschaltung mitbringt, sondern indem je Ansicht ein eigener
	 * Regler eingeblendet wird. Welcher, entscheidet der Umschalter unten im
	 * Kopfbaukasten ueber den Kontext '__device'.
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
		 * Priorität 5: Kadence liest seine Optionsdateien auf 1 ein und baut
		 * die Bedienelemente auf 10. Dazwischen ist das Fenster, in dem sich
		 * eigene Einstellungen einreihen lassen.
		 */
		add_action( 'customize_register', [ $this, 'register_settings' ], 5 );

		add_action( 'wp_enqueue_scripts', [ $this, 'inline_styles' ], 30 );

		/*
		 * Kadence rendert ein Kopf-Element ueber get_template_part(). Der
		 * Aufruf feuert zuerst diesen Haken und sucht die Datei danach im
		 * Theme — sie fehlt dort, also bleibt es bei unserer Ausgabe. So
		 * braucht es keine Datei im Theme, die ein Theme-Update ueberleben
		 * muesste.
		 */
		add_action( 'get_template_part_template-parts/header/' . self::ITEM, [ $this, 'render' ] );
	}

	/**
	 * Das Element im Kopfbaukasten anbieten — fuer Desktop und Handy.
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
	 * Den Bereich in Kadences Verzeichnis eintragen.
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
	 * Die Einstellungen des Elements: ein Reiter "Allgemein", einer "Design".
	 *
	 * Aufgebaut wie die Suche im Theme — ein Bereich, in dem die Reiter nur
	 * umschalten, welche Regler sichtbar sind. Das spart den zweiten Bereich,
	 * den der Warenkorb dafuer braucht.
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

		// Je Ansicht ein eigener Regler. Sichtbar ist immer nur der, dessen
		// Ansicht unten gewaehlt ist — deshalb braucht keiner eine eigene
		// Umschaltung, und die Beschriftung der vier Seiten bleibt stehen.
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
	 * Einen Wert aus den Theme-Einstellungen holen.
	 *
	 * @param string $key
	 * @param mixed  $fallback
	 * @return mixed
	 */
	private function setting( string $key, $fallback ) {
		return function_exists( 'Kadence\kadence' ) ? \Kadence\kadence()->option( $key, $fallback ) : $fallback;
	}

	/**
	 * Der eingestellte Aussenabstand einer Ansicht als CSS-Wert.
	 *
	 * Bewusst selbst gerechnet statt ueber render_measure() des Themes: das
	 * ist eine Innerei der Stil-Komponente, und diese Klasse soll ein
	 * Theme-Update ueberstehen.
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

		// Nichts eingetragen: dann auch keine Regel ausgeben.
		$gesetzt = array_filter( $seiten, static function ( $wert ) {
			return is_numeric( $wert );
		} );

		if ( empty( $gesetzt ) ) {
			return '';
		}

		/*
		 * Nur echte Einheiten durchlassen. Zeichen bloss herauszufiltern
		 * genuegte nicht: aus einem verunglueckten Wert wurde dann zwar nichts
		 * Gefaehrliches, aber "5pxbodydisplaynone" — eine Regel, die der
		 * Browser stillschweigend verwirft.
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
	 * Den Abstand ins Stylesheet nachreichen, je Ansicht eine Regel.
	 *
	 * Reihenfolge und Umbruchpunkte wie im Theme: der Desktopwert steht ohne
	 * Bedingung, danach Tablet, danach Handy. Beide Bedingungen greifen auf
	 * einem Telefon, die spaetere gewinnt. Die Umbruchpunkte kommen ueber
	 * dieselben Filter wie bei Kadence, damit eine verschobene Grenze auch
	 * hier gilt.
	 */
	public function inline_styles(): void {
		if ( ! wp_style_is( 'sk-theme', 'enqueued' ) ) {
			return;
		}

		$auswahl = '.sk-header-inbox .sk-inbox-link';

		$ansichten = [
			'desktop' => '',
			'tablet'  => apply_filters( 'kadence_tablet_media_query', '(max-width: 1024px)' ),
			'mobile'  => apply_filters( 'kadence_mobile_media_query', '(max-width: 767px)' ),
		];

		$css = '';

		foreach ( $ansichten as $ansicht => $bedingung ) {
			$margin = $this->margin_css( $ansicht );

			if ( '' === $margin ) {
				continue;
			}

			$regel = $auswahl . '{margin:' . $margin . ';}';
			$css  .= '' === $bedingung ? $regel : '@media ' . $bedingung . '{' . $regel . '}';
		}

		if ( '' !== $css ) {
			wp_add_inline_style( 'sk-theme', $css );
		}
	}

	/**
	 * Adresse der Chatuebersicht.
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
				/* translators: %d: Anzahl ungelesener Nachrichten. */
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
