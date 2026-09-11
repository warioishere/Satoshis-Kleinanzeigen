<?php

namespace SK\Core\Trust;

defined( 'ABSPATH' ) || exit;

/**
 * Central registry for the trust signals shown next to a vendor.
 *
 * A signal is one small chip: sats received in zaps, a verified link, later
 * the social-graph line and the Lightning proofs. Modules register a
 * renderer here; the store banner, the vendor box on the product page and
 * the feed card ask the registry for the chips of one vendor in one
 * context. Signals are shown side by side and never summed into a score —
 * each stands for a fact the viewer can check on its own.
 *
 * A signal that has nothing to say for a vendor returns '' and leaves no
 * trace. Absence is silent by design: a vendor without a signal looks
 * exactly like one before the signal existed.
 *
 * The registry lives in core, not in a module, so a chip such as the zap
 * total renders whether or not the reputation module is switched on.
 */
class TrustSignals {

    const CONTEXT_STORE   = 'store';
    const CONTEXT_PRODUCT = 'product';
    const CONTEXT_CARD    = 'card';
    const CONTEXT_FEED    = 'feed';
    /** The store's trust page, where a signal explains itself. */
    const CONTEXT_PAGE    = 'page';

    /** @var array<string, array{render: callable, priority: int}> */
    private static array $signals = [];

    /**
     * Register a signal.
     *
     * @param string   $id       Unique id, used to replace a signal on re-registration.
     * @param callable $render   function( int $vendor_id, string $context ): string — full HTML
     *                           of the chip (an <li> in the store context, inline
     *                           markup elsewhere), or '' for nothing.
     * @param int      $priority Lower renders first.
     */
    public static function register( string $id, callable $render, int $priority = 10 ): void {
        self::$signals[ $id ] = [
            'render'   => $render,
            'priority' => $priority,
        ];
    }

    public static function unregister( string $id ): void {
        unset( self::$signals[ $id ] );
    }

    public static function has( string $id ): bool {
        return isset( self::$signals[ $id ] );
    }

    /**
     * All chips of one vendor in one context, in priority order.
     */
    public static function html( int $vendor_id, string $context ): string {
        if ( $vendor_id <= 0 || empty( self::$signals ) ) {
            return '';
        }

        $signals = self::$signals;

        uasort( $signals, static function ( array $a, array $b ): int {
            return $a['priority'] <=> $b['priority'];
        } );

        $out = '';

        foreach ( $signals as $id => $signal ) {
            try {
                $chip = (string) call_user_func( $signal['render'], $vendor_id, $context );
            } catch ( \Throwable $e ) {
                error_log( '[SK Trust] signal ' . $id . ' failed: ' . $e->getMessage() );
                $chip = '';
            }

            if ( '' !== $chip ) {
                $out .= $chip;
            }
        }

        return $out;
    }

    public static function render( int $vendor_id, string $context ): void {
        echo self::html( $vendor_id, $context ); // phpcs:ignore WordPress.Security.EscapeOutput -- each renderer escapes its own markup.
    }
}
