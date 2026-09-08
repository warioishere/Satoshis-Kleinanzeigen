<?php

namespace SK\Core\Nostr;

defined( 'ABSPATH' ) || exit;

/**
 * NIP-56 report types, and the ones that matter on a classifieds site.
 *
 * The same four types decide what a buyer's browser counts, what the
 * operator's cron keeps and how the admin page labels them; they used to
 * be listed in each of those places.
 */
final class ReportTypes {

    /** Everything NIP-56 defines. */
    const ALL = [ 'nudity', 'malware', 'profanity', 'illegal', 'spam', 'impersonation', 'other' ];

    /** The types a report against a vendor is counted for here. */
    const COUNTED = [ 'spam', 'impersonation', 'illegal', 'malware' ];

    public static function is_counted( string $type ): bool {
        return in_array( strtolower( $type ), self::COUNTED, true );
    }

    /** @return array<string, string> type => label, for the counted types. */
    public static function labels(): array {
        return [
            'spam'          => __( 'Spam', 'sk-core' ),
            'impersonation' => __( 'Identitätsmissbrauch', 'sk-core' ),
            'illegal'       => __( 'Illegal', 'sk-core' ),
            'malware'       => __( 'Schadsoftware', 'sk-core' ),
        ];
    }

    /** The label of a type, or the type itself when it has none. */
    public static function label( string $type ): string {
        return self::labels()[ strtolower( $type ) ] ?? $type;
    }
}
