<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Silences the auto posters during an import.
 *
 * A 28-item catalog would otherwise trigger 28 Telegram messages, 28 Nostr
 * posts, and 28 feed entries all at once. That's spam for every channel —
 * and it's not an edge case, it's the normal case during an import.
 *
 * Only callbacks from sk-notifications and sk-feed are removed, and
 * deliberately so. A blanket remove_all_actions() on save_post_product
 * would also hit WooCommerce, which maintains its lookup tables there.
 */
final class Silence {

    /** Hooks the posters are attached to. */
    const HOOKS = [
        'transition_post_status',
        'save_post',
        'save_post_product',
        'woocommerce_new_product',
        'woocommerce_update_product',
    ];

    /**
     * Modules whose callbacks get paused — everything that sends outward
     * on publish.
     *
     * sk-nostr-market belongs here: it also hooks into
     * transition_post_status and would push the whole catalog out to
     * Nostr marketplaces.
     *
     * Deliberately NOT included is sk-anti-fraud — its checks should still
     * run during an import too.
     */
    const MODULES = [ 'sk-notifications', 'sk-feed', 'sk-nostr-market' ];

    /** @var array<string,array> */
    private static $removed = [];

    public static function start(): void {
        global $wp_filter;

        self::$removed = [];

        foreach ( self::HOOKS as $hook ) {
            if ( empty( $wp_filter[ $hook ] ) ) {
                continue;
            }

            foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
                foreach ( $callbacks as $id => $entry ) {
                    if ( ! self::is_poster( $entry['function'] ) ) {
                        continue;
                    }

                    self::$removed[] = [
                        'hook'     => $hook,
                        'id'       => $id,
                        'priority' => $priority,
                        'entry'    => $entry,
                    ];

                    unset( $wp_filter[ $hook ]->callbacks[ $priority ][ $id ] );
                }
            }
        }
    }

    public static function stop(): void {
        global $wp_filter;

        foreach ( self::$removed as $item ) {
            if ( empty( $wp_filter[ $item['hook'] ] ) ) {
                continue;
            }
            $wp_filter[ $item['hook'] ]->callbacks[ $item['priority'] ][ $item['id'] ] = $item['entry'];
        }

        self::$removed = [];
    }

    public static function count(): int {
        return count( self::$removed );
    }

    /**
     * Does this callback come from one of the poster modules?
     */
    private static function is_poster( $callback ): bool {
        try {
            if ( $callback instanceof \Closure ) {
                $ref = new \ReflectionFunction( $callback );
            } elseif ( is_string( $callback ) && function_exists( $callback ) ) {
                $ref = new \ReflectionFunction( $callback );
            } elseif ( is_array( $callback ) && count( $callback ) === 2 ) {
                $ref = new \ReflectionMethod( $callback[0], $callback[1] );
            } else {
                return false;
            }

            $file = (string) $ref->getFileName();
        } catch ( \Throwable $e ) {
            return false;
        }

        if ( $file === '' ) {
            return false;
        }

        foreach ( self::MODULES as $module ) {
            if ( strpos( $file, '/modules/' . $module . '/' ) !== false ) {
                return true;
            }
        }

        return false;
    }
}
