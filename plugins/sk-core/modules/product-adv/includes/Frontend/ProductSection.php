<?php
namespace SK\Modules\ProductAdvertisement\Frontend;

use SK\Core\ProductSections\AbstractProductSection;
use SK\Modules\ProductAdvertisement\Manager;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'AbstractProductSection' ) ) {
    return;
}

/**
 * Top rated products section class.
 *
 * For displaying top rated products section to single store page.
 *
 *
 */
class ProductSection extends AbstractProductSection {

    /**
     * Set unique section id for the this section.
     *
     *
     * @return void
     */
    protected function set_section_id() {
        $this->section_id = 'advertised';
    }

    /**
     * Get single store page section title.
     *
     *
     * @return string
     */
    public function get_section_title() {
        $sections_appearance = sk_get_option( 'product_sections', 'sk_appearance' );
        $section_title       = isset( $sections_appearance[ $this->get_section_id() . '_title' ] ) ? $sections_appearance[ $this->get_section_id() . '_title' ] : __( 'Popular Products', 'sk' );

        return apply_filters( "sk_{$this->get_section_id()}_product_section_title", $section_title );
    }

    /**
     * Get single store page section title.
     *
     *
     * @return string
     */
    public function get_section_label() {
        return __( 'Advertising Products', 'sk' );
    }

    /**
     * Get section products.
     *
     *
     * @param int $vendor_id
     *
     * @return \WP_Query
     */
    public function get_products( $vendor_id ) {
        // get advertisements from database
        $args = [
            'vendor_id' => $vendor_id,
            'count'     => $this->item_count,
        ];

        $manager  = new Manager();
        $products = $manager->get_advertisement_for_display( $args );

        return $products;
    }
}
