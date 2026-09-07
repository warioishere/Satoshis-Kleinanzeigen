<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Import in batches instead of all at once.
 *
 * The import mainly costs time for images — up to five downloads per item
 * from a foreign server. Six items already took over fifteen seconds; a
 * 200-item catalog inevitably hits PHP's time limit, and the vendor sees a
 * blank page.
 *
 * That's why only the job itself lives here: file, mapping, selection, and
 * how far it has progressed. The browser fetches one batch after another
 * and can show the progress along the way.
 *
 * Deliberately, the items themselves aren't stored, only their keys: a
 * catalog with descriptions would otherwise blow past the transient size
 * limit.
 */
final class Job {

    const TRANSIENT = 'sk_import_job_';

    /** Maximum time a batch may take to process. */
    const BUDGET = 10;

    /** Bounds for the batch size, which adjusts to the measured speed. */
    const MIN_BATCH = 1;
    const MAX_BATCH = 20;

    /** Job lives long enough for a large catalog. */
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
                // Start small: the first batch doesn't yet know how
                // expensive an item is. With three items, it measured at
                // 15 seconds — too close to the time limit.
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
     * Rebuild items from the file and narrow them down to the selection.
     *
     * @return array<int,array>|\WP_Error
     */
    private static function items( array $job ) {
        $items = Source::items( $job['path'], $job['mapping'] );
        if ( is_wp_error( $items ) ) {
            return $items;
        }

        if ( ! empty( $job['keys'] ) ) {
            $keys  = $job['keys'];
            $items = array_values(
                array_filter( $items, static fn( $item ) => in_array( (string) ( $item['key'] ?? '' ), $keys, true ) )
            );
        }

        return $items;
    }

    /**
     * Process one batch.
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
            // Keep only the first messages — with a broken catalog, the
            // list would otherwise end up longer than the catalog itself.
            $job['result']['errors'] = array_slice(
                array_merge( $job['result']['errors'], $result['errors'] ),
                0,
                20
            );
        }

        $job['offset'] += count( $slice );

        // Adjust the next batch size to the measured speed.
        // With a safety margin: items in a catalog vary in cost, and too
        // large a batch would hit the time limit instead of just taking
        // longer.
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
