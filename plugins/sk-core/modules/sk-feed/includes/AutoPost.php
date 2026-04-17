<?php

namespace SK\Modules\Feed;

defined( 'ABSPATH' ) || exit;

class AutoPost {

	public function __construct() {
		add_action( 'transition_post_status', [ $this, 'on_product_publish' ], 20, 3 );
		add_action( 'transition_post_status', [ $this, 'on_gesuch_publish' ], 20, 3 );
	}

	public function on_product_publish( string $new_status, string $old_status, \WP_Post $post ) {
		if ( $new_status !== 'publish' || $old_status === 'publish' ) {
			return;
		}

		if ( $post->post_type !== 'product' ) {
			return;
		}

		$vendor_id = (int) $post->post_author;

		// Check vendor opt-in (default: on).
		$auto_post = get_user_meta( $vendor_id, 'sk_feed_auto_post', true );
		if ( $auto_post === 'off' ) {
			return;
		}

		// Prevent duplicate auto-posts for same product.
		$existing = get_posts( [
			'post_type'   => PostType::POST_TYPE,
			'author'      => $vendor_id,
			'meta_key'    => '_sk_feed_product_id',
			'meta_value'  => $post->ID,
			'fields'      => 'ids',
			'numberposts' => 1,
		] );

		if ( ! empty( $existing ) ) {
			return;
		}

		$product_url   = get_permalink( $post->ID );
		$product_title = $post->post_title;

		$content = sprintf(
			"Neues Produkt: <strong>%s</strong>",
			esc_html( $product_title )
		);

		$feed_post_id = wp_insert_post( [
			'post_type'    => PostType::POST_TYPE,
			'post_status'  => 'publish',
			'post_content' => $content,
			'post_author'  => $vendor_id,
		] );

		if ( ! is_wp_error( $feed_post_id ) ) {
			update_post_meta( $feed_post_id, '_sk_feed_type', 'product_announce' );
			update_post_meta( $feed_post_id, '_sk_feed_product_id', $post->ID );

			// Copy product thumbnail.
			$thumb_id = get_post_thumbnail_id( $post->ID );
			if ( $thumb_id ) {
				set_post_thumbnail( $feed_post_id, $thumb_id );
			}
		}
	}

	public function on_gesuch_publish( string $new_status, string $old_status, \WP_Post $post ) {
		if ( $new_status !== 'publish' || $old_status === 'publish' ) {
			return;
		}

		if ( $post->post_type !== 'gesuch' ) {
			return;
		}

		$vendor_id = (int) $post->post_author;

		$auto_post = get_user_meta( $vendor_id, 'sk_feed_auto_post', true );
		if ( $auto_post === 'off' ) {
			return;
		}

		$existing = get_posts( [
			'post_type'   => PostType::POST_TYPE,
			'author'      => $vendor_id,
			'meta_key'    => '_sk_feed_gesuch_id',
			'meta_value'  => $post->ID,
			'fields'      => 'ids',
			'numberposts' => 1,
		] );

		if ( ! empty( $existing ) ) {
			return;
		}

		$content = sprintf(
			"Neues Gesuch: <strong>%s</strong>",
			esc_html( $post->post_title )
		);

		$feed_post_id = wp_insert_post( [
			'post_type'    => PostType::POST_TYPE,
			'post_status'  => 'publish',
			'post_content' => $content,
			'post_author'  => $vendor_id,
		] );

		if ( ! is_wp_error( $feed_post_id ) ) {
			update_post_meta( $feed_post_id, '_sk_feed_type', 'gesuch_announce' );
			update_post_meta( $feed_post_id, '_sk_feed_gesuch_id', $post->ID );

			$thumb_id = get_post_thumbnail_id( $post->ID );
			if ( $thumb_id ) {
				set_post_thumbnail( $feed_post_id, $thumb_id );
			}
		}
	}
}
