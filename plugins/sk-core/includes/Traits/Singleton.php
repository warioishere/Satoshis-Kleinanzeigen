<?php

namespace SK\Core\Traits;

/**
 * Singleton Trait
 *
 */
trait Singleton {

    /**
     * Singleton class instance holder
     *
     *
     * @var static
     */
    protected static $instance;

    /**
     * Make a class instance
     *
     *
     * @return static
     */
    public static function instance() {
        if ( ! isset( static::$instance ) && ! ( static::$instance instanceof static ) ) {
            static::$instance = new static();

            if ( method_exists( static::$instance, 'boot' ) ) {
                static::$instance->boot();
            }
        }

        return static::$instance;
    }
}
