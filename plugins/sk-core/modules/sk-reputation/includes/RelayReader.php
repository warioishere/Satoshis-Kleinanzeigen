<?php

namespace SK\Modules\Reputation;

use SK\Core\Nostr\Events;
use SK\Core\Nostr\Relays;

defined( 'ABSPATH' ) || exit;

/**
 * Reading from the site's relays, for cron jobs only — a thin front for
 * SK\Core\Nostr\Relays, kept for the module's callers and tests. New code
 * should call Relays directly.
 */
class RelayReader {

    const TIMEOUT    = Relays::READ_TIMEOUT;
    const MAX_EVENTS = Relays::MAX_EVENTS;

    /** @return string[] The site's relays, none while sk_auth is off. */
    public static function relays(): array {
        return sk_module_active( 'sk_auth' ) ? Relays::list() : [];
    }

    /** @return array<int, array> Verified events. */
    public static function req( string $relay, array $filters, int $timeout = self::TIMEOUT, int $max = self::MAX_EVENTS ): array {
        return self::fetch( $relay, $filters, $timeout, $max )['events'];
    }

    /** @return array{events: array<int, array>, eose: bool} */
    public static function fetch( string $relay, array $filters, int $timeout = self::TIMEOUT, int $max = self::MAX_EVENTS ): array {
        return Relays::fetch( $relay, $filters, [ 'timeout' => $timeout, 'max' => $max ] );
    }

    /** @param string[]|null $authors */
    public static function verified( $event, ?int $kind = null, ?array $authors = null ): bool {
        return Events::verify( $event, $kind, $authors );
    }

    /**
     * @param string[] $authors
     * @param int|null $answered Relay requests that reached EOSE.
     * @return array<string, array> author => event
     */
    public static function latest( int $kind, array $authors, ?int &$answered = null ): array {
        return Relays::latest( $kind, $authors, $answered, self::relays() );
    }
}
