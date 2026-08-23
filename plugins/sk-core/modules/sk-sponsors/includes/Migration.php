<?php

namespace SK\Modules\Sponsors;

defined( 'ABSPATH' ) || exit;

/**
 * Einmaliger Import der Bestandssponsoren.
 *
 * Bis August 2026 lagen Sponsoren als Blog-Beiträge; die Ziel-URL kam vom
 * Plugin wp-post-image-carousel (_wppic_image_link), die Reihenfolge von
 * wp-post-rank (_post_rank). Der Import übernimmt Titel, Text, Logo, Ziel
 * und Reihenfolge, damit niemand die Einträge abtippen muss.
 *
 * Ausgewählt wird über die Ziel-URL, nicht über die Kategorie "sponsoren":
 * Die früheren Kadence-Blöcke haben gar nicht nach Kategorie gefiltert,
 * weshalb mindestens ein Sponsor ("Clavastack") unter "Allgemein" liegt und
 * trotzdem auf der Startseite steht. Wer nach Kategorie auswählt, verliert ihn.
 *
 * Die Quellbeiträge bleiben unangetastet — der Import ist wiederholbar und
 * legt nichts doppelt an (_sk_sponsor_legacy_post_id).
 */
final class Migration {

    const LEGACY_CATEGORY  = 'sponsoren';
    const LEGACY_URL_META  = '_wppic_image_link';
    const LEGACY_RANK_META = '_post_rank';

    /** Bisher zeigte die Startseite die besten drei als "Top Sponsors". */
    const TOP_COUNT = 3;

    /**
     * @return array{created:int,skipped:int,missing_url:int}
     */
    public static function run(): array {
        $result = [ 'created' => 0, 'skipped' => 0, 'missing_url' => 0 ];

        $legacy = self::legacy_posts();

        if ( empty( $legacy ) ) {
            return $result;
        }

        // Reihenfolge einmal vorab bestimmen, damit die Top-Stufe genau die
        // drei Einträge trifft, die auch bisher oben standen.
        $ranked = [];
        foreach ( $legacy as $post ) {
            $ranked[ $post->ID ] = (int) get_post_meta( $post->ID, self::LEGACY_RANK_META, true );
        }
        arsort( $ranked );
        $top_ids = array_slice( array_keys( $ranked ), 0, self::TOP_COUNT );

        foreach ( $legacy as $post ) {
            if ( self::already_imported( (int) $post->ID ) ) {
                $result['skipped']++;
                continue;
            }

            $url = (string) get_post_meta( $post->ID, self::LEGACY_URL_META, true );
            if ( $url === '' ) {
                // Ohne Ziel gibt es nichts zu verlinken und nichts zu zählen.
                $result['missing_url']++;
                continue;
            }

            $new_id = wp_insert_post(
                [
                    'post_type'    => PostType::POST_TYPE,
                    'post_status'  => $post->post_status === 'publish' ? 'publish' : 'draft',
                    'post_title'   => $post->post_title,
                    'post_content' => $post->post_content,
                    'post_name'    => $post->post_name,
                ],
                true
            );

            if ( is_wp_error( $new_id ) || ! $new_id ) {
                continue;
            }

            update_post_meta( $new_id, PostType::META_URL, esc_url_raw( $url ) );
            // Die alte Rangzahl wird zum Tiebreaker, nicht zum Preis: Solange
            // niemand zahlt, sind alle Monatsraten 0 und die Startseite behaelt
            // exakt ihre bisherige Reihenfolge.
            update_post_meta( $new_id, PostType::META_SORT_HINT, (int) $ranked[ $post->ID ] );
            update_post_meta( $new_id, PostType::META_MONTHLY, 0 );
            update_post_meta( $new_id, PostType::META_BALANCE, 0 );
            update_post_meta(
                $new_id,
                PostType::META_TIER,
                in_array( $post->ID, $top_ids, true ) ? PostType::TIER_TOP : PostType::TIER_STANDARD
            );
            update_post_meta( $new_id, PostType::META_STARTS, '' );
            update_post_meta( $new_id, PostType::META_EXPIRES, '' );
            update_post_meta( $new_id, PostType::META_LEGACY, (int) $post->ID );

            $thumb_id = (int) get_post_thumbnail_id( $post->ID );
            if ( $thumb_id ) {
                set_post_thumbnail( $new_id, $thumb_id );
            }

            $result['created']++;
        }

        return $result;
    }

    /**
     * Alle Altbeiträge, die als Sponsorenkachel gerendert wurden.
     *
     * @return \WP_Post[]
     */
    public static function legacy_posts(): array {
        return get_posts(
            [
                'post_type'      => 'post',
                'post_status'    => [ 'publish', 'draft' ],
                'posts_per_page' => -1,
                'orderby'        => 'date',
                'order'          => 'ASC',
                'meta_query'     => [
                    [
                        'key'     => self::LEGACY_URL_META,
                        'value'   => '',
                        'compare' => '!=',
                    ],
                ],
            ]
        );
    }

    private static function already_imported( int $legacy_id ): bool {
        $existing = get_posts(
            [
                'post_type'      => PostType::POST_TYPE,
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_key'       => PostType::META_LEGACY,
                'meta_value'     => $legacy_id,
                'no_found_rows'  => true,
            ]
        );

        return ! empty( $existing );
    }
}
