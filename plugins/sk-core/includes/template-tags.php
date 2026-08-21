<?php
/**
 * Custom template tags for this theme.
 *
 * Eventually, some of the functionality here could be replaced by core features
 *
 */

use SK\Core\Vendor\Vendor;

if ( ! function_exists( 'sk_content_nav' ) ) :

    /**
     * Display navigation to next/previous pages when applicable
     */
    function sk_content_nav( $nav_id, $query = null ) {
        global $wp_query, $post;

        if ( $query ) {
            $wp_query = $query; //phpcs:ignore
        }

        // Don't print empty markup on single pages if there's nowhere to navigate.
        if ( is_single() ) {
            $previous = ( is_attachment() ) ? get_post( $post->post_parent ) : get_adjacent_post( false, '', true );
            $next = get_adjacent_post( false, '', false );

            if ( ! $next && ! $previous ) {
                return;
            }
        }

        // Don't print empty markup in archives if there's only one page.
        if ( $wp_query->max_num_pages < 2 && ( is_home() || is_archive() || is_search() ) ) {
            return;
        }

        $nav_class = 'site-navigation paging-navigation';
        if ( is_single() ) {
            $nav_class = 'site-navigation post-navigation';
        }
        ?>
        <nav role="navigation" id="<?php echo esc_attr( $nav_id ); ?>" class="<?php echo esc_attr( $nav_class ); ?>">

            <ul class="pager">
            <?php if ( is_single() ) : // navigation links for single posts ?>

                <li class="previous">
                    <?php previous_post_link( '%link', _x( '&larr;', 'Previous post link', 'sk-core' ) . ' %title' ); ?>
                </li>
                <li class="next">
                    <?php next_post_link( '%link', '%title ' . _x( '&rarr;', 'Next post link', 'sk-core' ) ); ?>
                </li>

            <?php endif; ?>
            </ul>


            <?php if ( $wp_query->max_num_pages > 1 && ( sk_is_store_page() || is_home() || is_archive() || is_search() ) ) : // navigation links for home, archive, and search pages ?>
                <?php sk_page_navi( '', '', $wp_query ); ?>
            <?php endif; ?>

        </nav>
        <?php
    }

endif;

if ( ! function_exists( 'sk_page_navi' ) ) :

    function sk_page_navi( $before, $after, $wp_query ) {
        if ( ! ( $wp_query instanceof WP_Query ) ) {
            return;
        }

        $posts_per_page = intval( get_query_var( 'posts_per_page' ) );
        $paged          = intval( get_query_var( 'paged' ) );
        $numposts       = $wp_query->found_posts;
        $max_page       = $wp_query->max_num_pages;

        if ( $numposts <= $posts_per_page ) {
            return;
        }

        if ( empty( $paged ) || $paged === 0 ) {
            $paged = 1;
        }

        $pages_to_show         = 7;
        $pages_to_show_minus_1 = $pages_to_show - 1;
        $half_page_start       = floor( $pages_to_show_minus_1 / 2 );
        $half_page_end         = ceil( $pages_to_show_minus_1 / 2 );
        $start_page            = $paged - $half_page_start;

        if ( $start_page <= 0 ) {
            $start_page = 1;
        }

        $end_page = $paged + $half_page_end;

        if ( ( $end_page - $start_page ) !== $pages_to_show_minus_1 ) {
            $end_page = $start_page + $pages_to_show_minus_1;
        }

        if ( $end_page > $max_page ) {
            $start_page = $max_page - $pages_to_show_minus_1;
            $end_page = $max_page;
        }

        if ( $start_page <= 0 ) {
            $start_page = 1;
        }

        echo wp_kses_post( $before ) . '<div class="sk-pagination-container"><ul class="sk-pagination">';
        if ( $paged > 1 ) {
            $first_page_text = '&laquo;';
            echo '<li class="prev"><a href="' . esc_url( get_pagenum_link() ) . '" title="First">' . esc_html( $first_page_text ) . '</a></li>';
        }

        $prevposts = get_previous_posts_link( __( '&larr; Previous', 'sk-core' ) );
        if ( $prevposts ) {
            echo '<li>' . wp_kses( $prevposts, [ 'a' => [ 'href' => [] ] ] ) . '</li>';
        } else {
            echo '<li class="disabled"><a href="#">' . esc_html__( '&larr; Previous', 'sk-core' ) . '</a></li>';
        }
        for ( $i = $start_page; $i <= $end_page; $i++ ) {
            if ( (int) $i === $paged ) {
                echo '<li class="active"><a href="#">' . esc_html( $i ) . '</a></li>';
            } else {
                echo '<li><a href="' . esc_url( get_pagenum_link( $i ) ) . '">' . esc_html( number_format_i18n( $i ) ) . '</a></li>';
            }
        }

        if ( (int) $paged < $max_page ) {
            echo '<li class="">';
            next_posts_link( __( 'Next &rarr;', 'sk-core' ) );
            echo '</li>';
        } else {
            echo '<li class="disabled"><a href="#">' . esc_html__( 'Next &rarr;', 'sk-core' ) . '</a></li>';
        }
        if ( (int) $paged < $max_page ) {
            $last_page_text = '&raquo;';
            echo '<li class="next"><a href="' . esc_url( get_pagenum_link( $max_page ) ) . '" title="Last">' . esc_html( $last_page_text ) . '</a></li>';
        }

        echo '</ul></div>' . wp_kses_post( $after );
    }

endif;

function sk_product_dashboard_errors() {
    $type = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : ''; //phpcs:ignore

    switch ( $type ) {
        case 'product_deleted':
            sk_get_template_part(
                'global/sk-success', '', array(
                    'deleted' => true,
                    'message' => __( 'Product successfully deleted', 'sk-core' ),
                )
            );
            break;
        case 'product_duplicated':
            sk_get_template_part(
                'global/sk-success', '', [
                    'deleted' => false,
                    'message' => __( 'Product successfully duplicated', 'sk-core' ),
                ]
            );
            break;
        case 'error':
            sk_get_template_part(
                'global/sk-error', '', array(
                    'deleted' => false,
                    'message' => __( 'Something went wrong!', 'sk-core' ),
                )
            );
            break;

        default:
            do_action( 'sk_product_dashboard_errors', $type );
            break;
    }
}

if ( ! function_exists( 'sk_store_category_menu' ) ) :

    /**
     * Store category menu for a store
     *
     * @param  int $seller_id
     *
     *
     * @return void
     */
    function sk_store_category_menu( $seller_id ) {
        ?>
    <div class="store-cat-stack-sk cat-drop-stack">
        <?php
        $seller_id = empty( $seller_id ) ? get_query_var( 'author' ) : $seller_id;
        $vendor    = sk()->vendor->get( $seller_id );
        if ( $vendor instanceof Vendor ) {
            $categories = $vendor->get_store_categories();
            $walker = new \SK\Core\Walkers\StoreCategory( $seller_id );
            echo '<ul>';
            echo wp_kses_post( call_user_func_array( array( &$walker, 'walk' ), array( $categories, 0, array() ) ) );
            echo '</ul>';
        }
        ?>
    </div>
        <?php
    }

endif;
if ( ! function_exists( 'sk_store_term_menu_list' ) ) :

    /**
     * Store category menu for a store
     *
     *
     * @param int    $seller_id
     * @param array  $taxonomy
     * @param string $query_type
     *
     * @return void
     */
    function sk_store_term_menu_list( $seller_id, $taxonomy, $query_type ) {
        ?>
    <div class="store-cat-stack-sk cat-drop-stack">
        <?php
        $seller_id = empty( $seller_id ) ? get_query_var( 'author' ) : $seller_id;
        $vendor    = sk()->vendor->get( $seller_id );
        $store_url = sk_get_store_url( $seller_id );

        if ( $vendor instanceof Vendor ) {
            $terms = $vendor->get_vendor_used_terms_list( $seller_id, $taxonomy );

            echo '<ul>';
            $_chosen_attributes = sk_get_chosen_taxonomy_attributes();
            foreach ( $terms as $term ) {
                $current_values = isset( $_chosen_attributes[ $taxonomy ]['terms'] ) ? $_chosen_attributes[ $taxonomy ]['terms'] : [];
                $option_is_set  = in_array( $term->slug, $current_values, true );
                $filter_name    = 'filter_' . wc_attribute_taxonomy_slug( $taxonomy );
                // phpcs:ignore
                $current_filter = isset( $_GET[ $filter_name ] ) ? explode( ',', wc_clean( wp_unslash( $_GET[ $filter_name ] ) ) ) : array();

                if ( ! in_array( $term->slug, $current_filter, true ) ) {
                    $current_filter[] = $term->slug;
                }

                $link = remove_query_arg( $filter_name, $store_url );
                // Add current filters to URL.
                foreach ( $current_filter as $key => $value ) {
                    // Exclude query arg for current term archive term.
                    if ( $value === sk_get_current_term_slug() ) {
                        unset( $current_filter[ $key ] );
                    }

                    // Exclude self so filter can be unset on click.
                    if ( $option_is_set && $value === $term->slug ) {
                        unset( $current_filter[ $key ] );
                    }
                }

                if ( ! empty( $current_filter ) ) {
                    asort( $current_filter );
                    $link = add_query_arg( $filter_name, implode( ',', $current_filter ), $link );

                    // Add Query type Arg to URL.
                    if ( 'or' === $query_type && ! ( 1 === count( $current_filter ) && $option_is_set ) ) {
                        $link = add_query_arg( 'query_type_' . wc_attribute_taxonomy_slug( $taxonomy ), 'or', $link );
                    }
                    $link = str_replace( '%2C', ',', $link );
                }

                $checked = '';
                if ( $option_is_set ) {
                    $checked = 'checked';
                }

                echo '
                <li class="parent-cat-wrap">
                    <a href="' . esc_url( $link ) . '"> <input style="pointer-events: none;" type="checkbox" ' . esc_attr( $checked ) . '> &nbsp;' . esc_html( $term->name ) . ' <span style="float: right" class="count">(' . esc_html( $term->count ) . ')</span> </a>
                </li>';
            }
            echo '</ul>';
        }
        ?>
    </div>
        <?php
    }

endif;
/**
 * Return the currently viewed term slug.
 *
 * @return int
 */
function sk_get_current_term_slug() {
    return absint( is_tax() ? get_queried_object()->slug : 0 );
}

/**
 * Get chosen taxonomy attributes.
 *
 *
 * @return array
 */
function sk_get_chosen_taxonomy_attributes() {
    $attributes = [];
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    if ( empty( $_GET ) ) {
        return $attributes;
    }

    foreach ( $_GET as $key => $value ) {
        if ( 0 !== strpos( $key, 'filter_' ) ) {
            continue;
        }

        $attribute    = wc_sanitize_taxonomy_name( str_replace( 'filter_', '', $key ) );
        $taxonomy     = wc_attribute_taxonomy_name( $attribute );
        $filter_terms = ! empty( $value ) ? explode( ',', wc_clean( wp_unslash( $value ) ) ) : [];

        if ( empty( $filter_terms ) || ! taxonomy_exists( $taxonomy ) || ! wc_attribute_taxonomy_id_by_name( $attribute ) ) {
            continue;
        }

        // phpcs:ignore
        $query_type                            = ! empty( $_GET['query_type_' . $attribute] ) && in_array(  $_GET['query_type_' . $attribute], [ 'and', 'or' ], true ) ? wc_clean( wp_unslash( $_GET['query_type_' . $attribute] ) ) : '';
        $attributes[ $taxonomy ]['terms']      = array_map( 'sanitize_title', $filter_terms ); // Ensures correct encoding.
        $attributes[ $taxonomy ]['query_type'] = $query_type ? $query_type : 'and';
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    return $attributes;
}

if ( ! function_exists( 'sk_seller_not_enabled_notice' ) ) :

    function sk_seller_not_enabled_notice() {
        sk_get_template_part( 'global/seller-warning' );
    }

endif;

if ( ! function_exists( 'sk_header_user_menu' ) ) :

    /**
     * User top navigation menu
     *
     * @return void
     */
    function sk_header_user_menu() {
        global $current_user;

        $user_id  = sk_get_current_user_id();
        $nav_urls = sk_get_dashboard_nav();

        sk_get_template_part(
            'global/header-menu', '', array(
                'current_user' => $current_user,
                'user_id' => $user_id,
                'nav_urls' => $nav_urls,
            )
        );
    }

endif;

add_action( 'template_redirect', 'sk_myorder_login_check' );

/**
 * Redirect My order in Login page without user logged login
 *
 *
 * @return [redirect]
 */
function sk_myorder_login_check() {
    global $post;

    if ( ! $post ) {
        return;
    }

    if ( ! isset( $post->ID ) ) {
        return;
    }

    $my_order_page_id = sk_get_option( 'my_orders', 'sk_pages' );

    if ( (int) $my_order_page_id === $post->ID ) {
        sk_redirect_login();
    }
}

/**
 * Store sidebar widget args
 *
 * @return array
 */
function sk_store_sidebar_args() {
    $args = [
        'before_widget' => '<aside class="widget sk-store-widget %s">',
        'after_widget'  => '</aside>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ];

    return apply_filters( 'sk_store_sidebar_args', $args );
}

/**
 * Store single category widget
 *
 * @return void
 */
function sk_store_category_widget() {
    $args = sk_store_sidebar_args();

    if ( sk()->widgets->is_exists( 'store_category_menu' ) ) {
        the_widget( sk()->widgets->store_category_menu, [ 'title' => __( 'Store Product Category', 'sk-core' ) ], $args );
    }
}

/**
 * Store single location widget
 *
 * @return void
 */
function sk_store_location_widget() {
    $args = sk_store_sidebar_args();

    if ( sk()->widgets->is_exists( 'store_location' ) && 'on' === sk_get_option( 'store_map', 'sk_general', 'on' ) ) {
        the_widget( sk()->widgets->store_location, [ 'title' => __( 'Store Location', 'sk-core' ) ], $args );
    }
}

/**
 * Store contact form widget
 *
 * @return void
 */
function sk_store_contact_widget() {
    $args = sk_store_sidebar_args();

    if ( sk()->widgets->is_exists( 'store_contact_form' ) && 'on' === sk_get_option( 'contact_seller', 'sk_general', 'on' ) ) {
        the_widget( sk()->widgets->store_contact_form, [ 'title' => __( 'Contact Vendor', 'sk-core' ) ], $args );
    }
}

