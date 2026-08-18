<?php
/**
 * SK Geolocation Filters
 *
 * This template provides location filtering functionality for vendors and products.
 * It allows users to search vendors and products based on location, distance, categories, and keywords.
 *
 *
 * @var string   $scope                Determines search target: 'product', 'vendor', or empty for both
 * @var string   $display              Controls the display style of the filter
 * @var array    $placeholders         Placeholder texts for different input fields
 * @var string   $seller_s             Current vendor search term
 * @var string   $s                    Current product search term
 * @var string   $address              Current location address
 * @var float|int $distance            Current search radius distance
 * @var array    $slider               Contains min/max values and unit for radius slider
 * @var string   $latitude             Latitude coordinate for location-based search
 * @var string   $longitude            Longitude coordinate for location-based search
 * @var string   $wc_shop_page         URL for the WooCommerce shop page
 * @var string   $store_listing_page   URL for the SK store listing page
 * @var array    $categories           List of store categories (when applicable)
 * @var string   $store_category       Currently selected store category
 * @var array    $wc_categories_args   Arguments for product categories dropdown
 * @var string   $mapbox_access_token  API key for Mapbox integration (if used)
 */

// Do not allow direct access to the file.
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

global $wp_query;
?>

<form role="search" method="get" class="sk-geolocation-location-filters" action="<?php echo esc_url( home_url( '/' ) ); ?>" data-scope="<?php echo esc_attr( $scope ); ?>" data-display="<?php echo esc_attr( $display ); ?>">
    <div class="sk-geolocation-filters-loading" style="text-align: center;">
        <img src="<?php echo SK_CORE_ASSETS . '/images/ajax-loader.gif'; ?>" alt="" style="display: inline-block;">
    </div>

    <div class="sk-row sk-clearfix sk-hide">
        <div class="sk-w12 <?php echo ! $scope ? 'sk-hide' : ''; ?>">
            <div class="range-slider-container sk-clearfix">
                <span class="sk-range-slider-value sk-left">
                    <?php esc_html_e( 'Radius', 'sk' ); ?> <span><?php echo esc_html( $distance ); ?></span><?php echo esc_html( $slider['unit'] ); ?>
                </span>

                <input
                    class="sk-range-slider sk-left"
                    type="range"
                    value="<?php echo esc_attr( $distance ); ?>"
                    min="<?php echo esc_attr( $slider['min'] ); ?>"
                    max="<?php echo esc_attr( $slider['max'] ); ?>"
                >
            </div>
        </div>

        <div class="sk-geo-filters-column">
            <div class="<?php echo ! $scope ? 'sk-input-group' : ' no-dropdown'; ?>">
                <?php if ( 'vendor' === $scope ) : ?>
                    <input type="text" class="sk-form-control" name="sk_seller_search" placeholder="<?php echo esc_attr( $placeholders['search_vendors'] ); ?>" value="<?php echo esc_attr( $seller_s ); ?>">
                <?php elseif ( 'product' === $scope ) : ?>
                    <input type="text" class="sk-form-control" name="s" placeholder="<?php echo esc_attr( $placeholders['search_products'] ); ?>" value="<?php echo esc_attr( $s ); ?>">
                <?php else : ?>
                    <input type="text" class="sk-form-control" name="s" placeholder="<?php echo esc_attr( $placeholders['search_products'] ); ?>" value="<?php echo esc_attr( $s ); ?>">
                    <input type="text" class="sk-form-control sk-hide" name="sk_seller_search" placeholder="<?php echo esc_attr( $placeholders['search_vendors'] ); ?>" value="<?php echo esc_attr( $seller_s ); ?>">
                <?php endif; ?>

                <?php if ( ! $scope ) : ?>
                    <div class="sk-input-group-btn">
                        <span class="sk-geo-input-group-btn" data-toggle="sk-geo-dropdown">
                            <span class="sk-geo-filter-scope"><?php esc_html_e( 'Product', 'sk' ); ?></span> <span class="sk-geo-caret"></span>
                        </span>

                        <ul class="sk-geo-dropdown-menu dropdown-menu-right sk-geo-filter-scope-switch">
                            <li><a href="#" data-switch-scope="product"><?php esc_html_e( 'Product', 'sk' ); ?></a></li>
                            <li><a href="#" data-switch-scope="vendor"><?php esc_html_e( 'Vendor', 'sk' ); ?></a></li>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php wp_nonce_field( 'sk_store_lists_filter_nonce', '_store_filter_nonce', false ); ?>
            </div>
        </div>

        <div class="sk-geo-filters-column">
            <div class="location-address">
                <input type="text" placeholder="<?php echo esc_attr( $placeholders['location'] ); ?>" value="<?php echo esc_attr( $address ); ?>">

                <?php if ( is_ssl() || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) : ?>
                    <i class="locate-icon sk-hide" style="background-image: url(<?php echo SK_GEOLOCATION_URL . '/assets/images/locate.svg'; ?>)"></i>
                    <i class="locate-loader sk-hide" style="background-image: url(<?php echo SK_GEOLOCATION_URL . '/assets/images/spinner.svg'; ?>)"></i>
                <?php endif; ?>
            </div>
        </div>

        <?php if ( 'vendor' !== $scope ) : ?>
            <?php $selected = $wp_query->query_vars['product_cat'] ?? ''; ?>
            <div
                id="sk-geo-product-categories-root"
                class="sk-geo-filters-column sk-geo-product-categories sk-layout sk-w6"
                data-selected="<?php echo esc_attr( $selected ); ?>"
            ></div>
        <?php endif; ?>

        <?php if ( 'product' !== $scope && sk_is_store_categories_feature_on() ) : ?>
            <div class="sk-geo-filters-column sk-geo-store-categories">
                <select class="dropdown_product_cat" name="store_categories" id="store-category-dropdown">
                    <option value=""><?php echo esc_html( __( 'Select a store category', 'sk' ) ); ?></option>
                    <?php foreach ( $categories as $category ) : ?>
                        <option value="<?php echo esc_attr( $category->slug ); ?>" <?php echo ( $category->slug === $store_category ) ? 'selected' : ''; ?>>
                            <?php echo esc_html( $category->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>


        <div class="sk-geo-filters-column">
            <button type="button" class="sk-btn <?php echo esc_attr( 'product' === $scope ? 'sk-geo-product-search-btn' : 'sk-geo-filters-search-btn' ); ?>">
                <?php esc_html_e( 'Search', 'sk' ); ?>
            </button>
        </div>

    </div>

    <input type="hidden" name="latitude" value="<?php echo esc_attr( $latitude ); ?>">
    <input type="hidden" name="longitude" value="<?php echo esc_attr( $longitude ); ?>">
    <input type="hidden" name="wc_shop_page" value="<?php echo esc_attr( $wc_shop_page ); ?>">
    <input type="hidden" name="sk_store_listing_page" value="<?php echo esc_url_raw( $store_listing_page ); ?>">
</form>
