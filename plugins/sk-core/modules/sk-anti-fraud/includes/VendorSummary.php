<?php

namespace SK\Modules\AntiFraud;

defined( 'ABSPATH' ) || exit;

/**
 * Short vendor profile for admin notifications.
 *
 * A report or a flagged listing means little on its own — whether the account
 * is two days or two years old, has one listing or eighty, decides how urgent
 * it is. These lines go into every anti-fraud mail.
 */
final class VendorSummary {

    /**
     * Plain text lines describing the vendor.
     *
     * @return string[]
     */
    public static function lines( int $user_id ): array {
        $user = get_userdata( $user_id );

        if ( ! $user ) {
            return [ sprintf( __( 'Anbieter: unbekannt (#%d)', 'sk-core' ), $user_id ) ];
        }

        $lines = [];

        $store = get_user_meta( $user_id, 'sk_store_name', true );
        $lines[] = sprintf(
            __( 'Anbieter: %s', 'sk-core' ),
            ( $store ? $store . ' / ' : '' ) . $user->user_login . ' (#' . $user_id . ')'
        );

        if ( $user->user_email ) {
            $lines[] = sprintf( __( 'E-Mail: %s', 'sk-core' ), $user->user_email );
        }

        $registered = strtotime( $user->user_registered );

        if ( $registered ) {
            $days    = max( 0, (int) floor( ( time() - $registered ) / DAY_IN_SECONDS ) );
            $lines[] = sprintf(
                /* translators: 1: date, 2: number of days */
                __( 'Registriert: %1$s (vor %2$d Tagen)', 'sk-core' ),
                date_i18n( 'd.m.Y', $registered ),
                $days
            );
        }

        $counts   = self::count_listings( $user_id );
        $lines[]  = sprintf(
            /* translators: 1: published, 2: draft, 3: total */
            __( 'Inserate: %1$d online, %2$d Entwurf, %3$d gesamt', 'sk-core' ),
            $counts['publish'],
            $counts['draft'],
            $counts['total']
        );

        $reports = self::count_reports( $user_id );

        if ( $reports['total'] > 0 ) {
            $lines[] = sprintf(
                /* translators: 1: total reports, 2: distinct reporters */
                __( 'Meldungen gesamt: %1$d von %2$d verschiedenen Nutzern', 'sk-core' ),
                $reports['total'],
                $reports['reporters']
            );
        }

        $pack_id = (int) get_user_meta( $user_id, 'product_package_id', true );

        if ( $pack_id ) {
            $pack    = get_post( $pack_id );
            $end     = get_user_meta( $user_id, 'product_pack_enddate', true );
            $lines[] = sprintf(
                __( 'Paket: %1$s%2$s', 'sk-core' ),
                $pack ? $pack->post_title : '#' . $pack_id,
                $end ? sprintf( __( ' (bis %s)', 'sk-core' ), $end ) : ''
            );
        }

        return $lines;
    }

    /**
     * Same lines as one text block.
     */
    public static function text( int $user_id ): string {
        return implode( "\n", self::lines( $user_id ) );
    }

    /**
     * @return array{publish:int,draft:int,total:int}
     */
    public static function count_listings( int $user_id ): array {
        global $wpdb;

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT post_status, COUNT(*) AS n
               FROM {$wpdb->posts}
              WHERE post_author = %d AND post_type = 'product'
              GROUP BY post_status",
            $user_id
        ) );

        $counts = [ 'publish' => 0, 'draft' => 0, 'total' => 0 ];

        foreach ( (array) $rows as $row ) {
            $n = (int) $row->n;

            // Revisions and auto-drafts aren't listings.
            if ( in_array( $row->post_status, [ 'auto-draft', 'inherit', 'trash' ], true ) ) {
                continue;
            }

            if ( isset( $counts[ $row->post_status ] ) ) {
                $counts[ $row->post_status ] += $n;
            }

            $counts['total'] += $n;
        }

        return $counts;
    }

    /**
     * @return array{total:int,reporters:int}
     */
    public static function count_reports( int $user_id ): array {
        global $wpdb;

        $table = $wpdb->prefix . 'sk_report_abuse_reports';

        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            return [ 'total' => 0, 'reporters' => 0 ];
        }

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) AS total, COUNT(DISTINCT customer_id) AS reporters
               FROM {$table} WHERE vendor_id = %d",
            $user_id
        ) );

        return [
            'total'     => $row ? (int) $row->total : 0,
            'reporters' => $row ? (int) $row->reporters : 0,
        ];
    }
}
