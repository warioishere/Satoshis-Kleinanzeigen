<?php
/**
 * The template for displaying product content within loops
 *
 * This template can be overridden by copying it to yourtheme/sk/store-lists-filter.php
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 */

defined( 'ABSPATH' ) || exit; ?>

<?php do_action( 'sk_before_store_lists_filter', $stores ); ?>

<div id="sk-store-listing-filter-wrap">
    <?php do_action( 'sk_before_store_lists_filter_left', $stores ); ?>
    <div class="left">
        <p class="item store-count">
            <?php
            // translators: 1) number of stores
            printf( esc_html( _n( 'Total store showing: %s', 'Total stores showing: %s', $number_of_store, 'sk-core' ) ), esc_html( number_format_i18n( $number_of_store ) ) );
            ?>
        </p>
    </div>

    <?php do_action( 'sk_before_store_lists_filter_right', $stores ); ?>
    <div class="right">
        <div class="item">
            <div class="sk-icons">
                <div class="sk-icon-div"></div>
                <div class="sk-icon-div"></div>
                <div class="sk-icon-div"></div>
            </div>

            <button class="sk-store-list-filter-button sk-btn sk-btn-theme">
                <?php esc_html_e( 'Filter', 'sk-core' ); ?>
            </button>
        </div>

        <form name="stores_sorting" class="sort-by item" method="get">
            <label><?php esc_html_e( 'Sort by', 'sk-core' ); ?>:</label>

            <select name="stores_orderby" id="stores_orderby" aria-label="<?php esc_html_e( 'Sort by', 'sk-core' ); ?>">
                <?php foreach ( $sort_filters as $key => $filter ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $sort_by, $key ); ?> ><?php echo esc_html( $filter ); ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <div class="toggle-view item">
            <span class="dashicons dashicons-screenoptions" data-view="grid-view"></span>
            <span class="dashicons dashicons-menu-alt" data-view="list-view"></span>
        </div>
    </div>
</div>

<?php do_action( 'sk_before_store_lists_filter_form', $stores ); ?>

<form role="store-list-filter" method="get" name="sk_store_lists_filter_form" id="sk-store-listing-filter-form-wrap" style="display: none">

    <?php
    do_action( 'sk_before_store_lists_filter_search', $stores );

    if ( apply_filters( 'sk_load_store_lists_filter_search_bar', true ) ) :
        ?>
        <div class="store-search grid-item">
            <input type="search" class="store-search-input" name="sk_seller_search" placeholder="<?php esc_attr_e( 'Search Vendors', 'sk-core' ); ?>">
        </div>
        <?php
    endif;

    do_action( 'sk_before_store_lists_filter_apply_button', $stores );
    ?>

    <div class="apply-filter">
        <button id="cancel-filter-btn" class="sk-btn sk-btn-theme"><?php esc_html_e( 'Cancel', 'sk-core' ); ?></button>
        <button id="apply-filter-btn" class="sk-btn sk-btn-theme" type="submit"><?php esc_html_e( 'Apply', 'sk-core' ); ?></button>
    </div>

    <?php do_action( 'sk_after_store_lists_filter_apply_button', $stores ); ?>
    <?php wp_nonce_field( 'sk_store_lists_filter_nonce', '_store_filter_nonce', false ); ?>
</form>
