<?php

namespace SK\Modules\Auth;

use SK\Core\Nostr\Relays;

defined( 'ABSPATH' ) || exit;

/**
 * Publishing to relays — now a thin front for SK\Core\Nostr\Relays, kept
 * so the many callers across the modules need not change at once. New
 * code should call Relays directly.
 */
class RelayPublisher {

    const TIMEOUT    = Relays::PUBLISH_TIMEOUT;
    const STALL_SKIP = Relays::STALL_SKIP;

    /**
     * @param array|\swentel\nostr\EventInterface $event
     * @param string[]                            $relays
     * @return array{accepted: string[], rejected: array<string, string>}
     */
    public static function publish( $event, array $relays, ?string $auth_privkey = null ): array {
        return Relays::publish( $event, $relays, $auth_privkey );
    }

    public static function stalled( string $url ): bool {
        return Relays::stalled( $url );
    }

    public static function mark_attempt( string $url ): void {
        Relays::mark_attempt( $url );
    }

    public static function clear_attempt( string $url ): void {
        Relays::clear_attempt( $url );
    }

    public static function answer_challenge( \WebSocket\Client $client, string $relay_url, string $challenge, string $privkey ): string {
        return Relays::answer_challenge( $client, $relay_url, $challenge, $privkey );
    }

    public static function throwaway_key(): string {
        return Relays::throwaway_key();
    }

    public static function wants_auth( string $reason ): bool {
        return Relays::wants_auth( $reason );
    }
}
