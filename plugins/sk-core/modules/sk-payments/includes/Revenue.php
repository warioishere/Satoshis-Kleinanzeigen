<?php

namespace SK\Modules\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Revenue report in fiat.
 *
 * The amount in francs or euros is not derived from today's rate, but from
 * the rate that applied at the time of payment — it is stored with every
 * payment. That's exactly what makes the report usable for a tax return at
 * all: the value at the moment funds arrived counts, and it can no longer
 * be reconstructed later.
 *
 * The date of confirmation is what matters, not the date of the order —
 * before that, no money has moved.
 */
final class Revenue {

    /** Payments where the money has arrived. */
    const SETTLED = [ 'confirmed', 'delivered' ];

    /** Arrived, but disputed — report separately instead of silently counting it in. */
    const DISPUTED = 'disputed';

    private static function table(): string {
        global $wpdb;

        return $wpdb->prefix . 'sk_lightning_payments';
    }

    /**
     * Fiat amount of a payment.
     *
     * @return float|null null = no rate recorded, so no number may be shown
     *                   here either, otherwise the total would be silently wrong.
     */
    public static function fiat( int $sats, $rate ): ?float {
        $rate = (float) $rate;

        return $rate > 0 ? $sats / 100000000 * $rate : null;
    }

    /**
     * Monthly totals.
     *
     * @param string $role 'sales' or 'purchases'
     *
     * @return array<int,array{monat:string,anzahl:int,sats:int,fiat:float,ohne_kurs:int}>
     */
    public static function months( int $user_id, string $role = 'sales', ?int $year = null ): array {
        global $wpdb;

        $rows = self::rows( $user_id, $role, $year );
        $per  = [];

        foreach ( $rows as $row ) {
            $month = substr( $row['datum'], 0, 7 );

            if ( ! isset( $per[ $month ] ) ) {
                $per[ $month ] = [ 'monat' => $month, 'anzahl' => 0, 'sats' => 0, 'fiat' => 0.0, 'ohne_kurs' => 0 ];
            }

            $per[ $month ]['anzahl']++;
            $per[ $month ]['sats'] += $row['sats'];

            if ( $row['fiat'] === null ) {
                $per[ $month ]['ohne_kurs']++;
            } else {
                $per[ $month ]['fiat'] += $row['fiat'];
            }
        }

        krsort( $per );

        return array_values( $per );
    }

    /**
     * Individual payments, formatted the way they belong in the export.
     *
     * @return array<int,array>
     */
    public static function rows( int $user_id, string $role = 'sales', ?int $year = null ): array {
        global $wpdb;

        $table  = self::table();
        $column = $role === 'purchases' ? 'buyer_id' : 'vendor_id';

        $states = array_merge( self::SETTLED, [ self::DISPUTED ] );
        $marks  = implode( ', ', array_fill( 0, count( $states ), '%s' ) );

        $sql  = "SELECT * FROM {$table} WHERE {$column} = %d AND status IN ({$marks})";
        $args = array_merge( [ $user_id ], $states );

        if ( $year ) {
            $sql   .= ' AND YEAR( COALESCE( confirmed_at, created_at ) ) = %d';
            $args[] = $year;
        }

        $sql .= ' ORDER BY COALESCE( confirmed_at, created_at ) DESC';

        $result = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) );
        $rows   = [];

        foreach ( (array) $result as $payment ) {
            $meta    = json_decode( (string) $payment->metadata, true );
            $meta    = is_array( $meta ) ? $meta : [];
            $sats    = (int) $payment->amount_sats;
            $partner = (int) ( $role === 'purchases' ? $payment->vendor_id : $payment->buyer_id );
            $user    = $partner ? get_userdata( $partner ) : null;

            $rows[] = [
                'datum'     => (string) ( $payment->confirmed_at ?: $payment->created_at ),
                'referenz'  => substr( (string) $payment->payment_hash, 0, 16 ),
                'artikel'   => $payment->product_id ? (string) get_the_title( (int) $payment->product_id ) : '',
                'variante'  => (string) ( $meta['variant'] ?? '' ),
                'sats'      => $sats,
                'kurs'      => $payment->exchange_rate !== null ? (float) $payment->exchange_rate : null,
                'fiat'      => self::fiat( $sats, $payment->exchange_rate ),
                'weg'       => $payment->context === 'onchain' ? 'Onchain' : 'Lightning',
                'status'    => (string) $payment->status,
                'gegenüber' => $user ? $user->display_name : '',
            ];
        }

        return $rows;
    }

    /**
     * Years for which payments exist.
     *
     * @return int[]
     */
    public static function years( int $user_id, string $role = 'sales' ): array {
        global $wpdb;

        $table  = self::table();
        $column = $role === 'purchases' ? 'buyer_id' : 'vendor_id';
        $states = array_merge( self::SETTLED, [ self::DISPUTED ] );
        $marks  = implode( ', ', array_fill( 0, count( $states ), '%s' ) );

        $years = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT YEAR( COALESCE( confirmed_at, created_at ) ) FROM {$table}
                 WHERE {$column} = %d AND status IN ({$marks})
                 ORDER BY 1 DESC",
                ...array_merge( [ $user_id ], $states )
            )
        );

        return array_map( 'intval', array_filter( $years ) );
    }

    /**
     * Rows as CSV, with semicolons — that's how Excel here opens the file
     * into columns without any extra steps.
     */
    public static function csv( array $rows ): string {
        $out = fopen( 'php://temp', 'r+' );

        fwrite( $out, "\xEF\xBB\xBF" ); // BOM, otherwise Excel mangles the umlauts

        fputcsv(
            $out,
            [ 'Datum', 'Referenz', 'Artikel', 'Ausführung', 'Betrag Sats', 'Kurs EUR/BTC', 'Betrag EUR', 'Zahlweg', 'Status', 'Gegenüber' ],
            ';'
        );

        foreach ( $rows as $row ) {
            fputcsv(
                $out,
                [
                    $row['datum'],
                    $row['referenz'],
                    $row['artikel'],
                    $row['variante'],
                    $row['sats'],
                    $row['kurs'] !== null ? number_format( $row['kurs'], 2, ',', '' ) : '',
                    $row['fiat'] !== null ? number_format( $row['fiat'], 2, ',', '' ) : '',
                    $row['weg'],
                    $row['status'],
                    $row['gegenüber'],
                ],
                ';'
            );
        }

        rewind( $out );
        $csv = (string) stream_get_contents( $out );
        fclose( $out );

        return $csv;
    }
}
