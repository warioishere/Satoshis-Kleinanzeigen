<?php
/**
 * Country filter for geolocation maps (DE, AT, CH).
 *
 * Ported from plugin: sk-geolocation-country-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SK_Geolocation_Country_Filter {

	private $country_keywords = [
		'de' => [
			'names'    => [ 'Deutschland', 'Germany', 'german' ],
			'codes'    => [ 'DE' ],
			'keywords' => [ 'deutschland', 'germany', 'german', 'deutsch' ],
		],
		'at' => [
			'names'    => [ 'Österreich', 'Austria', 'austrian' ],
			'codes'    => [ 'AT' ],
			'keywords' => [ 'österreich', 'austria', 'austrian', 'österreichisch' ],
		],
		'ch' => [
			'names'    => [ 'Schweiz', 'Switzerland', 'swiss' ],
			'codes'    => [ 'CH' ],
			'keywords' => [ 'schweiz', 'switzerland', 'swiss' ],
		],
	];

	private $excluded_locations = [ 'dhaka' ];

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 20 );

		add_shortcode( 'sk_country_filter', [ $this, 'render_shortcode' ] );

		add_action( 'wp_ajax_sk_filter_by_country', [ $this, 'ajax_filter_by_country' ] );
		add_action( 'wp_ajax_nopriv_sk_filter_by_country', [ $this, 'ajax_filter_by_country' ] );
		add_action( 'wp_ajax_sk_filter_products_by_country', [ $this, 'ajax_filter_products_by_country' ] );
		add_action( 'wp_ajax_nopriv_sk_filter_products_by_country', [ $this, 'ajax_filter_products_by_country' ] );

		add_action( 'woocommerce_product_query', [ $this, 'filter_products_by_country' ], 20 );
	}

	public function enqueue_scripts() {
		if ( ! $this->should_load_on_page() ) {
			return;
		}

		wp_enqueue_script(
			'sk-geo-country-filter',
			SK_GEOLOCATION_ASSETS . '/js/sk-geo-country-filter.js',
			[ 'jquery' ],
			SK_GEOLOCATION_VERSION,
			true
		);

		wp_enqueue_style(
			'sk-geo-country-filter',
			SK_GEOLOCATION_ASSETS . '/css/sk-geo-country-filter.css',
			[],
			SK_GEOLOCATION_VERSION
		);

		wp_localize_script( 'sk-geo-country-filter', 'SkCountryFilter', [
			'countries' => $this->get_countries_config(),
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'sk_country_filter' ),
		] );
	}

	private function should_load_on_page() {
		if ( function_exists( 'sk_is_store_listing' ) && sk_is_store_listing() ) {
			return true;
		}
		if ( is_shop() || is_product_taxonomy() ) {
			return true;
		}
		return false;
	}

	private function get_countries_config() {
		return [
			[
				'code'     => 'de',
				'label'    => _x( 'Deutschland', 'country name', 'sk-core' ),
				'flag'     => "\xF0\x9F\x87\xA9\xF0\x9F\x87\xAA",
				'keywords' => $this->country_keywords['de']['keywords'],
			],
			[
				'code'     => 'at',
				'label'    => _x( 'Österreich', 'country name', 'sk-core' ),
				'flag'     => "\xF0\x9F\x87\xA6\xF0\x9F\x87\xB9",
				'keywords' => $this->country_keywords['at']['keywords'],
			],
			[
				'code'     => 'ch',
				'label'    => _x( 'Schweiz', 'country name', 'sk-core' ),
				'flag'     => "\xF0\x9F\x87\xA8\xF0\x9F\x87\xAD",
				'keywords' => $this->country_keywords['ch']['keywords'],
			],
		];
	}

	public function render_shortcode( $atts ) {
		$is_store_listing = function_exists( 'sk_is_store_listing' ) && sk_is_store_listing();
		$is_product_page  = is_shop() || is_product_taxonomy();

		if ( ! $is_store_listing && ! $is_product_page ) {
			return '';
		}

		if ( ! $this->is_geolocation_enabled() ) {
			return '';
		}

		$atts = shortcode_atts( [
			'style'    => 'buttons',
			'show_all' => 'true',
		], $atts, 'sk_country_filter' );

		ob_start();
		?>
		<div class="sk-geo-country-filter-wrapper" id="sk-country-filter">
			<div class="sk-geo-country-filter-container">
				<label class="sk-geo-country-filter-label">
					<?php esc_html_e( 'Nach Land filtern:', 'sk-core' ); ?>
				</label>
				<div class="sk-geo-country-filter-options" data-style="<?php echo esc_attr( $atts['style'] ); ?>">
					<?php if ( 'true' === $atts['show_all'] ) : ?>
						<button class="sk-geo-country-filter-btn sk-geo-country-filter-btn--all active" data-country="all">
							<?php esc_html_e( 'Alle Länder', 'sk-core' ); ?>
						</button>
					<?php endif; ?>

					<?php foreach ( $this->get_countries_config() as $country ) : ?>
						<button
							class="sk-geo-country-filter-btn"
							data-country="<?php echo esc_attr( $country['code'] ); ?>"
							title="<?php echo esc_attr( $country['label'] ); ?>"
						>
							<span class="flag"><?php echo esc_html( $country['flag'] ); ?></span>
							<span class="label"><?php echo esc_html( $country['label'] ); ?></span>
						</button>
					<?php endforeach; ?>
				</div>
				<div class="sk-geo-country-filter-status"></div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function is_geolocation_enabled() {
		if ( ! function_exists( 'sk_get_option' ) ) {
			return false;
		}
		if ( function_exists( 'sk_is_store_listing' ) && sk_is_store_listing() ) {
			return 'on' === sk_get_option( 'show_locations_map', 'sk_geolocation', 'on' );
		}
		if ( is_shop() || is_product_taxonomy() ) {
			$show = sk_get_option( 'show_location_map_pages', 'sk_geolocation', 'shop' );
			return in_array( $show, [ 'shop', 'all' ], true );
		}
		return false;
	}

	public function ajax_filter_by_country() {
		check_ajax_referer( 'sk_country_filter', 'nonce' );

		$country = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : 'all';

		if ( ! function_exists( 'sk_get_sellers' ) ) {
			wp_send_json_error( [ 'message' => 'SK not available' ] );
		}

		$sellers_response = sk_get_sellers( [ 'number' => -1, 'offset' => 0 ] );
		$sellers          = isset( $sellers_response['users'] ) ? $sellers_response['users'] : [];

		if ( empty( $sellers ) ) {
			wp_send_json_error( [ 'message' => 'No sellers found' ] );
		}

		$filtered = [];
		foreach ( $sellers as $seller ) {
			if ( empty( $seller->sk_geo_latitude ) || empty( $seller->sk_geo_longitude ) ) {
				continue;
			}
			if ( $this->is_location_excluded( $seller->ID, 'vendor' ) ) {
				continue;
			}
			if ( 'all' !== $country && $this->extract_vendor_country( $seller->ID ) !== $country ) {
				continue;
			}
			$filtered[] = [
				'id'        => $seller->ID,
				'latitude'  => $seller->sk_geo_latitude,
				'longitude' => $seller->sk_geo_longitude,
				'address'   => isset( $seller->sk_geo_address ) ? $seller->sk_geo_address : '',
				'country'   => $this->extract_vendor_country( $seller->ID ),
			];
		}

		wp_send_json_success( [
			'sellers' => $filtered,
			'country' => $country,
			'count'   => count( $filtered ),
		] );
	}

	public function is_location_excluded( $id, $type = 'vendor' ) {
		$address = '';
		if ( 'vendor' === $type ) {
			$address = get_user_meta( $id, 'sk_geo_address', true );
		} elseif ( 'product' === $type ) {
			$address = get_post_meta( $id, 'sk_geo_address', true );
		}
		if ( empty( $address ) ) {
			return false;
		}
		$address_lower = strtolower( $address );
		foreach ( $this->excluded_locations as $excluded ) {
			if ( strpos( $address_lower, strtolower( $excluded ) ) !== false ) {
				return true;
			}
		}
		return false;
	}

	public function extract_vendor_country( $vendor_id ) {
		$store_settings = sk_get_store_info( $vendor_id );

		if ( isset( $store_settings['address']['country'] ) && ! empty( $store_settings['address']['country'] ) ) {
			$code = strtolower( $store_settings['address']['country'] );
			if ( in_array( $code, [ 'de', 'at', 'ch' ], true ) ) {
				return $code;
			}
		}

		$address = get_user_meta( $vendor_id, 'sk_geo_address', true );
		if ( ! empty( $address ) ) {
			return $this->detect_country_from_address( $address );
		}

		return 'unknown';
	}

	private function detect_country_from_address( $address ) {
		$address_lower = strtolower( $address );
		foreach ( $this->country_keywords as $code => $data ) {
			foreach ( $data['keywords'] as $keyword ) {
				if ( strpos( $address_lower, $keyword ) !== false ) {
					return $code;
				}
			}
		}
		return 'unknown';
	}

	public function filter_products_by_country( $query ) {
		if ( is_admin() || ! is_main_query() ) {
			return;
		}

		$selected = isset( $_GET['sk_country'] ) ? sanitize_text_field( wp_unslash( $_GET['sk_country'] ) ) : '';

		if ( empty( $selected ) || 'all' === $selected ) {
			return;
		}

		$product_ids = $this->get_product_ids_by_country( $selected );

		if ( empty( $product_ids ) ) {
			$query->set( 'post__in', [ 0 ] );
			return;
		}

		$query->set( 'post__in', $product_ids );
	}

	private function get_product_ids_by_country( $country ) {
		if ( ! function_exists( 'sk_get_sellers' ) ) {
			return [];
		}

		$sellers_response = sk_get_sellers( [ 'number' => -1, 'offset' => 0 ] );
		$sellers          = isset( $sellers_response['users'] ) ? $sellers_response['users'] : [];

		$vendor_ids = [];
		foreach ( $sellers as $seller ) {
			if ( $this->extract_vendor_country( $seller->ID ) === $country ) {
				$vendor_ids[] = $seller->ID;
			}
		}

		if ( empty( $vendor_ids ) ) {
			return [];
		}

		$vendor_products = new \WP_Query( [
			'post_type'      => 'product',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'author__in'     => $vendor_ids,
		] );

		$product_ids = [];
		if ( $vendor_products->have_posts() ) {
			foreach ( $vendor_products->posts as $product_id ) {
				if ( ! $this->is_location_excluded( $product_id, 'product' ) ) {
					$product_ids[] = $product_id;
				}
			}
		}
		wp_reset_postdata();

		return $product_ids;
	}

	public function ajax_filter_products_by_country() {
		check_ajax_referer( 'sk_country_filter', 'nonce' );

		$country     = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : 'all';
		$product_ids = [];

		if ( 'all' !== $country ) {
			$product_ids = $this->get_product_ids_by_country( $country );
		} else {
			if ( ! function_exists( 'sk_get_sellers' ) ) {
				wp_send_json_error( [ 'message' => 'SK not available' ] );
			}

			$all_sellers    = sk_get_sellers( [ 'number' => -1, 'offset' => 0 ] );
			$all_vendor_ids = [];
			if ( isset( $all_sellers['users'] ) ) {
				foreach ( $all_sellers['users'] as $seller ) {
					$all_vendor_ids[] = $seller->ID;
				}
			}

			if ( ! empty( $all_vendor_ids ) ) {
				$all_products = new \WP_Query( [
					'post_type'      => 'product',
					'posts_per_page' => -1,
					'post_status'    => 'publish',
					'fields'         => 'ids',
					'author__in'     => $all_vendor_ids,
				] );

				if ( $all_products->have_posts() ) {
					foreach ( $all_products->posts as $product_id ) {
						if ( ! $this->is_location_excluded( $product_id, 'product' ) ) {
							$product_ids[] = $product_id;
						}
					}
				}
				wp_reset_postdata();
			}
		}

		wp_send_json_success( [
			'product_ids' => $product_ids,
			'country'     => $country,
			'count'       => count( $product_ids ),
		] );
	}
}
