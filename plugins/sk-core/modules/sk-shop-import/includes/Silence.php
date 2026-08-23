<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Schaltet die Auto-Poster waehrend eines Imports stumm.
 *
 * Ein Katalog mit 28 Artikeln loeste sonst 28 Telegram-Nachrichten, 28
 * Nostr-Beitraege und 28 Feed-Eintraege auf einen Schlag aus. Das ist fuer
 * jeden Kanal Spam — und es ist kein Grenzfall, sondern der Normalfall beim
 * Import.
 *
 * Entfernt werden gezielt nur Rueckrufe aus sk-notifications und sk-feed.
 * Ein pauschales remove_all_actions() auf save_post_product wuerde auch
 * WooCommerce treffen, das dort seine Nachschlagetabellen pflegt.
 */
final class Silence {

    /** Haken, an denen die Poster haengen. */
    const HOOKS = [
        'transition_post_status',
        'save_post',
        'save_post_product',
        'woocommerce_new_product',
        'woocommerce_update_product',
    ];

    /**
     * Module, deren Rueckrufe pausiert werden — alles, was beim
     * Veroeffentlichen nach draussen sendet.
     *
     * sk-nostr-market gehoert dazu: Es haengt ebenfalls an
     * transition_post_status und wuerde den ganzen Katalog auf
     * Nostr-Marktplaetze schieben.
     *
     * Bewusst NICHT dabei ist sk-anti-fraud — dessen Pruefungen sollen auch
     * bei einem Import laufen.
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
     * Stammt dieser Rueckruf aus einem der Poster-Module?
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
