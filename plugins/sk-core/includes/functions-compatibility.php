<?php

/**
 * WooCommerce compatibility wrappers.
 * Cleaned up: WC 2.6 backward-compatibility branches removed (plugin requires WC 8.5+).
 */

function sk_wc_get_product( $product ) {
	return wc_get_product( $product );
}

function sk_get_prop( $object, $prop, $callback = false ) {
	$fn_name = $callback ? $callback : 'get_' . $prop;
	return $object->$fn_name();
}

function sk_replace_func( $old_method, $new_method, $object = null ) {
	return $object ? $object->$new_method() : call_user_func( $new_method );
}

function sk_get_date_created( $order ) {
	return wc_format_datetime( $order->get_date_created(), wc_date_format() . ', ' . wc_time_format() );
}

function sk_get_metadata( $order, $item_id ) {
	$item = new WC_Order_Item( $order );
	return $item->get_meta_data();
}

function sk_get_product_downloads( $product ) {
	return $product->get_downloads();
}

/**
 * Save variation product price.
 */
function sk_save_product_price( $product_id, $regular_price, $sale_price = '', $date_from = '', $date_to = '' ) {
	$product = wc_get_product( absint( $product_id ) );

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$regular_price = wc_format_decimal( $regular_price );
	$sale_price    = '' === $sale_price ? '' : wc_format_decimal( $sale_price );
	$date_from     = wc_clean( $date_from );
	$date_to       = wc_clean( $date_to );
	$now           = sk_current_datetime();

	$product->set_regular_price( $regular_price );
	$product->set_sale_price( $sale_price );

	if ( $date_from && $date_to ) {
		$product->set_date_on_sale_from( $now->modify( $date_from )->modify( 'today' )->getTimestamp() );
		$product->set_date_on_sale_to( $now->modify( $date_to )->setTime( 23, 59, 59 )->getTimestamp() );
	}

	if ( $date_to && ! $date_from ) {
		$product->set_date_on_sale_from( $now->getTimestamp() );
	}

	if ( '' !== $sale_price && '' === $date_to && '' === $date_from ) {
		$product->set_price( $sale_price );
	} else {
		$product->set_price( $regular_price );
	}

	if ( '' !== $sale_price && $date_from && $now->modify( $date_from )->getTimestamp() < $now->getTimestamp() ) {
		$product->set_price( $sale_price );
	}

	if ( $date_to && $now->modify( $date_to )->getTimestamp() < $now->getTimestamp() ) {
		$product->set_price( $regular_price );
		$product->set_date_on_sale_from();
		$product->set_date_on_sale_to();
	}

	$product->save();
}

/**
 * Process product files download paths/permissions.
 */
function sk_process_product_file_download_paths_permission( $product_id, $variation_id, $downloadable_files ) {
	global $wpdb;

	if ( $variation_id ) {
		$product_id = $variation_id;
	}

	$product               = wc_get_product( $product_id );
	$existing_download_ids = array_keys( (array) $product->get_downloads() );
	$updated_download_ids  = array_keys( (array) $downloadable_files );

	$new_download_ids     = array_filter( array_diff( $updated_download_ids, $existing_download_ids ) );
	$removed_download_ids = array_filter( array_diff( $existing_download_ids, $updated_download_ids ) );

	if ( ! empty( $new_download_ids ) || ! empty( $removed_download_ids ) ) {
		$existing_permissions = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT * from {$wpdb->prefix}woocommerce_downloadable_product_permissions WHERE product_id = %d GROUP BY order_id",
				$product_id
			)
		);

		foreach ( $existing_permissions as $existing_permission ) {
			$order = wc_get_order( $existing_permission->order_id );

			if ( $order && $order->get_id() ) {
				if ( ! empty( $removed_download_ids ) ) {
					foreach ( $removed_download_ids as $download_id ) {
						if ( apply_filters( 'woocommerce_process_product_file_download_paths_remove_access_to_old_file', true, $download_id, $product_id, $order ) ) {
							$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
								$wpdb->prepare(
									"DELETE FROM {$wpdb->prefix}woocommerce_downloadable_product_permissions WHERE order_id = %d AND product_id = %d AND download_id = %s",
									$order->get_id(), $product_id, $download_id
								)
							);
						}
					}
				}
				if ( ! empty( $new_download_ids ) ) {
					foreach ( $new_download_ids as $download_id ) {
						if ( apply_filters( 'woocommerce_process_product_file_download_paths_grant_access_to_new_file', true, $download_id, $product_id, $order ) ) {
							if (
								! $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
									$wpdb->prepare(
										"SELECT 1=1 FROM {$wpdb->prefix}woocommerce_downloadable_product_permissions WHERE order_id = %d AND product_id = %d AND download_id = %s",
										$order->get_id(), $product_id, $download_id
									)
								)
							) {
								wc_downloadable_file_permission( $download_id, $product_id, $order );
							}
						}
					}
				}
			}
		}
	}
}

add_action( 'sk_process_file_download', 'sk_process_product_file_download_paths_permission', 10, 3 );
