<?php
/**
 * Ensures geolocation maps show every matching vendor and product across paginated results.
 *
 * Ported from mu-plugin: sk-geolocation-full-markers.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SK_Geolocation_Full_Markers {

	private static $instance = null;
	private $captured_vendor_args = [];
	private $captured_vendor_requested = [];
	private $capture_vendor_args_enabled = true;
	private $vendor_markers_rendered = false;
	private $captured_product_query_vars = [];
	private $capture_product_query_enabled = true;
	private $displayed_product_ids = [];
	private $product_markers_rendered = false;
	private $vendor_hook_removed = false;

	private function __construct() {
		add_filter( 'sk_seller_listing_args', [ $this, 'capture_vendor_listing_args' ], PHP_INT_MAX, 2 );
		add_action( 'init', [ $this, 'maybe_remove_vendor_footer_hook' ], 99 );
		add_action( 'woocommerce_product_query', [ $this, 'capture_product_query_vars' ], PHP_INT_MAX );
		add_action( 'woocommerce_after_shop_loop_item', [ $this, 'store_displayed_product_id' ], 1 );
		add_action( 'wp_footer', [ $this, 'render_all_markers' ], 0 );
	}

	public static function bootstrap() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
	}

	public function capture_vendor_listing_args( $args, $requested_data ) {
		if ( ! $this->capture_vendor_args_enabled ) {
			return $args;
		}
		$this->captured_vendor_args      = $args;
		$this->captured_vendor_requested = is_array( $requested_data ) ? $requested_data : [];
		return $args;
	}

	public function maybe_remove_vendor_footer_hook() {
		if ( $this->vendor_hook_removed ) {
			return;
		}
		if ( ! class_exists( 'SK_Geolocation_Vendor_View' ) ) {
			return;
		}
		remove_action( 'sk_seller_listing_footer_content', [ 'SK_Geolocation_Vendor_View', 'seller_listing_footer_content' ], 11 );
		$this->vendor_hook_removed = true;
	}

	public function capture_product_query_vars( $query ) {
		if ( is_admin() || ! $this->capture_product_query_enabled ) {
			return;
		}
		if ( ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return;
		}
		if ( ! $this->is_product_map_enabled() ) {
			return;
		}
		$this->captured_product_query_vars = $query->query_vars;
	}

	public function store_displayed_product_id() {
		if ( ! $this->is_product_map_enabled() ) {
			return;
		}
		$product_id = get_the_ID();
		if ( $product_id ) {
			$this->displayed_product_ids[ $product_id ] = true;
		}
	}

	public function render_all_markers() {
		if ( is_admin() || ! function_exists( 'sk' ) ) {
			return;
		}

		$vendor_markup  = $this->build_vendor_marker_markup();
		$product_markup = $this->build_product_marker_markup();

		if ( '' === $vendor_markup && '' === $product_markup ) {
			return;
		}

		echo '<div class="sk-geolocation-all-markers" hidden>';
		echo $vendor_markup;
		echo $product_markup;
		echo '</div>';
	}

	private function build_vendor_marker_markup() {
		if ( $this->vendor_markers_rendered ) {
			return '';
		}
		if ( ! function_exists( 'sk_is_store_listing' ) || ! sk_is_store_listing() ) {
			return '';
		}
		if ( ! class_exists( 'SK_Geolocation_Vendor_View' ) ) {
			return '';
		}
		if ( ! SK_Geolocation_Vendor_View::is_geolocation_show_on_store_listing_page() ) {
			return '';
		}
		if ( ! class_exists( 'SK\\Core\\Vendor\\Vendor' ) ) {
			return '';
		}
		if ( empty( $this->captured_vendor_args ) ) {
			return '';
		}

		$args      = $this->captured_vendor_args;
		$requested = $this->captured_vendor_requested;
		$args['number'] = -1;
		$args['offset'] = 0;

		$this->capture_vendor_args_enabled = false;
		$args = apply_filters( 'sk_seller_listing_args', $args, $requested );
		$this->capture_vendor_args_enabled = true;

		$sellers = sk_get_sellers( $args );

		if ( empty( $sellers['users'] ) ) {
			return '';
		}

		$output = '';

		foreach ( $sellers['users'] as $seller ) {
			$seller_id = $seller->ID ?? 0;
			$lat = get_user_meta( $seller_id, 'sk_geo_latitude', true );
			$lng = get_user_meta( $seller_id, 'sk_geo_longitude', true );

			if ( empty( $lat ) || empty( $lng ) ) {
				continue;
			}

			$vendor = new SK\Core\Vendor\Vendor( $seller );

			$info_window_data = [
				'title'   => $vendor->get_shop_name(),
				'link'    => sk_get_store_url( $vendor->get_id() ),
				'image'   => $vendor->get_avatar(),
				'address' => get_user_meta( $vendor->get_id(), 'sk_geo_address', true ),
			];

			$info = apply_filters( 'sk_geolocation_info_vendor', $info_window_data, $vendor );

			$args = [
				'id'               => $seller_id,
				'sk_geo_latitude'  => $lat,
				'sk_geo_longitude' => $lng,
				'sk_geo_address'   => get_user_meta( $vendor->get_id(), 'sk_geo_address', true ),
				'info'             => wp_json_encode( $info ),
			];

			ob_start();
			sk_geo_get_template( 'item-geolocation-data', $args );
			$output .= ob_get_clean();
		}

		$this->vendor_markers_rendered = true;
		return $output;
	}

	private function build_product_marker_markup() {
		if ( $this->product_markers_rendered ) {
			return '';
		}
		if ( ! $this->is_product_map_enabled() ) {
			return '';
		}
		if ( empty( $this->captured_product_query_vars ) ) {
			return '';
		}
		if ( ! function_exists( 'wc_get_product' ) ) {
			return '';
		}

		$query_vars                  = $this->captured_product_query_vars;
		$query_vars['posts_per_page'] = -1;
		$query_vars['paged']          = 1;
		$query_vars['offset']         = 0;
		$query_vars['fields']         = 'ids';
		$query_vars['no_found_rows']  = true;

		$this->capture_product_query_enabled = false;

		$query = new WP_Query();
		$query->parse_query( $query_vars );

		if ( function_exists( 'WC' ) && WC()->query ) {
			do_action( 'woocommerce_product_query', $query, WC()->query );
		}

		$product_ids = $query->get_posts();
		wp_reset_postdata();

		$this->capture_product_query_enabled = true;

		if ( empty( $product_ids ) || ! is_array( $product_ids ) ) {
			return '';
		}

		$skipped_ids = array_keys( $this->displayed_product_ids );
		if ( ! empty( $skipped_ids ) ) {
			$product_ids = array_diff( $product_ids, $skipped_ids );
		}

		if ( empty( $product_ids ) ) {
			return '';
		}

		$output = '';

		foreach ( $product_ids as $product_id ) {
			$latitude  = get_post_meta( $product_id, 'sk_geo_latitude', true );
			$longitude = get_post_meta( $product_id, 'sk_geo_longitude', true );

			if ( empty( $latitude ) || empty( $longitude ) ) {
				continue;
			}

			$address = get_post_meta( $product_id, 'sk_geo_address', true );
			$post    = get_post( $product_id );
			$product = wc_get_product( $product_id );

			if ( ! $post || ! $product ) {
				continue;
			}

			$image_src = wp_get_attachment_image_src( $product->get_image_id(), 'full' );
			$image     = ! empty( $image_src[0] ) ? $image_src[0] : wc_placeholder_img_src();

			$info_window_data = [
				'title'   => $post->post_title,
				'link'    => get_permalink( $product_id ),
				'image'   => $image,
				'address' => $address,
			];

			$info = apply_filters( 'sk_geolocation_info_product', $info_window_data, $post, $product );

			$args = [
				'id'               => $product_id,
				'sk_geo_latitude'  => $latitude,
				'sk_geo_longitude' => $longitude,
				'sk_geo_address'   => $address,
				'info'             => wp_json_encode( $info ),
			];

			ob_start();
			sk_geo_get_template( 'item-geolocation-data', $args );
			$output .= ob_get_clean();
		}

		$this->product_markers_rendered = true;
		return $output;
	}

	private function is_product_map_enabled() {
		$show_map_pages = sk_get_option( 'show_location_map_pages', 'sk_geolocation', 'shop' );
		return ( is_shop() || is_product_taxonomy() ) && in_array( $show_map_pages, [ 'shop', 'all' ], true );
	}
}
