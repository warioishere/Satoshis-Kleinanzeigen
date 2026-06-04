<?php

namespace SK\Modules\ProductVotes;

defined( 'ABSPATH' ) || exit;

final class Ajax {

	public function __construct() {
		add_action( 'wp_ajax_sk_product_vote', [ $this, 'cast_vote' ] );
		// No nopriv handler: anonymous can't vote.
	}

	public function cast_vote(): void {
		check_ajax_referer( 'sk_product_vote', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( [ 'message' => __( 'Login erforderlich.', 'sk-core' ) ], 401 );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$value      = isset( $_POST['value'] ) ? (int) $_POST['value'] : 0;

		if ( ! $product_id || ( $value !== 1 && $value !== -1 ) ) {
			wp_send_json_error( [ 'message' => __( 'Ungültige Anfrage.', 'sk-core' ) ], 400 );
		}

		if ( ! Voting::can_vote_on( $product_id, $user_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Du bist nicht berechtigt, dieses Inserat zu bewerten.', 'sk-core' ) ], 403 );
		}

		$persisted = Voting::cast_vote( $product_id, $user_id, $value );
		$counts    = Voting::get_counts( $product_id );
		$show      = Voting::should_show_counts( $product_id );

		wp_send_json_success( [
			'user_vote' => $persisted,
			'hot'       => $counts['hot'],
			'cold'      => $counts['cold'],
			'total'     => $counts['total'],
			'show'      => $show,
		] );
	}
}
