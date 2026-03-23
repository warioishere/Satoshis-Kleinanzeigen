<?php

namespace SK\Core\Dashboard\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * SMTP Configuration — configures PHPMailer via wp-config.php constants.
 */
class SmtpConfig {

    public function __construct() {
        if ( defined( 'SMTP_server' ) && SMTP_server ) {
            add_action( 'phpmailer_init', [ $this, 'configure' ] );
        }
    }

    public function configure( $phpmailer ) {
        $phpmailer->isSMTP();
        $phpmailer->Host       = SMTP_server;
        $phpmailer->SMTPAuth   = defined( 'SMTP_AUTH' ) ? SMTP_AUTH : true;
        $phpmailer->Port       = defined( 'SMTP_PORT' ) ? SMTP_PORT : 587;
        $phpmailer->Username   = defined( 'SMTP_username' ) ? SMTP_username : '';
        $phpmailer->Password   = defined( 'SMTP_password' ) ? SMTP_password : '';
        $phpmailer->SMTPSecure = defined( 'SMTP_SECURE' ) ? SMTP_SECURE : 'tls';
        $phpmailer->From       = defined( 'SMTP_FROM' ) ? SMTP_FROM : $phpmailer->From;
        $phpmailer->FromName   = defined( 'SMTP_NAME' ) ? SMTP_NAME : $phpmailer->FromName;
    }
}
