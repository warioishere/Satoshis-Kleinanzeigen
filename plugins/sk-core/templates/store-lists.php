<?php
global $post;

$pagination_base = empty( $post ) ? '' : str_replace( $post->ID, '%#%', esc_url( get_pagenum_link( $post->ID ) ) );

$search_query = null;

if ( 'yes' === $search ) {
    $search_query = $sk_seller_search;
}

if ( apply_filters( 'sk_store_lists_filter', true ) ) {
    /**
     * Hooks: sk_store_lists_filter_form
     *
     *
     * @hooked \SK\Core\Vendor\StoreListsFilter::filter_area() - 10
     */
    do_action( 'sk_store_lists_filter_form', $sellers );
}

/**
 *  Added extra search field after store listing search
 *
 * `sk_after_seller_listing_serach_form` - action
 *
 *
 * @param array|object $sellers
 */
do_action( 'sk_after_seller_listing_serach_form', $sellers );

/**
 * Action hook before starting seller listing loop
 *
 *
 * @var array $sellers
 */
do_action( 'sk_before_seller_listing_loop', $sellers );

$template_args = [
    'sellers'         => $sellers,
    'limit'           => $limit,
    'offset'          => $offset,
    'paged'           => $paged,
    'search_query'    => $search_query,
    'pagination_base' => $pagination_base,
    'per_row'         => $per_row,
    'search_enabled'  => $search,
    'image_size'      => $image_size,
];

sk_get_template_part( 'store-lists-loop', false, $template_args );

/**
 * Action hook after finishing seller listing loop
 *
 *
 * @var array $sellers
 */
do_action( 'sk_after_seller_listing_loop', $sellers );
