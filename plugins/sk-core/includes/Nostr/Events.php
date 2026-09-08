<?php

namespace SK\Core\Nostr;

defined( 'ABSPATH' ) || exit;

/**
 * Nostr events as plain arrays: verify, sign, read tags, store.
 *
 * Modules deal in arrays — the shape a relay sends and a browser posts —
 * and never touch the signing library themselves. Only this class and the
 * relay transport know `swentel\nostr`.
 *
 * verify() is the one gate for everything that arrives from outside:
 * well-formed, id recomputed, Schnorr signature valid, and when asked, of
 * a given kind and from one of a given set of authors. It never throws.
 */
final class Events {

    /**
     * Is this event what it claims to be?
     *
     * @param array|object|string $event   Array, stdClass, or JSON.
     * @param int|null            $kind    Required kind, if any.
     * @param string[]|null       $authors Allowed authors (hex, any case), if any.
     */
    public static function verify( $event, ?int $kind = null, ?array $authors = null ): bool {
        $event = self::to_array( $event );

        if ( null === $event ) {
            return false;
        }

        foreach ( [ 'id', 'pubkey', 'sig', 'content' ] as $field ) {
            if ( ! is_string( $event[ $field ] ?? null ) ) {
                return false;
            }
        }

        if ( ! is_int( $event['created_at'] ?? null ) || ! is_int( $event['kind'] ?? null ) || ! is_array( $event['tags'] ?? null ) ) {
            return false;
        }

        if ( null !== $kind && $kind !== $event['kind'] ) {
            return false;
        }

        $pubkey = strtolower( $event['pubkey'] );

        if ( ! Keys::is_hex( $pubkey ) || ! Keys::is_hex( strtolower( $event['id'] ) ) || ! Keys::is_signature( strtolower( $event['sig'] ) ) ) {
            return false;
        }

        if ( null !== $authors && ! in_array( $pubkey, array_map( 'strtolower', array_map( 'strval', $authors ) ), true ) ) {
            return false;
        }

        if ( ! class_exists( '\swentel\nostr\Event\Event' ) ) {
            return false;
        }

        try {
            return (bool) ( new \swentel\nostr\Event\Event() )->verify( (object) $event );
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    /**
     * Sign an event. Returns the complete event as an array.
     *
     * @param string   $privkey    Hex or nsec.
     * @param int|null $created_at Defaults to now.
     * @throws \RuntimeException When the key is unusable or the library is missing.
     */
    public static function sign( int $kind, string $content, array $tags, string $privkey, ?int $created_at = null ): array {
        $hex = Keys::nsec_to_hex( $privkey );

        if ( '' === $hex ) {
            throw new \RuntimeException( 'Not a private key.' );
        }

        if ( ! class_exists( '\swentel\nostr\Sign\Sign' ) ) {
            throw new \RuntimeException( 'Nostr library missing.' );
        }

        $event = new \swentel\nostr\Event\Event();
        $event->setKind( $kind );
        $event->setCreatedAt( $created_at ?? time() );
        $event->setContent( $content );
        $event->setTags( array_values( $tags ) );

        ( new \swentel\nostr\Sign\Sign() )->signEvent( $event, $hex );

        return $event->toArray();
    }

    /** The NIP-01 id of an unsigned event array (pubkey, created_at, kind, tags, content). */
    public static function id( array $event ): string {
        return hash( 'sha256', (string) wp_json_encode(
            [ 0, strtolower( (string) ( $event['pubkey'] ?? '' ) ), (int) ( $event['created_at'] ?? 0 ), (int) ( $event['kind'] ?? 0 ), (array) ( $event['tags'] ?? [] ), (string) ( $event['content'] ?? '' ) ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) );
    }

    /**
     * The library's object for a signed event array — for the transport,
     * which still speaks EventInterface. Verify first; this does not.
     */
    public static function to_object( array $event ): \swentel\nostr\Event\Event {
        return ( new \swentel\nostr\Event\Event() )->populate( (object) $event );
    }

    /** First value of the first tag with this name, or ''. */
    public static function tag( array $event, string $name ): string {
        foreach ( (array) ( $event['tags'] ?? [] ) as $tag ) {
            if ( is_array( $tag ) && $name === ( $tag[0] ?? '' ) ) {
                return (string) ( $tag[1] ?? '' );
            }
        }

        return '';
    }

    /**
     * Every first value of the tags with this name. With $hex_only, only
     * values that are keys, lowercased — the usual case for p and e tags.
     *
     * @return string[]
     */
    public static function tag_values( array $event, string $name, bool $hex_only = false ): array {
        $out = [];

        foreach ( (array) ( $event['tags'] ?? [] ) as $tag ) {
            if ( ! is_array( $tag ) || $name !== ( $tag[0] ?? '' ) || ! is_string( $tag[1] ?? null ) ) {
                continue;
            }

            $value = $hex_only ? strtolower( $tag[1] ) : $tag[1];

            if ( $hex_only && ! Keys::is_hex( $value ) ) {
                continue;
            }

            $out[] = $value;
        }

        return $out;
    }

    /**
     * An event as it goes into user meta or an option. The meta API strips
     * slashes from what it is given, which turns a "ü" escaped as ü
     * into u00fc and breaks the id; hence unescaped output, slashed once
     * for the API.
     */
    public static function encode_for_meta( array $event ): string {
        return wp_slash( (string) wp_json_encode( $event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
    }

    /** The stored JSON back to an array, or null. */
    public static function decode( $raw ): ?array {
        if ( ! is_string( $raw ) || '' === $raw ) {
            return null;
        }

        $event = json_decode( $raw, true );

        return is_array( $event ) ? $event : null;
    }

    /** @return array|null */
    private static function to_array( $event ): ?array {
        if ( is_string( $event ) ) {
            $event = json_decode( $event, true );
        } elseif ( is_object( $event ) ) {
            $event = json_decode( (string) wp_json_encode( $event ), true );
        }

        return is_array( $event ) ? $event : null;
    }
}
