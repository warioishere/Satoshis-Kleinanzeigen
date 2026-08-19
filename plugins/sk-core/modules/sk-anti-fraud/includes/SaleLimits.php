<?php

namespace SK\Modules\AntiFraud;

defined( 'ABSPATH' ) || exit;

class SaleLimits {

    public function __construct() {
        // Block publishing products over the limit.
        add_action( 'transition_post_status', [ $this, 'check_on_publish' ], 10, 3 );
        // Show notice in vendor dashboard.
        add_action( 'sk_dashboard_content_inside_before', [ $this, 'show_limit_notice' ] );
    }

    public function check_on_publish( $new_status, $old_status, $post ) {
        if ( 'publish' !== $new_status || 'product' !== $post->post_type ) {
            return;
        }

        $vendor_id = (int) $post->post_author;
        if ( ! $vendor_id || ! $this->is_limited( $vendor_id ) ) {
            return;
        }

        $product = wc_get_product( $post->ID );
        if ( ! $product ) {
            return;
        }

        $max_sats = (int) sk_get_option( 'sk_antifraud_sale_limit_sats', 'sk_antifraud', '50000' );
        $price    = (float) $product->get_price();

        if ( $price > $max_sats ) {
            // Revert to draft.
            remove_action( 'transition_post_status', [ $this, 'check_on_publish' ], 10 );
            wp_update_post( [ 'ID' => $post->ID, 'post_status' => 'draft' ] );
            add_action( 'transition_post_status', [ $this, 'check_on_publish' ], 10, 3 );

            // Store error message for display.
            set_transient(
                'sk_sale_limit_error_' . $vendor_id,
                sprintf(
                    __( 'Dein Produkt "%s" überschreitet das Verkaufslimit von %s Sats für neue Anbieter. Nach %d bestätigten Lieferungen wird das Limit aufgehoben.', 'sk-core' ),
                    $product->get_name(),
                    number_format( $max_sats ),
                    (int) sk_get_option( 'sk_antifraud_sale_limit_threshold', 'sk_antifraud', '5' )
                ),
                60
            );
        }
    }

    public function show_limit_notice() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $vendor_id = get_current_user_id();

        // Show error from blocked publish.
        $error = get_transient( 'sk_sale_limit_error_' . $vendor_id );
        if ( $error ) {
            delete_transient( 'sk_sale_limit_error_' . $vendor_id );
            echo '<div class="sk-alert sk-alert-warning" style="margin-bottom:16px;">';
            echo '<i class="fas fa-exclamation-triangle"></i> ' . esc_html( $error );
            echo '</div>';
            return;
        }

        // Show general info about limit.
        if ( $this->is_limited( $vendor_id ) ) {
            $max_sats  = (int) sk_get_option( 'sk_antifraud_sale_limit_sats', 'sk_antifraud', '50000' );
            $threshold = (int) sk_get_option( 'sk_antifraud_sale_limit_threshold', 'sk_antifraud', '5' );

            echo '<div class="sk-alert sk-alert-info" style="margin-bottom:16px;font-size:13px;">';
            echo '<i class="fas fa-info-circle"></i> ';
            printf(
                esc_html__( 'Als neuer Anbieter kannst du Produkte bis max. %s Sats listen. Nach %d bestätigten Lieferungen wird das Limit aufgehoben.', 'sk-core' ),
                number_format( $max_sats ),
                $threshold
            );
            echo '</div>';
        }
    }

    private function is_limited( int $vendor_id ): bool {
        $threshold = (int) sk_get_option( 'sk_antifraud_sale_limit_threshold', 'sk_antifraud', '5' );

        global $wpdb;
        $delivered = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT valid_transactions FROM {$wpdb->prefix}sk_reputation_scores WHERE vendor_id = %d",
            $vendor_id
        ) );

        return $delivered < $threshold;
    }
}
