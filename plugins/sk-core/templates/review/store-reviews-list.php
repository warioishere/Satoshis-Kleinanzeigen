<?php
/**
 * Reviews about the shop, with the vendor's reply.
 *
 * There is no delete button on purpose: a shop that can remove what it
 * does not like turns the rating into decoration.
 *
 * @var \WP_Post[] $reviews
 * @var string     $notice
 */

defined( 'ABSPATH' ) || exit;

use SK\Core\Dashboard\Modules\FeedbackTabs;
use SK\Core\StoreReviews;
?>
<?php if ( '' !== $notice ) : ?>
    <div class="sk-alert sk-alert-success"><?php echo esc_html( $notice ); ?></div>
<?php endif; ?>

<?php if ( empty( $reviews ) ) : ?>
    <div class="sk-settings-form">
        <div class="sk-settings-section">
            <div class="sk-settings-field">
                <div class="sk-settings-input">
                    <p><?php esc_html_e( 'Noch keine Bewertungen über deinen Shop.', 'sk-core' ); ?></p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php foreach ( $reviews as $sk_review ) :
    $sk_id     = (int) $sk_review->ID;
    $sk_reply  = StoreReviews::reply_of( $sk_id );
    $sk_stars  = StoreReviews::rating( $sk_id );
    $sk_author = get_userdata( (int) $sk_review->post_author );
    ?>
    <form method="post" action="<?php echo esc_url( FeedbackTabs::url( FeedbackTabs::SHOP ) ); ?>" class="sk-settings-form sk-question">
        <?php wp_nonce_field( FeedbackTabs::NONCE, 'sk_sr_nonce' ); ?>
        <input type="hidden" name="sk_sr_id" value="<?php echo $sk_id; ?>">

        <div class="sk-settings-section">
            <div class="sk-settings-section-title">
                <span class="sk-stars" aria-label="<?php echo esc_attr( sprintf( __( '%d von 5', 'sk-core' ), $sk_stars ) ); ?>">
                    <?php for ( $sk_i = 1; $sk_i <= 5; $sk_i++ ) : ?>
                        <i class="fas fa-star<?php echo $sk_i <= $sk_stars ? '' : ' is-empty'; ?>"></i>
                    <?php endfor; ?>
                </span>
                <?php if ( '' === $sk_reply ) : ?>
                    <span class="sk-question__flag"><?php esc_html_e( 'offen', 'sk-core' ); ?></span>
                <?php endif; ?>
            </div>

            <div class="sk-settings-field">
                <label class="sk-settings-label">
                    <?php echo esc_html( $sk_author ? $sk_author->display_name : __( 'Unbekannt', 'sk-core' ) ); ?>
                    <span class="sk-question__when"><?php echo esc_html( get_the_date( 'd.m.Y', $sk_review ) ); ?></span>
                </label>
                <div class="sk-settings-input">
                    <p class="sk-question__text"><?php echo esc_html( $sk_review->post_content ); ?></p>
                </div>
            </div>

            <div class="sk-settings-field">
                <label class="sk-settings-label" for="sk-sr-<?php echo $sk_id; ?>"><?php esc_html_e( 'Deine Antwort', 'sk-core' ); ?></label>
                <div class="sk-settings-input">
                    <textarea name="sk_sr_text" id="sk-sr-<?php echo $sk_id; ?>" rows="3" class="sk-form-control"
                              maxlength="<?php echo (int) StoreReviews::MAX_LENGTH; ?>"
                              placeholder="<?php esc_attr_e( 'Sachlich antworten wirkt besser als jede Bewertung — auch bei den Mitlesenden.', 'sk-core' ); ?>"><?php echo esc_textarea( $sk_reply ); ?></textarea>

                    <div class="sk-question__actions">
                        <button type="submit" class="sk-btn sk-btn-theme">
                            <?php echo '' !== $sk_reply ? esc_html__( 'Antwort ändern', 'sk-core' ) : esc_html__( 'Antworten', 'sk-core' ); ?>
                        </button>
                    </div>

                    <p class="sk-question__hint">
                        <?php esc_html_e( 'Deine Antwort steht öffentlich unter der Bewertung. Bewertungen selbst kannst du nicht löschen.', 'sk-core' ); ?>
                    </p>
                </div>
            </div>
        </div>
    </form>
<?php endforeach; ?>
