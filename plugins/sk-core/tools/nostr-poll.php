<?php
/**
 * Fetches incoming Nostr direct messages, once per call.
 *
 * The bridge poll is a WordPress cron event, and WordPress cron on this site
 * is driven by a system cron that runs wp-cron.php every five minutes. That
 * cadence decides how long a Nostr reply sits on a relay before it shows up
 * in the chat, and five minutes is a long time to stare at a chat window.
 *
 * Raising the wp-cron.php frequency would speed up every scheduled job on the
 * site, from every plugin. This runs the one job instead, so the interval of
 * the mailbox is independent of everything else.
 *
 * Usage (crontab of the site user, alongside the existing wp-cron.php line):
 *   * * * * * /opt/keyhelp/php/8.3/bin/php /path/to/wp-content/plugins/sk-core/tools/nostr-poll.php
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

if ( ! class_exists( '\SK\Modules\NostrMarket\Bridge\NostrDMListener' ) ) {
    // Module switched off or not installed — nothing to do, and not an error.
    exit( 0 );
}

if ( ! \SK\Modules\NostrMarket\Bridge\ChatBridge::is_enabled() ) {
    exit( 0 );
}

\SK\Modules\NostrMarket\Bridge\NostrDMListener::poll();
