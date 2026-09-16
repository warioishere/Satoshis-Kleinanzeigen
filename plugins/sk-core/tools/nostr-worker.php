<?php
/**
 * The resident Nostr worker: one WordPress boot, one open connection per
 * relay, events pushed to the handlers as they arrive (see
 * SK\Core\Nostr\Worker). Ends itself after a few minutes and expects to be
 * started again — the systemd unit does that.
 *
 * Usage:
 *   /opt/keyhelp/php/8.3/bin/php /path/to/wp-content/plugins/sk-core/tools/nostr-worker.php [--relay=wss://…] [--lifetime=300]
 *
 * The file lives inside a web-readable directory, so it refuses to do
 * anything unless it was started from a command line.
 */

if ( 'cli' !== PHP_SAPI ) {
    http_response_code( 404 );
    exit;
}

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';

if ( ! file_exists( $wp_load ) ) {
    fwrite( STDERR, "wp-load.php not found at {$wp_load}\n" );
    exit( 1 );
}

define( 'WP_USE_THEMES', false );
require $wp_load;

exit( \SK\Core\Nostr\Worker::main( $argv ) );
