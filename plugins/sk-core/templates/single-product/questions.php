<?php
/**
 * Questions and answers in the listing's tab.
 *
 * @var int     $product_id
 * @var array[] $rows    Each ['question' => WP_Comment, 'answer' => ?WP_Comment]
 * @var bool    $may_ask
 * @var string  $notice
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="sk-product-questions">

    <?php if ( '' !== $notice ) : ?>
        <p class="sk-product-questions__notice"><?php echo esc_html( $notice ); ?></p>
    <?php endif; ?>

    <?php if ( empty( $rows ) ) : ?>
        <p class="sk-product-questions__empty"><?php esc_html_e( 'Noch keine Fragen zu diesem Inserat.', 'sk-core' ); ?></p>
    <?php else : ?>
        <ul class="sk-product-questions__list">
            <?php foreach ( $rows as $sk_row ) : ?>
                <li>
                    <div class="sk-product-questions__q">
                        <span class="sk-product-questions__who"><?php echo esc_html( $sk_row['question']->comment_author ); ?></span>
                        <span class="sk-product-questions__when"><?php echo esc_html( wp_date( 'd.m.Y', strtotime( $sk_row['question']->comment_date_gmt . ' UTC' ) ) ); ?></span>
                        <p><?php echo esc_html( $sk_row['question']->comment_content ); ?></p>
                    </div>

                    <?php if ( $sk_row['answer'] ) : ?>
                        <div class="sk-product-questions__a">
                            <span class="sk-product-questions__who"><?php esc_html_e( 'Antwort des Anbieters', 'sk-core' ); ?></span>
                            <p><?php echo esc_html( $sk_row['answer']->comment_content ); ?></p>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ( $may_ask ) : ?>
        <form method="post" class="sk-product-questions__form">
            <?php wp_nonce_field( 'sk_ask', 'sk_ask_nonce' ); ?>
            <input type="hidden" name="sk_ask_product" value="<?php echo (int) $product_id; ?>">

            <label for="sk-ask-text"><?php esc_html_e( 'Etwas fragen', 'sk-core' ); ?></label>
            <textarea name="sk_ask_text" id="sk-ask-text" rows="3" required
                      maxlength="<?php echo (int) \SK\Core\Questions::MAX_LENGTH; ?>"
                      placeholder="<?php esc_attr_e( 'Frag öffentlich — die Antwort hilft auch allen anderen.', 'sk-core' ); ?>"></textarea>

            <p class="sk-product-questions__hint">
                <?php esc_html_e( 'Deine Frage erscheint hier, sobald der Anbieter sie beantwortet oder freigibt. Etwas Persönliches besprichst du besser im Chat.', 'sk-core' ); ?>
            </p>

            <button type="submit" class="sk-btn sk-btn-theme"><?php esc_html_e( 'Frage senden', 'sk-core' ); ?></button>
        </form>
    <?php elseif ( ! is_user_logged_in() ) : ?>
        <p class="sk-product-questions__hint">
            <?php esc_html_e( 'Zum Fragen bitte anmelden.', 'sk-core' ); ?>
        </p>
    <?php endif; ?>
</div>
