<?php

namespace SK\Core\Abstracts;

abstract class SkShortcode {

    protected $shortcode = '';

    public function __construct() {
        if ( empty( $this->shortcode ) ) {
            _doing_it_wrong( static::class, __( '$shortcode property is empty.', 'sk-core' ), '3.0.0' );
        }

        add_shortcode( $this->shortcode, [ $this, 'render_shortcode' ] );
    }

    public function get_shortcode() {
        return $this->shortcode;
    }

    abstract public function render_shortcode( $atts );
}
