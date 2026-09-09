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

        wp_localize_script(
            'sk-follow-store', 'skFollowStore', array(
                '_nonce'        => wp_create_nonce( 'sk_follow_store' ),
                'button_labels' => sk_follow_store_button_labels(),
                'error_text'    => __( 'Das hat nicht geklappt. Bitte versuche es erneut.', 'sk-core' ),
            )
        );
    }

    /**
     * Enqueue module scripts
     *
     *
     * @return void
     */
    public function enqueue_scripts() {
        // The follower dashboard renders store cards, which carry follow buttons.
        global $wp;
        $on_followers_page = isset( $wp->query_vars['followers'] );

        if ( ! sk_is_store_listing() && ! sk_is_store_page() && ! $on_followers_page ) {
            return;
        }

        // Font Awesome was enqueued from init, i.e. on every page of the site,
        // while the module's own assets were carefully restricted to these three.
        if ( 'off' === sk_get_option( 'disable_sk_fontawesome', 'sk_appearance', 'off' ) ) {
            wp_enqueue_style( 'sk-fontawesome' );
        }

        wp_enqueue_style( 'sk-follow-store' );
        wp_enqueue_script( 'sk-follow-store' );
    }
}
