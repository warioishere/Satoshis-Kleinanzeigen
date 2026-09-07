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

	/** Customizer-Bereich hinter dem Zahnrad am Element. */
	const SECTION = 'sk_customizer_header_inbox';

	/** Nur zeigen, wenn wirklich etwas ungelesen ist. */
	const OPTION_ONLY_UNREAD = 'sk_header_inbox_only_unread';

	public function __construct() {
		add_filter( 'kadence_theme_customizer_control_choices', [ $this, 'register_choice' ] );
		add_action( 'customize_register', [ $this, 'register_section' ], 30 );

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
	 * Der Bereich hinter dem Zahnrad. Ohne ihn liefe der Knopf am Element ins
	 * Leere.
	 *
	 * @param \WP_Customize_Manager $wp_customize
	 */
	public function register_section( $wp_customize ): void {
		$wp_customize->add_section( self::SECTION, [
			'title'    => __( 'Postfach', 'sk-core' ),
			'panel'    => 'kadence_customizer_header',
			'priority' => 20,
		] );

		$wp_customize->add_setting( self::OPTION_ONLY_UNREAD, [
			'default'           => 0,
			'type'              => 'option',
			'sanitize_callback' => static function ( $wert ) {
				return $wert ? 1 : 0;
			},
			'transport'         => 'refresh',
		] );

		$wp_customize->add_control( self::OPTION_ONLY_UNREAD, [
			'section'     => self::SECTION,
			'label'       => __( 'Nur bei ungelesenen Nachrichten zeigen', 'sk-core' ),
			'description' => __( 'Sonst steht das Symbol immer da, auch wenn nichts wartet.', 'sk-core' ),
			'type'        => 'checkbox',
		] );
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

		if ( 0 === $ungelesen && get_option( self::OPTION_ONLY_UNREAD, 0 ) ) {
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
