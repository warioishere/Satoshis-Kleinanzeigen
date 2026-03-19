<?php
/**
 * Load all product related functions
 *
 */

use SK\Core\ProductCategory\Helper;

/**
 * SK insert new product
 *
 *
 * @param array $args
 *
 * @return int|bool|WP_Error
 */
function sk_save_product( $args ) {
    $defaults = [
        'post_title'       => '',
        'post_content'     => '',
        'post_excerpt'     => '',
        'post_status'      => '',
        'post_type'        => 'product',
        'product_tag'      => [],
        '_visibility'      => 'visible',
    ];

    $data = wp_parse_args( $args, $defaults );

    if ( empty( $data['post_title'] ) ) {
        return new WP_Error( 'no-title', __( 'Please enter product title', 'sk-core' ) );
    }

    if ( ! isset( $data['chosen_product_cat'] ) ) {
        if ( Helper::product_category_selection_is_single() ) {
            if ( absint( $data['product_cat'] ) < 0 ) {
                return new WP_Error( 'no-category', __( 'Please select a category', 'sk-core' ) );
            }
        } else {
            if ( ! isset( $data['product_cat'] ) && empty( $data['product_cat'] ) ) {
                return new WP_Error( 'no-category', __( 'Please select at least one category', 'sk-core' ) );
            }
        }
    } elseif ( empty( $data['chosen_product_cat'] ) ) {
        return new WP_Error( 'no-category', __( 'Please select a category', 'sk-core' ) );
    }

    $error = apply_filters( 'sk_new_product_popup_args', '', $data );

    if ( is_wp_error( $error ) ) {
        return $error;
    }

    $post_status = ! empty( $data['post_status'] ) ? sanitize_text_field( $data['post_status'] ) : sk_get_default_product_status();

    $post_arr = [
        'post_type'    => 'product',
        'post_status'  => $post_status,
        'post_title'   => sanitize_text_field( $data['post_title'] ),
        'post_content' => wp_kses_post( $data['post_content'] ),
        'post_excerpt' => wp_kses_post( $data['post_excerpt'] ),
    ];

    if ( ! empty( $data['ID'] ) ) {
        $post_arr['ID'] = absint( $data['ID'] );

        if ( ! sk_is_product_author( $post_arr['ID'] ) ) {
            return new WP_Error( 'not-own', __( 'Sorry, You can not modify another vendor\'s product !', 'sk-core' ) );
        }

        $is_updating = true;
    } else {
        $is_updating = false;
    }

    $post_arr = apply_filters( 'sk_insert_product_post_data', $post_arr, $data );

    $post_data = [
        'id'                 => $is_updating ? $post_arr['ID'] : '',
        'name'               => $post_arr['post_title'],
        'type'               => ! empty( $data['product_type'] ) ? $data['product_type'] : 'simple',
        'description'        => $post_arr['post_content'],
        'short_description'  => $post_arr['post_excerpt'],
        'status'             => $post_status,
    ];

    if ( ! isset( $data['chosen_product_cat'] ) ) {
        if ( Helper::product_category_selection_is_single() ) {
            $cat_ids[] = $data['product_cat'];
        } else {
            if ( ! empty( $data['product_cat'] ) ) {
                $cat_ids = array_map( 'absint', (array) $data['product_cat'] );
            }
        }
        $post_data['categories'] = $cat_ids;
    }

    if ( isset( $data['feat_image_id'] ) ) {
        $post_data['featured_image_id'] = ! empty( $data['feat_image_id'] ) ? absint( $data['feat_image_id'] ) : '';
    }

    if ( isset( $data['product_image_gallery'] ) ) {
        $post_data['gallery_image_ids'] = ! empty( $data['product_image_gallery'] ) ? array_filter( explode( ',', wc_clean( $data['product_image_gallery'] ) ) ) : [];
    }

    if ( isset( $data['product_tag'] ) ) {
        /**
         * Filter of maximun a vendor can add tags.
         *
         *
         * @param integer default -1
         */
        $maximum_tags_select_length = apply_filters( 'sk_product_tags_select_max_length', -1 );

        // Setting limitation for how many product tags that vendor can input.
        if ( $maximum_tags_select_length !== -1 && count( $data['product_tag'] ) !== 0 && count( $data['product_tag'] ) > $maximum_tags_select_length ) {
            /* translators: %s: maximum tag length */
            return new WP_Error( 'tags-limit', sprintf( __( 'You can only select %s tags', 'sk-core' ), number_format_i18n( $maximum_tags_select_length ) ) );
        }

        $post_data['tags'] = array_map( 'absint', (array) $data['product_tag'] );
    }

    if ( isset( $data['_regular_price'] ) ) {
        $post_data['regular_price'] = $data['_regular_price'] === '' ? '' : wc_format_decimal( $data['_regular_price'] );
    }

    if ( isset( $data['_sale_price'] ) ) {
        $post_data['sale_price'] = wc_format_decimal( $data['_sale_price'] );
    }

    if ( isset( $data['_sale_price_dates_from'] ) ) {
        $post_data['date_on_sale_from'] = wc_clean( $data['_sale_price_dates_from'] );
    }

    if ( isset( $data['_sale_price_dates_to'] ) ) {
        $post_data['date_on_sale_to'] = wc_clean( $data['_sale_price_dates_to'] );
    }

    if ( isset( $data['_visibility'] ) && array_key_exists( $data['_visibility'], sk_get_product_visibility_options() ) ) {
        $post_data['catalog_visibility'] = sanitize_text_field( $data['_visibility'] );
    }

    $product = sk()->product->create( $post_data );

    if ( $product ) {
        $chosen_cat = Helper::product_category_selection_is_single() ? [ reset( $data['chosen_product_cat'] ) ] : $data['chosen_product_cat'];
        Helper::set_object_terms_from_chosen_categories( $product->get_id(), $chosen_cat );
    }

    if ( ! $is_updating ) {
        do_action( 'sk_new_product_added', $product->get_id(), $data );
    } else {
        do_action( 'sk_product_updated', $product->get_id(), $data );
    }

    if ( $product ) {
        return $product->get_id();
    }

    return false;
}

/**
 * Show options for the variable product type.
 *
 *
 * @return void
 */
function sk_product_output_variations() {
    global $post, $wpdb;

    // Get attributes
    $attributes = maybe_unserialize( get_post_meta( $post->ID, '_product_attributes', true ) );

    // See if any are set
    $variation_attribute_found = false;

    if ( $attributes ) {
        foreach ( $attributes as $attribute ) {
            if ( ! empty( $attribute['is_variation'] ) ) {
                $variation_attribute_found = true;
                break;
            }
        }
    }

    $variations_count       = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM $wpdb->posts WHERE post_parent = %d AND post_type = 'product_variation' AND post_status IN ('publish', 'private', 'pending')", $post->ID ) ) );
    $variations_per_page    = absint( apply_filters( 'woocommerce_admin_meta_boxes_variations_per_page', 15 ) );
    $variations_total_pages = ceil( $variations_count / $variations_per_page ); ?>
    <div id="sk-variable-product-options" class="">
        <div id="sk-variable-product-options-inner">

        <?php if ( ! $variation_attribute_found ) { ?>

            <div id="sk-info-message" class="sk-alert sk-alert-info">
                <p>
                    <?php echo wp_kses( __( 'Before you can add a variation you need to add some variation attributes on the <strong>Attributes</strong> section', 'sk-core' ), [ 'strong' => [] ] ); ?>
                </p>
            </div>

        <?php } else { ?>

            <div class="sk-variation-top-toolbar">
                <div class="sk-variation-label content-half-part">
                    <div class="toolbar toolbar-top">
                        <select id="field_to_edit" class="variation-actions sk-form-control sk-w10">
                            <option data-global="true" value="add_variation"><?php esc_html_e( 'Add variation', 'sk-core' ); ?></option>
                            <option data-global="true" value="link_all_variations"><?php esc_html_e( 'Create variations from all attributes', 'sk-core' ); ?></option>
                            <option value="delete_all"><?php esc_html_e( 'Delete all variations', 'sk-core' ); ?></option>
                            <optgroup label="<?php esc_attr_e( 'Status', 'sk-core' ); ?>">
                                <option value="toggle_enabled"><?php esc_html_e( 'Toggle &quot;Enabled&quot;', 'sk-core' ); ?></option>
                                <option value="toggle_downloadable"><?php esc_html_e( 'Toggle &quot;Downloadable&quot;', 'sk-core' ); ?></option>
                                <option value="toggle_virtual"><?php esc_html_e( 'Toggle &quot;Virtual&quot;', 'sk-core' ); ?></option>
                            </optgroup>
                            <optgroup label="<?php esc_attr_e( 'Pricing', 'sk-core' ); ?>">
                                <option value="variable_regular_price"><?php esc_html_e( 'Set regular prices', 'sk-core' ); ?></option>
                                <option value="variable_regular_price_increase"><?php esc_html_e( 'Increase regular prices (fixed amount or percentage)', 'sk-core' ); ?></option>
                                <option value="variable_regular_price_decrease"><?php esc_html_e( 'Decrease regular prices (fixed amount or percentage)', 'sk-core' ); ?></option>
                                <option value="variable_sale_price"><?php esc_html_e( 'Set sale prices', 'sk-core' ); ?></option>
                                <option value="variable_sale_price_increase"><?php esc_html_e( 'Increase sale prices (fixed amount or percentage)', 'sk-core' ); ?></option>
                                <option value="variable_sale_price_decrease"><?php esc_html_e( 'Decrease sale prices (fixed amount or percentage)', 'sk-core' ); ?></option>
                                <option value="variable_sale_schedule"><?php esc_html_e( 'Set scheduled sale dates', 'sk-core' ); ?></option>
                            </optgroup>
                            <optgroup label="<?php esc_attr_e( 'Inventory', 'sk-core' ); ?>">
                                <option value="toggle_manage_stock"><?php esc_html_e( 'Toggle &quot;Manage stock&quot;', 'sk-core' ); ?></option>
                                <option value="variable_stock"><?php esc_html_e( 'Stock', 'sk-core' ); ?></option>
                            </optgroup>
                            <optgroup label="<?php esc_attr_e( 'Shipping', 'sk-core' ); ?>">
                                <option value="variable_length"><?php esc_html_e( 'Length', 'sk-core' ); ?></option>
                                <option value="variable_width"><?php esc_html_e( 'Width', 'sk-core' ); ?></option>
                                <option value="variable_height"><?php esc_html_e( 'Height', 'sk-core' ); ?></option>
                                <option value="variable_weight"><?php esc_html_e( 'Weight', 'sk-core' ); ?></option>
                            </optgroup>
                            <optgroup label="<?php esc_attr_e( 'Downloadable products', 'sk-core' ); ?>">
                                <option value="variable_download_limit"><?php esc_html_e( 'Download limit', 'sk-core' ); ?></option>
                                <option value="variable_download_expiry"><?php esc_html_e( 'Download expiry', 'sk-core' ); ?></option>
                            </optgroup>
                            <?php do_action( 'sk_variable_product_bulk_edit_actions' ); ?>
                        </select>
                        <a class="sk-btn sk-btn-default do_variation_action"><?php esc_html_e( 'Go', 'sk-core' ); ?></a>
                    </div>
                </div>

                <div class="sk-variation-default-toolbar content-half-part">

                    <div class="sk-variations-defaults">
                        <span class="sk-variation-default-label sk-left float-none"><i class="fas fa-question-circle tips" title="<?php esc_attr_e( 'Default Form Values: These are the attributes that will be pre-selected on the frontend.', 'sk-core' ); ?>" aria-hidden="true"></i></span>

                        <?php
                            $default_attributes = maybe_unserialize( get_post_meta( $post->ID, '_default_attributes', true ) );

                        foreach ( $attributes as $attribute ) {

                            // Only deal with attributes that are variations
                            if ( ! $attribute['is_variation'] ) {
                                continue;
                            }

                            echo '<div class="sk-variation-default-select sk-w5 float-none">';

                            // Get current value for variation (if set)
                            $variation_selected_value = isset( $default_attributes[ sanitize_title( $attribute['name'] ) ] ) ? $default_attributes[ sanitize_title( $attribute['name'] ) ] : '';

                            // Name will be something like attribute_pa_color
                            echo '<select class="sk-form-control" name="default_attribute_' . esc_attr( sanitize_title( $attribute['name'] ) ) . '" data-current="' . esc_attr( $variation_selected_value ) . '"><option value="">' . esc_html__( 'No default', 'sk-core' ) . ' ' . esc_html( wc_attribute_label( $attribute['name'] ) ) . '&hellip;</option>';

                            // Get terms for attribute taxonomy or value if its a custom attribute
                            if ( $attribute['is_taxonomy'] ) {
                                $post_terms = wp_get_post_terms( $post->ID, $attribute['name'] );

                                foreach ( $post_terms as $term ) {
                                    echo '<option ' . selected( $variation_selected_value, $term->slug, false ) . ' value="' . esc_attr( $term->slug ) . '">' . esc_html( apply_filters( 'woocommerce_variation_option_name', $term->name ) ) . '</option>';
                                }
                            } else {
                                $options = wc_get_text_attributes( $attribute['value'] );

                                foreach ( $options as $option ) {
                                    $selected = sanitize_title( $variation_selected_value ) === $variation_selected_value ? selected( $variation_selected_value, sanitize_title( $option ), false ) : selected( $variation_selected_value, $option, false );
                                    echo '<option ' . esc_attr( $selected ) . ' value="' . esc_attr( $option ) . '">' . esc_html( apply_filters( 'woocommerce_variation_option_name', $option ) ) . '</option>';
                                }
                            }

                            echo '</select>';
                            echo '</div>';
                        }
                        ?>
                        <div class="sk-clearfix"></div>

                    </div>

                </div>
                <div class="sk-clearfix"></div>
            </div>

            <div class="sk-variations-container wc-metaboxes" data-attributes="
            <?php
                // esc_attr does not double encode - htmlspecialchars does
                echo esc_attr( htmlspecialchars( wp_json_encode( $attributes ) ) );
            ?>
            " data-total="<?php echo esc_attr( $variations_count ); ?>" data-total_pages="<?php echo esc_attr( $variations_total_pages ); ?>" data-page="1" data-edited="false">
            </div>

            <div class="sk-variation-action-toolbar">
                <button class="sk-btn sk-btn-default sk-btn-theme save-variation-changes"><?php esc_html_e( 'Save Variations', 'sk-core' ); ?></button>
                <button class="sk-btn sk-btn-default cancel-variation-changes"><?php esc_html_e( 'Cancel', 'sk-core' ); ?></button>

                <div class="sk-variations-pagenav sk-right">
                    <span class="displaying-num">
                        <?php
                            /* translators: number of items */
                            $variations_count_text = _n( '%s item', '%s items', esc_attr( $variations_count ), 'sk-core' );
                            printf( esc_html( $variations_count_text ), esc_attr( $variations_count ) );
                        ?>
                    </span>
                    <span class="expand-close">
                        (<a href="#" class="expand_all"><?php esc_html_e( 'Expand', 'sk-core' ); ?></a> / <a href="#" class="close_all"><?php esc_html_e( 'Close', 'sk-core' ); ?></a>)
                    </span>
                    <span class="pagination-links">
                        <a class="first-page disabled" title="<?php esc_attr_e( 'Go to the first page', 'sk-core' ); ?>" href="#">&laquo;</a>
                        <a class="prev-page disabled" title="<?php esc_attr_e( 'Go to the previous page', 'sk-core' ); ?>" href="#">&lsaquo;</a>
                        <span class="paging-select">
                            <label for="current-page-selector-1" class="screen-reader-text"><?php esc_html_e( 'Select Page', 'sk-core' ); ?></label>
                            <select class="page-selector" id="current-page-selector-1" title="<?php esc_attr_e( 'Current page', 'sk-core' ); ?>">
                                <?php for ( $i = 1; $i <= $variations_total_pages; $i++ ) { ?>
                                    <option value="<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?></option>
                                <?php } ?>
                            </select>
                            <?php esc_html_x( 'of', 'number of pages', 'sk-core' ); ?> <span class="total-pages"><?php echo esc_html( $variations_total_pages ); ?></span>
                        </span>
                        <a class="next-page" title="<?php esc_attr_e( 'Go to the next page', 'sk-core' ); ?>" href="#">&rsaquo;</a>
                        <a class="last-page" title="<?php esc_attr_e( 'Go to the last page', 'sk-core' ); ?>" href="#">&raquo;</a>
                    </span>
                </div>

                <div class="sk-clearfix"></div>
            </div>
            <div class="sk-clearfix"></div>
        <?php } ?>

        </div>

    </div>
    <?php
}

/**
 * Get product visibility options.
 *
 *
 * @return array
 */
function sk_get_product_visibility_options() {
    return apply_filters(
        'sk_product_visibility_options', [
            'visible' => __( 'Visible', 'sk-core' ),
            'catalog' => __( 'Catalog', 'sk-core' ),
            'search'  => __( 'Search', 'sk-core' ),
            'hidden'  => __( 'Hidden', 'sk-core' ),
        ]
    );
}

/**
 * Search product data for a term and users ids and return only ids.
 *
 * @param string $term
 * @param string $user_ids
 * @param string $type               of product
 * @param bool   $include_variations in search or not
 *
 * @return array of ids
 */
function sk_search_seller_products( $term, $user_ids = false, $type = '', $include_variations = false ) {
    global $wpdb;

    $like_term     = '%' . $wpdb->esc_like( $term ) . '%';
    $post_types    = $include_variations ? [ 'product', 'product_variation' ] : [ 'product' ];
    $post_statuses = current_user_can( 'edit_private_products' ) ? [ 'private', 'publish' ] : [ 'publish' ];
    $type_join     = '';
    $type_where    = '';
    $users_where   = '';
    $query_args    = [ $like_term, $like_term, $like_term ];

    if ( $type ) {
        if ( in_array( $type, [ 'virtual', 'downloadable' ], true ) ) {
            $type_join  = " LEFT JOIN {$wpdb->postmeta} postmeta_type ON posts.ID = postmeta_type.post_id ";
            $type_where = " AND ( postmeta_type.meta_key = %s AND postmeta_type.meta_value = 'yes' ) ";
            $query_args[] = "_{$type}";
        }
    }

    if ( ! empty( $user_ids ) ) {
        if ( is_array( $user_ids ) ) {
            $users_where = " AND posts.post_author IN ('" . implode( "','", array_filter( array_map( 'absint', $user_ids ) ) ) . "')";
        } elseif ( is_numeric( $user_ids ) ) {
            $users_where = ' AND posts.post_author = %d';
            $query_args[] = $user_ids;
        }
    }
    // phpcs:ignore WordPress.DB.PreparedSQL
    $product_ids = $wpdb->get_col(
        // phpcs:disable
        $wpdb->prepare( "
            SELECT DISTINCT posts.ID FROM {$wpdb->posts} posts
            LEFT JOIN {$wpdb->postmeta} postmeta ON posts.ID = postmeta.post_id
            $type_join
            WHERE (
                posts.post_title LIKE %s
                OR posts.post_content LIKE %s
                OR (
                    postmeta.meta_key = '_sku' AND postmeta.meta_value LIKE %s
                )
            )
            AND posts.post_type IN ('" . implode( "','", $post_types ) . "')
            AND posts.post_status IN ('" . implode( "','", $post_statuses ) . "')
            $type_where
            $users_where
            ORDER BY posts.post_parent ASC, posts.post_title ASC
            ",
            $query_args
        )
        // phpcs:enable
    );

    if ( is_numeric( $term ) ) {
        $post_id   = absint( $term );
        $post_type = get_post_type( $post_id );

        if ( 'product_variation' === $post_type && $include_variations ) {
            $product_ids[] = $post_id;
        } elseif ( 'product' === $post_type ) {
            $product_ids[] = $post_id;
        }

        $product_ids[] = wp_get_post_parent_id( $post_id );
    }

    return wp_parse_id_list( $product_ids );
}

/**
 * Callback for array filter to get products the user can edit only.
 *
 *
 * @param WC_Product $product
 *
 * @return bool
 */
function sk_products_array_filter_editable( $product ) {
    return $product && is_a( $product, 'WC_Product' ) && current_user_can( 'skdar', $product->get_id() );
}

/**
 * Get row action for product
 *
 *
 * @param object|int|string $post
 * @param bool              $format_html (Optional)
 *
 * @return array
 */
function sk_product_get_row_action( $post, $format_html = true ) {
    if ( is_numeric( $post ) ) {
        $post = get_post( $post );
    }

    if ( empty( $post->ID ) ) {
        return [];
    }

    $row_action      = [];
    $row_action_html = [];
    $product_id      = $post->ID;

    if ( current_user_can( 'sk_edit_product' ) ) {
        $row_action['edit'] = [
            'title' => __( 'Edit', 'sk-core' ),
            'url'   => sk_edit_product_url( $product_id ),
            'class' => 'edit',
        ];
    }

    if ( current_user_can( 'sk_delete_product' ) ) {
        $row_action['delete'] = [
            'title' => __( 'Delete Permanently', 'sk-core' ),
            'url'   => wp_nonce_url(
                add_query_arg(
                    [
                        'action'     => 'sk-delete-product',
                        'product_id' => $product_id,
                    ], sk_get_navigation_url( 'products' )
                ), 'sk-delete-product'
            ),
            'class' => 'delete',
            'other' => 'onclick="sk_show_delete_prompt( event, \'' . __( 'Are you sure?', 'sk-core' ) . '\' );"',
        ];
    }

    if ( current_user_can( 'sk_view_product' ) && $post->post_status !== 'pending' ) {
        $row_action['view'] = [
            'title' => __( 'View', 'sk-core' ),
            'url'   => get_permalink( $product_id ),
            'class' => 'view',
        ];
    }

    $row_action = apply_filters( 'sk_product_row_actions', $row_action, $post );

    if ( empty( $row_action ) ) {
        return $row_action;
    }

    if ( ! $format_html ) {
        return $row_action;
    }

    $i            = 0;
    $action_count = count( $row_action );

    foreach ( $row_action as $key => $action ) {
        ++$i;

        $sep = ( $i < $action_count ) ? ' | ' : '';

        $row_action_html[ $key ] = sprintf( '<span class="%s"><a href="%s" %s>%s</a>%s</span>', $action['class'], esc_url( $action['url'] ), isset( $action['other'] ) ? $action['other'] : '', $action['title'], $sep );
    }

    $row_action_html = apply_filters( 'sk_product_row_action_html', $row_action_html, $post );

    return implode( ' ', $row_action_html );
}

/**
 * SK get vendor by product
 *
 * @param int|WC_Product $product Product ID or Product Object
 * @param bool $get_vendor return true to get vendor id, otherwise it will return \SK\Core\Vendor\Vendor object
 *
 *
 * @return int|\SK\Core\Vendor\Vendor|false on failure
 */
function sk_get_vendor_by_product( $product, $get_vendor_id = false ) {
    if ( ! $product instanceof WC_Product ) {
        $product = wc_get_product( $product );
    }

    if ( ! $product ) {
        return false;
    }

    $vendor_id = get_post_field( 'post_author', $product->get_id() );

    if ( ! $vendor_id && 'variation' === $product->get_type() ) {
        $vendor_id = get_post_field( 'post_author', $product->get_parent_id() );
    }

    $vendor_id = apply_filters( 'sk_get_vendor_by_product', $vendor_id, $product );

    return false === $get_vendor_id ? sk()->vendor->get( $vendor_id ) : (int) $vendor_id;
}

/**
 * Get translated product stock status
 *
 *
 * @param mix $stock
 *
 * @return string | array if stock parameter is not provided
 */
function sk_get_translated_product_stock_status( $stock = false ) {
    $stock_status = wc_get_product_stock_status_options();

    if ( ! $stock ) {
        return $stock_status;
    }

    return isset( $stock_status[ $stock ] ) ? $stock_status[ $stock ] : '';
}

/**
 * Get sk store products filter catalog orderby
 *
 *
 * @return array
 */
function sk_store_product_catalog_orderby() {
    $show_default_orderby = 'menu_order' === apply_filters( 'sk_default_store_products_orderby', get_option( 'woocommerce_default_catalog_orderby', 'menu_order' ) );

    $catalog_orderby_options = apply_filters(
        'sk_store_product_catalog_orderby',
        array(
            'menu_order' => __( 'Default sorting', 'sk-core' ),
            'popularity' => __( 'Sort by popularity', 'sk-core' ),
            'rating'     => __( 'Sort by average rating', 'sk-core' ),
            'date'       => __( 'Sort by latest', 'sk-core' ),
            'price'      => __( 'Sort by price: low to high', 'sk-core' ),
            'price-desc' => __( 'Sort by price: high to low', 'sk-core' ),
        )
    );

    $default_orderby = wc_get_loop_prop( 'is_search' ) ? 'relevance' : apply_filters( 'sk_default_store_products_orderby', get_option( 'woocommerce_default_catalog_orderby', '' ) );
    $orderby = isset( $_GET['product_orderby'] ) ? wc_clean( wp_unslash( $_GET['product_orderby'] ) ) : $default_orderby; //phpcs:ignore

    if ( wc_get_loop_prop( 'is_search' ) ) {
        $catalog_orderby_options = array_merge( array( 'relevance' => __( 'Relevance', 'sk-core' ) ), $catalog_orderby_options );

        unset( $catalog_orderby_options['menu_order'] );
    }

    if ( ! $show_default_orderby ) {
        unset( $catalog_orderby_options['menu_order'] );
    }

    if ( ! wc_review_ratings_enabled() ) {
        unset( $catalog_orderby_options['rating'] );
    }

    if ( ! array_key_exists( $orderby, $catalog_orderby_options ) ) {
        $orderby = current( array_keys( $catalog_orderby_options ) );
    }

    $orderby_options = array(
        'show_default_orderby'    => $show_default_orderby,
        'orderby'                 => $orderby,
        'catalogs'                => $catalog_orderby_options,
    );

    return $orderby_options;
}
