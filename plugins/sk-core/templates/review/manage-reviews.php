<?php

defined( 'ABSPATH' ) || exit;

/**
 * Manage Reviews Template.
 *
 */
?>

<form id="sk_comments-form" action="" method="post">
    <div class="sk-reviews-bulk-bar">
        <label class="sk-reviews-check-all-wrap">
            <input class="sk-check-all" type="checkbox">
            <span><?php esc_html_e( 'Alle auswählen', 'sk-core' ); ?></span>
        </label>
        <div class="sk-reviews-bulk-actions">
            <select name="comment_status" class="sk-reviews-bulk-select">
                <option value="none"><?php esc_html_e( 'Massenaktionen', 'sk-core' ); ?></option>
                <?php if ( $comment_status === 'hold' ) : ?>
                    <option value="approve"><?php esc_html_e( 'Genehmigen', 'sk-core' ); ?></option>
                    <option value="spam"><?php esc_html_e( 'Als Spam markieren', 'sk-core' ); ?></option>
                    <option value="trash"><?php esc_html_e( 'In Papierkorb', 'sk-core' ); ?></option>
                    <option value="delete"><?php esc_html_e( 'Dauerhaft löschen', 'sk-core' ); ?></option>
                <?php elseif ( $comment_status === 'spam' ) : ?>
                    <option value="approve"><?php esc_html_e( 'Kein Spam', 'sk-core' ); ?></option>
                    <option value="delete"><?php esc_html_e( 'Dauerhaft löschen', 'sk-core' ); ?></option>
                <?php elseif ( $comment_status === 'trash' ) : ?>
                    <option value="approve"><?php esc_html_e( 'Wiederherstellen', 'sk-core' ); ?></option>
                    <option value="delete"><?php esc_html_e( 'Dauerhaft löschen', 'sk-core' ); ?></option>
                <?php else : ?>
                    <option value="hold"><?php esc_html_e( 'Ausstehend markieren', 'sk-core' ); ?></option>
                    <option value="spam"><?php esc_html_e( 'Als Spam markieren', 'sk-core' ); ?></option>
                    <option value="trash"><?php esc_html_e( 'In Papierkorb', 'sk-core' ); ?></option>
                    <option value="delete"><?php esc_html_e( 'Dauerhaft löschen', 'sk-core' ); ?></option>
                <?php endif; ?>
            </select>
            <?php wp_nonce_field( 'sk_comment_nonce_action', 'sk_comment_nonce' ); ?>
            <button type="submit" class="sk-btn sk-btn-sm" name="comt_stat_sub"><?php esc_html_e( 'Anwenden', 'sk-core' ); ?></button>
        </div>
    </div>
