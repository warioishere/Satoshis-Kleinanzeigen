<?php

namespace SK\Core;

defined( 'ABSPATH' ) || exit;

/**
 * AJAX search box for WooCommerce product categories, via the
 * [woo_kategorie_finder] shortcode.
 *
 * Absorbed from the standalone "Woo Kategorie Finder (AJAX)" plugin;
 * behavior unchanged.
 */
final class CategoryFinder {

	public static function init(): void {
		add_shortcode( 'woo_kategorie_finder', [ __CLASS__, 'render_search_box' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'wp_ajax_wkf_search_categories', [ __CLASS__, 'ajax_search_categories' ] );
		add_action( 'wp_ajax_nopriv_wkf_search_categories', [ __CLASS__, 'ajax_search_categories' ] );
	}

	public static function render_search_box(): string {
		ob_start();
		?>
		<div class="wkf-autocomplete-wrapper">
			<input type="text" id="wkf-cat-search" placeholder="<?php esc_attr_e( 'Kategorie suchen...', 'sk-core' ); ?>">
			<ul id="wkf-results"></ul>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function enqueue_assets(): void {
		wp_enqueue_script(
			'wkf-ajax-script',
			plugins_url( 'assets/js/sk-category-finder.js', SK_CORE_FILE ),
			[ 'jquery' ],
			SK_CORE_VERSION,
			true
		);
		wp_localize_script( 'wkf-ajax-script', 'wkf_ajax_object', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
		] );

		wp_enqueue_style( 'wkf-style', plugins_url( 'assets/css/sk-category-finder.css', SK_CORE_FILE ), [], SK_CORE_VERSION );
	}

	public static function ajax_search_categories(): void {
		$term    = isset( $_POST['term'] ) ? sanitize_text_field( $_POST['term'] ) : '';
		$results = [];

		if ( '' !== $term ) {
			$terms = get_terms( [
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'name__like' => $term,
			] );

			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $t ) {
					$results[] = [
						'name' => $t->name,
						'link' => get_term_link( $t ),
					];
				}
			}
		}

		wp_send_json( $results );
	}
}
