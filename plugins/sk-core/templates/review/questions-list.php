<?php
/**
 * Questions about the vendor's listings, inside the feedback page.
 *
 * @var string  $status   open|answered|all
 * @var array[] $rows     Each ['question' => WP_Comment, 'answer' => ?WP_Comment]
 * @var int     $open
 * @var int     $answered
 * @var string  $notice
 */

defined( 'ABSPATH' ) || exit;

use SK\Core\Dashboard\Modules\FeedbackTabs;
use SK\Core\Questions;

$sk_url    = FeedbackTabs::url( FeedbackTabs::ASKED );
$sk_states = [
    'open'     => sprintf( __( 'Offen (%d)', 'sk-core' ), $open ),
    'answered' => sprintf( __( 'Beantwortet (%d)', 'sk-core' ), $answered ),
    'all'      => __( 'Alle', 'sk-core' ),
];
?>
<?php if ( '' !== $notice ) : ?>
    <div class="sk-alert sk-alert-success"><?php echo esc_html( $notice ); ?></div>
<?php endif; ?>

<ul class="sk-questions-tabs sk-questions-tabs--states">
    <?php foreach ( $sk_states as $sk_key => $sk_label ) : ?>
        <li<?php echo $sk_key === $status ? ' class="active"' : ''; ?>>
            <a href="<?php echo esc_url( add_query_arg( 'status', $sk_key, $sk_url ) ); ?>"><?php echo esc_html( $sk_label ); ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ( empty( $rows ) ) : ?>
    <div class="sk-settings-form">
        <div class="sk-settings-section">
            <div class="sk-settings-field">
                <div class="sk-settings-input">
                    <p>
                        <?php
                        echo 'open' === $status
                            ? esc_html__( 'Keine offenen Fragen. Sobald jemand etwas zu einem deiner Inserate wissen will, steht es hier.', 'sk-core' )
                            : esc_html__( 'Hier ist noch nichts.', 'sk-core' );
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php foreach ( $rows as $sk_row ) :
    $sk_q       = $sk_row['question'];
    $sk_a       = $sk_row['answer'];
    $sk_product = (int) $sk_q->comment_post_ID;
    ?>
    <form method="post" action="<?php echo esc_url( $sk_url ); ?>" class="sk-settings-form sk-question">
        <?php wp_nonce_field( FeedbackTabs::NONCE_Q, 'sk_q_nonce' ); ?>
        <input type="hidden" name="sk_q_id" value="<?php echo (int) $sk_q->comment_ID; ?>">
        <input type="hidden" name="sk_q_status" value="<?php echo esc_attr( $status ); ?>">

        <div class="sk-settings-section">
            <div class="sk-settings-section-title">
                <a href="<?php echo esc_url( (string) get_permalink( $sk_product ) ); ?>"><?php echo esc_html( get_the_title( $sk_product ) ); ?></a>
                <?php if ( ! $sk_a ) : ?>
                    <span class="sk-question__flag"><?php esc_html_e( 'offen', 'sk-core' ); ?></span>
                <?php endif; ?>
            </div>

            <div class="sk-settings-field">
                <label class="sk-settings-label">
                    <?php echo esc_html( $sk_q->comment_author ); ?>
                    <span class="sk-question__when"><?php echo esc_html( wp_date( 'd.m.Y H:i', strtotime( $sk_q->comment_date_gmt . ' UTC' ) ) ); ?></span>
                </label>
                <div class="sk-settings-input">
                    <p class="sk-question__text"><?php echo esc_html( $sk_q->comment_content ); ?></p>
                </div>
            </div>

            <div class="sk-settings-field">
                <label class="sk-settings-label" for="sk-q-<?php echo (int) $sk_q->comment_ID; ?>"><?php esc_html_e( 'Deine Antwort', 'sk-core' ); ?></label>
                <div class="sk-settings-input">
                    <textarea name="sk_q_text" id="sk-q-<?php echo (int) $sk_q->comment_ID; ?>" rows="3" class="sk-form-control"
                              maxlength="<?php echo (int) Questions::MAX_LENGTH; ?>"
                              placeholder="<?php esc_attr_e( 'Antwort schreiben — damit wird die Frage öffentlich sichtbar.', 'sk-core' ); ?>"><?php echo esc_textarea( $sk_a ? $sk_a->comment_content : '' ); ?></textarea>

                    <div class="sk-question__actions">
                        <button type="submit" name="sk_q_action" value="answer" class="sk-btn sk-btn-theme">
                            <?php echo $sk_a ? esc_html__( 'Antwort ändern', 'sk-core' ) : esc_html__( 'Antworten', 'sk-core' ); ?>
                        </button>

                        <?php if ( '1' !== (string) $sk_q->comment_approved ) : ?>
                            <button type="submit" name="sk_q_action" value="approve" class="sk-btn sk-btn-default" formnovalidate>
                                <?php esc_html_e( 'Nur veröffentlichen', 'sk-core' ); ?>
                            </button>
                        <?php endif; ?>

                        <button type="submit" name="sk_q_action" value="reject" class="sk-btn sk-btn-default sk-question__reject" formnovalidate
                                onclick="return confirm('<?php echo esc_js( __( 'Diese Frage löschen?', 'sk-core' ) ); ?>');">
                            <?php esc_html_e( 'Löschen', 'sk-core' ); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
<?php endforeach; ?>
