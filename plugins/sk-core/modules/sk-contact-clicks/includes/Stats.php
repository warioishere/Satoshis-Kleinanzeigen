<?php

namespace SK\Modules\ContactClicks;

defined( 'ABSPATH' ) || exit;

/**
 * Auswertung der Kontaktklicks.
 *
 * "clicks" ist die Summe aller Klicks, "unique" die Zahl der Besucher-Tage.
 * Fuer die Frage, ob ein Inserat Kontakte erzeugt, ist die zweite Zahl die
 * belastbarere — mehrfaches Klicken derselben Person zaehlt darin einmal.
 */
final class Stats {

    private static function table(): string {
        global $wpdb;

        return $wpdb->prefix . 'sk_contact_clicks';
    }

    /**
     * @return array{clicks:int,unique:int}
     */
    public static function totals( string $from, string $to ): array {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                // DISTINCT, sonst zaehlt eine Person doppelt, die am selben
                // Tag zwei Kanaele benutzt. Der Hash rotiert taeglich, ueber
                // mehrere Tage sind das folglich Besuchertage, keine Koepfe.
                'SELECT COALESCE(SUM(clicks),0) c, COUNT(DISTINCT visitor_hash, click_day) u FROM ' . self::table() . ' WHERE click_day BETWEEN %s AND %s',
                $from,
                $to
            ),
            ARRAY_A
        );

        return [ 'clicks' => (int) ( $row['c'] ?? 0 ), 'unique' => (int) ( $row['u'] ?? 0 ) ];
    }

    /**
     * @return array<string,array{clicks:int,unique:int}>
     */
    public static function by_channel( string $from, string $to ): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT channel, COALESCE(SUM(clicks),0) c, COUNT(*) u FROM ' . self::table() . '
                 WHERE click_day BETWEEN %s AND %s GROUP BY channel ORDER BY c DESC',
                $from,
                $to
            ),
            ARRAY_A
        );

        $out = [];
        foreach ( (array) $rows as $r ) {
            $out[ $r['channel'] ] = [ 'clicks' => (int) $r['c'], 'unique' => (int) $r['u'] ];
        }

        return $out;
    }

    /**
     * @return array<int,array{product_id:int,vendor_id:int,clicks:int,unique:int}>
     */
    public static function top_products( string $from, string $to, int $limit = 20 ): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT product_id, MAX(vendor_id) vendor_id, COALESCE(SUM(clicks),0) c, COUNT(*) u
                 FROM ' . self::table() . '
                 WHERE click_day BETWEEN %s AND %s AND product_id > 0
                 GROUP BY product_id ORDER BY c DESC LIMIT %d',
                $from,
                $to,
                $limit
            ),
            ARRAY_A
        );

        $out = [];
        foreach ( (array) $rows as $r ) {
            $out[] = [
                'product_id' => (int) $r['product_id'],
                'vendor_id'  => (int) $r['vendor_id'],
                'clicks'     => (int) $r['c'],
                'unique'     => (int) $r['u'],
            ];
        }

        return $out;
    }

    /**
     * Kontakte je Tag, aeltester Tag zuerst.
     *
     * @return array<string,int>
     */
    public static function daily( string $from, string $to ): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT click_day, COALESCE(SUM(clicks),0) c FROM ' . self::table() . '
                 WHERE click_day BETWEEN %s AND %s GROUP BY click_day ORDER BY click_day ASC',
                $from,
                $to
            ),
            ARRAY_A
        );

        $out = [];
        foreach ( (array) $rows as $r ) {
            $out[ $r['click_day'] ] = (int) $r['c'];
        }

        return $out;
    }

    /**
     * Aufrufe aller Inserate insgesamt.
     *
     * Kommt aus dem vorhandenen Zaehler (postmeta "pageview") und ist deshalb
     * kumulativ, nicht auf den Zeitraum begrenzt — taugt also nur fuer eine
     * grobe Einordnung, nicht fuer eine exakte Quote.
     */
    public static function total_views(): int {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COALESCE(SUM(CAST(meta_value AS UNSIGNED)),0) FROM {$wpdb->postmeta} WHERE meta_key = 'pageview'"
        );
    }

    public static function first_day(): string {
        global $wpdb;

        return (string) $wpdb->get_var( 'SELECT MIN(click_day) FROM ' . self::table() );
    }
}
