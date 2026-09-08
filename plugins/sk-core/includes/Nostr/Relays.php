<?php

namespace SK\Core\Nostr;

defined( 'ABSPATH' ) || exit;

/**
 * Every relay connection the plugin makes, in one place.
 *
 * Reading (REQ until EOSE) and publishing (EVENT until OK) share the relay
 * list, the timeouts, the NIP-42 handling and — above all — the circuit
 * breaker: a relay that took a PHP worker down mid-connection is left alone
 * for a while, whoever dials it next. Before, each module had its own
 * WebSocket loop and only some of them had the breaker.
 *
 * What comes back from a relay is verified before it is returned (id and
 * signature, see Events); a caller that wants raw events says so. Nothing
 * here may run while a page renders — relay work belongs in cron, AJAX or
 * after fastcgi_finish_request().
 */
final class Relays {

    const OPTION   = 'nostr_login_relays';
    const DEFAULTS = "wss://relay.damus.io\nwss://nos.lol\nwss://relay.primal.net\nwss://relay.snort.social";

    /** Seconds for a read: connect, REQ, events until EOSE. */
    const READ_TIMEOUT = 10;

    /** Seconds for a publish: connect, EVENT, wait for the OK. */
    const PUBLISH_TIMEOUT = 5;

    /** Events accepted from one REQ before the connection is dropped. */
    const MAX_EVENTS = 2000;

    /** How long a relay is left alone after an attempt that never returned. */
    const STALL_SKIP = 6 * HOUR_IN_SECONDS;

    // ── The list ─────────────────────────────────────────────────────────

    /**
     * The site's relays from the Nostr settings: newline, comma or space
     * separated, websocket URLs only, each once.
     *
     * @return string[]
     */
    public static function list(): array {
        $option = get_option( self::OPTION, self::DEFAULTS );
        $relays = preg_split( '/[\s,]+/', (string) $option );
        $relays = array_filter( array_map( 'trim', (array) $relays ), static function ( $r ) {
            return (bool) preg_match( '#^wss?://\S+$#i', $r );
        } );

        return array_values( array_unique( $relays ) );
    }

    // ── The circuit breaker ──────────────────────────────────────────────

    /** Is this relay left alone after an attempt that never came back? */
    public static function stalled( string $url ): bool {
        return (bool) get_transient( self::breaker_key( $url ) );
    }

    /**
     * Note an attempt before it starts. Not every failure comes back as a
     * failure: a relay has taken the whole PHP process down mid-connection,
     * and such an attempt never reaches clear_attempt(). The marker outlives
     * the crash, so the next caller skips that relay instead of dying with it.
     */
    public static function mark_attempt( string $url ): void {
        set_transient( self::breaker_key( $url ), 1, self::STALL_SKIP );
    }

    /** The attempt came back, whatever it brought. */
    public static function clear_attempt( string $url ): void {
        delete_transient( self::breaker_key( $url ) );
    }

    private static function breaker_key( string $url ): string {
        return 'sk_relay_stalled_' . md5( $url );
    }

    // ── Reading ──────────────────────────────────────────────────────────

    /**
     * One REQ against one relay: the events until EOSE, and whether the
     * relay finished answering. An empty result means two different
     * things — the relay has nothing, or it never answered — and `eose`
     * tells them apart.
     *
     * Options: `timeout` (s), `max` (events), `verify` (default true: only
     * events with a valid id and signature), `auth_privkey` (answer a
     * NIP-42 challenge with this key and re-send the REQ once).
     *
     * @return array{events: array<int, array>, eose: bool}
     */
    public static function fetch( string $relay, array $filters, array $opts = [] ): array {
        $timeout = (int) ( $opts['timeout'] ?? self::READ_TIMEOUT );
        $max     = (int) ( $opts['max'] ?? self::MAX_EVENTS );
        $verify  = (bool) ( $opts['verify'] ?? true );
        $privkey = isset( $opts['auth_privkey'] ) && is_string( $opts['auth_privkey'] ) ? $opts['auth_privkey'] : '';

        $none = [ 'events' => [], 'eose' => false ];

        if ( ! class_exists( '\WebSocket\Client' ) || self::stalled( $relay ) ) {
            return $none;
        }

        self::mark_attempt( $relay );

        $events = [];
        $eose   = false;
        $sub    = bin2hex( random_bytes( 8 ) );
        $req    = wp_json_encode( array_merge( [ 'REQ', $sub ], array_values( $filters ) ) );

        // NIP-42 state: the answer sent, whether it was accepted, whether
        // the REQ was refused for lack of auth and whether it went out again.
        $auth_id = null;
        $authed  = false;
        $reopen  = false;
        $resent  = false;

        try {
            $client = new \WebSocket\Client( $relay );
            $client->setTimeout( $timeout );
            $client->text( $req );

            $deadline = microtime( true ) + $timeout;

            while ( microtime( true ) < $deadline && count( $events ) < $max ) {
                $data = json_decode( $client->receive()->getContent(), true );

                if ( ! is_array( $data ) || ! isset( $data[0] ) ) {
                    continue;
                }

                if ( 'AUTH' === $data[0] ) {
                    if ( null === $auth_id && '' !== $privkey && is_string( $data[1] ?? null ) && '' !== $data[1] ) {
                        $auth_id = self::answer_challenge( $client, $relay, $data[1], $privkey );
                    }
                    continue;
                }

                if ( 'OK' === $data[0] && null !== $auth_id && ( $data[1] ?? '' ) === $auth_id ) {
                    $authed = ! empty( $data[2] );

                    if ( $authed && $reopen && ! $resent ) {
                        $client->text( $req );
                        $resent = true;
                    }
                    continue;
                }

                if ( ( $data[1] ?? '' ) !== $sub ) {
                    continue;
                }

                if ( 'CLOSED' === $data[0] && null !== $auth_id && ! $resent && self::wants_auth( (string) ( $data[2] ?? '' ) ) ) {
                    $reopen = true;

                    if ( $authed ) {
                        $client->text( $req );
                        $resent = true;
                    }
                    continue;
                }

                if ( 'EOSE' === $data[0] || 'CLOSED' === $data[0] ) {
                    $eose = true;
                    break;
                }

                if ( 'EVENT' === $data[0] && is_array( $data[2] ?? null ) && is_string( $data[2]['id'] ?? null ) ) {
                    if ( ! $verify || Events::verify( $data[2] ) ) {
                        $events[] = $data[2];
                    }
                }
            }

            try {
                $client->text( wp_json_encode( [ 'CLOSE', $sub ] ) );
                $client->close();
            } catch ( \Throwable $ignored ) {
                // The relay may already be gone.
            }
        } catch ( \Throwable $e ) {
            // A silent or unreachable relay contributes nothing.
        }

        self::clear_attempt( $relay );

        return [ 'events' => $events, 'eose' => $eose ];
    }

    /**
     * The same REQ against several relays (the site's, by default), merged
     * and deduplicated by id. `answered` counts the relays that reached EOSE.
     *
     * @param string[]|null $relays
     * @return array{events: array<string, array>, answered: int} id => event
     */
    public static function query( array $filters, ?array $relays = null, array $opts = [] ): array {
        $events   = [];
        $answered = 0;

        foreach ( $relays ?? self::list() as $relay ) {
            $result = self::fetch( $relay, $filters, $opts );

            if ( $result['eose'] ) {
                $answered++;
            }

            foreach ( $result['events'] as $event ) {
                $id = strtolower( (string) ( $event['id'] ?? '' ) );

                if ( Keys::is_hex( $id ) && ! isset( $events[ $id ] ) ) {
                    $events[ $id ] = $event;
                }
            }
        }

        return [ 'events' => $events, 'answered' => $answered ];
    }

    /**
     * The newest replaceable event of a kind per author across the relays,
     * each verified against its signature and the author it was asked for.
     *
     * @param string[]      $authors
     * @param int|null      $answered Set to the number of relay requests that reached EOSE.
     * @param string[]|null $relays
     * @return array<string, array> author => event
     */
    public static function latest( int $kind, array $authors, ?int &$answered = null, ?array $relays = null ): array {
        $latest   = [];
        $answered = 0;
        $authors  = array_values( array_unique( array_filter( array_map( [ Keys::class, 'to_hex' ], $authors ) ) ) );

        foreach ( array_chunk( $authors, 100 ) as $chunk ) {
            $result = self::query( [ [ 'kinds' => [ $kind ], 'authors' => $chunk ] ], $relays );
            $answered += $result['answered'];

            foreach ( $result['events'] as $event ) {
                if ( ! Events::verify( $event, $kind, $chunk ) ) {
                    continue;
                }

                $author = strtolower( $event['pubkey'] );

                if ( ! isset( $latest[ $author ] ) || $latest[ $author ]['created_at'] < $event['created_at'] ) {
                    $latest[ $author ] = $event;
                }
            }
        }

        return $latest;
    }

    /**
     * A connection for several requests to one relay (see RelaySession):
     * for pollers that page through many filters and must not reconnect
     * for each. Options as for fetch(); `timeout` covers the whole session.
     */
    public static function session( string $relay, array $opts = [] ): RelaySession {
        return new RelaySession( $relay, $opts );
    }

    // ── Publishing ───────────────────────────────────────────────────────

    /**
     * Send a signed event and report which relays accepted it.
     *
     * Only an OK-true for this very event id counts; NOTICE is skipped, an
     * AUTH challenge is answered (with the auth key, else a throwaway key),
     * and an event refused with "auth-required" is sent once more after the
     * relay took the answer.
     *
     * @param array|\swentel\nostr\EventInterface $event
     * @param string[]|null                        $relays       The site's relays by default.
     * @param string|null                          $auth_privkey Key for NIP-42, normally the signer's.
     * @return array{accepted: string[], rejected: array<string, string>}
     */
    public static function publish( $event, ?array $relays = null, ?string $auth_privkey = null ): array {
        $relays   = $relays ?? self::list();
        $accepted = [];
        $rejected = [];

        if ( is_array( $event ) ) {
            try {
                $event = Events::to_object( $event );
            } catch ( \Throwable $e ) {
                $event = null;
            }
        }

        if ( ! $event instanceof \swentel\nostr\EventInterface ) {
            foreach ( $relays as $url ) {
                $rejected[ $url ] = 'not an event';
            }

            return compact( 'accepted', 'rejected' );
        }

        if ( ! class_exists( '\WebSocket\Client' ) ) {
            foreach ( $relays as $url ) {
                $rejected[ $url ] = 'websocket client missing';
            }

            return compact( 'accepted', 'rejected' );
        }

        $payload  = ( new \swentel\nostr\Message\EventMessage( $event ) )->generate();
        $event_id = (string) $event->getId();

        foreach ( $relays as $url ) {
            if ( self::stalled( $url ) ) {
                $rejected[ $url ] = 'skipped: an earlier attempt never returned';
                continue;
            }

            self::mark_attempt( $url );

            $result = self::send_one( $url, $payload, $event_id, $auth_privkey );

            self::clear_attempt( $url );

            if ( true === $result ) {
                $accepted[] = $url;
            } else {
                $rejected[ $url ] = $result;
                error_log( '[SK Nostr] ' . $url . ' did not accept ' . substr( $event_id, 0, 12 ) . ': ' . $result );
            }
        }

        return compact( 'accepted', 'rejected' );
    }

    /** @return true|string True when accepted, otherwise the reason. */
    private static function send_one( string $url, string $payload, string $event_id, ?string $auth_privkey ) {
        $client  = null;
        $notice  = '';
        $auth_id = null;
        $authed  = false;
        $resend  = false;
        $resent  = false;

        try {
            $client = new \WebSocket\Client( $url );
            $client->setTimeout( self::PUBLISH_TIMEOUT );
            $client->text( $payload );

            $deadline = microtime( true ) + self::PUBLISH_TIMEOUT;

            while ( microtime( true ) < $deadline ) {
                $data = json_decode( $client->receive()->getContent(), true );

                if ( ! is_array( $data ) || ! isset( $data[0] ) ) {
                    continue;
                }

                switch ( $data[0] ) {
                    case 'AUTH':
                        if ( null === $auth_id && is_string( $data[1] ?? null ) && '' !== $data[1] ) {
                            $auth_id = self::answer_challenge( $client, $url, $data[1], $auth_privkey ?: self::throwaway_key() );
                        }
                        continue 2;

                    case 'OK':
                        if ( null !== $auth_id && ( $data[1] ?? '' ) === $auth_id ) {
                            $authed = ! empty( $data[2] );

                            if ( $authed && $resend && ! $resent ) {
                                $client->text( $payload );
                                $resent = true;
                            }

                            continue 2;
                        }

                        if ( ( $data[1] ?? '' ) !== $event_id ) {
                            continue 2;
                        }

                        if ( ! empty( $data[2] ) ) {
                            $client->disconnect();

                            return true;
                        }

                        $reason = (string) ( $data[3] ?? '' );

                        if ( self::wants_auth( $reason ) && ! $resent && null !== $auth_id ) {
                            $resend = true;

                            if ( $authed ) {
                                $client->text( $payload );
                                $resent = true;
                            }

                            continue 2;
                        }

                        $client->disconnect();

                        return $reason ?: 'rejected without reason';

                    case 'NOTICE':
                        $notice = (string) ( $data[1] ?? '' );
                        continue 2;

                    case 'CLOSED':
                        $client->disconnect();

                        return 'closed: ' . (string) ( $data[2] ?? '' );

                    default:
                        continue 2;
                }
            }

            $client->disconnect();

            return 'no OK within ' . self::PUBLISH_TIMEOUT . 's' . ( $notice ? ' (notice: ' . $notice . ')' : '' );
        } catch ( \Throwable $e ) {
            if ( $client ) {
                try {
                    $client->disconnect();
                } catch ( \Throwable $ignored ) {
                    // Already gone.
                }
            }

            return $e->getMessage() . ( $notice ? ' (notice: ' . $notice . ')' : '' );
        }
    }

    // ── NIP-42 ───────────────────────────────────────────────────────────

    /**
     * Answer a relay's AUTH challenge: a kind 22242 naming the relay and
     * the challenge, signed with the given key, sent as ["AUTH", event].
     *
     * @return string The id of the auth event, so its OK can be told apart.
     */
    public static function answer_challenge( \WebSocket\Client $client, string $relay_url, string $challenge, string $privkey ): string {
        $auth = Events::sign( 22242, '', [ [ 'relay', $relay_url ], [ 'challenge', $challenge ] ], $privkey );

        $client->text( '["AUTH",' . wp_json_encode( $auth, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ']' );

        return (string) $auth['id'];
    }

    /** A key for challenges when the caller has none to offer. */
    public static function throwaway_key(): string {
        return Keys::generate()['priv'];
    }

    /** Does a relay's reason say it wants NIP-42 first? */
    public static function wants_auth( string $reason ): bool {
        return 0 === strpos( $reason, 'auth-required' );
    }

    // ── Foreign relay URLs ───────────────────────────────────────────────

    /**
     * May a relay URL that a stranger published be dialled from here?
     *
     * Only TLS relays under a public DNS name. The name is resolved and
     * every address it yields has to be public; an IP literal, a name that
     * resolves into a private or reserved range, and anything that does
     * not resolve at all is refused. Without this a recipient's relay list
     * could point the server at an internal service, the loopback
     * interface or a host known to take the PHP worker down.
     */
    public static function is_public_url( string $url ): bool {
        $parts = wp_parse_url( trim( $url ) );

        if ( ! is_array( $parts ) || 'wss' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) || empty( $parts['host'] ) ) {
            return false;
        }

        if ( isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
            return false;
        }

        $host = strtolower( (string) $parts['host'] );

        // A DNS name with a public suffix — no IP literals, no single labels.
        if ( strlen( $host ) > 253
            || false === strpos( $host, '.' )
            || filter_var( $host, FILTER_VALIDATE_IP ) !== false
            || '[' === $host[0]
            || ! preg_match( '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host )
            || preg_match( '/\.(?:localhost|local|internal|intranet|lan|home|corp|arpa|test|example|invalid|onion)$/', $host ) ) {
            return false;
        }

        $addresses = [];
        $v4        = gethostbynamel( $host );

        if ( is_array( $v4 ) ) {
            $addresses = $v4;
        }

        foreach ( (array) @dns_get_record( $host, DNS_AAAA ) as $record ) {
            if ( ! empty( $record['ipv6'] ) ) {
                $addresses[] = $record['ipv6'];
            }
        }

        if ( empty( $addresses ) ) {
            return false;
        }

        foreach ( $addresses as $ip ) {
            if ( false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                return false;
            }
        }

        return true;
    }
}
