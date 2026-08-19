<?php
namespace SK\Modules\ProductAdvertisement\Frontend;

use SK\Modules\ProductAdvertisement\Manager;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class ProductWidget
 *
 *
 */
class ProductWidget extends \WC_Widget {

    /**
     * Instance key to keep track of the widget inside widget container in sk-lite
     *
     *
     * @var string
     */
    const INSTANCE_KEY = 'product_adv__ProductWidget'; // Naming Structure: {module_slug}__{ClassName}

    /**
     * ProductWidget constructor.
     *
     */
    public function __construct() {
        $this->widget_cssclass    = 'woocommerce widget_products sk_product_advertisement_widget';
        $this->widget_description = __( 'A list of advertised products.', 'sk' );
        $this->widget_id          = 'sk_product_advertisement_widget';
        $this->widget_name        = __( 'SK: Advertised Products', 'sk' );
        $this->settings           = [
            'title'       => [
                'type'  => 'text',
                'std'   => __( 'Products', 'sk' ),
                'label' => __( 'Title', 'sk' ),
            ],
            'count'      => [
                'type'  => 'number',
                'step'  => 1,
                'min'   => 1,
                'max'   => '',
                'std'   => get_option( 'woocommerce_catalog_columns', 3 ),
                'label' => __( 'Number of products to show', 'sk' ),
            ],
            'orderby'     => [
                'type'    => 'select',
                'std'     => 'added',
                'label'   => __( 'Order by', 'sk' ),
                'options' => [
                    'product_title' => __( 'Title', 'sk' ),
                    'added'         => __( 'Date', 'sk' ),
                    'expires_at'    => __( 'Expire Date', 'sk' ),
                    'price'         => __( 'Price', 'sk' ),
                ],
            ],
            'order'       => [
                'type'    => 'select',
                'std'     => 'desc',
                'label'   => _x( 'Order', 'Sorting order', 'sk' ),
                'options' => [
                    'asc'  => __( 'ASC', 'sk' ),
                    'desc' => __( 'DESC', 'sk' ),
                ],
            ],
            'vendor_id'       => [
                'type'  => 'text',
                'std'   => '',
                'label' => __( 'Vendor(s)', 'sk' ),
            ],
            'vendor_only_advertisement' => [
                'type'  => 'checkbox',
                'std'   => 0,
                'label' => __( 'Display vendor only advertisements on single store page.', 'sk' ),
            ],
        ];

        parent::__construct();
    }

    /**
     * Query the products and return them.
     *
     *
     * @param array $args     Arguments.
     * @param array $instance Widget instance.
     *
     * @return bool|\WP_Query false if no advertisement exists
     */
    public function get_products( $args, $instance ) {
        $count       = ! empty( $instance['count'] ) ? absint( $instance['count'] ) : $this->settings['count']['std'];
        $orderby     = ! empty( $instance['orderby'] ) ? sanitize_title( $instance['orderby'] ) : $this->settings['orderby']['std'];
        $order       = ! empty( $instance['order'] ) ? sanitize_title( $instance['order'] ) : $this->settings['order']['std'];
        $vendor_ids  = ! empty( $instance['vendor_id'] ) ? sanitize_text_field( $instance['vendor_id'] ) : '';
        $vendor_only = ! empty( $instance['vendor_only_advertisement'] ) ? true : false;

        $atts = [
            'count'     => $count, //,
            'vendor_id' => $vendor_ids, // comma separated values
            'order'     => $order,
            'orderby'   => $orderby,
            'vendor_only_advertisement' => $vendor_only,
        ];

        $manager = new Manager();
        return $manager->get_advertisement_for_display( apply_filters( 'sk_product_advertisement_widget_args', $atts ) );
    }

    /**
     * Output widget.
     *
     *
     * @param array $args     Arguments.
     * @param array $instance Widget instance.
     *
     * @see WP_Widget
     */
    public function widget( $args, $instance ) {
        ob_start();

        $products = $this->get_products( $args, $instance );
        if ( $products && $products->have_posts() ) {
            $this->widget_start( $args, $instance );

            echo wp_kses_post( apply_filters( 'sk_before_widget_product_list', '<ul class="product_list_widget">' ) );

            $template_args = array(
                'widget_id'   => isset( $args['widget_id'] ) ? $args['widget_id'] : $this->widget_id,
                'show_rating' => true,
            );

            while ( $products->have_posts() ) {
                $products->the_post();
                wc_get_template( 'content-widget-product.php', $template_args );
            }

            echo wp_kses_post( apply_filters( 'sk_after_widget_product_list', '</ul>' ) );

            $this->widget_end( $args );
        }

        wp_reset_postdata();

        echo ob_get_clean();
    }
}
