<?php wp_enqueue_style( 'sk-report-abuse-button', SK_REPORT_ABUSE_ASSETS . '/css/report-button.css', [], SK_CORE_VERSION ); ?>
<a href="#report-abuse" class="sk-report-abuse-button">
    <i class="fas fa-flag"></i> <?php echo esc_html( $label ); ?>
</a>
