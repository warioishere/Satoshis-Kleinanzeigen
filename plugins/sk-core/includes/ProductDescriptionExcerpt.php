<?php

namespace SK\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Shows a word-count excerpt of the full product description under the
 * title on shop/category loops. Absorbed from the standalone
 * "Produktbeschreibung im Shop anzeigen" plugin — its Settings → page is
 * gone, the word count now lives in SK Admin → Settings → General.
 */
final class ProductDescriptionExcerpt {

	/** The legacy option this setting replaces. */
	const LEGACY_OPTION = 'pbs_wortanzahl';

	public static function init(): void {
		self::migrate_legacy_option();

		add_filter( 'sk_settings_general_site_options', [ __CLASS__, 'add_field' ], 9 );
		add_action( 'woocommerce_after_shop_loop_item_title', [ __CLASS__, 'render_excerpt' ], 9 );
	}

	/**
	 * The old scalar option (Settings → Produktbeschreibung Shop) migrated
	 * into the sk_general section once, then dropped.
	 */
	private static function migrate_legacy_option(): void {
		$legacy = get_option( self::LEGACY_OPTION, null );

		if ( null === $legacy ) {
			return;
		}

		$section = get_option( 'sk_general' );
		$section = is_array( $section ) ? $section : [];

		if ( ! isset( $section['pbs_wortanzahl'] ) ) {
			$section['pbs_wortanzahl'] = $legacy;
			update_option( 'sk_general', $section );
		}

		delete_option( self::LEGACY_OPTION );
	}

	public static function add_field( $settings_fields ) {
		$settings_fields['pbs_wortanzahl'] = [
			'name'    => 'pbs_wortanzahl',
			'label'   => __( 'Wortanzahl für Beschreibungsauszug', 'sk-core' ),
			'desc'    => __( 'Wie viele Wörter der Produktbeschreibung auf der Shop- und Kategorieseite angezeigt werden.', 'sk-core' ),
			'type'    => 'number',
			'default' => '20',
		];

		return $settings_fields;
	}

	public static function render_excerpt(): void {
		global $post;

		$anzahl    = (int) sk_get_option( 'pbs_wortanzahl', 'sk_general', 20 );
		$inhalt    = wp_strip_all_tags( $post->post_content );
		$wortliste = explode( ' ', $inhalt );

		if ( count( $wortliste ) > $anzahl ) {
			$inhalt = implode( ' ', array_slice( $wortliste, 0, $anzahl ) ) . '...';
		}

		echo '<div class="produkt-beschreibung-auszug" style="margin-top:5px; color:#ccc; font-size:0.9em;">' . esc_html( $inhalt ) . '</div>';
	}
}
