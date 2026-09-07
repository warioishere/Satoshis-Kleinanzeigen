<?php

namespace SK\Modules\Sponsors;

defined( 'ABSPATH' ) || exit;

/**
 * One-time import of existing sponsors.
 *
 * Until August 2026, sponsors were blog posts; the target URL came from the
 * wp-post-image-carousel plugin (_wppic_image_link), the order from
 * wp-post-rank (_post_rank). The import carries over title, text, logo,
 * target, and order, so nobody has to retype the entries.
 *
 * Selection happens via the target URL, not via the "sponsoren" category:
 * the earlier Kadence blocks didn't filter by category at all, which is why
 * at least one sponsor ("Clavastack") sits under "Allgemein" and still shows
 * on the homepage. Selecting by category would lose it.
 *
 * The source posts stay untouched — the import is repeatable and never
 * creates duplicates (_sk_sponsor_legacy_post_id).
 */
final class Migration {

    const LEGACY_CATEGORY  = 'sponsoren';
    const LEGACY_URL_META  = '_wppic_image_link';
    const LEGACY_RANK_META = '_post_rank';

    /** Previously the homepage showed the top three as "Top Sponsors". */
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

        // Determine the order once upfront, so the top tier picks exactly
        // the three entries that were previously shown at the top.
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
                // Without a target there's nothing to link and nothing to count.
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
            // The old rank number becomes the tiebreaker, not the price: as
            // long as nobody pays, all monthly rates are 0 and the homepage
            // keeps exactly its previous order.
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
     * All legacy posts that were rendered as a sponsor tile.
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
