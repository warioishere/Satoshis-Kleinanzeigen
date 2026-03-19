<?php

namespace SK\Core\Exceptions;

use Exception;

class SkException extends Exception {

    /**
     * Error code
     *
     *
     * @var string
     */
    protected $error_code = '';

    /**
     * Class constructor
     *
     *                         useful for multiple error codes and messages in
     *                         a single WP_Error instance.
     *
     * @param string|\WP_Error $error_code  Error code string or WP_Error
     * @param string           $message
     * @param int              $status_code
     */
    public function __construct( $error_code, $message = '', $status_code = 422 ) {
        $this->error_code = $error_code;

        parent::__construct( $message, $status_code );
    }

    /**
     * Get error code
     *
     *
     * @return string
     */
    final public function get_error_code() {
        return $this->error_code;
    }

    /**
     * Get error message
     *
     *
     * @return string
     */
    final public function get_message() {
        return $this->getMessage();
    }

    /**
     * Get error status code
     *
     *
     * @return int
     */
    final public function get_status_code() {
        return $this->getCode();
    }
}
