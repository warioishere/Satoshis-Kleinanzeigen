<?php
/**
 * SK Error template
 *
 *
 */
?>
<div class="sk-alert sk-alert-danger">
    <?php if ( $deleted ) : ?>
        <button type="button" class="sk-close" data-dismiss="alert">&times;</button>
    <?php endif ?>
    <strong><?php echo wp_kses_post( $message ); ?></strong>
</div>
