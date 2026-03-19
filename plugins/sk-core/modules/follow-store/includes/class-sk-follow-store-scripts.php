<?php

class SK_Follow_Store_Scripts {

    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        add_action( 'init', [ $this, 'register_scripts' ] );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Register scripts
     *
     */
    public function register_scripts() {
        list( $suffix, $script_version ) = sk_get_script_suffix_and_version();

        wp_register_style( 'sk-follow-store', SK_FOLLOW_STORE_ASSETS . '/js/follow-store' . $suffix . '.css', array( 'sk-theme' ), SK_FOLLOW_STORE_VERSION );
        wp_register_script( 'sk-follow-store', SK_FOLLOW_STORE_ASSETS . '/js/follow-store' . $suffix . '.js', array( 'jquery' ), SK_FOLLOW_STORE_VERSION, true );

        if ( 'off' === sk_get_option( 'disable_sk_fontawesome', 'sk_appearance', 'off' ) ) {
            wp_enqueue_style( 'sk-fontawesome' );
        }

        $sk_follow_store = array(
            '_nonce'        => wp_create_nonce( 'sk_follow_store' ),
            'button_labels' => sk_follow_store_button_labels(),
        );

        wp_localize_script( 'sk-follow-store', 'skFollowStore', $sk_follow_store );
    }

    /**
     * Enqueue module scripts
     *
     *
     * @return void
     */
    public function enqueue_scripts() {
        if ( sk_is_store_listing() || sk_is_store_page() || ( is_account_page() && false !== get_query_var( 'following', false ) ) ) {
            wp_enqueue_style( 'sk-follow-store' );
            wp_enqueue_script( 'sk-follow-store' );
        }
    }
}
