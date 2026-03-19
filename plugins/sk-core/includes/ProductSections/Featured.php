<?php

namespace SK\Core\ProductSections;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Featured products section class.
 *
 * For displaying featured products section to single store page.
 *
 *
 */
class Featured extends AbstractProductSection {
    /**
     * Set unique section id for the this section.
     *
     *
     * @return void
     */
    protected function set_section_id() {
        $this->section_id = 'featured';
    }

    /**
     * Get single store page section title.
     *
     *
     * @return string
     */
    public function get_section_title() {
        $sections_appearance = sk_get_option( 'product_sections', 'sk_appearance' );
        $section_title       = isset( $sections_appearance[ $this->get_section_id() . '_title' ] ) ? $sections_appearance[ $this->get_section_id() . '_title' ] : __( 'Featured Products', 'sk-core' );

        return apply_filters( "sk_{$this->get_section_id()}_product_section_title", $section_title );
    }

    /**
     * Get label for this section.
     *
     *
     * @return string
     */
    public function get_section_label() {
        return __( 'featured products', 'sk-core' );
    }

    /**
     * Get section products.
     *
     *
     * @return \WP_Query
     */
    public function get_products( $vendor_id ) {
        return sk_get_featured_products( $this->item_count, $vendor_id );
    }
}
