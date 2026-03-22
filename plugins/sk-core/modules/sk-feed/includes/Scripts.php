<?php

namespace SK\Modules\Feed;

defined( 'ABSPATH' ) || exit;

class Scripts {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function enqueue() {
		if ( ! $this->should_load() ) {
			return;
		}

		wp_enqueue_script(
			'sk-feed',
			SK_FEED_URL . '/assets/js/sk-feed.js',
			[ 'jquery' ],
			SK_FEED_VERSION,
			true
		);

		// Load follow-store script on feed pages for inline follow buttons.
		if ( get_query_var( 'sk_feed_view' ) && wp_script_is( 'sk-follow-store', 'registered' ) ) {
			wp_enqueue_style( 'sk-follow-store' );
			wp_enqueue_script( 'sk-follow-store' );
		}

		wp_localize_script( 'sk-feed', 'skFeed', [
			'ajaxurl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'sk_feed' ),
			'isLoggedIn' => is_user_logged_in(),
			'loginUrl'   => home_url( '/mein-konto/' ),
			'i18n'       => [
				'confirm_delete'  => __( 'Beitrag wirklich löschen?', 'sk-core' ),
				'report_success'  => __( 'Danke für die Meldung.', 'sk-core' ),
				'already_reported' => __( 'Bereits gemeldet.', 'sk-core' ),
				'error'           => __( 'Ein Fehler ist aufgetreten.', 'sk-core' ),
				'loading'         => __( 'Wird geladen…', 'sk-core' ),
				'no_more'         => __( 'Keine weiteren Beiträge.', 'sk-core' ),
			],
		] );
	}

	private function should_load(): bool {
		// Own pages: /community/ and /community/post/{id}/
		if ( get_query_var( 'sk_feed_view' ) ) {
			return true;
		}

		// Store pages (incl. all tabs + feed tab).
		if ( function_exists( 'sk_is_store_page' ) && sk_is_store_page() ) {
			return true;
		}

		if ( function_exists( 'sk_is_store_review_page' ) && sk_is_store_review_page() ) {
			return true;
		}

		if ( get_query_var( 'vendor_feed' ) ) {
			return true;
		}

		// Vendor dashboard.
		if ( function_exists( 'sk_is_seller_dashboard' ) && sk_is_seller_dashboard() ) {
			return true;
		}

		return false;
	}
}
