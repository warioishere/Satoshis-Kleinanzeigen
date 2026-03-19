<div id="sk-seller-listing-wrap" class="grid-view">
    <div class="seller-listing-content">
        <?php if ( $sellers['users'] ) : ?>
            <ul class="sk-seller-wrap">
                <?php
                foreach ( $sellers['users'] as $seller ) {
                    $vendor                   = sk()->vendor->get( $seller->ID );
                    $store_name               = $vendor->get_shop_name();
                    $store_url                = $vendor->get_shop_url();
                    $store_rating             = $vendor->get_rating();
                    $is_store_featured        = $vendor->is_featured();
                    $store_phone              = $vendor->get_phone();
                    $store_info               = $vendor->get_shop_info();
                    $store_address            = sk_get_seller_short_address( $seller->ID );
                    $store_banner_url         = $vendor->get_banner();
                    ?>

                    <li class="sk-single-seller woocommerce coloum-<?php echo esc_attr( $per_row ); ?>">
                        <div class="store-wrapper">
                            <div class="store-header">
                                <div class="store-banner">
                                    <a href="<?php echo esc_url( $store_url ); ?>">
                                        <img src="<?php echo is_array( $store_banner_url ) ? esc_attr( $store_banner_url[0] ) : esc_attr( $store_banner_url ); ?>">
                                    </a>
                                </div>
                            </div>

                            <div class="store-content">
                                <div class="store-data-container">
                                    <div class="featured-favourite">
                                        <?php if ( $is_store_featured ) : ?>
                                            <div class="featured-label"><?php esc_html_e( 'Featured', 'sk-core' ); ?></div>
                                        <?php endif ?>

                                        <?php do_action( 'sk_seller_listing_after_featured', $seller, $store_info ); ?>
                                    </div>
                                    <div class="store-data">
                                        <h2><a href="<?php echo esc_attr( $store_url ); ?>"><?php echo esc_html( $store_name ); ?></a> <?php do_action( 'sk_store_list_loop_after_store_name', $vendor ); ?></h2>

                                        <?php if ( ! empty( $store_rating['count'] ) ) : ?>
                                            <div class="sk-seller-rating"
                                                title="
                                                <?php
                                                    printf(
                                                        // translators: 1) seller rating
                                                        esc_attr__( 'Rated %s out of 5', 'sk-core' ), esc_html( number_format_i18n( $store_rating['rating'] ) )
                                                    );
                                                ?>
                                                ">
                                                <?php echo wp_kses_post( sk_generate_ratings( $store_rating['rating'], 5 ) ); ?>
                                                <p class="rating">
                                                    <?php
                                                    echo esc_html(
                                                        // translators: 1) seller rating
                                                        sprintf( __( '%s out of 5', 'sk-core' ), number_format_i18n( $store_rating['rating'] ) )
                                                    );
                                                    ?>
                                                </p>
                                            </div>
                                        <?php endif ?>

                                        <?php if ( ! sk_is_vendor_info_hidden( 'address' ) && $store_address ) : ?>
                                            <?php
                                            $allowed_tags = [
                                                'span' => [
                                                    'class' => [],
                                                ],
                                                'br'   => [],
                                            ];
                                            ?>
                                            <p class="store-address"><?php echo wp_kses( $store_address, $allowed_tags ); ?></p>
                                        <?php endif ?>

                                        <?php if ( ! sk_is_vendor_info_hidden( 'phone' ) && ! empty( $store_phone ) && ! in_array( strtolower( trim( $store_phone ) ), [ 'no', 'nein', 'n/a', '-' ], true ) && preg_match( '/\d/', $store_phone ) ) { ?>
                                            <p class="store-phone">
                                                <i class="fas fa-phone-alt" aria-hidden="true"></i> <?php echo esc_html( $store_phone ); ?>
                                            </p>
                                        <?php } ?>

                                        <?php do_action( 'sk_seller_listing_after_store_data', $seller, $store_info ); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="store-footer">
                                <div class="seller-avatar">
                                    <a href="<?php echo esc_url( $store_url ); ?>">
                                        <img src="<?php echo esc_url( $vendor->get_avatar() ); ?>"
                                            alt="<?php echo esc_attr( $vendor->get_shop_name() ); ?>"
                                            size="150" />
                                    </a>
                                </div>
                                <a href="<?php echo esc_url( $store_url ); ?>" title="<?php esc_attr_e( 'Visit Store', 'sk-core' ); ?>">
                                    <span class="dashicons dashicons-arrow-right-alt2 sk-btn-theme sk-btn-round"></span>
                                </a>
                                <?php do_action( 'sk_seller_listing_footer_content', $seller, $store_info ); ?>
                            </div>
                        </div>
                    </li>

                <?php } ?>
                <div class="sk-clearfix"></div>
            </ul> <!-- .sk-seller-wrap -->

            <?php
            $user_count   = $sellers['count'];
            $num_of_pages = ceil( $user_count / $limit );

            if ( $num_of_pages > 1 ) {
                echo '<div class="pagination-container clearfix">';

                $pagination_args = [
                    'current'   => $paged,
                    'total'     => $num_of_pages,
                    'base'      => $pagination_base,
                    'type'      => 'array',
                    'prev_text' => __( '&larr; Previous', 'sk-core' ),
                    'next_text' => __( 'Next &rarr;', 'sk-core' ),
                ];

                if ( ! empty( $search_query ) ) {
                    $pagination_args['add_args'] = [
                        'sk_seller_search' => $search_query,
                    ];
                }

                $page_links = paginate_links( $pagination_args );

                if ( $page_links ) {
                    $pagination_links = '<div class="pagination-wrap">';
                    $pagination_links .= '<ul class="pagination"><li>';
                    $pagination_links .= join( "</li>\n\t<li>", $page_links );
                    $pagination_links .= "</li>\n</ul>\n";
                    $pagination_links .= '</div>';

                    echo wp_kses_post( $pagination_links );
                }

                echo '</div>';
            }
            ?>

        <?php else : ?>
            <p class="sk-error"><?php esc_html_e( 'No vendor found!', 'sk-core' ); ?></p>
        <?php endif; ?>
    </div>
</div>
