<?php
/**
 * SK Message Template
 *
 *
 */
?>
<div class="sk-message">
    <button type="button" class="sk-close" data-dismiss="alert">&times;</button>
    <strong><?php echo wp_kses_post( $message ); ?></strong>
</div>
