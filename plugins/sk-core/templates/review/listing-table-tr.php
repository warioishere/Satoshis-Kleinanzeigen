<?php
/**
 * Review Card Template (formerly listing-table-tr.php)
 *
 */

$can_manage = current_user_can( 'sk_manage_reviews' ) && sk_get_option( 'seller_review_manage', 'sk_selling', 'on' ) === 'on';
$rating     = intval( get_comment_meta( $comment->comment_ID, 'rating', true ) );
?>
<div class="sk-review-card <?php echo esc_attr( $comment_status ); ?>">

    <?php if ( $can_manage ) : ?>
        <div class="sk-review-card__check">
            <input class="sk-check-col" type="checkbox" name="commentid[]" value="<?php echo intval( $comment->comment_ID ); ?>">
        </div>
    <?php endif; ?>

    <div class="sk-review-card__avatar">
        <?php echo $comment_author_img; ?>
    </div>

    <div class="sk-review-card__body">

        <div class="sk-review-card__header">
            <div class="sk-review-card__author-info">
                <strong class="sk-review-card__name"><?php echo esc_html( $comment->comment_author ); ?></strong>
                <?php if ( $comment->comment_author_email ) : ?>
                    <span class="sk-review-card__email"><?php echo esc_html( $comment->comment_author_email ); ?></span>
                <?php endif; ?>
            </div>
            <div class="sk-review-card__meta">
                <?php if ( get_option( 'woocommerce_enable_review_rating' ) === 'yes' && $rating ) : ?>
                    <div class="sk-review-card__stars">
                        <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                            <i class="<?php echo $i <= $rating ? 'fas' : 'far'; ?> fa-star"></i>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
                <span class="sk-review-card__date"><?php echo esc_html( $comment_date ); ?></span>
            </div>
        </div>

        <div class="sk-review-card__content">
            <?php echo wp_kses_post( $comment->comment_content ); ?>
        </div>

        <div class="sk-review-card__footer">
            <a href="<?php echo esc_url( $permalink ); ?>" class="sk-review-card__view-link" target="_blank">
                <i class="fas fa-external-link-alt"></i> <?php esc_html_e( 'Bewertung ansehen', 'sk' ); ?>
            </a>

            <?php if ( $can_manage ) : ?>
                <div class="sk-review-card__actions">
                    <?php if ( $page_status === '0' ) : ?>
                        <a href="#" class="sk-cmt-action sk-review-action approve" data-curr_page="<?php echo esc_attr( $page_status ); ?>" data-post_type="<?php echo esc_attr( $post_type ); ?>" data-page_status="0" data-comment_id="<?php echo intval( $comment->comment_ID ); ?>" data-cmt_status="1"><?php esc_html_e( 'Genehmigen', 'sk' ); ?></a>
                        <a href="#" class="sk-cmt-action sk-review-action spam"    data-curr_page="<?php echo esc_attr( $page_status ); ?>" data-post_type="<?php echo esc_attr( $post_type ); ?>" data-page_status="0" data-comment_id="<?php echo intval( $comment->comment_ID ); ?>" data-cmt_status="spam"><?php esc_html_e( 'Spam', 'sk' ); ?></a>
                        <a href="#" class="sk-cmt-action sk-review-action trash"   data-curr_page="<?php echo esc_attr( $page_status ); ?>" data-post_type="<?php echo esc_attr( $post_type ); ?>" data-page_status="0" data-comment_id="<?php echo intval( $comment->comment_ID ); ?>" data-cmt_status="trash"><?php esc_html_e( 'Papierkorb', 'sk' ); ?></a>

                    <?php elseif ( $page_status === 'spam' ) : ?>
                        <a href="#" class="sk-cmt-action sk-review-action approve" data-curr_page="<?php echo esc_attr( $page_status ); ?>" data-post_type="<?php echo esc_attr( $post_type ); ?>" data-page_status="spam" data-comment_id="<?php echo intval( $comment->comment_ID ); ?>" data-cmt_status="1"><?php esc_html_e( 'Kein Spam', 'sk' ); ?></a>
                        <a href="#" class="sk-cmt-action sk-review-action delete"  data-curr_page="<?php echo esc_attr( $page_status ); ?>" data-post_type="<?php echo esc_attr( $post_type ); ?>" data-page_status="spam" data-comment_id="<?php echo intval( $comment->comment_ID ); ?>" data-cmt_status="delete"><?php esc_html_e( 'Dauerhaft löschen', 'sk' ); ?></a>

                    <?php elseif ( $page_status === 'trash' ) : ?>
                        <a href="#" class="sk-cmt-action sk-review-action approve" data-curr_page="<?php echo esc_attr( $page_status ); ?>" data-post_type="<?php echo esc_attr( $post_type ); ?>" data-page_status="trash" data-comment_id="<?php echo intval( $comment->comment_ID ); ?>" data-cmt_status="1"><?php esc_html_e( 'Wiederherstellen', 'sk' ); ?></a>
                        <a href="#" class="sk-cmt-action sk-review-action delete"  data-curr_page="<?php echo esc_attr( $page_status ); ?>" data-post_type="<?php echo esc_attr( $post_type ); ?>" data-page_status="trash" data-comment_id="<?php echo intval( $comment->comment_ID ); ?>" data-cmt_status="delete"><?php esc_html_e( 'Dauerhaft löschen', 'sk' ); ?></a>

                    <?php else : ?>
                        <?php if ( $comment_status === 'approved' ) : ?>
                            <a href="#" class="sk-cmt-action sk-review-action unapprove" data-curr_page="<?php echo esc_attr( $page_status ); ?>" data-post_type="<?php echo esc_attr( $post_type ); ?>" data-page_status="1" data-comment_id="<?php echo intval( $comment->comment_ID ); ?>" data-cmt_status="0"><?php esc_html_e( 'Ablehnen', 'sk' ); ?></a>
                        <?php else : ?>
                            <a href="#" class="sk-cmt-action sk-review-action approve" data-curr_page="<?php echo esc_attr( $page_status ); ?>" data-post_type="<?php echo esc_attr( $post_type ); ?>" data-page_status="1" data-comment_id="<?php echo intval( $comment->comment_ID ); ?>" data-cmt_status="1"><?php esc_html_e( 'Genehmigen', 'sk' ); ?></a>
                        <?php endif; ?>
                        <a href="#" class="sk-cmt-action sk-review-action spam"  data-curr_page="<?php echo esc_attr( $page_status ); ?>" data-post_type="<?php echo esc_attr( $post_type ); ?>" data-page_status="1" data-comment_id="<?php echo intval( $comment->comment_ID ); ?>" data-cmt_status="spam"><?php esc_html_e( 'Spam', 'sk' ); ?></a>
                        <a href="#" class="sk-cmt-action sk-review-action trash" data-curr_page="<?php echo esc_attr( $page_status ); ?>" data-post_type="<?php echo esc_attr( $post_type ); ?>" data-page_status="1" data-comment_id="<?php echo intval( $comment->comment_ID ); ?>" data-cmt_status="trash"><?php esc_html_e( 'Papierkorb', 'sk' ); ?></a>
                    <?php endif; ?>
                </div>

                <div style="display:none">
                    <div class="sk-cmt-hid-status"><?php echo esc_attr( $comment->comment_approved ); ?></div>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>
