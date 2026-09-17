<?php

namespace SK\Modules\Escrow;

use SK\Modules\Payments\Chat\ChatIntegration;

defined( 'ABSPATH' ) || exit;

/**
 * Every step is written into the buyer/seller chat of the order, and the
 * party that has to act next gets an email. The chat is the contact route
 * of the marketplace; the mail exists because the acceptance and signing
 * steps are time-critical and a silent counterparty stalls the trade.
 */
final class Notify {

    /** Post a message into the order's chat as the given user. */
    public static function chat( object $row, int $from_user, string $text ): void {
        if ( ! $row->chat_id || ! class_exists( ChatIntegration::class ) ) {
            return;
        }

        ChatIntegration::add_chat_message_static( (int) $row->chat_id, $from_user, $text );
    }

    public static function mail( int $user_id, string $subject, string $body ): void {
        $user = get_userdata( $user_id );
        if ( ! $user || ! is_email( $user->user_email ) ) {
            return;
        }

        wp_mail( $user->user_email, $subject, $body . "\n\n" . sk_get_navigation_url( 'lightning-transactions' ) );
    }

    private static function title( object $row ): string {
        $title = $row->product_id ? (string) get_the_title( (int) $row->product_id ) : '';

        return trim( $title ) !== '' ? $title : __( 'Inserat', 'sk-core' );
    }

    private static function sats( int $sats ): string {
        return number_format_i18n( $sats ) . ' sats';
    }

    public static function requested( object $row ): void {
        $text = sprintf(
            /* translators: 1: product, 2: amount */
            __( "Treuhand-Anfrage: %1\$s (%2\$s).\nDer Verkäufer nimmt die Anfrage unter „Verkäufe“ an, danach erscheint hier die Einzahlungsadresse.", 'sk-core' ),
            self::title( $row ),
            self::sats( (int) $row->amount_sats )
        );
        self::chat( $row, (int) $row->buyer_id, $text );
        self::mail(
            (int) $row->vendor_id,
            sprintf( __( 'Treuhand-Anfrage: %s', 'sk-core' ), self::title( $row ) ),
            sprintf( __( 'Ein Käufer möchte %1$s über die Treuhand kaufen (%2$s). Bitte nimm die Anfrage unter „Verkäufe“ an oder lehne sie ab. Unbeantwortete Anfragen laufen nach %3$d Tagen ab.', 'sk-core' ), self::title( $row ), self::sats( (int) $row->amount_sats ), Cron::REQUEST_DAYS )
        );
    }

    public static function accepted( object $row, array $meta ): void {
        $text = sprintf(
            /* translators: 1: amount to deposit, 2: address */
            __( "Treuhand angenommen. Bitte %1\$s an die Treuhand-Adresse einzahlen:\n%2\$s\nDie Adresse wird in deinem Browser gegen den Descriptor geprüft, bevor du einzahlst.", 'sk-core' ),
            self::sats( (int) $meta['deposit_sat'] ),
            (string) $meta['address']
        );
        self::chat( $row, (int) $row->vendor_id, $text );
        self::mail(
            (int) $row->buyer_id,
            sprintf( __( 'Treuhand angenommen: %s', 'sk-core' ), self::title( $row ) ),
            sprintf( __( 'Der Verkäufer hat deine Treuhand-Anfrage für %1$s angenommen. Bitte zahle %2$s unter „Käufe“ ein.', 'sk-core' ), self::title( $row ), self::sats( (int) $meta['deposit_sat'] ) )
        );
    }

    public static function declined( object $row, string $note ): void {
        $text = __( 'Treuhand-Anfrage abgelehnt.', 'sk-core' ) . ( $note !== '' ? ' ' . $note : '' );
        self::chat( $row, (int) $row->vendor_id, $text );
        self::mail( (int) $row->buyer_id, sprintf( __( 'Treuhand-Anfrage abgelehnt: %s', 'sk-core' ), self::title( $row ) ), $text );
    }

    public static function cancelled( object $row ): void {
        self::chat( $row, (int) $row->buyer_id, __( 'Treuhand-Anfrage zurückgezogen.', 'sk-core' ) );
    }

    public static function expired( object $row ): void {
        self::chat( $row, (int) $row->buyer_id, __( 'Treuhand-Anfrage abgelaufen: Der Verkäufer hat nicht rechtzeitig angenommen.', 'sk-core' ) );
    }

    public static function funded( object $row, array $meta ): void {
        $text = sprintf( __( 'Einzahlung in die Treuhand bestätigt (%s). Der Verkäufer kann jetzt liefern.', 'sk-core' ), self::sats( (int) ( $meta['funded_sat'] ?? $row->amount_sats ) ) );
        self::chat( $row, (int) $row->buyer_id, $text );
        self::mail( (int) $row->vendor_id, sprintf( __( 'Treuhand eingezahlt: %s', 'sk-core' ), self::title( $row ) ), $text );
    }

    public static function released( object $row ): void {
        $text = __( 'Der Käufer hat den Erhalt bestätigt und die Auszahlung signiert. Bitte unter „Verkäufe“ gegenzeichnen, dann wird ausgezahlt.', 'sk-core' );
        self::chat( $row, (int) $row->buyer_id, $text );
        self::mail( (int) $row->vendor_id, sprintf( __( 'Auszahlung freigegeben: %s', 'sk-core' ), self::title( $row ) ), $text );
    }

    public static function refund_started( object $row ): void {
        $text = __( 'Der Verkäufer erstattet den Betrag. Bitte unter „Käufe“ gegenzeichnen, dann geht die Erstattung an deine Adresse.', 'sk-core' );
        self::chat( $row, (int) $row->vendor_id, $text );
        self::mail( (int) $row->buyer_id, sprintf( __( 'Erstattung angeboten: %s', 'sk-core' ), self::title( $row ) ), $text );
    }

    public static function settled( object $row, string $type, string $txid ): void {
        $text = $type === 'refund'
            ? sprintf( __( 'Erstattung gesendet. Transaktion: %s', 'sk-core' ), $txid )
            : sprintf( __( 'Auszahlung gesendet. Transaktion: %s', 'sk-core' ), $txid );
        self::chat( $row, (int) $row->vendor_id, $text );
        self::mail( (int) $row->buyer_id, sprintf( __( 'Treuhand abgeschlossen: %s', 'sk-core' ), self::title( $row ) ), $text );
        self::mail( (int) $row->vendor_id, sprintf( __( 'Treuhand abgeschlossen: %s', 'sk-core' ), self::title( $row ) ), $text );
    }

    /** A goodwill claim (§7) was filed: the admin has to pay or decline it. */
    public static function claim_filed( object $row, string $role, int $amount ): void {
        $admin = get_option( 'admin_email' );
        if ( $admin ) {
            wp_mail(
                $admin,
                sprintf( 'Kulanzantrag: %s', self::title( $row ) ),
                sprintf( "%s beantragt %s aus dem Kulanzfonds.\n%s", $role === 'buyer' ? 'Der Käufer' : 'Der Verkäufer', self::sats( $amount ), admin_url( 'admin.php?page=weo-disputes' ) )
            );
        }
    }

    public static function claim_settled( object $row, array $claim ): void {
        $text = 'paid' === ( $claim['status'] ?? '' )
            ? sprintf( __( 'Dein Kulanzantrag wurde ausgezahlt: %s.', 'sk-core' ), self::sats( (int) $claim['amount'] ) )
            : __( 'Dein Kulanzantrag wurde abgelehnt.', 'sk-core' );
        if ( ! empty( $claim['note'] ) ) {
            $text .= ' ' . $claim['note'];
        }

        self::mail( (int) $claim['user_id'], sprintf( __( 'Treuhand: Kulanzantrag zu %s', 'sk-core' ), self::title( $row ) ), $text );
    }

    /** The rulebook was applied; the marketplace still has to confirm. */
    public static function decided( object $row, array $decision ): void {
        $text = sprintf(
            /* translators: 1: outcome, 2: reasoning, 3: rules */
            __( "Entscheidung nach dem Treuhand-Regelwerk: %1\$s\n%2\$s (%3\$s)\nDer Marktplatz prüft, dass die Entscheidung dem Regelwerk folgt, und bestätigt sie.", 'sk-core' ),
            Dispute::outcome_text( $decision ),
            (string) ( $decision['reasoning'] ?? '' ),
            implode( ', ', (array) ( $decision['rules_applied'] ?? [] ) )
        );

        self::chat( $row, 0, $text );
        foreach ( [ (int) $row->buyer_id, (int) $row->vendor_id ] as $user_id ) {
            self::mail( $user_id, sprintf( __( 'Treuhand: Entscheidung zu %s', 'sk-core' ), self::title( $row ) ), $text );
        }

        $admin = get_option( 'admin_email' );
        if ( $admin ) {
            wp_mail( $admin, sprintf( 'Escrow-Entscheidung: %s', self::title( $row ) ), $text . "\n" . admin_url( 'admin.php?page=weo-disputes' ) );
        }
    }

    /** A deadline of the rulebook passed; the prescribed transaction awaits the signatures. */
    public static function escalated( object $row, string $type, string $reason ): void {
        $who = $type === 'payout'
            ? __( 'Der Verkäufer signiert die Auszahlung unter „Verkäufe“, der Marktplatz zeichnet gegen.', 'sk-core' )
            : __( 'Der Käufer signiert die Erstattung unter „Käufe“, der Marktplatz zeichnet gegen.', 'sk-core' );
        $text = sprintf( __( "Frist nach dem Treuhand-Regelwerk abgelaufen. %1\$s\n%2\$s", 'sk-core' ), $reason, $who );

        self::chat( $row, (int) $row->buyer_id, $text );
        foreach ( [ (int) $row->buyer_id, (int) $row->vendor_id ] as $user_id ) {
            self::mail( $user_id, sprintf( __( 'Treuhand: Frist abgelaufen bei %s', 'sk-core' ), self::title( $row ) ), $text );
        }

        $admin = get_option( 'admin_email' );
        if ( $admin ) {
            wp_mail( $admin, sprintf( 'Escrow-Frist: %s', self::title( $row ) ), $reason . "\n" . admin_url( 'admin.php?page=weo-disputes' ) );
        }
    }

    public static function disputed( object $row ): void {
        $text = __( 'Problem gemeldet. Die Treuhand ist eingefroren, der Marktplatz prüft und entscheidet über Auszahlung oder Erstattung.', 'sk-core' );
        self::chat( $row, (int) $row->buyer_id, $text );
        self::mail( (int) $row->vendor_id, sprintf( __( 'Problem gemeldet: %s', 'sk-core' ), self::title( $row ) ), $text );

        $admin = get_option( 'admin_email' );
        if ( $admin ) {
            wp_mail( $admin, sprintf( 'Escrow-Dispute: %s', self::title( $row ) ), admin_url( 'admin.php?page=weo-disputes' ) );
        }
    }
}
