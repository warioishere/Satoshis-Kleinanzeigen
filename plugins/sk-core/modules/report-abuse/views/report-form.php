<form class="sk-form-container" id="<?php echo esc_attr( $id ); ?>">
    <div><?php echo $text; ?></div>

    <fieldset>
        <ul class="<?php echo $option_list_classes; ?>">
            <?php foreach( $abuse_reasons as $abuse_reason ): ?>
                <li>
                    <label class="<?php echo $option_label_classes; ?>">
                        <input required type="radio" name="reason" value="<?php echo esc_attr( $abuse_reason['value'] ); ?>"> <?php echo esc_html( $abuse_reason['value'] ); ?>
                    </label>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ( ! is_user_logged_in() && 'off' === $reported_by_logged_in_users_only ): ?>
            <div class="sk-form-group">
                <label><?php esc_html_e( 'Your Name', 'sk' ); ?></label>
                <input type="text" required class="sk-form-control" name="customer_name">
            </div>

            <div class="sk-form-group">
                <label><?php esc_html_e( 'Your Email', 'sk' ); ?></label>
                <input type="email" required class="sk-form-control" name="customer_email">
            </div>
        <?php endif; ?>

        <div class="sk-form-group">
            <label><?php esc_html_e( 'Description', 'sk' ); ?></label>
            <textarea name="description" class="sk-form-control" rows="4"></textarea>
        </div>

        <div class="sk-form-group">
            <p class="sk-popup-error"></p>

            <button type="submit" class="sk-w4 sk-btn sk-btn-theme" id="sk-report-abuse-form-submit-btn">
                <?php esc_html_e( 'Report Abuse', 'sk' ); ?>
            </button>

            <button type="button" class="sk-w4 sk-btn sk-btn-theme sk-hide" id="sk-report-abuse-form-working-btn">
                <i class="fas fa-sync-alt fa-spin"></i>&nbsp;&nbsp;<?php esc_html_e( 'Reporting', 'sk' ); ?>...
            </button>
        </div>
    </fieldset>
</form>
<div class="sk-clearfix"></div>
