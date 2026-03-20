<?php

namespace SK\Modules\Auth\Lnurl;

/**
 * LNURL Auth Plugin Bootstrap — simplified for sk-core integration.
 *
 * Original author: Joel Stuedle <joel.stuedle@gmail.com>
 * Original repo: https://github.com/joel-st/lnurl-auth-for-wordpress
 *
 * Removed the singleton/dynamic-property pattern. Classes are instantiated directly.
 */
class Plugin {

    private static $instance;

    public $Helpers;
    public $Assets;
    public $Transients;
    public $Login;
    public $Settings;

    public static function get_instance( $file = '' ) {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function run() {
        $this->Helpers    = new Helpers();
        $this->Transients = new Transients();
        $this->Assets     = new Assets();
        $this->Login      = new Login();
        $this->Settings   = new Settings();

        // Run methods that register hooks.
        if ( method_exists( $this->Login, 'run' ) ) {
            $this->Login->run();
        }
        if ( method_exists( $this->Settings, 'run' ) ) {
            $this->Settings->run();
        }

        // Shortcode.
        add_shortcode( 'lnurl_auth', [ $this, 'lnurl_auth_shortcode' ] );
    }

    public function lnurl_auth_shortcode( $atts ) {
        ob_start();
        $this->Login->lnurl_auth_markup( $atts );
        $output = ob_get_clean();

        $this->Assets->lnurl_auth_enqueue_scripts_styles();

        return $output;
    }
}
