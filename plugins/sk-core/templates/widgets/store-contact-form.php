<?php
/**
 * SK Store Contact Form widget Template
 *
 *
 */
?>

<form id="sk-form-contact-seller" action="" method="post" class="seller-form clearfix">
    <div class="ajax-response"></div>
    <ul>
        <li class="sk-form-group">
            <input type="text" name="name" value="<?php echo esc_attr( $username ); ?>" placeholder="<?php esc_attr_e( 'Your Name', 'sk-core' ); ?>" class="sk-form-control" minlength="5" required="required">
        </li>
        <li class="sk-form-group">
            <input type="email" name="email" value="<?php echo esc_attr( $email ); ?>" placeholder="<?php esc_attr_e( 'you@example.com', 'sk-core' ); ?>" class="sk-form-control" required="required">
        </li>
        <li class="sk-form-group">
            <textarea name="message" maxlength="1000" cols="25" rows="6" value="" placeholder="<?php esc_attr_e( 'Type your message...', 'sk-core' ); ?>" class="sk-form-control" required="required"></textarea>
        </li>
    </ul>

    <?php do_action( 'sk_contact_form', $seller_id ); ?>

    <?php wp_nonce_field( 'sk_contact_seller', 'sk_contact_seller_nonce' ); ?>
    <input type="hidden" name="seller_id" value="<?php echo esc_html( $seller_id ); ?>">
    <input type="hidden" name="action" value="sk_contact_seller">
    <input type="submit" name="store_message_send" value="<?php esc_attr_e( 'Send Message', 'sk-core' ); ?>" class="sk-right sk-btn sk-btn-theme">
</form>
