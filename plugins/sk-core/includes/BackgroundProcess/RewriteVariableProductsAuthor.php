<?php

namespace SK\Core\BackgroundProcess;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Background_Process', false ) ) {
    include_once dirname( WC_PLUGIN_FILE ) . 'includes/abstracts/class-wc-background-process.php';
}

use WC_Background_Process;

/**
 * RewriteVariableProductsAuthor Class.
 *
 */
class RewriteVariableProductsAuthor extends WC_Background_Process {

    /**
     * Initiate new background process.
     */
    public function __construct() {
        $this->action = 'sk_update_variable_product_variations_author_ids';

        parent::__construct();
    }

    /**
     * Dispatch updater.
     *
     * Updater will still run via cron job if this fails for any reason.
     *
     *
     * @return void
     */
    public function dispatch() {
        $dispatched = parent::dispatch();

        if ( is_wp_error( $dispatched ) ) {
            sk_log(
                sprintf( 'Unable to dispatch SK variable product variations author update: %s', $dispatched->get_error_message() ),
                'error'
            );
        }
    }

    /**
     * Perform updates.
     *
     *
     * @param array $args
     *
     * @return bool|array
     */
    public function task( $args ) {
        if ( empty( $args['updating'] ) || empty( $args['page'] ) ) {
            return false;
        }

        if ( 'sk_update_variable_product_variations_author_ids' === $args['updating'] ) {
            return $this->rewrite_variable_product_variations_author_ids( $args['page'] );
        }

        return false;
    }

    /**
     * Rewrite variable product variations author IDs.
     *
     *
     * @param int $page
     *
     * @return bool|array
     */
    protected function rewrite_variable_product_variations_author_ids( $page = 1 ) {
        $args = [
            'type'  => 'variable',
            'limit' => 50,
            'page'  => $page,
        ];

        $variable_products = wc_get_products( $args );

        if ( empty( $variable_products ) ) {
            return false;
        }

        foreach ( $variable_products as $variable_product ) {
            $product_author = get_post_field( 'post_author', $variable_product->get_id() );

            // Rewrite authors of the variations.
            sk_override_author_for_product_variations( $variable_product, $product_author );
        }

        return [
            'updating' => 'sk_update_variable_product_variations_author_ids',
            'page'     => ++$page,
        ];
    }

    /**
     * Complete the process.
     *
     *
     * @return void
     */
    protected function complete() {
        set_transient( 'sk_variable_products_author_updated', true, HOUR_IN_SECONDS );
        sk_log( 'Variable product variations author updated successfully', 'info' );
        parent::complete();
    }
}
