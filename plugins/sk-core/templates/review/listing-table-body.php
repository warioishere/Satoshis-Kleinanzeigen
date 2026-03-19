<?php
/**
 * Review Listing Table Body Template
 *
 */

if ( count( $comments ) === 0 ) {
    ?>
    <div class="sk-reviews-empty">
        <i class="fas fa-star-half-alt"></i>
        <p><?php esc_html_e( 'Keine Bewertungen gefunden.', 'sk' ); ?></p>
    </div>
    <?php
} else {
    foreach ( $comments as $comment ) {
        sk_ext()->review->render_row( $comment, $post_type );
    }
}
