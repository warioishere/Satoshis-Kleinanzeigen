<?php

namespace SK\Modules\Reputation;

use SK\Core\Nostr\Events;
use SK\Core\Nostr\Keys;
use SK\Core\Nostr\Relays;

defined( 'ABSPATH' ) || exit;

/**
 * The marketplace's web of trust: the keys the marketplace key follows,
 * and the keys those follow. Rebuilt from the relays once a day, kept as
 * 16-hex prefixes in a transient.
 *
 * Vendors' own keys are not in it: anyone with a Nostr login could report
 * a competitor and look like the community doing it. Their reports are
 * kept separately, marked as coming from a vendor (see Reports::accept).
 */
final class WebOfTrust {

    const TRANSIENT = 'sk_reputation_wot_v2';

    /** Keys are kept as this many leading hex characters. */
    const PREFIX = 16;

    /** @return array<string, int> prefix => 1 */
    public static function prefixes(): array {
        $cached = get_transient( self::TRANSIENT );

        if ( is_array( $cached ) && ! empty( $cached ) ) {
            return $cached;
        }

        $wot         = [];
        $marketplace = self::marketplace_pubkey();

        if ( '' !== $marketplace ) {
            $degree1 = self::follows_of( [ $marketplace ] );

            foreach ( $degree1 as $key ) {
                $wot[ substr( $key, 0, self::PREFIX ) ] = 1;
            }

            foreach ( self::follows_of( $degree1 ) as $key ) {
                $wot[ substr( $key, 0, self::PREFIX ) ] = 1;
            }
        }

        set_transient( self::TRANSIENT, $wot, DAY_IN_SECONDS );

        return $wot;
    }

    /** Is this key (hex, any case) in the web of trust? */
    public static function contains( string $pubkey, ?array $prefixes = null ): bool {
        $pubkey = Keys::to_hex( $pubkey );

        return '' !== $pubkey && isset( ( $prefixes ?? self::prefixes() )[ substr( $pubkey, 0, self::PREFIX ) ] );
    }

    /**
     * Union of the p tags of the newest kind 3 of each author, from every relay.
     *
     * @param string[] $authors
     * @return string[]
     */
    private static function follows_of( array $authors ): array {
        if ( empty( $authors ) ) {
            return [];
        }

        $follows = [];

        foreach ( Relays::latest( 3, $authors ) as $event ) {
            foreach ( Events::tag_values( $event, 'p', true ) as $key ) {
                $follows[ $key ] = 1;
            }
        }

        return array_keys( $follows );
    }

    /** The marketplace key roots the web of trust only while its module runs. */
    private static function marketplace_pubkey(): string {
        return sk_module_active( 'sk_nostr_market' ) ? Keys::marketplace_pubkey() : '';
    }
}
