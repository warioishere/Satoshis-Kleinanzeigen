<?php

namespace SK\Core\Utilities;

class AdminSettings {

    /**
     * Get new seller selling status setting.
     * We are placing this function here because this function may access from admin and front-end both.
     *
     *
     * @param string $status
     *
     * @return string
     */
    public function get_new_seller_enable_selling_status( $status = '' ) {
        // Before this feature the default was 'on'
        if ( empty( $status ) ) {
            $status = sk_get_option( 'new_seller_enable_selling', 'sk_selling', 'on' );
        }

        if ( $status === 'on' ) {
            $status = 'automatically';
        } elseif ( $status === 'off' ) {
            $status = 'manually';
        }

        return apply_filters( 'sk_new_seller_enable_selling_status', $status );
    }

    /**
     * SK new seller enable selling statuses.
     *
     *
     * @return array
     */
    public function new_seller_enable_selling_statuses() {
        return apply_filters(
            'sk_new_seller_enable_selling_statuses', [
                'automatically' => __( 'Automatically', 'sk-core' ),
                'manually'      => __( 'Manually', 'sk-core' ),
            ]
        );
    }
}
