<?php

namespace SK\Core\Traits;

trait ChainableContainer {

    /**
     * Contains chainable class instances
     *
     * @var array
     */
    protected $container = [];

    /**
     * Cloning is forbidden.
     *
     */
    public function __clone() {
        $message = ' Backtrace: ' . wp_debug_backtrace_summary();
        _doing_it_wrong( __METHOD__, $message . esc_html__( 'Cloning is forbidden.', 'sk-core' ), SK_CORE_VERSION ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Unserializing instances of this class is forbidden.
     *
     */
    public function __wakeup() {
        $message = ' Backtrace: ' . wp_debug_backtrace_summary();
        _doing_it_wrong( __METHOD__, $message . esc_html__( 'Unserializing instances of this class is forbidden.', 'sk-core' ), SK_CORE_VERSION ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Magic getter to get chainable container instance
     *
     *
     * @param string $prop
     *
     * @return mixed
     */
    public function __get( $prop ) {
        if ( array_key_exists( $prop, $this->container ) ) {
            return $this->container[ $prop ];
        }
    }
}
