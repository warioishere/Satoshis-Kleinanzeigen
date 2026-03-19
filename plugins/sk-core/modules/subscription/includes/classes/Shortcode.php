<?php

namespace SK\Modules\Subscription;

use SK\Modules\Subscription\SubscriptionPack;
use SK\Modules\Subscription\Helper;
use SK\Core\Traits\Singleton;

defined( 'ABSPATH' ) || exit;

/**
 * DPS Shortcode Class
 */
class Shortcode {

    use Singleton;

    /**
     * Boot method
     */
    public function boot() {
        $this->init_hooks();
    }

    /**
     * Init all hooks
     *
     * @return void
     */
    private function init_hooks() {
        add_shortcode( 'dps_product_pack', [ __CLASS__, 'create_subscription_package_shortcode' ] );
        add_action( 'sk_after_saving_settings', [ __CLASS__, 'insert_shortcode_into_page' ], 10, 2 );

        add_filter( 'sk_button_shortcodes', array( $this, 'add_to_sk_shortcode_menu' ) );
    }

    /**
     * Create subscription package shortcode
     *
     * @return void
     */
    public static function create_subscription_package_shortcode() {
        wp_enqueue_style( 'dps-custom-style' );
        wp_enqueue_script( 'dps-custom-js' );

        $user_id            = sk_get_current_user_id();
        $subscription_packs = sk()->subscription->all();
        $link               = sk_get_navigation_url( 'subscription' );
        $active_tab         = ! empty( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'subscription_packs';

        ob_start();

        if ( function_exists( 'wc_print_notices' ) ) {
            wc_print_notices();
        }

        sk_get_template_part(
            'dashboard/index', '',
            [
                'is_subscription'    => true,
                'link'               => $link,
                'active_tab'         => $active_tab,
                'user_id'            => $user_id,
                'subscription_packs' => $subscription_packs,
            ]
        );

        $contents = ob_get_clean();

        return apply_filters( 'sk_sub_shortcode', $contents, $subscription_packs );
    }

    /**
     * Insert subscription shortcode into specefied page
     *
     * @param  string $option
     * @param  array $value
     *
     * @return void
     */
    public static function insert_shortcode_into_page( $option, $value ) {
        if ( ! $option || 'sk_product_subscription' !== $option ) {
            return;
        }

        $page_id = isset( $value['subscription_pack'] ) ? $value['subscription_pack'] : null;

        if ( ! $page_id ) {
            return;
        }

        $content = [
            'ID'           => $page_id,
            'post_content' => '[dps_product_pack]',
        ];

        $insert = wp_update_post( $content );

        if ( is_wp_error( $insert ) ) {
            return wp_send_json_error( $insert->get_error_message() );
        }
    }

    /**
     * Add product subscription shortcode
     *
     *
     * @param array $shortcodes
     *
     * @return array
     */
    public function add_to_sk_shortcode_menu( $shortcodes ) {
        $shortcodes['dps_product_pack'] = array(
            'title'   => __( 'Create product subscription pack shortcode', 'sk' ),
            'content' => '[dps_product_pack]'
        );

        return $shortcodes;
    }
}

Shortcode::instance();
