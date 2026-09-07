<?php

namespace SK\Modules\Sponsors;

defined( 'ABSPATH' ) || exit;

/**
 * Evaluation of the click table.
 *
 * "clicks" is the sum of all clicks, "unique" is the number of visitor-days.
 * For a pitch to a sponsor, the second number is the more honest one, since
 * repeated clicks by the same person only count once there.
 */
final class Stats {

    private static function table(): string {
        global $wpdb;

        return $wpdb->prefix . 'sk_sponsor_clicks';
    }

    /**
     * @return array{clicks:int,unique:int}
     */
    public static function for_sponsor( int $sponsor_id, string $from, string $to ): array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT COALESCE(SUM(clicks),0) AS clicks, COUNT(*) AS uniques
                 FROM ' . self::table() . '
                 WHERE sponsor_id = %d AND click_day BETWEEN %s AND %s',
                $sponsor_id,
                $from,
                $to
            ),
            ARRAY_A
        );

        return [
            'clicks' => (int) ( $row['clicks'] ?? 0 ),
            'unique' => (int) ( $row['uniques'] ?? 0 ),
        ];
    }

    /**
     * Clicks for all sponsors in the period, indexed by sponsor_id.
     *
     * @return array<int,array{clicks:int,unique:int}>
     */
    public static function by_sponsor( string $from, string $to ): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT sponsor_id, COALESCE(SUM(clicks),0) AS clicks, COUNT(*) AS uniques
                 FROM ' . self::table() . '
                 WHERE click_day BETWEEN %s AND %s
                 GROUP BY sponsor_id',
                $from,
                $to
            ),
            ARRAY_A
        );

        $out = [];
        foreach ( (array) $rows as $row ) {
            $out[ (int) $row['sponsor_id'] ] = [
                'clicks' => (int) $row['clicks'],
                'unique' => (int) $row['uniques'],
            ];
        }

        return $out;
    }

    /**
     * Clicks per month for a sponsor, oldest month first.
     *
     * @return array<string,int> [ 'YYYY-MM' => clicks ]
     */
    public static function monthly( int $sponsor_id, int $months = 6 ): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE_FORMAT(click_day,'%%Y-%%m') AS ym, COALESCE(SUM(clicks),0) AS clicks
                 FROM " . self::table() . '
                 WHERE sponsor_id = %d AND click_day >= %s
                 GROUP BY ym ORDER BY ym ASC',
                $sponsor_id,
                gmdate( 'Y-m-01', strtotime( '-' . max( 1, $months - 1 ) . ' months' ) )
            ),
            ARRAY_A
        );

        $out = [];
        foreach ( (array) $rows as $row ) {
            $out[ $row['ym'] ] = (int) $row['clicks'];
        }

        return $out;
    }

    public static function total_clicks( string $from, string $to ): int {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COALESCE(SUM(clicks),0) FROM ' . self::table() . ' WHERE click_day BETWEEN %s AND %s',
                $from,
                $to
            )
        );
    }
}
