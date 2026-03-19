<?php

namespace SK\Core\Exceptions;

use SK\Core\Contracts\Hookable;

/**
 * Handles application-level exceptions and errors in a unified way.
 *
 * This class registers shutdown and error handlers to catch critical or fatal errors.
 * It also displays admin notices in case a module is forcefully deactivated.
 */
class Handler implements Hookable {

    /**
     * Registers hooks for error handling and admin notices.
     *
     * @return void
     */
    public function register_hooks(): void {
        add_action( 'sk_admin_notices', [ $this, 'sk_store_follow_module_deactivation_notice' ] );

        add_action( 'woocommerce_shutdown_error', [ $this, 'on_woocommerce_shutdown' ] );
    }

    /**
     * Conditionally deactivates the Follow Store  module based on error content.
     *
     * @see https://github.com/getsk/sk-pro/issues/4401
     *
     * @param array $error {
     *     Error details.
     *
     *     @type string $message Error message text.
     *     @type int    $type    Error type code.
     *     @type string $file    File where the error occurred.
     *     @type int    $line    Line number of the error.
     * }
     *
     * @return void
     */
    private function maybe_deactivate_store_follow_module( array $error ): void {
        $msg = $error['message'] ?? '';

        if ( strpos( $msg, 'Abstract_SK_Background_Processes' ) !== false ) {
            sk_log( '[SK] Deactivating follow_store module due to error: ' . $msg );

            $active_modules = get_option( 'sk_pro_active_modules', [] );

            if ( in_array( 'follow_store', $active_modules, true ) ) {
                $updated_modules = array_filter(
                    $active_modules,
                    fn( $module ) => $module !== 'follow_store'
                );

                update_option( 'sk_pro_active_modules', $updated_modules );
                set_transient( 'sk_store_follow_deactivated_forcefully', 'yes', WEEK_IN_SECONDS );

                error_log( '[SK] follow_store module deactivated.' );
            }

            $processes = get_option( 'sk_background_processes', [] );

            unset( $processes['SK_Follow_Store_Send_Updates'] );

            update_option( 'sk_background_processes', $processes );
        }
    }

    /**
     * Adds an admin notice when the Follow Store  module is forcefully deactivated.
     *
     * @param array $notices Existing SK admin notices.
     *
     * @return array Updated list of notices including the deactivation message.
     */
    public function sk_store_follow_module_deactivation_notice( array $notices ): array {
        if (
            ! current_user_can( 'manage_options' ) ||
            ! get_transient( 'sk_store_follow_deactivated_forcefully' )
        ) {
            return $notices;
        }

        if ( sk()->is_pro_exists() && sk_ext()->module->is_active( 'follow_store' ) ) {
            delete_transient( 'sk_store_follow_deactivated_forcefully' );

            return $notices;
		}

        $notices[] = [
            'type'        => 'warning',
            'title'       => __( 'Follow Store  Module Deactivated', 'sk-core' ),
            'description' => __( 'The <strong>Follow Store </strong> module has been automatically deactivated due to incompatibility with the current versions of SK Lite and SK Pro. Please update SK Pro to the latest version and then reactivate the Follow Store  module.', 'sk-core' ),
            'priority'    => 1,
            'actions'     => [
                [
                    'type'   => 'primary',
                    'text'   => __( 'Activate Module', 'sk-core' ),
                    'action' => admin_url( 'admin.php?page=sk&tab=modules' ),
                ],
            ],
            'scope' => 'global',
        ];

        return $notices;
    }

    /**
     * Deal with WooCommerce Shutdown.
     *
     * @param array $error
     * @return void
     */
    public function on_woocommerce_shutdown( $error ) {
        $this->maybe_deactivate_store_follow_module( $error );
    }
}
