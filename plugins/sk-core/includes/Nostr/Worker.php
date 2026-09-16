<?php

namespace SK\Core\Nostr;

defined( 'ABSPATH' ) || exit;

/**
 * The resident relay reader.
 *
 * Boots WordPress once, holds one connection per relay with the site's
 * subscriptions on it, and hands every event that arrives to the module
 * that handles it — the same handlers the cron polls feed, without the
 * minutes in between. Started by tools/nostr-worker.php under systemd,
 * which starts it again whenever it ends.
 *
 * It ends itself: a PHP process that has run WordPress for long enough
 * carries stale options and static caches, so after a few minutes (or
 * when memory grows) the loop is left and the next process starts with
 * fresh ones. What arrives in the gap is caught by the subscriptions'
 * `since` on the next dial.
 *
 * A relay that cannot be reached, or that the circuit breaker marks, is
 * skipped and tried again later; the others are read meanwhile.
 */
final class Worker {

    /** Seconds this process runs before it makes way for a fresh one. */
    const LIFETIME = 5 * MINUTE_IN_SECONDS;

    /** Memory at which the process ends early. */
    const MEMORY_MAX = 256 * 1024 * 1024;

    /** Seconds between heartbeats, while at least one relay is being read. */
    const HEARTBEAT = 30;

    /** Seconds before a relay that refused or dropped the connection is dialled again. */
    const RETRY = 30;

    /** Seconds one read may take once the socket said it has data. */
    const READ_TIMEOUT = 10;

    /** Seconds one select() waits before the loop looks around. */
    const TICK = 1.0;

    /** @var array<string, RelaySession> url => session */
    private array $sessions = [];

    /** @var array<string, int> url => earliest next dial */
    private array $retry_at = [];

    /** @var array<string, array{filters: array, on_event: callable, beat: callable}> */
    private array $subs = [];

    private string $auth_privkey = '';

    private bool $stop = false;

    /**
     * Entry point for the CLI script. Options: --relay=wss://… (only this
     * one, for a test), --lifetime=<s>.
     */
    public static function main( array $argv ): int {
        $opts     = getopt( '', [ 'relay::', 'lifetime::' ] );
        $relays   = Relays::list();
        $lifetime = max( 10, (int) ( $opts['lifetime'] ?? self::LIFETIME ) );

        if ( ! empty( $opts['relay'] ) ) {
            $relays = [ (string) $opts['relay'] ];
        }

        if ( empty( $relays ) ) {
            self::log( 'no relays configured' );

            return 0;
        }

        ( new self() )->run( $relays, $lifetime );

        return 0;
    }

    /**
     * What the active modules want pushed, each with its filters, the
     * handler and a heartbeat that records "read up to now".
     */
    private function subscriptions(): array {
        $subs = [];

        if ( sk_module_active( 'sk_nostr_market' )
            && class_exists( 'SK\Modules\NostrMarket\Bridge\NostrDMListener' )
            && \SK\Modules\NostrMarket\Bridge\ChatBridge::is_enabled() ) {
            $boxes = \SK\Modules\NostrMarket\Bridge\NostrDMListener::mailboxes();

            if ( $boxes ) {
                // Two days back, as the poll asks: a gift wrap carries a
                // made-up timestamp up to that far in the past.
                $subs['dm'] = [
                    'filters'  => [ [ 'kinds' => [ 4, 1059 ], '#p' => $boxes, 'since' => time() - 2 * DAY_IN_SECONDS ] ],
                    'on_event' => [ \SK\Modules\NostrMarket\Bridge\NostrDMListener::class, 'handle' ],
                    'beat'     => static fn() => update_option( \SK\Modules\NostrMarket\Bridge\NostrDMListener::LAST_SEEN_KEY, time() ),
                ];

                // The one mailbox key every process holds answers NIP-42.
                $this->auth_privkey = (string) \SK\Modules\NostrMarket\EventSender::get_privkey();
            }
        }

        if ( sk_module_active( 'sk_auth' ) && class_exists( 'SK\Modules\Auth\NostrRelaySync' ) ) {
            // From the last checkpoint, a minute of overlap for the restart
            // gap; handle() takes every event once.
            $since   = (int) get_option( \SK\Modules\Auth\NostrRelaySync::LAST_SYNC_KEY, time() - 600 ) - MINUTE_IN_SECONDS;
            $filters = \SK\Modules\Auth\NostrRelaySync::filters( max( 0, $since ) );

            if ( ! empty( $filters[0]['authors'] ) ) {
                $subs['sync'] = [
                    'filters'  => $filters,
                    'on_event' => [ \SK\Modules\Auth\NostrRelaySync::class, 'handle' ],
                    'beat'     => static fn() => update_option( \SK\Modules\Auth\NostrRelaySync::LAST_SYNC_KEY, time() ),
                ];
            }
        }

        return $subs;
    }

    public function run( array $relays, int $lifetime ): void {
        $this->subs = $this->subscriptions();

        if ( empty( $this->subs ) ) {
            self::log( 'nothing to listen for (modules off or no keys); exiting' );

            return;
        }

        if ( function_exists( 'pcntl_async_signals' ) ) {
            pcntl_async_signals( true );

            foreach ( [ SIGTERM, SIGINT ] as $signal ) {
                pcntl_signal( $signal, function () {
                    $this->stop = true;
                } );
            }
        }

        $deadline  = time() + $lifetime;
        $last_beat = time();

        self::log( sprintf( 'up: %d relay(s), subscriptions %s, lifetime %ds', count( $relays ), implode( ',', array_keys( $this->subs ) ), $lifetime ) );

        while ( ! $this->stop && time() < $deadline && memory_get_usage( true ) < self::MEMORY_MAX ) {
            foreach ( $relays as $url ) {
                $this->ensure( $url );
            }

            $open = array_filter( $this->sessions, static fn( RelaySession $s ) => $s->is_open() );

            if ( empty( $open ) ) {
                sleep( 1 );
                continue;
            }

            foreach ( RelaySession::wait( $open, self::TICK ) as $url => $session ) {
                if ( ! $session->pump() ) {
                    self::log( "$url: connection ended" );
                    $this->retry_at[ $url ] = time() + 5;
                }
            }

            if ( time() - $last_beat >= self::HEARTBEAT ) {
                $last_beat = time();

                foreach ( $this->subs as $sub ) {
                    ( $sub['beat'] )();
                }
            }
        }

        foreach ( $this->sessions as $session ) {
            $session->close();
        }

        self::log( sprintf( 'down: %s, %.0f MB', $this->stop ? 'signal' : ( time() >= $deadline ? 'lifetime over' : 'memory' ), memory_get_peak_usage( true ) / 1048576 ) );
    }

    /** Connected and subscribed to this relay, or a fresh attempt when due. */
    private function ensure( string $url ): void {
        $session = $this->sessions[ $url ] ?? null;

        if ( $session && $session->is_open() && $session->subscribed() > 0 ) {
            return;
        }

        if ( ( $this->retry_at[ $url ] ?? 0 ) > time() ) {
            return;
        }

        $this->retry_at[ $url ] = time() + self::RETRY;

        if ( $session ) {
            $session->close();
            unset( $this->sessions[ $url ] );
        }

        if ( Relays::stalled( $url ) ) {
            self::log( "$url: skipped, an earlier attempt never returned" );

            return;
        }

        $session = Relays::session( $url, [
            'timeout'      => self::READ_TIMEOUT,
            'verify'       => false, // Each handler checks what it needs.
            'auth_privkey' => $this->auth_privkey,
        ] );

        if ( ! $session->open() ) {
            self::log( "$url: unreachable" );

            return;
        }

        foreach ( $this->subs as $name => $sub ) {
            if ( null === $session->subscribe( $sub['filters'], $sub['on_event'] ) ) {
                self::log( "$url: lost while subscribing $name" );

                return;
            }
        }

        $this->sessions[ $url ] = $session;
        self::log( "$url: connected" );
    }

    private static function log( string $line ): void {
        fwrite( STDOUT, gmdate( 'Y-m-d H:i:s' ) . ' ' . $line . "\n" );
    }
}
