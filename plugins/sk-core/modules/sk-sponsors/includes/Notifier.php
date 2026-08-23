<?php

namespace SK\Modules\Sponsors;

defined( 'ABSPATH' ) || exit;

/**
 * Erinnerung, wenn das Guthaben zur Neige geht.
 *
 * Hängt an dem Ereignis, das Billing beim Abbuchen auslöst. Verschickt wird
 * höchstens einmal pro Kalendermonat und Sponsor — eine Mahnkaskade wäre bei
 * Werbepartnern der falsche Ton.
 */
final class Notifier {

    const META_LAST_MAIL = '_sk_sponsor_last_reminder_month';

    public function __construct() {
        add_action( 'sk_sponsors_balance_low', [ __CLASS__, 'on_balance_low' ], 10, 3 );
    }

    public static function on_balance_low( int $sponsor_id, int $balance, int $monthly ): void {
        $email = (string) get_post_meta( $sponsor_id, PostType::META_EMAIL, true );
        if ( $email === '' || ! is_email( $email ) ) {
            return;
        }

        $month = current_time( 'Y-m' );
        if ( (string) get_post_meta( $sponsor_id, self::META_LAST_MAIL, true ) === $month ) {
            return;
        }
        update_post_meta( $sponsor_id, self::META_LAST_MAIL, $month );

        $name  = get_the_title( $sponsor_id );
        $link  = Portal::url_for( $sponsor_id );
        $stats = Stats::for_sponsor(
            $sponsor_id,
            gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
            current_time( 'Y-m-d' )
        );

        $subject = $balance <= 0
            ? sprintf( __( '%s: Dein Platz auf Satoshis Kleinanzeigen ist ausgelaufen', 'sk-core' ), $name )
            : sprintf( __( '%s: Dein Guthaben geht zur Neige', 'sk-core' ), $name );

        $lines = [
            sprintf( __( 'Hallo %s,', 'sk-core' ), $name ),
            '',
        ];

        if ( $balance <= 0 ) {
            $lines[] = __( 'dein Guthaben ist aufgebraucht, dein Platz wird derzeit nicht mehr angezeigt.', 'sk-core' );
        } else {
            $lines[] = sprintf(
                /* translators: 1: balance, 2: monthly rate */
                __( 'dein Guthaben beträgt noch %1$s Sats bei einer Monatsrate von %2$s Sats — das reicht nicht mehr für den nächsten Monat.', 'sk-core' ),
                number_format_i18n( $balance ),
                number_format_i18n( $monthly )
            );
        }

        $lines[] = '';
        $lines[] = sprintf(
            /* translators: 1: clicks, 2: unique visitors */
            __( 'Zur Einordnung: In den letzten 30 Tagen wurde dein Platz %1$s mal angeklickt, von %2$s verschiedenen Besuchern.', 'sk-core' ),
            number_format_i18n( $stats['clicks'] ),
            number_format_i18n( $stats['unique'] )
        );
        $lines[] = '';
        $lines[] = __( 'Verlängern kannst du hier — Betrag frei wählbar, Zahlung über Bitcoin/Lightning:', 'sk-core' );
        $lines[] = $link;
        $lines[] = '';
        $lines[] = __( 'Auf der Seite siehst du auch deine Klickzahlen und den aktuellen Stand.', 'sk-core' );
        $lines[] = '';
        $lines[] = __( 'Viele Grüße', 'sk-core' );
        $lines[] = get_bloginfo( 'name' );

        wp_mail(
            $email,
            $subject,
            implode( "\n", $lines ),
            [ 'Content-Type: text/plain; charset=UTF-8' ]
        );

        do_action( 'sk_sponsors_reminder_sent', $sponsor_id, $email, $balance );
    }
}
