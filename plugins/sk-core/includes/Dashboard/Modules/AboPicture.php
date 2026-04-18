<?php

namespace SK\Core\Dashboard\Modules;

/**
 * Injects each subscription pack's product thumbnail below its title on the
 * subscription pack listing (dashboard + public subscription page).
 */
class AboPicture {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    public function enqueue(): void {
        $is_dashboard         = function_exists( 'sk_is_seller_dashboard' ) && sk_is_seller_dashboard();
        $is_subscription_page = function_exists( 'is_page' ) && is_page( 'inserate-abos' );

        if ( ! $is_dashboard && ! $is_subscription_page ) {
            return;
        }

        wp_enqueue_style( 'sk-notices' );

        wp_register_script(
            'sk-abo-picture',
            SK_CORE_ASSETS . '/js/notices/abo-picture.js',
            [],
            self::asset_version(),
            [ 'in_footer' => true, 'strategy' => 'defer' ]
        );

        wp_add_inline_script(
            'sk-abo-picture',
            'window.DST_ALL_PACKS = ' . wp_json_encode( $this->get_all_subscription_packs(), JSON_UNESCAPED_UNICODE ) . ';',
            'before'
        );

        wp_enqueue_script( 'sk-abo-picture' );
    }

    private static function asset_version(): string {
        $file = SK_CORE_DIR . '/assets/js/notices/abo-picture.js';
        return file_exists( $file ) ? (string) filemtime( $file ) : SK_CORE_VERSION;
    }

    private function get_all_subscription_packs(): array {
        if ( ! function_exists( 'wc_get_products' ) ) {
            return [];
        }

        $products = wc_get_products( [
            'status'  => 'publish',
            'limit'   => -1,
            'type'    => 'product_pack',
            'orderby' => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
        ] );

        $out = [];
        foreach ( $products as $p ) {
            $pid      = $p->get_id();
            $thumb_id = get_post_thumbnail_id( $pid );
            $alt      = $thumb_id ? (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) : '';
            $out[]    = [
                'id'    => (int) $pid,
                'title' => $p->get_name(),
                'thumb' => (string) ( get_the_post_thumbnail_url( $pid, 'medium' ) ?: '' ),
                'alt'   => $alt ?: $p->get_name(),
            ];
        }

        return $out;
    }
}
