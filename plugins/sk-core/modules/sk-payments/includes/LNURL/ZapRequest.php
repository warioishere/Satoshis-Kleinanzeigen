<?php

namespace SK\Modules\Payments\LNURL;

defined( 'ABSPATH' ) || exit;

/**
 * Validation for NIP-57 zap requests (kind 9734).
 *
 * The marketplace signs the resulting zap receipt with its own key, so a zap
 * request that has not been proven authentic must never reach it: otherwise
 * anyone could have us attest that some pubkey zapped some event.
 */
class ZapRequest {

    /** A zap request is a small event. Anything larger is not one. */
    const MAX_LENGTH = 8192;

    const KIND = 9734;

    /**
     * @param string $raw               Raw JSON as the wallet sent it.
     * @param string $recipient_pubkey  The vendor's Nostr pubkey, hex.
     * @param int    $amount_msats      Amount the invoice will be made out to.
     *
     * @return string The unmodified JSON when the request checks out, '' otherwise.
     *                The string has to stay byte-identical — the zapper signed
     *                exactly these bytes and the receipt has to carry them.
     */
    public static function validate( string $raw, string $recipient_pubkey, int $amount_msats ): string {
        if ( $raw === '' || strlen( $raw ) > self::MAX_LENGTH ) {
            return '';
        }

        $event = json_decode( $raw, true );

        if ( ! is_array( $event ) ) {
            return '';
        }

        foreach ( [ 'id', 'pubkey', 'sig', 'created_at', 'kind', 'tags', 'content' ] as $field ) {
            if ( ! array_key_exists( $field, $event ) ) {
                return '';
            }
        }

        if ( (int) $event['kind'] !== self::KIND ) {
            return '';
        }

        if ( ! is_array( $event['tags'] ) || ! is_string( $event['content'] ) ) {
            return '';
        }

        if ( ! self::is_hex( $event['id'], 64 )
            || ! self::is_hex( $event['pubkey'], 64 )
            || ! self::is_hex( $event['sig'], 128 ) ) {
            return '';
        }

        // The id has to be the hash of the canonical serialisation, otherwise
        // the signature below would authenticate different content than the
        // receipt ends up carrying.
        if ( ! hash_equals( self::event_id( $event ), strtolower( (string) $event['id'] ) ) ) {
            return '';
        }

        if ( ! self::signature_valid( (string) $event['pubkey'], (string) $event['sig'], (string) $event['id'] ) ) {
            return '';
        }

        // NIP-57: exactly one recipient, and it must be the vendor being paid.
        $recipients = self::tag_values( $event['tags'], 'p' );

        if ( count( $recipients ) !== 1 ) {
            return '';
        }

        if ( $recipient_pubkey !== '' && ! hash_equals( strtolower( $recipient_pubkey ), strtolower( $recipients[0] ) ) ) {
            return '';
        }

        // NIP-57: at most one event may be zapped.
        if ( count( self::tag_values( $event['tags'], 'e' ) ) > 1 ) {
            return '';
        }

        // An amount tag must agree with the invoice, or the receipt would
        // claim a payment that never happened at that size.
        $amounts = self::tag_values( $event['tags'], 'amount' );

        if ( $amounts && (int) $amounts[0] !== $amount_msats ) {
            return '';
        }

        return $raw;
    }

    private static function is_hex( $value, int $length ): bool {
        return is_string( $value ) && (bool) preg_match( '/^[0-9a-fA-F]{' . $length . '}$/', $value );
    }

    /**
     * NIP-01 event id: sha256 over [0, pubkey, created_at, kind, tags, content]
     * with no whitespace, slashes and unicode left alone.
     */
    private static function event_id( array $event ): string {
        $serialised = wp_json_encode(
            [
                0,
                (string) $event['pubkey'],
                (int) $event['created_at'],
                (int) $event['kind'],
                $event['tags'],
                (string) $event['content'],
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return hash( 'sha256', (string) $serialised );
    }

    private static function signature_valid( string $pubkey, string $sig, string $id ): bool {
        if ( ! class_exists( '\\Mdanter\\Ecc\\Crypto\\Signature\\SchnorrSigner' ) ) {
            // Without a way to check the signature we cannot accept the request.
            return false;
        }

        try {
            $signer = new \Mdanter\Ecc\Crypto\Signature\SchnorrSigner();

            return $signer->verify( strtolower( $pubkey ), strtolower( $sig ), strtolower( $id ) );
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    /**
     * All values of a given tag name.
     */
    private static function tag_values( array $tags, string $name ): array {
        $values = [];

        foreach ( $tags as $tag ) {
            if ( is_array( $tag ) && ( $tag[0] ?? '' ) === $name && isset( $tag[1] ) && is_string( $tag[1] ) ) {
                $values[] = $tag[1];
            }
        }

        return $values;
    }
}
