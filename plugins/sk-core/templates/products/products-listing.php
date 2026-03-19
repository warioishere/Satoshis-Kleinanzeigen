<?php
    global $post;
?>

<?php do_action( 'sk_dashboard_wrap_start' ); ?>

<div class="sk-dashboard-wrap">

    <?php

        /**
         *  Adding sk_dashboard_content_before hook
         *
         *  @hooked get_dashboard_side_navigation
         *
         */
        do_action( 'sk_dashboard_content_before' );
    ?>

        <div class="sk-dashboard-content sk-product-listing">

            <?php

            /**
             *  Adding sk_dashboard_content_before hook
             *
             *  @hooked get_dashboard_side_navigation
             *
             */
            do_action( 'sk_dashboard_content_inside_before' );
            do_action( 'sk_before_listing_product' );
            ?>

                <article class="sk-product-listing-area">
                    <?php
                    $one_step_product_create = 'on' === sk_get_option( 'one_step_product_create', 'sk_selling', 'on' );
                    $disable_product_popup   = $one_step_product_create || 'on' === sk_get_option( 'disable_product_popup', 'sk_selling', 'off' );
                    $new_product_url         = $one_step_product_create ? sk_edit_product_url( 0, true ) : add_query_arg(
                        [
                            '_sk_add_product_nonce' => wp_create_nonce( 'sk_add_product_nonce' ),
                        ],
                        sk_get_navigation_url( 'new-product' )
                    );
                    $product_listing_args    = [
                        'author'         => sk_get_current_user_id(),
                        'posts_per_page' => 1,
                        'post_status'    => apply_filters(
                            'sk_product_listing_post_statuses', [
                                'publish',
                                'draft',
                                'pending',
                                'future',
                            ]
                        ),
                    ];
                    $product_query           = sk()->product->all( $product_listing_args );

                    if ( $product_query->have_posts() ) {
                        ?>

                        <div class="sk-product-listing-header sk-product-listing-header--stacked">
                            <h2><?php esc_html_e( 'Meine Inserate', 'sk-core' ); ?></h2>
                            <?php if ( sk_is_seller_enabled( sk_get_current_user_id() ) && current_user_can( 'sk_add_product' ) ) : ?>
                                <a href="<?php echo esc_url( $new_product_url ); ?>" class="sk-btn sk-btn-btc <?php echo $disable_product_popup ? '' : 'sk-add-new-product'; ?>">
                                    + <?php esc_html_e( 'Inserat erstellen', 'sk-core' ); ?>
                                </a>
                            <?php endif; ?>
                            <?php sk_product_listing_filter(); ?>
                        </div>

                        <?php sk_product_dashboard_errors(); ?>

                        <div class="sk-dashboard-product-listing-wrapper">

                            <form id="product-filter" method="POST">
                                <?php wp_nonce_field( 'bulk_product_status_change', 'security' ); ?>
                                <table class="sk-table product-listing-table sk-inline-editable-table" id="sk-product-list-table">
                                    <thead>
                                        <tr>
                                            <th class="col-thumb"><?php esc_html_e( 'Inserat', 'sk-core' ); ?></th>
                                            <th class="col-status"><?php esc_html_e( 'Status', 'sk-core' ); ?></th>
                                            <?php do_action( 'sk_product_list_table_after_status_table_header' ); ?>
                                            <th class="col-price"><?php esc_html_e( 'Preis', 'sk-core' ); ?></th>
                                            <th class="col-date"><?php esc_html_e( 'Datum', 'sk-core' ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php do_action( 'sk_product_list_before_table_body_start' ); ?>
                                        <?php
                                        $post_statuses  = apply_filters( 'sk_product_listing_post_statuses', [ 'publish', 'draft', 'pending', 'future' ] );
                                        $stock_statuses = apply_filters( 'sk_product_stock_statuses', [ 'instock', 'outofstock' ] );
                                        $product_types  = apply_filters( 'sk_product_types', [ 'simple' => esc_html__( 'Simple', 'sk-core' ) ] );

                                        $args = array(
                                            'posts_per_page' => 15,
                                            'paged'          => 1,
                                            'author'         => sk_get_current_user_id(),
                                            'post_status'    => $post_statuses,
                                            'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
                                                array(
                                                    'taxonomy' => 'product_type',
                                                    'field'    => 'slug',
                                                    'terms'    => ! sk()->is_pro_exists() ? [ 'simple' ] : apply_filters( 'sk_product_listing_exclude_type', array() ),
                                                    'operator' => ! sk()->is_pro_exists() ? 'IN' : 'NOT IN',
                                                ),
                                            ),
                                        );

                                        if ( isset( $_GET['_product_listing_filter_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_product_listing_filter_nonce'] ) ), 'product_listing_filter' ) ) {
                                            if ( isset( $_GET['pagenum'] ) ) {
                                                $args['paged'] = absint( $_GET['pagenum'] );
                                            }

                                            if ( isset( $_GET['post_status'] ) && in_array( $_GET['post_status'], $post_statuses, true ) ) {
                                                $args['post_status'] = sanitize_text_field( wp_unslash( $_GET['post_status'] ) );
                                            }

                                            if ( isset( $_GET['date'] ) && $_GET['date'] !== 0 ) {
                                                $args['m'] = sanitize_text_field( wp_unslash( $_GET['date'] ) );
                                            }

                                            if ( isset( $_GET['product_cat'] ) && intval( $_GET['product_cat'] ) !== -1 ) {
                                                $args['tax_query'][] = array(
                                                    'taxonomy' => 'product_cat',
                                                    'field' => 'id',
                                                    'terms' => intval( $_GET['product_cat'] ),
                                                    'include_children' => false,
                                                );
                                            }
                                            if ( isset( $_GET['product_brand'] ) && intval( $_GET['product_brand'] ) !== -1 ) {
                                                $args['tax_query'][] = array(
                                                    'taxonomy' => 'product_brand',
                                                    'field' => 'id',
                                                    'terms' => intval( $_GET['product_brand'] ),
                                                    'include_children' => false,
                                                );
                                            }

                                            if ( ! empty( $_GET['product_type'] ) ) {
                                                $product_type = sanitize_text_field( wp_unslash( $_GET['product_type'] ) );
                                                if ( array_key_exists( $product_type, $product_types ) ) {
                                                    $args['tax_query'][] = [
                                                        'taxonomy' => 'product_type',
                                                        'field'    => 'slug',
                                                        'terms'    => $product_type,
                                                    ];
                                                }
                                            }

                                            if ( ! empty( $_GET['product_search_name'] ) ) {
                                                $args['s'] = sanitize_text_field( wp_unslash( $_GET['product_search_name'] ) );
                                            }

                                            if ( isset( $_GET['post_status'] ) && in_array( $_GET['post_status'], $stock_statuses, true ) ) {
                                                $args['meta_query'][] = array(
                                                    'key' => '_stock_status',
                                                    'value' => sanitize_text_field( wp_unslash( $_GET['post_status'] ) ),
                                                    'compare' => '=',
                                                );
                                            }
                                        }

                                        $original_post = $post;
                                        $product_args  = apply_filters( 'sk_pre_product_listing_args', $args, [] );
                                        $product_query = sk()->product->all( apply_filters( 'sk_product_listing_arg', $product_args ) );

                                        if ( $product_query->have_posts() ) {
                                            while ( $product_query->have_posts() ) {
                                                $product_query->the_post();

                                                $row_actions = sk_product_get_row_action( $post );
                                                $tr_class = ( $post->post_status === 'pending' ) ? 'danger' : '';
                                                $view_class = ( $post->post_status === 'pending' ) ? 'sk-hide' : '';
                                                $product = wc_get_product( $post->ID );

                                                $row_args = array(
                                                    'post' => $post,
                                                    'product' => $product,
                                                    'tr_class' => $tr_class,
                                                    'row_actions' => $row_actions,
                                                );

                                                sk_get_template_part( 'products/products-listing-row', '', $row_args );

                                                do_action( 'sk_product_list_table_after_row', $product, $post );
                                            }
                                        } else {
                                            ?>
                                            <tr>
                                                <td colspan="11"><?php esc_html_e( 'No product found', 'sk-core' ); ?></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>

                                </table>
                            </form>
                        </div>
                        <?php
                        wp_reset_postdata();

                        $pagenum  = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 1;
                        $base_url = sk_get_navigation_url( 'products' );

                        if ( $product_query->max_num_pages > 1 ) {
                            echo '<div class="pagination-wrap">';
                            $page_links = paginate_links(
                                array(
                                    'current'   => $pagenum,
                                    'total'     => $product_query->max_num_pages,
                                    'base'      => $base_url . '%_%',
                                    'format'    => '?pagenum=%#%',
                                    'add_args'  => [
                                        '_product_listing_filter_nonce' => wp_create_nonce( 'product_listing_filter' ),
                                    ],
                                    'type'      => 'array',
                                    'prev_text' => esc_html__( '&laquo; Previous', 'sk-core' ),
                                    'next_text' => esc_html__( 'Next &raquo;', 'sk-core' ),
                                )
                            );

                            echo '<ul class="pagination"><li>';
                            echo implode( "</li>\n\t<li>", $page_links ); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped,WordPress.Security.EscapeOutput.OutputNotEscaped
                            echo "</li>\n</ul>\n";
                            echo '</div>';
                        }
                        ?>
                        <?php
                    } else {
                        ?>
                        <div class="sk-dashboard-product-listing-wrapper sk-dashboard-not-product-found">
                            <img src="<?php echo esc_url( plugins_url( 'assets/images/no-product-found.svg', SK_CORE_FILE ) ); ?>" alt="sk setup" class="no-product-found-icon">
                            <h4 class="sk-blank-product-message">
                                <?php esc_html_e( 'No Products Found!', 'sk-core' ); ?>
                            </h4>

                            <?php if ( sk_is_seller_enabled( sk_get_current_user_id() ) ) : ?>
                                <h2 class="sk-blank-product-message">
                                    <?php esc_html_e( 'Ready to start selling something awesome?', 'sk-core' ); ?>
                                </h2>

                                <span class="sk-add-product-link">
                                    <?php if ( current_user_can( 'sk_add_product' ) ) : ?>
                                        <a href="<?php echo esc_url( $new_product_url ); ?>" class="sk-btn sk-btn-theme <?php echo $disable_product_popup ? '' : 'sk-add-new-product'; ?>">
                                            <i class="fas fa-briefcase">&nbsp;</i>
                                            <?php esc_html_e( 'Add new product', 'sk-core' ); ?>
                                        </a>
                                    <?php endif ?>

                                    <?php
                                        do_action( 'sk_after_add_product_btn' );
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php } ?>
                </article>

                <?php

                /**
                 *  Adding sk_dashboard_content_before hook
                 *
                 *  @hooked get_dashboard_side_navigation
                 *
                 */
                do_action( 'sk_dashboard_content_inside_after' );
                do_action( 'sk_after_listing_product' );
                ?>

        </div><!-- #primary .content-area -->

        <?php

        /**
         *  Adding sk_dashboard_content_after hook
         *
         */
        do_action( 'sk_dashboard_content_after' );
        ?>

    </div><!-- .sk-dashboard-wrap -->

<?php do_action( 'sk_dashboard_wrap_end' ); ?>
<script>
(function () {
    'use strict';
    var primary = ['edit', 'delete', 'view'];

    /* ── Mobile only: add "mehr ▾" pill that expands row details ── */
    function initDesktopCollapse() {
        if (window.innerWidth > 768) return;

        document.querySelectorAll('#sk-product-list-table .row-actions').forEach(function (wrap) {
            if (wrap.dataset.skDesktop) return;
            wrap.dataset.skDesktop = '1';

            // Toggle link
            var toggle = document.createElement('span');
            toggle.className = 'sk-row-toggle';
            toggle.innerHTML = '<a href="#" class="sk-row-toggle-link">mehr ▾</a>';
            toggle.querySelector('a').addEventListener('click', function (e) {
                e.preventDefault();
                var tr = wrap.closest('tr');
                if (tr) {
                    tr.classList.toggle('is-expanded');
                    this.textContent = tr.classList.contains('is-expanded') ? 'weniger ▴' : 'mehr ▾';
                }
            });
            wrap.appendChild(toggle);
        });
    }

    /* ── Mobile: toggle-row button expands Status / Preis / Datum ── */
    function initToggleRow() {
        document.querySelectorAll('#sk-product-list-table .toggle-row').forEach(function (btn) {
            if (btn.dataset.skInit) return;
            btn.dataset.skInit = '1';
            btn.addEventListener('click', function () {
                var tr = btn.closest('tr');
                if (tr) tr.classList.toggle('is-expanded');
            });
        });
    }

    function init() {
        initDesktopCollapse();
        initToggleRow();
    }

    document.addEventListener('DOMContentLoaded', init);
    document.addEventListener('sk_product_inline_edit_done', init);
})();
</script>
