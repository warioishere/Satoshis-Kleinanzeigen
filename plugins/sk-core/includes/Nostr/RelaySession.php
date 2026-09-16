<?php

namespace SK\Core\Nostr;

use Phrity\Net\StreamCollection;
use Phrity\Net\StreamFactory;

defined( 'ABSPATH' ) || exit;

/**
 * One connection to one relay, several requests over it.
 *
 * For the caller who pages through many mailboxes and does not want to
 * reconnect for every REQ: open once, request() as often as needed — each
 * with a fresh subscription id, read until EOSE, events handed to a
 * callback one by one — and close(). The circuit breaker is set on open()
 * and cleared on close(), so a relay that takes the worker down mid-session
 * is left alone afterwards like everywhere else.
 *
 * NIP-42 is handled once per session: a challenge is answered with the
 * session's auth key, and a REQ that the relay closed with "auth-required"
 * is sent again after the answer was accepted.
 *
 * The second way to use it is to stay: subscribe() sends a REQ and keeps
 * it, wait() blocks on the sockets of several sessions at once, and
 * pump() reads one message and hands an event to the subscription's
 * callback. That is the resident worker's loop; the circuit breaker
 * covers the dial and is lifted as soon as a subscription is held, since
 * the connection then outlives every other caller's attempt.
 *
 * Obtained through Relays::session(); never runs while a page renders.
 */
final class RelaySession {

    private string $url;
    private int $timeout;
    private bool $verify;
    private string $auth_privkey;

    /** @var \WebSocket\Client|null */
    private $client = null;

    /** The client's stream collection, held here so wait() can select on it. */
    private ?StreamCollection $streams = null;

    /** Subscriptions kept open: id => req (JSON), on_event, auth ('' | 'refused' | 'resent'). */
    private array $subs = [];

    private float $deadline = 0.0;

    // NIP-42 state for this connection.
    private ?string $auth_id = null;
    private bool $authed    = false;

    /**
     * @param array $opts timeout (s, for the whole session), verify (default
     *                    true), auth_privkey (hex or nsec for NIP-42).
     */
    public function __construct( string $url, array $opts = [] ) {
        $this->url          = $url;
        $this->timeout      = (int) ( $opts['timeout'] ?? Relays::READ_TIMEOUT );
        $this->verify       = (bool) ( $opts['verify'] ?? true );
        $this->auth_privkey = isset( $opts['auth_privkey'] ) && is_string( $opts['auth_privkey'] ) ? $opts['auth_privkey'] : '';
    }

    /** Connect. False when the relay is stalled, missing or unreachable. */
    public function open(): bool {
        if ( ! class_exists( '\WebSocket\Client' ) || Relays::stalled( $this->url ) ) {
            return false;
        }

        Relays::mark_attempt( $this->url );

        try {
            $this->streams = new StreamCollection();
            $shared        = $this->streams;

            $this->client = new \WebSocket\Client( $this->url );
            $this->client->setTimeout( $this->timeout );
            // The client attaches its socket to the collection its factory
            // hands out; handing out ours is how wait() gets to select on it.
            $this->client->setStreamFactory( new class( $shared ) extends StreamFactory {
                public function __construct( private StreamCollection $shared ) {
                    parent::__construct();
                }

                public function createStreamCollection(): StreamCollection {
                    return $this->shared;
                }
            } );
            // Answer pings and close handshakes; a held connection is dropped
            // by the relay otherwise.
            $this->client->addMiddleware( new \WebSocket\Middleware\CloseHandler() );
            $this->client->addMiddleware( new \WebSocket\Middleware\PingResponder() );
            // The library dials lazily on the first send; connect here so an
            // unreachable relay is known before any request is built.
            $this->client->connect();
            $this->deadline = microtime( true ) + $this->timeout;

            return true;
        } catch ( \Throwable $e ) {
            $this->client = null;
            Relays::clear_attempt( $this->url );

            return false;
        }
    }

    public function is_open(): bool {
        return null !== $this->client;
    }

    /** Seconds left in this session's budget. */
    public function remaining(): float {
        return max( 0.0, $this->deadline - microtime( true ) );
    }

    /**
     * One REQ: events until EOSE, each to $on_event( array $event ). The
     * callback may return false to stop reading this request early.
     *
     * @return array{eose: bool, count: int, stopped: bool} eose: the relay
     *         finished; count: events handed over; stopped: the callback
     *         ended it early.
     */
    public function request( array $filters, callable $on_event ): array {
        $out = [ 'eose' => false, 'count' => 0, 'stopped' => false ];

        if ( null === $this->client ) {
            return $out;
        }

        $sub    = bin2hex( random_bytes( 8 ) );
        $req    = wp_json_encode( array_merge( [ 'REQ', $sub ], array_values( $filters ) ) );
        $reopen = false;
        $resent = false;

        try {
            $this->client->text( $req );

            while ( microtime( true ) < $this->deadline ) {
                $data = json_decode( $this->client->receive()->getContent(), true );

                if ( ! is_array( $data ) || ! isset( $data[0] ) ) {
                    continue;
                }

                if ( 'AUTH' === $data[0] ) {
                    if ( null === $this->auth_id && '' !== $this->auth_privkey && is_string( $data[1] ?? null ) && '' !== $data[1] ) {
                        $this->auth_id = Relays::answer_challenge( $this->client, $this->url, $data[1], $this->auth_privkey );
                    }
                    continue;
                }

                if ( 'OK' === $data[0] && null !== $this->auth_id && ( $data[1] ?? '' ) === $this->auth_id ) {
                    $this->authed = ! empty( $data[2] );

                    if ( $this->authed && $reopen && ! $resent ) {
                        $this->client->text( $req );
                        $resent = true;
                    }
                    continue;
                }

                // Answers to an earlier, already closed subscription.
                if ( ( $data[1] ?? '' ) !== $sub ) {
                    continue;
                }

                if ( 'CLOSED' === $data[0] && null !== $this->auth_id && ! $resent && Relays::wants_auth( (string) ( $data[2] ?? '' ) ) ) {
                    $reopen = true;

                    if ( $this->authed ) {
                        $this->client->text( $req );
                        $resent = true;
                    }
                    continue;
                }

                if ( 'EOSE' === $data[0] || 'CLOSED' === $data[0] ) {
                    $out['eose'] = true;
                    break;
                }

                if ( 'EVENT' !== $data[0] || ! is_array( $data[2] ?? null ) || ! is_string( $data[2]['id'] ?? null ) ) {
                    continue;
                }

                if ( $this->verify && ! Events::verify( $data[2] ) ) {
                    continue;
                }

                $out['count']++;

                if ( false === $on_event( $data[2] ) ) {
                    $out['stopped'] = true;
                    break;
                }
            }

            try {
                $this->client->text( wp_json_encode( [ 'CLOSE', $sub ] ) );
            } catch ( \Throwable $ignored ) {
                // The relay may already be gone.
            }
        } catch ( \Throwable $e ) {
            // A silent or broken connection ends the request; the caller sees no EOSE.
            $this->close();
        }

        return $out;
    }

    // ── Staying: subscriptions held open ─────────────────────────────────

    /**
     * Send a REQ and keep it: every event the relay sends for it, now or
     * later, goes to $on_event( array $event ). Returns the subscription
     * id, or null when the connection is gone.
     *
     * Lifts the breaker mark: the dial is over, and the mark would keep
     * every other caller off this relay for as long as the subscription
     * is held.
     */
    public function subscribe( array $filters, callable $on_event ): ?string {
        if ( null === $this->client ) {
            return null;
        }

        $sub = bin2hex( random_bytes( 8 ) );
        $req = wp_json_encode( array_merge( [ 'REQ', $sub ], array_values( $filters ) ) );

        try {
            $this->client->text( $req );
        } catch ( \Throwable $e ) {
            $this->close();

            return null;
        }

        $this->subs[ $sub ] = [ 'req' => $req, 'on_event' => $on_event, 'auth' => '' ];

        Relays::clear_attempt( $this->url );

        return $sub;
    }

    /** Subscriptions the relay has not closed. */
    public function subscribed(): int {
        return count( $this->subs );
    }

    /**
     * Read one message and deal with it: an event goes to its
     * subscription's callback, AUTH and OK keep the NIP-42 state, CLOSED
     * ends a subscription (or re-sends it once the relay took the auth
     * answer), everything else is dropped. Blocks until a message is
     * there, so call it after wait() said one is.
     *
     * @return bool False once the connection is gone.
     */
    public function pump(): bool {
        if ( null === $this->client ) {
            return false;
        }

        try {
            $message = $this->client->receive();

            if ( ! $this->client->isConnected() ) {
                $this->close();

                return false;
            }

            if ( 'text' !== $message->getOpcode() ) {
                return true;
            }

            $data = json_decode( $message->getContent(), true );

            if ( ! is_array( $data ) || ! isset( $data[0] ) ) {
                return true;
            }

            switch ( $data[0] ) {
                case 'AUTH':
                    if ( null === $this->auth_id && '' !== $this->auth_privkey && is_string( $data[1] ?? null ) && '' !== $data[1] ) {
                        $this->auth_id = Relays::answer_challenge( $this->client, $this->url, $data[1], $this->auth_privkey );
                    }
                    break;

                case 'OK':
                    if ( null !== $this->auth_id && ( $data[1] ?? '' ) === $this->auth_id ) {
                        $this->authed = ! empty( $data[2] );

                        if ( $this->authed ) {
                            $this->resend_refused();
                        }
                    }
                    break;

                case 'CLOSED':
                    $sub = (string) ( $data[1] ?? '' );

                    if ( ! isset( $this->subs[ $sub ] ) ) {
                        break;
                    }

                    if ( null !== $this->auth_id && '' === $this->subs[ $sub ]['auth'] && Relays::wants_auth( (string) ( $data[2] ?? '' ) ) ) {
                        $this->subs[ $sub ]['auth'] = 'refused';

                        if ( $this->authed ) {
                            $this->resend_refused();
                        }
                        break;
                    }

                    unset( $this->subs[ $sub ] );
                    error_log( '[SK Nostr] ' . $this->url . ' closed a subscription: ' . (string) ( $data[2] ?? '' ) );
                    break;

                case 'EVENT':
                    $sub = (string) ( $data[1] ?? '' );

                    if ( ! isset( $this->subs[ $sub ] ) || ! is_array( $data[2] ?? null ) || ! is_string( $data[2]['id'] ?? null ) ) {
                        break;
                    }

                    if ( $this->verify && ! Events::verify( $data[2] ) ) {
                        break;
                    }

                    ( $this->subs[ $sub ]['on_event'] )( $data[2] );
                    break;
            }

            return true;
        } catch ( \Throwable $e ) {
            // A timeout mid-frame or a dropped socket; the caller re-dials.
            $this->close();

            return false;
        }
    }

    /** Send every subscription the relay refused for lack of auth, once. */
    private function resend_refused(): void {
        foreach ( $this->subs as $sub => $entry ) {
            if ( 'refused' === $entry['auth'] ) {
                $this->subs[ $sub ]['auth'] = 'resent';
                $this->client->text( $entry['req'] );
            }
        }
    }

    /**
     * Block until one of the sessions has something to read, at most
     * $timeout seconds. Returns the ready ones under their original keys;
     * closed sessions are left out.
     *
     * @param array<int|string, RelaySession> $sessions
     * @return array<int|string, RelaySession>
     */
    public static function wait( array $sessions, float $timeout ): array {
        $all = new StreamCollection();
        $map = [];

        foreach ( $sessions as $key => $session ) {
            if ( null === $session->client || null === $session->streams ) {
                continue;
            }

            foreach ( $session->streams as $stream ) {
                $map[ $all->attach( $stream ) ] = $key;
            }
        }

        $ready = [];

        if ( count( $all ) > 0 ) {
            foreach ( $all->waitRead( $timeout ) as $name => $stream ) {
                $ready[ $map[ $name ] ] = $sessions[ $map[ $name ] ];
            }
        }

        return $ready;
    }

    /** Disconnect and lift the breaker mark. Safe to call twice. */
    public function close(): void {
        if ( null !== $this->client ) {
            try {
                $this->client->disconnect();
            } catch ( \Throwable $ignored ) {
                // Already gone.
            }

            $this->client = null;
        }

        $this->streams = null;
        $this->subs    = [];

        Relays::clear_attempt( $this->url );
    }

    public function __destruct() {
        $this->close();
    }
}
