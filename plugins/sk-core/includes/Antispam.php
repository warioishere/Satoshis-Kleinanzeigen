<?php

namespace SK\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Honeypot + timing defense on WP comment forms.
 *
 * Logged-in-only commenting is enforced via the WP option comment_registration=1
 * (set in DB). Honeypot catches the few authenticated-but-fake submissions and
 * scripted bots that hit wp-comments-post.php directly.
 */
final class Antispam {

    private const HONEYPOT_FIELD = 'sk_url_check';
    private const TIMESTAMP_FIELD = 'sk_ts';
    private const MIN_SECONDS    = 3;

    public static function init(): void {
        add_action( 'comment_form_after_fields',      [ __CLASS__, 'render_honeypot' ] );
        add_action( 'comment_form_logged_in_after',   [ __CLASS__, 'render_honeypot' ] );
        add_filter( 'preprocess_comment',             [ __CLASS__, 'validate' ], 5 );
    }

    public static function render_honeypot(): void {
        echo '<p style="position:absolute;left:-9999px;height:0;overflow:hidden" aria-hidden="true">'
            . '<label>Website (leer lassen)</label>'
            . '<input type="text" name="' . esc_attr( self::HONEYPOT_FIELD ) . '" value="" tabindex="-1" autocomplete="off">'
            . '<input type="hidden" name="' . esc_attr( self::TIMESTAMP_FIELD ) . '" value="' . esc_attr( time() ) . '">'
            . '</p>';
    }

    public static function validate( $commentdata ) {
        if ( ! empty( $_POST[ self::HONEYPOT_FIELD ] ) ) {
            wp_die( __( 'Spam-Schutz: Honeypot ausgefüllt.', 'sk-core' ), '', [ 'response' => 403 ] );
        }

        $ts = isset( $_POST[ self::TIMESTAMP_FIELD ] ) ? (int) $_POST[ self::TIMESTAMP_FIELD ] : 0;
        if ( $ts && ( time() - $ts ) < self::MIN_SECONDS ) {
            wp_die( __( 'Spam-Schutz: zu schnell abgesendet.', 'sk-core' ), '', [ 'response' => 403 ] );
        }

        return $commentdata;
    }
}
