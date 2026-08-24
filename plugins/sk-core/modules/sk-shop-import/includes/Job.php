<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Import in Stapeln statt in einem Rutsch.
 *
 * Der Import kostet vor allem Zeit für die Bilder — je Artikel bis zu fünf
 * Downloads von einem fremden Server. Sechs Artikel brauchten damit schon
 * über fünfzehn Sekunden; ein Katalog mit zweihundert läuft unweigerlich in
 * die Zeitgrenze von PHP, und der Verkäufer sieht eine weisse Seite.
 *
 * Deshalb liegt hier nur der Auftrag: Datei, Zuordnung, Auswahl und wie weit
 * er gediehen ist. Der Browser holt einen Stapel nach dem anderen ab und kann
 * dabei anzeigen, wo es steht.
 *
 * Bewusst werden nicht die Artikel selbst gespeichert, sondern nur ihre
 * Schlüssel: ein Katalog mit Beschreibungen sprengt sonst den Transient.
 */
final class Job {

    const TRANSIENT = 'sk_import_job_';

    /** Wie lange ein Stapel höchstens rechnen darf. */
    const BUDGET = 10;

    /** Grenzen für die Stapelgrösse, die sich am gemessenen Tempo ausrichtet. */
    const MIN_BATCH = 1;
    const MAX_BATCH = 20;

    /** Auftrag lebt lange genug für einen grossen Katalog. */
    const TTL = 2 * HOUR_IN_SECONDS;

    public static function create( int $vendor_id, string $path, array $mapping, array $keys, array $args, int $total ): void {
        set_transient(
            self::TRANSIENT . $vendor_id,
            [
                'path'    => $path,
                'mapping' => $mapping,
                'keys'    => array_values( $keys ),
                'args'    => $args,
                'offset'  => 0,
                'total'   => $total,
                // Klein anfangen: der erste Stapel weiss noch nicht, wie
                // teuer ein Artikel ist. Mit drei Artikeln lag er gemessen bei
                // 15 Sekunden — zu nah an der Zeitgrenze.
                'batch'   => 1,
                'result'  => [ 'created' => 0, 'updated' => 0, 'skipped' => 0, 'images' => 0, 'errors' => [] ],
            ],
            self::TTL
        );
    }

    public static function get( int $vendor_id ): ?array {
        $job = get_transient( self::TRANSIENT . $vendor_id );

        return is_array( $job ) ? $job : null;
    }

    public static function clear( int $vendor_id ): void {
        delete_transient( self::TRANSIENT . $vendor_id );
    }

    /**
     * Artikel aus der Datei neu aufbauen und auf die Auswahl eindampfen.
     *
     * @return array<int,array>|\WP_Error
     */
    private static function items( array $job ) {
        $csv = Csv::read( $job['path'] );
        if ( is_wp_error( $csv ) ) {
            return $csv;
        }

        $items = Catalog::build( $csv['headers'], $csv['rows'], $job['mapping'] );

        if ( ! empty( $job['keys'] ) ) {
            $keys  = $job['keys'];
            $items = array_values(
                array_filter( $items, static fn( $item ) => in_array( (string) ( $item['key'] ?? '' ), $keys, true ) )
            );
        }

        return $items;
    }

    /**
     * Einen Stapel abarbeiten.
     *
     * @return array{done:int,total:int,fertig:bool,result:array}|\WP_Error
     */
    public static function step( int $vendor_id ) {
        $job = self::get( $vendor_id );
        if ( ! $job ) {
            return new \WP_Error( 'sk_import_job', __( 'Kein laufender Import gefunden.', 'sk-core' ) );
        }

        if ( ! Storage::belongs_to( $job['path'], $vendor_id ) ) {
            self::clear( $vendor_id );
            return new \WP_Error( 'sk_import_job', __( 'Die hochgeladene Datei wurde nicht gefunden.', 'sk-core' ) );
        }

        $items = self::items( $job );
        if ( is_wp_error( $items ) ) {
            self::clear( $vendor_id );
            return $items;
        }

        $size  = max( self::MIN_BATCH, min( self::MAX_BATCH, (int) $job['batch'] ) );
        $slice = array_slice( $items, (int) $job['offset'], $size );

        if ( empty( $slice ) ) {
            return self::finish( $vendor_id, $job );
        }

        $started = microtime( true );
        $result  = Importer::run( $slice, $job['args'] );
        $elapsed = max( 0.001, microtime( true ) - $started );

        foreach ( [ 'created', 'updated', 'skipped', 'images' ] as $key ) {
            $job['result'][ $key ] += (int) ( $result[ $key ] ?? 0 );
        }
        if ( ! empty( $result['errors'] ) ) {
            // Nur die ersten Meldungen behalten — bei einem kaputten Katalog
            // waere die Liste sonst laenger als der Katalog selbst.
            $job['result']['errors'] = array_slice(
                array_merge( $job['result']['errors'], $result['errors'] ),
                0,
                20
            );
        }

        $job['offset'] += count( $slice );

        // Naechste Stapelgroesse am gemessenen Tempo ausrichten.
        // Mit Sicherheitsabschlag: die Artikel eines Katalogs sind unterschiedlich
        // teuer, und ein zu grosser Stapel laeuft in die Zeitgrenze statt nur
        // laenger zu dauern.
        $per_item     = $elapsed / count( $slice );
        $job['batch'] = (int) max( self::MIN_BATCH, min( self::MAX_BATCH, floor( self::BUDGET * 0.7 / $per_item ) ) );

        if ( $job['offset'] >= $job['total'] ) {
            return self::finish( $vendor_id, $job );
        }

        set_transient( self::TRANSIENT . $vendor_id, $job, self::TTL );

        return [
            'done'   => (int) $job['offset'],
            'total'  => (int) $job['total'],
            'fertig' => false,
            'result' => $job['result'],
        ];
    }

    private static function finish( int $vendor_id, array $job ): array {
        set_transient( 'sk_import_result_' . $vendor_id, $job['result'], 600 );

        Storage::forget( $job['path'] );
        delete_user_meta( $vendor_id, '_sk_import_file' );
        self::clear( $vendor_id );

        return [
            'done'   => (int) $job['total'],
            'total'  => (int) $job['total'],
            'fertig' => true,
            'result' => $job['result'],
        ];
    }
}
