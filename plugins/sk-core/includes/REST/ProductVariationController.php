<?php

namespace SK\Core\REST;

use WC_Data;
use WC_Product;
use WC_Product_Variation;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;
use WP_REST_Request;
use SK\Core\REST\ProductController;

/**
* Product Variation controller
*
*
*/
class ProductVariationController extends ProductController {

    /**
     * Endpoint namespace.
     *
     * @var string
     */
    protected $namespace = 'sk/v1';

    /**
     * Route base.
     *
     * @var string
     */
    protected $rest_base = 'products/(?P<product_id>[\d]+)/variations';

    /**
     * Post type.
     *
     * @var string
     */
    protected $post_type = 'product_variation';

    /**
     * Register the routes for products.
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace, '/' . $this->rest_base, array(
                'args' => array(
                    'product_id' => array(
                        'description' => __( 'Unique identifier for the variable product.', 'sk' ),
                        'type'        => 'integer',
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_items' ),
                    'permission_callback' => array( $this, 'get_product_permissions_check' ),
                    'args'                => $this->get_collection_params(), // @phpstan-ignore-line
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'create_item' ),
                    'permission_callback' => array( $this, 'create_product_permissions_check' ),
                    'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ), // @phpstan-ignore-line
                ),
                'schema' => array( $this, 'get_public_item_schema' ),
            )
        );
        register_rest_route(
            $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
                'args' => array(
                    'product_id' => array(
                        'description' => __( 'Unique identifier for the variable product.', 'sk' ),
                        'type'        => 'integer',
                    ),
                    'id' => array(
                        'description' => __( 'Unique identifier for the variation.', 'sk' ),
                        'type'        => 'integer',
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_item' ),
                    'permission_callback' => array( $this, 'get_single_product_permissions_check' ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'update_item' ),
                    'permission_callback' => array( $this, 'update_product_permissions_check' ),
                    'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ), // @phpstan-ignore-line
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'delete_item' ),
                    'permission_callback' => array( $this, 'delete_product_permissions_check' ),
                ),

                'schema' => array( $this, 'get_public_item_schema' ),
            )
        );

        register_rest_route(
            $this->namespace, '/' . $this->rest_base . '/batch', array(
                'args'   => array(
                    'product_id' => array(
                        'description' => __( 'Product ID for which variations will be managed.', 'sk' ),
                        'type'        => 'integer',
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'batch_items' ),
                    'permission_callback' => array( $this, 'batch_items_permissions_check' ),
                    'validate_callback'   => array( $this, 'validate_batch_items' ),
                    'args'                => rest_get_endpoint_args_for_schema( $this->get_batch_schema(), WP_REST_Server::EDITABLE ), // @phpstan-ignore-line
                ),
                'schema' => array( $this, 'get_batch_schema' ),
            )
        );
    }

    /**
     * Get object.
     *
     * @param  int $id Object ID | Object.
     * @return WC_Product|null|false
     */
    public function get_object( $id ) {
        return wc_get_product( $id );
    }

    /**
     * Get a collection of posts.
     *
     *
     * @param WP_REST_Request $request Full details about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_items( $request ) {
        // Get variations
        $args = apply_filters(
            'woocommerce_ajax_admin_get_variations_args', [
				'post_status' => wp_parse_args( [ 'private', 'publish' ], $this->post_status ),
			], $request->get_param( 'product_id' )
        );

        /**
         * We have to set `post_status` in this way because in sk lite it checks for `status` in sk lite `ProductController.php` and
         * `post_status` in `SkRESTController.php` if not found the status is updating  and the variations goes blank, see the issue and links as provided below.
         *
         * @see https://github.com/getsk/sk/blob/e1e8dd1b26ddd46a45f76bec32dc1b7bdc00d5db/includes/REST/ProductController.php#L632
         * @see https://github.com/getsk/sk/blob/e1e8dd1b26ddd46a45f76bec32dc1b7bdc00d5db/includes/Abstracts/SkRESTController.php#L298
         *
         * @issue https://github.com/getsk/plugin-vendor-dashboard/pull/119#issuecomment-2272773255
         */
        $request->set_param( 'post_status', $args['post_status'] );
        $request->set_param( 'status', $args['post_status'] );

        $response = parent::get_items( $request ); // @phpstan-ignore-line

        $data = $response->get_data();

        $loop = 0;

        foreach ( $data as $key => $item ) {
            $data[ $key ]['menu_order'] = $loop;

            ++$loop;
        }

        $response->set_data( $data );

        return $response;
    }

    /**
     * Prepare objects query.
     *
     *
     * @param  WP_REST_Request $request Full details about the request.
     * @return array
     */
    protected function prepare_items_query( $prepared_args = array(), $request = null ) {
        $args = parent::prepare_items_query( $prepared_args, $request ); // @phpstan-ignore-line

        $args['orderby'] = [
            'menu_order' => 'ASC',
            'ID'         => 'ASC',
        ];

        return apply_filters( 'sk_pro_api_get_product_variations_args', $args );
    }

    /**
     * Validation before create variation item
     *
     * @param WP_REST_Request $request
     *
     *
     * @return bool|WP_Error
     */
    public function validation_before_create_item( $request ) {
        $variable_product_id = $request->get_param( 'product_id' );
        $variable_product    = wc_get_product( $variable_product_id );

        if ( ! $variable_product ) {
            return new WP_Error(
                'invalid_product_id',
                __( 'Invalid product id to create variations.', 'sk' ),
                [ 'status' => 404 ]
            );
        }

        return true;
    }

    /**
     * Validation before create variation item
     *
     * @param WP_REST_Request $request
     *
     *
     * @return bool|WP_Error
     */
    public function validate_batch_items( $request ) {
        $data = $request->get_params();

        if ( isset( $data['update'] ) && is_array( $data['update'] ) ) {
            foreach ( $data['update'] as $key => $item ) {
                if ( isset( $item['regular_price'] ) && ( ! empty( $item['regular_price'] ) && ! is_numeric( $item['regular_price'] ) ) ) {
                    return new WP_Error(
                        'invalid_param',
                        /* translators: %d: decimal */
                        sprintf( __( 'Param update[%d][regular_price] is not of type number or an empty value', 'sk' ), $key ),
                        [
                            'status' => 400,
                        ]
                    );
                }
            }
        }

        return true;
    }

    /**
     * Check if a given request has access to batch modification of items.
     *
     *
     * @param  WP_REST_Request $request Details about the request.
     *
     * @return bool
     */
    public function batch_items_permissions_check( $request ) {
        return current_user_can( 'skdar' );
    }

    /**
     * Get product data.
     *
     * @param WC_Product      $data_object Product instance.
     * @param WP_REST_Request $request     Request
     *
     * @return WP_REST_Response
     */
    protected function prepare_data_for_response( $data_object, $request ) {
        $data = array(
            'id'                    => $data_object->get_id(),
            'date_created'          => wc_rest_prepare_date_response( $data_object->get_date_created(), false ),
            'date_created_gmt'      => wc_rest_prepare_date_response( $data_object->get_date_created() ),
            'date_modified'         => wc_rest_prepare_date_response( $data_object->get_date_modified(), false ),
            'date_modified_gmt'     => wc_rest_prepare_date_response( $data_object->get_date_modified() ),
            'description'           => wc_format_content( $data_object->get_description() ),
            'permalink'             => $data_object->get_permalink(),
            'sku'                   => $data_object->get_sku(),
            'price'                 => $data_object->get_price(),
            'regular_price'         => $data_object->get_regular_price(),
            'sale_price'            => $data_object->get_sale_price(),
            'date_on_sale_from'     => wc_rest_prepare_date_response( $data_object->get_date_on_sale_from(), false ),
            'date_on_sale_from_gmt' => wc_rest_prepare_date_response( $data_object->get_date_on_sale_from() ),
            'date_on_sale_to'       => wc_rest_prepare_date_response( $data_object->get_date_on_sale_to(), false ),
            'date_on_sale_to_gmt'   => wc_rest_prepare_date_response( $data_object->get_date_on_sale_to() ),
            'on_sale'               => $data_object->is_on_sale(),
            'visible'               => $data_object->is_visible(),
            'purchasable'           => $data_object->is_purchasable(),
            'virtual'               => $data_object->is_virtual(),
            'downloadable'          => $data_object->is_downloadable(),
            'downloads'             => $this->get_downloads( $data_object ), // @phpstan-ignore-line
            'download_limit'        => '' !== $data_object->get_download_limit() ? (int) $data_object->get_download_limit() : -1,
            'download_expiry'       => '' !== $data_object->get_download_expiry() ? (int) $data_object->get_download_expiry() : -1,
            'tax_status'            => $data_object->get_tax_status(),
            'tax_class'             => $data_object->get_tax_class(),
            'manage_stock'          => $data_object->managing_stock(),
            'stock_quantity'        => $data_object->get_stock_quantity(),
            'in_stock'              => $data_object->is_in_stock(),
            'backorders'            => $data_object->get_backorders(),
            'backorders_allowed'    => $data_object->backorders_allowed(),
            'backordered'           => $data_object->is_on_backorder(),
            'weight'                => $data_object->get_weight(),
            'dimensions'            => array(
                'length'            => $data_object->get_length(),
                'width'             => $data_object->get_width(),
                'height'            => $data_object->get_height(),
            ),
            'length'                => $data_object->get_length(),
            'width'                 => $data_object->get_width(),
            'height'                => $data_object->get_height(),
            'shipping_class'        => $data_object->get_shipping_class(),
            'shipping_class_id'     => $data_object->get_shipping_class_id(),
            'image'                 => current( $this->get_images( $data_object ) ), // @phpstan-ignore-line
            'attributes'            => $this->get_attributes( $data_object ), // @phpstan-ignore-line
            'menu_order'            => $data_object->get_menu_order(),
            'meta_data'             => $data_object->get_meta_data(),
        );

        $response = rest_ensure_response( $data );
        $response->add_links( $this->prepare_links( $data_object, $request ) ); // @phpstan-ignore-line

        return apply_filters( "sk_rest_prepare_{$this->post_type}_object", $response, $data_object );
    }

    /**
     * Prepare a single variation for create or update.
     *
     * @param  WP_REST_Request $request Request object.
     * @param  bool            $creating If is creating a new object.
     *
     * @return WP_Error|WC_Data
     */
    protected function prepare_object_for_database( $request, $creating = false ) {
        if ( isset( $request['id'] ) ) {
            $variation = wc_get_product( absint( $request['id'] ) );
        } else {
            $variation = new WC_Product_Variation();
        }

        $variation->set_parent_id( absint( $request['product_id'] ) );

        // Status.
        if ( isset( $request['visible'] ) ) {
            $variation->set_status( false === $request['visible'] ? 'private' : 'publish' );
        }

        // SKU.
        if ( isset( $request['sku'] ) ) {
            $variation->set_sku( wc_clean( $request['sku'] ) );
        }

        // Thumbnail.
        if ( isset( $request['image'] ) ) {
            if ( is_array( $request['image'] ) ) {
                $image = $request['image'];
                if ( is_array( $image ) ) {
                    $image['position'] = 0;
                }

                $variation = $this->set_product_images( $variation, array( $image ) ); // @phpstan-ignore-line
            } else {
                $variation->set_image_id( '' );
            }
        }

        // Virtual variation.
        if ( isset( $request['virtual'] ) ) {
            $variation->set_virtual( $request['virtual'] );
        }

        // Downloadable variation.
        if ( isset( $request['downloadable'] ) ) {
            $variation->set_downloadable( $request['downloadable'] );
        }

        // Downloads.
        if ( $variation->get_downloadable() ) {
            // Downloadable files.
            if ( isset( $request['downloads'] ) && is_array( $request['downloads'] ) ) {
                $variation = $this->save_downloadable_files( $variation, $request['downloads'] ); // @phpstan-ignore-line
            }

            // Download limit.
            if ( isset( $request['download_limit'] ) ) {
                $variation->set_download_limit( $request['download_limit'] );
            }

            // Download expiry.
            if ( isset( $request['download_expiry'] ) ) {
                $variation->set_download_expiry( $request['download_expiry'] );
            }
        }

        // Shipping data.
        $variation = $this->save_product_shipping_data( $variation, $request ); // @phpstan-ignore-line

        // Stock handling.
        if ( isset( $request['manage_stock'] ) ) {
            $variation->set_manage_stock( $request['manage_stock'] );
        }

        if ( isset( $request['in_stock'] ) ) {
            $variation->set_stock_status( true === $request['in_stock'] ? 'instock' : 'outofstock' );
        }

        if ( isset( $request['backorders'] ) ) {
            $variation->set_backorders( $request['backorders'] );
        }

        if ( $variation->get_manage_stock() ) {
            if ( isset( $request['stock_quantity'] ) ) {
                $variation->set_stock_quantity( $request['stock_quantity'] );
            } elseif ( isset( $request['inventory_delta'] ) ) {
                $stock_quantity  = wc_stock_amount( $variation->get_stock_quantity() );
                $stock_quantity += wc_stock_amount( $request['inventory_delta'] );
                $variation->set_stock_quantity( $stock_quantity );
            }
        } else {
            $variation->set_backorders( 'no' );
            $variation->set_stock_quantity( '' );
        }

        // Regular Price.
        if ( isset( $request['regular_price'] ) ) {
            $variation->set_regular_price( $request['regular_price'] );
        }

        // Sale Price.
        if ( isset( $request['sale_price'] ) ) {
            $variation->set_sale_price( $request['sale_price'] );
        }

        if ( isset( $request['date_on_sale_from'] ) ) {
            $variation->set_date_on_sale_from( $request['date_on_sale_from'] );
        }

        if ( isset( $request['date_on_sale_from_gmt'] ) ) {
            $variation->set_date_on_sale_from( $request['date_on_sale_from_gmt'] ? strtotime( $request['date_on_sale_from_gmt'] ) : null );
        }

        if ( isset( $request['date_on_sale_to'] ) ) {
            $variation->set_date_on_sale_to( $request['date_on_sale_to'] );
        }

        if ( isset( $request['date_on_sale_to_gmt'] ) ) {
            $variation->set_date_on_sale_to( $request['date_on_sale_to_gmt'] ? strtotime( $request['date_on_sale_to_gmt'] ) : null );
        }

        // Tax class.
        if ( isset( $request['tax_class'] ) ) {
            $variation->set_tax_class( $request['tax_class'] );
        }

        // Description.
        if ( isset( $request['description'] ) ) {
            $variation->set_description( wp_kses_post( $request['description'] ) );
        }

        // Update taxonomies.
        if ( isset( $request['attributes'] ) ) {
            $attributes        = array();
            $parent            = wc_get_product( $variation->get_parent_id() );
            $parent_attributes = $parent->get_attributes();

            foreach ( $request['attributes'] as $attribute ) {
                $attribute_id   = 0;
                $attribute_name = '';

                // Check ID for global attributes or name for product attributes.
                if ( ! empty( $attribute['id'] ) ) {
                    $attribute_id   = absint( $attribute['id'] );
                    $attribute_name = wc_attribute_taxonomy_name_by_id( $attribute_id );
                } elseif ( ! empty( $attribute['name'] ) ) {
                    $attribute_name = sanitize_title( $attribute['name'] );
                }

                if ( ! $attribute_id && ! $attribute_name ) {
                    continue;
                }

                if ( ! isset( $parent_attributes[ $attribute_name ] ) || ! $parent_attributes[ $attribute_name ]->get_variation() ) {
                    continue;
                }

                $attribute_key   = sanitize_title( $parent_attributes[ $attribute_name ]->get_name() );
                $attribute_value = isset( $attribute['option'] ) ? wc_clean( stripslashes( $attribute['option'] ) ) : '';

                if ( $parent_attributes[ $attribute_name ]->is_taxonomy() ) {
                    // If dealing with a taxonomy, we need to get the slug from the name posted to the API.
                    // @codingStandardsIgnoreStart
                    $term = get_term_by( 'name', $attribute_value, $attribute_name );
                    // @codingStandardsIgnoreEnd

                    if ( $term && ! is_wp_error( $term ) ) {
                        $attribute_value = $term->slug;
                    } else {
                        $attribute_value = sanitize_title( $attribute_value );
                    }
                }

                $attributes[ $attribute_key ] = $attribute_value;
            }

            $variation->set_attributes( $attributes );
        }

        // Menu order.
        if ( isset( $request['menu_order'] ) ) {
            $variation->set_menu_order( $request['menu_order'] );
        }

        // Meta data.
        if ( isset( $request['meta_data'] ) && is_array( $request['meta_data'] ) ) {
            foreach ( $request['meta_data'] as $meta ) {
                $variation->update_meta_data( $meta['key'], $meta['value'], isset( $meta['id'] ) ? $meta['id'] : '' );
            }
        }

        return apply_filters( "sk_rest_pre_insert_{$this->post_type}_object", $variation, $request, $creating );
    }

    /**
     * Prepare objects query.
     *
     * @param  WP_REST_Request $request Full details about the request.
     * @return array
     */
    protected function prepare_objects_query( $request ) {
        $args = parent::prepare_objects_query( $request ); // @phpstan-ignore-line

        $args['post_parent'] = $request['product_id'];

        return $args;
    }

    /**
     * Delete a variation.
     *
     * @param WP_REST_Request $request Full details about the request.
     *
     * @return bool|WP_Error|WP_REST_Response
     */
    public function delete_item( $request ) {
        $object = $this->get_object( (int) $request['id'] );
        $result = false;

        if ( ! $object || 0 === $object->get_id() ) {
            return new WP_Error(
                "sk_rest_{$this->post_type}_invalid_id", __( 'Invalid ID.', 'sk' ), array(
                    'status' => 404,
                )
            );
        }

        $response = $this->prepare_data_for_response( $object, $request );

        // If we're forcing, then delete permanently.
        $object->delete( true );
        $result = 0 === $object->get_id();

        if ( ! $result ) {
            return new WP_Error(
                /* translators: %s: post type */
                'sk_rest_cannot_delete', sprintf( __( 'The %s cannot be deleted.', 'sk' ), $this->post_type ), array(
                    'status' => 500,
                )
            );
        }

        // Delete parent product transients.
        if ( 0 !== $object->get_parent_id() ) {
            wc_delete_product_transients( $object->get_parent_id() );
        }

        do_action( "sk_rest_delete_{$this->post_type}_object", $object, $response, $request );

        return $response;
    }

    /**
     * Prepare links for the request.
     *
     * @param WC_Data         $data_object Object data.
     * @param WP_REST_Request $request     Request object.
     *
     * @return array                   Links for the given post.
     */
    protected function prepare_links( $data_object, $request ) {
        $product_id = $request['product_id'];
        $base       = str_replace( '(?P<product_id>[\d]+)', $product_id, $this->rest_base );
        $links      = array(
            'self' => array(
                'href' => rest_url( sprintf( '/%s/%s/%d', $this->namespace, $base, $data_object->get_id() ) ),
            ),
            'collection' => array(
                'href' => rest_url( sprintf( '/%s/%s', $this->namespace, $base ) ),
            ),
            'up' => array(
                'href' => rest_url( sprintf( '/%s/products/%d', $this->namespace, $product_id ) ),
            ),
        );
        return $links;
    }

    /**
     * Get the Variation's schema, conforming to JSON Schema.
     *
     * @return array
     */
    public function get_item_schema() {
        $weight_unit    = get_option( 'woocommerce_weight_unit' );
        $dimension_unit = get_option( 'woocommerce_dimension_unit' );
        $schema         = array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => $this->post_type,
            'type'       => 'object',
            'properties' => array(
                'id'                    => array(
                    'description' => __( 'Unique identifier for the resource.', 'sk' ),
                    'type'        => 'integer',
                    'context'     => array( 'view', 'edit' ),
                    'readonly'    => true,
                ),
                'date_created'          => array(
                    'description' => __( "The date the variation was created, in the site's timezone.", 'sk' ),
                    'type'        => 'date-time',
                    'context'     => array( 'view', 'edit' ),
                    'readonly'    => true,
                ),
                'date_modified'         => array(
                    'description' => __( "The date the variation was last modified, in the site's timezone.", 'sk' ),
                    'type'        => 'date-time',
                    'context'     => array( 'view', 'edit' ),
                    'readonly'    => true,
                ),
                'description'           => array(
                    'description' => __( 'Variation description.', 'sk' ),
                    'type'        => 'string',
                    'context'     => array( 'view', 'edit' ),
                ),
                'permalink'             => array(
                    'description' => __( 'Variation URL.', 'sk' ),
                    'type'        => 'string',
                    'format'      => 'uri',
                    'context'     => array( 'view', 'edit' ),
                    'readonly'    => true,
                ),
                'sku'                   => array(
                    'description' => __( 'Unique identifier.', 'sk' ),
                    'type'        => 'string',
                    'context'     => array( 'view', 'edit' ),
                ),
                'price'                 => array(
                    'description' => __( 'Current variation price.', 'sk' ),
                    'type'        => 'number',
                    'context'     => array( 'view', 'edit' ),
                    'readonly'    => true,
                ),
                'regular_price'         => array(
                    'description' => __( 'Variation regular price.', 'sk' ),
                    'type'        => array( 'number', 'string' ),
                    'context'     => array( 'view', 'edit' ),
                ),
                'sale_price'            => array(
                    'description' => __( 'Variation sale price.', 'sk' ),
                    'type'        => 'number',
                    'context'     => array( 'view', 'edit' ),
                ),
                'date_on_sale_from'     => array(
                    'description' => __( "Start date of sale price, in the site's timezone.", 'sk' ),
                    'type'        => 'date-time',
                    'context'     => array( 'view', 'edit' ),
                ),
                'date_on_sale_from_gmt' => array(
                    'description' => __( 'Start date of sale price, as GMT.', 'sk' ),
                    'type'        => 'date-time',
                    'context'     => array( 'view', 'edit' ),
                ),
                'date_on_sale_to'       => array(
                    'description' => __( "End date of sale price, in the site's timezone.", 'sk' ),
                    'type'        => 'date-time',
                    'context'     => array( 'view', 'edit' ),
                ),
                'date_on_sale_to_gmt'   => array(
                    'description' => __( 'End date of sale price, as GMT.', 'sk' ),
                    'type'        => 'date-time',
                    'context'     => array( 'view', 'edit' ),
                ),
                'on_sale'               => array(
                    'description' => __( 'Shows if the variation is on sale.', 'sk' ),
                    'type'        => 'boolean',
                    'context'     => array( 'view', 'edit' ),
                    'readonly'    => true,
                ),
                'visible'               => array(
                    'description' => __( "Define if the variation is visible on the product's page.", 'sk' ),
                    'type'        => 'boolean',
                    'default'     => true,
                    'context'     => array( 'view', 'edit' ),
                ),
                'purchasable'           => array(
                    'description' => __( 'Shows if the variation can be bought.', 'sk' ),
                    'type'        => 'boolean',
                    'context'     => array( 'view', 'edit' ),
                    'readonly'    => true,
                ),
                'virtual'               => array(
                    'description' => __( 'If the variation is virtual.', 'sk' ),
                    'type'        => 'boolean',
                    'default'     => false,
                    'context'     => array( 'view', 'edit' ),
                ),
                'downloadable'          => array(
                    'description' => __( 'If the variation is downloadable.', 'sk' ),
                    'type'        => 'boolean',
                    'default'     => false,
                    'context'     => array( 'view', 'edit' ),
                ),
                'downloads'             => array(
                    'description' => __( 'List of downloadable files.', 'sk' ),
                    'type'        => 'array',
                    'context'     => array( 'view', 'edit' ),
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'id'   => array(
                                'description' => __( 'File MD5 hash.', 'sk' ),
                                'type'        => 'string',
                                'context'     => array( 'view', 'edit' ),
                                'readonly'    => true,
                            ),
                            'name' => array(
                                'description' => __( 'File name.', 'sk' ),
                                'type'        => 'string',
                                'context'     => array( 'view', 'edit' ),
                            ),
                            'file' => array(
                                'description' => __( 'File URL.', 'sk' ),
                                'type'        => 'string',
                                'context'     => array( 'view', 'edit' ),
                            ),
                        ),
                    ),
                ),
                'download_limit'        => array(
                    'description' => __( 'Number of times downloadable files can be downloaded after purchase.', 'sk' ),
                    'type'        => 'integer',
                    'default'     => -1,
                    'context'     => array( 'view', 'edit' ),
                ),
                'download_expiry'       => array(
                    'description' => __( 'Number of days until access to downloadable files expires.', 'sk' ),
                    'type'        => 'integer',
                    'default'     => -1,
                    'context'     => array( 'view', 'edit' ),
                ),
                'tax_status'            => array(
                    'description' => __( 'Tax status.', 'sk' ),
                    'type'        => 'string',
                    'default'     => 'taxable',
                    'enum'        => array( 'taxable', 'shipping', 'none' ),
                    'context'     => array( 'view', 'edit' ),
                ),
                'tax_class'             => array(
                    'description' => __( 'Tax class.', 'sk' ),
                    'type'        => 'string',
                    'context'     => array( 'view', 'edit' ),
                ),
                'manage_stock'          => array(
                    'description' => __( 'Stock management at variation level.', 'sk' ),
                    'type'        => 'mixed',
                    'default'     => false,
                    'context'     => array( 'view', 'edit' ),
                ),
                'stock_quantity'        => array(
                    'description' => __( 'Stock quantity.', 'sk' ),
                    'type'        => 'integer',
                    'context'     => array( 'view', 'edit' ),
                ),
                'in_stock'              => array(
                    'description' => __( 'Controls whether or not the variation is listed as "in stock" or "out of stock" on the frontend.', 'sk' ),
                    'type'        => 'boolean',
                    'default'     => true,
                    'context'     => array( 'view', 'edit' ),
                ),
                'backorders'            => array(
                    'description' => __( 'If managing stock, this controls if backorders are allowed.', 'sk' ),
                    'type'        => 'string',
                    'default'     => 'no',
                    'enum'        => array( 'no', 'notify', 'yes' ),
                    'context'     => array( 'view', 'edit' ),
                ),
                'backorders_allowed'    => array(
                    'description' => __( 'Shows if backorders are allowed.', 'sk' ),
                    'type'        => 'boolean',
                    'context'     => array( 'view', 'edit' ),
                    'readonly'    => true,
                ),
                'backordered'           => array(
                    'description' => __( 'Shows if the variation is on backordered.', 'sk' ),
                    'type'        => 'boolean',
                    'context'     => array( 'view', 'edit' ),
                    'readonly'    => true,
                ),
                'weight'                => array(
                    /* translators: %s: weight unit */
                    'description' => sprintf( __( 'Variation weight (%s).', 'sk' ), $weight_unit ),
                    'type'        => 'string',
                    'context'     => array( 'view', 'edit' ),
                ),
                'dimensions'            => array(
                    'description' => __( 'Variation dimensions.', 'sk' ),
                    'type'        => 'object',
                    'context'     => array( 'view', 'edit' ),
                    'properties'  => array(
                        'length' => array(
                            /* translators: %s: dimension unit */
                            'description' => sprintf( __( 'Variation length (%s).', 'sk' ), $dimension_unit ),
                            'type'        => 'string',
                            'context'     => array( 'view', 'edit' ),
                        ),
                        'width'  => array(
                            /* translators: %s: dimension unit */
                            'description' => sprintf( __( 'Variation width (%s).', 'sk' ), $dimension_unit ),
                            'type'        => 'string',
                            'context'     => array( 'view', 'edit' ),
                        ),
                        'height' => array(
                            /* translators: %s: dimension unit */
                            'description' => sprintf( __( 'Variation height (%s).', 'sk' ), $dimension_unit ),
                            'type'        => 'string',
                            'context'     => array( 'view', 'edit' ),
                        ),
                    ),
                ),
                'shipping_class'        => array(
                    'description' => __( 'Shipping class slug.', 'sk' ),
                    'type'        => 'string',
                    'context'     => array( 'view', 'edit' ),
                ),
                'shipping_class_id'     => array(
                    'description' => __( 'Shipping class ID.', 'sk' ),
                    'type'        => 'string',
                    'context'     => array( 'view', 'edit' ),
                    'readonly'    => true,
                ),
                'image'                 => array(
                    'description' => __( 'Variation image data.', 'sk' ),
                    'type'        => 'object',
                    'context'     => array( 'view', 'edit' ),
                    'properties'  => array(
                        'id'                => array(
                            'description' => __( 'Image ID.', 'sk' ),
                            'type'        => 'integer',
                            'context'     => array( 'view', 'edit' ),
                        ),
                        'date_created'      => array(
                            'description' => __( "The date the image was created, in the site's timezone.", 'sk' ),
                            'type'        => 'date-time',
                            'context'     => array( 'view', 'edit' ),
                            'readonly'    => true,
                        ),
                        'date_created_gmt'  => array(
                            'description' => __( 'The date the image was created, as GMT.', 'sk' ),
                            'type'        => 'date-time',
                            'context'     => array( 'view', 'edit' ),
                            'readonly'    => true,
                        ),
                        'date_modified'     => array(
                            'description' => __( "The date the image was last modified, in the site's timezone.", 'sk' ),
                            'type'        => 'date-time',
                            'context'     => array( 'view', 'edit' ),
                            'readonly'    => true,
                        ),
                        'date_modified_gmt' => array(
                            'description' => __( 'The date the image was last modified, as GMT.', 'sk' ),
                            'type'        => 'date-time',
                            'context'     => array( 'view', 'edit' ),
                            'readonly'    => true,
                        ),
                        'src'               => array(
                            'description' => __( 'Image URL.', 'sk' ),
                            'type'        => 'string',
                            'format'      => 'uri',
                            'context'     => array( 'view', 'edit' ),
                        ),
                        'name'              => array(
                            'description' => __( 'Image name.', 'sk' ),
                            'type'        => 'string',
                            'context'     => array( 'view', 'edit' ),
                        ),
                        'alt'               => array(
                            'description' => __( 'Image alternative text.', 'sk' ),
                            'type'        => 'string',
                            'context'     => array( 'view', 'edit' ),
                        ),
                        'position'          => array(
                            'description' => __( 'Image position. 0 means that the image is featured.', 'sk' ),
                            'type'        => 'integer',
                            'context'     => array( 'view', 'edit' ),
                        ),
                    ),
                ),
                'attributes'            => array(
                    'description' => __( 'List of attributes.', 'sk' ),
                    'type'        => 'array',
                    'context'     => array( 'view', 'edit' ),
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'id'     => array(
                                'description' => __( 'Attribute ID.', 'sk' ),
                                'type'        => 'integer',
                                'context'     => array( 'view', 'edit' ),
                            ),
                            'name'   => array(
                                'description' => __( 'Attribute name.', 'sk' ),
                                'type'        => 'string',
                                'context'     => array( 'view', 'edit' ),
                            ),
                            'option' => array(
                                'description' => __( 'Selected attribute term name.', 'sk' ),
                                'type'        => 'string',
                                'context'     => array( 'view', 'edit' ),
                            ),
                        ),
                    ),
                ),
                'menu_order'            => array(
                    'description' => __( 'Menu order, used to custom sort products.', 'sk' ),
                    'type'        => 'integer',
                    'context'     => array( 'view', 'edit' ),
                ),
                'meta_data'             => array(
                    'description' => __( 'Meta data.', 'sk' ),
                    'type'        => 'array',
                    'context'     => array( 'view', 'edit' ),
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'id'    => array(
                                'description' => __( 'Meta ID.', 'sk' ),
                                'type'        => 'integer',
                                'context'     => array( 'view', 'edit' ),
                                'readonly'    => true,
                            ),
                            'key'   => array(
                                'description' => __( 'Meta key.', 'sk' ),
                                'type'        => 'string',
                                'context'     => array( 'view', 'edit' ),
                            ),
                            'value' => array(
                                'description' => __( 'Meta value.', 'sk' ),
                                'type'        => 'mixed',
                                'context'     => array( 'view', 'edit' ),
                            ),
                        ),
                    ),
                ),
            ),
        );

        return $this->add_additional_fields_schema( $schema ); // @phpstan-ignore-line
    }

    /**
    * Bulk update items.
    *
    * For now, we only support bulk update batch-items.
    * @todo: We can add create, delete support later.
    *
    *
    * @param WP_REST_Request $request Full details about the request.
    *
    * @return array|bool|WP_Error Of WP_Error or WP_REST_Response.
    */
    public function batch_items( $request ) {

        /**
        * REST Server
        *
        * @var WP_REST_Server $wp_rest_server
        */
        global $wp_rest_server;

        $items       = $request->get_params();
        $params      = $request->get_url_params();
        $product_id  = absint( $params['product_id'] );
        $response    = [];

        // Check batch limit.
        $limit = $this->check_batch_limit( $items );
        if ( is_wp_error( $limit ) ) {
            return $limit;
        }

        // Modify the request to append parent product id for batch request.
        foreach ( array( 'update' ) as $batch_type ) {
            if ( ! empty( $items[ $batch_type ] ) ) {
                $prepared_items = [];
                foreach ( $items[ $batch_type ] as $item ) {
                    $prepared_items[] = is_array( $item ) ? array_merge(
                        [
                            'product_id' => $product_id,
                        ], $item
                    ) : $item;
                }
                $items[ $batch_type ] = $prepared_items;
            }
        }

        // Process the requests.
        if ( ! empty( $items['update'] ) ) {
            foreach ( $items['update'] as $item ) {

                // We are preparing and passing new rest request server here so that we can re-use the save functionality of extended classes/
                $prepare_request = new WP_REST_Request();
                foreach ( $item as $key => $data ) {
                    $prepare_request->set_param( $key, $data );
                }
                $prepare_request->set_param( 'post_author', sk_get_current_user_id() );

                $_response = $this->update_item( $prepare_request ); // @phpstan-ignore-line

                if ( is_wp_error( $_response ) ) {
                    $response['update'][] = array(
                        'id'    => $item['id'],
                        'error' => array(
                            'code'    => $_response->get_error_code(),
                            'message' => $_response->get_error_message(),
                            'data'    => $_response->get_error_data(),
                        ),
                    );
                } else {
                    $response['update'][] = $wp_rest_server->response_to_data( $_response, '' );
                }
            }
        }

        if ( ! empty( $items['defaultAttributes'] ) ) {
            $default_attributes = wc_clean( $items['defaultAttributes'] );
            update_post_meta( $product_id, '_default_attributes', $default_attributes );
            $response['default_attributes'] = $default_attributes;
        }

        return $response;
    }

    /**
    * Check batch limit.
    *
    *
    * @param array $items Request items.
    *
    * @return bool|WP_Error
    */
    protected function check_batch_limit( $items ) {
        $limit = apply_filters( 'sk_rest_batch_items_limit', 100 );
        $total = 0;

        if ( ! empty( $items['update'] ) ) {
            $total += count( $items['update'] );
        }

        if ( $total > $limit ) {
            /* translators: %s: items limit */
            return new WP_Error( 'sk_rest_request_entity_too_large', sprintf( __( 'Unable to accept more than %s items for this request.', 'sk' ), $limit ), array( 'status' => 413 ) );
        }

        return true;
    }

    /**
    * Get the batch variations schema, conforming to JSON Schema.
    *
    *
    * @return array
    */
    public function get_batch_schema() {
        $schema = array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'batch',
            'type'       => 'object',
            'properties' => array(
                'update' => array(
                    'description' => __( 'List of updated resources.', 'sk' ),
                    'type'        => 'array',
                    'context'     => array( 'edit' ),
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => $this->get_item_schema()['properties'],
                    ),
                ),
            ),
        );

        return $schema;
    }
}
