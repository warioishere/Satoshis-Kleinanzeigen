<?php

namespace SK\Core\ProductSections;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Single store products class.
 *
 * For displaying additional products sections to single store page.
 *
 *
 */
abstract class AbstractProductSection {
    /**
     * Unique section id.
     *
     *
     * @var string
     */
    protected $section_id;

    /**
     * Show this section under customizer.
     *
     *
     * @var bool
     */
    protected $show_in_customizer = true;

    /**
     * Products to display in this sections.
     *
     *
     * @var int
     */
    protected $item_count = 3;

    /**
     * AbstractProductSection constructor.
     *
     *
     * @return void
     */
    public function __construct() {
        $this->set_section_id();
        $this->item_count = apply_filters( "sk_{$this->get_section_id()}_product_section_item_count", get_option( 'woocommerce_catalog_columns', 3 ) );
    }

    /**
     * Set unique section id for the this section.
     *
     *
     * @return void
     */
    abstract protected function set_section_id();

    /**
     * Get single store page section title.
     *
     *
     * @return string
     */
    abstract public function get_section_title();

    /**
     * Get label for this section.
     *
     *
     * @return string
     */
    abstract public function get_section_label();

    /**
     * Get products for this section
     *
     *
     * @return \WP_Query
     */
    abstract public function get_products( $vendor_id );

    /**
     * Get unique section id for this section.
     *
     *
     * @return string
     */
    public function get_section_id() {
        return $this->section_id;
    }

    /**
     * Set if need admin customizer settings for this section or not.
     *
     *
     * @return void
     */
    public function set_show_in_customizer( $value ) {
        $this->show_in_customizer = wc_string_to_bool( $value );
    }

    /**
     * Check if admin customizer settings is enabled for this section or not.
     *
     *
     * @return bool
     */
    public function get_show_in_customizer() {
        return $this->show_in_customizer;
    }

    /**
     * Check products block visibility settings by admin and vendor.
     *
     *
     * @return bool
     */
    public function is_enabled() {
        $customizer_settings = sk_get_option( 'product_sections', 'sk_appearance' );
        // Check if current products section enabled by admin.
        // for customizer settings default value is hide sections
        if ( $this->get_show_in_customizer() &&
            (
                ! isset( $customizer_settings[ $this->get_section_id() ] ) ||
                'on' === $customizer_settings[ $this->get_section_id() ]
            )
        ) {
            return false;
        }

        return true;
    }
}
