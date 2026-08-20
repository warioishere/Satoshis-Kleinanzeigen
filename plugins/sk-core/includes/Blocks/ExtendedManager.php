<?php

namespace SK\Core\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * SK Block manager class for PRO.
 *
 */
class ExtendedManager {

    /**
     * Block class mapping.
     *
     *
     * @var array
     */
    protected $block_classes;

    /**
     * Constructor class.
     *
     */
    public function __construct() {
        /**
         * Include classes for blocks or sections.
         */
        $this->init_block_classes();
        $this->include_block_classes();
    }

    /**
     * Init block classes.
     *
     *
     * @return void
     */
    public function init_block_classes() {
        if ( ! empty( $this->block_classes ) ) {
            return;
        }

        $this->block_classes = apply_filters(
            'sk_block_classes', [
                Product::class,
            ]
        );
    }

    /**
     * Include block classes.
     *
     *
     * @return void
     */
    public function include_block_classes() {
        foreach ( $this->block_classes as $block ) {
            new $block();
        }
    }
}
