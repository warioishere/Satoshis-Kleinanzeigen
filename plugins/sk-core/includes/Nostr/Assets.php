<?php

namespace SK\Core\Nostr;

defined( 'ABSPATH' ) || exit;

/**
 * The browser side of the Nostr core: `sk-nostr` (helpers, relay queries,
 * verification) and the Schnorr verifier bundle it fetches on demand.
 *
 * Registered, not enqueued: a module script that needs it lists `sk-nostr`
 * as a dependency and WordPress pulls it in. The relay list and the
 * verifier URL are localized here once, so no module ships its own copy.
 */
final class Assets {

    const HANDLE = 'sk-nostr';

    public static function init(): void {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register' ], 5 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'register' ], 5 );
    }

    public static function register(): void {
        if ( wp_script_is( self::HANDLE, 'registered' ) ) {
            return;
        }

        wp_register_script(
            self::HANDLE,
            plugins_url( 'assets/js/sk-nostr.js', SK_CORE_FILE ),
            [],
            self::version( 'sk-nostr.js' ),
            true
        );

        wp_localize_script( self::HANDLE, 'skNostrConfig', [
            'relays' => Relays::list(),
            'verify' => self::verifier_url(),
        ] );
    }

    /** The verifier bundle, versioned by its mtime. */
    public static function verifier_url(): string {
        return add_query_arg( 'ver', self::version( 'sk-nostr-verify.js' ), plugins_url( 'assets/js/sk-nostr-verify.js', SK_CORE_FILE ) );
    }

    private static function version( string $file ): string {
        $path = SK_CORE_DIR . '/assets/js/' . $file;

        return (string) ( file_exists( $path ) ? filemtime( $path ) : '1' );
    }
}
