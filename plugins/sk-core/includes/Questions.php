<?php

namespace SK\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Public questions on a listing.
 *
 * Built on WordPress comments rather than its own tables: moderation,
 * spam handling, author linkage and threading already exist there, and
 * they are the parts that would otherwise have to be written twice.
 *
 * A question is a comment of type sk_question, an answer its child. Both
 * carry the comment_approved flag, which is what the vendor moderates —
 * only the vendor, never the operator: it is their listing.
 *
 * Deliberately separate from the review tab. A review rates a completed
 * deal, a question comes before one, and mixing them would put a star
 * rating on "does it ship to Austria?".
 */
final class Questions {

    const TYPE = 'sk_question';

    /** Longest a question or answer may be. */
    const MAX_LENGTH = 1000;

    /** Seconds a member has to wait between two questions. */
    const THROTTLE = 60;

    /**
     * Ask a question. Members only — the open comment box collected
     * hundreds of spam entries and not one real question.
     *
     * @return int|\WP_Error The new comment id.
     */
    public static function ask( int $product_id, int $user_id, string $text ) {
        $text = self::clean( $text );

        if ( ! $user_id ) {
            return new \WP_Error( 'sk_q_login', __( 'Zum Fragen bitte anmelden.', 'sk-core' ) );
        }

        if ( 'product' !== get_post_type( $product_id ) || 'publish' !== get_post_status( $product_id ) ) {
            return new \WP_Error( 'sk_q_product', __( 'Dieses Inserat gibt es nicht.', 'sk-core' ) );
        }

        if ( (int) get_post_field( 'post_author', $product_id ) === $user_id ) {
            return new \WP_Error( 'sk_q_own', __( 'Auf dein eigenes Inserat kannst du nicht fragen.', 'sk-core' ) );
        }

        if ( mb_strlen( $text ) < 5 ) {
            return new \WP_Error( 'sk_q_short', __( 'Bitte stell eine richtige Frage.', 'sk-core' ) );
        }

        if ( self::asked_recently( $user_id ) ) {
            return new \WP_Error( 'sk_q_throttle', __( 'Einen Moment bitte, du hast gerade erst gefragt.', 'sk-core' ) );
        }

        $user = get_userdata( $user_id );

        $id = wp_insert_comment( [
            'comment_post_ID'      => $product_id,
            'comment_content'      => $text,
            'comment_type'         => self::TYPE,
            'user_id'              => $user_id,
            'comment_author'       => $user ? $user->display_name : '',
            'comment_author_email' => $user ? $user->user_email : '',
            'comment_approved'     => 0,
            'comment_parent'       => 0,
        ] );

        if ( ! $id ) {
            return new \WP_Error( 'sk_q_failed', __( 'Die Frage liess sich nicht speichern.', 'sk-core' ) );
        }

        set_transient( 'sk_q_last_' . $user_id, time(), self::THROTTLE );

        return (int) $id;
    }

    /**
     * The vendor answers. Answering publishes both: somebody who takes the
     * trouble to reply has read the question and wants it seen.
     *
     * @return true|\WP_Error
     */
    public static function answer( int $question_id, int $vendor_id, string $text ) {
        $question = self::owned( $question_id, $vendor_id );

        if ( is_wp_error( $question ) ) {
            return $question;
        }

        $text = self::clean( $text );

        if ( '' === $text ) {
            return new \WP_Error( 'sk_q_empty', __( 'Die Antwort ist leer.', 'sk-core' ) );
        }

        $existing = self::answer_of( $question_id );

        if ( $existing ) {
            wp_update_comment( [ 'comment_ID' => $existing->comment_ID, 'comment_content' => $text ] );
        } else {
            $user = get_userdata( $vendor_id );

            wp_insert_comment( [
                'comment_post_ID'      => (int) $question->comment_post_ID,
                'comment_content'      => $text,
                'comment_type'         => self::TYPE,
                'user_id'              => $vendor_id,
                'comment_author'       => $user ? $user->display_name : '',
                'comment_author_email' => $user ? $user->user_email : '',
                'comment_approved'     => 1,
                'comment_parent'       => $question_id,
            ] );
        }

        wp_set_comment_status( $question_id, 'approve' );

        return true;
    }

    /** Publish a question without answering it. */
    public static function approve( int $question_id, int $vendor_id ) {
        $question = self::owned( $question_id, $vendor_id );

        return is_wp_error( $question ) ? $question : wp_set_comment_status( $question_id, 'approve' );
    }

    /** Turn a question down. Deleted, not hidden — nothing to look back at. */
    public static function reject( int $question_id, int $vendor_id ) {
        $question = self::owned( $question_id, $vendor_id );

        if ( is_wp_error( $question ) ) {
            return $question;
        }

        $answer = self::answer_of( $question_id );

        if ( $answer ) {
            wp_delete_comment( (int) $answer->comment_ID, true );
        }

        return wp_delete_comment( $question_id, true );
    }

    /**
     * Questions to show on a listing: published ones, oldest first, each
     * with its answer.
     *
     * @return array<int,array{question:\WP_Comment,answer:?\WP_Comment}>
     */
    public static function for_product( int $product_id ): array {
        $rows = get_comments( [
            'post_id' => $product_id,
            'type'    => self::TYPE,
            'parent'  => 0,
            'status'  => 'approve',
            'orderby' => 'comment_date_gmt',
            'order'   => 'ASC',
        ] );

        $out = [];

        foreach ( $rows as $row ) {
            $out[] = [ 'question' => $row, 'answer' => self::answer_of( (int) $row->comment_ID ) ];
        }

        return $out;
    }

    /**
     * The vendor's questions across all their listings, newest first.
     *
     * @param string $status open|answered|all
     *
     * @return array<int,array{question:\WP_Comment,answer:?\WP_Comment}>
     */
    public static function for_vendor( int $vendor_id, string $status = 'open' ): array {
        $listings = get_posts( [
            'post_type'      => 'product',
            'post_status'    => [ 'publish', 'draft', 'pending' ],
            'author'         => $vendor_id,
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ] );

        if ( empty( $listings ) ) {
            return [];
        }

        $rows = get_comments( [
            'post__in' => array_map( 'intval', $listings ),
            'type'     => self::TYPE,
            'parent'   => 0,
            'status'   => 'all',
            'orderby'  => 'comment_date_gmt',
            'order'    => 'DESC',
        ] );

        $out = [];

        foreach ( $rows as $row ) {
            $answer = self::answer_of( (int) $row->comment_ID );

            if ( 'open' === $status && $answer ) {
                continue;
            }

            if ( 'answered' === $status && ! $answer ) {
                continue;
            }

            $out[] = [ 'question' => $row, 'answer' => $answer ];
        }

        return $out;
    }

    /** How many questions still wait for this vendor. */
    public static function count_open( int $vendor_id ): int {
        return count( self::for_vendor( $vendor_id, 'open' ) );
    }

    public static function answer_of( int $question_id ): ?\WP_Comment {
        $rows = get_comments( [
            'parent'  => $question_id,
            'type'    => self::TYPE,
            'status'  => 'all',
            'number'  => 1,
            'orderby' => 'comment_date_gmt',
            'order'   => 'ASC',
        ] );

        return $rows ? $rows[0] : null;
    }

    /**
     * The question, if it belongs to a listing of this vendor.
     *
     * @return \WP_Comment|\WP_Error
     */
    private static function owned( int $question_id, int $vendor_id ) {
        $question = get_comment( $question_id );

        if ( ! $question || self::TYPE !== $question->comment_type || (int) $question->comment_parent ) {
            return new \WP_Error( 'sk_q_missing', __( 'Diese Frage gibt es nicht.', 'sk-core' ) );
        }

        if ( ! $vendor_id || (int) get_post_field( 'post_author', $question->comment_post_ID ) !== $vendor_id ) {
            return new \WP_Error( 'sk_q_foreign', __( 'Das ist nicht dein Inserat.', 'sk-core' ) );
        }

        return $question;
    }

    private static function asked_recently( int $user_id ): bool {
        return (bool) get_transient( 'sk_q_last_' . $user_id );
    }

    private static function clean( string $text ): string {
        return mb_substr( trim( wp_strip_all_tags( $text ) ), 0, self::MAX_LENGTH );
    }
}
