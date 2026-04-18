<?php
/**
 * Review Status Filter Template
 *
 */
?>
<div class="sk-review-status-filter">
    <a href="<?php echo esc_url( $url ); ?>" class="sk-review-filter-tab<?php echo $status_class === 'approved' ? ' active' : ''; ?>">
        <?php esc_html_e( 'Genehmigt', 'sk' ); ?>
        <span class="sk-review-count"><?php echo intval( $approved ); ?></span>
    </a>
    <a href="<?php echo esc_url( add_query_arg( [ 'comment_status' => 'hold' ], $url ) ); ?>" class="sk-review-filter-tab<?php echo $status_class === 'hold' ? ' active' : ''; ?>">
        <?php esc_html_e( 'Ausstehend', 'sk' ); ?>
        <span class="sk-review-count pending<?php echo $pending ? '' : ' is-zero'; ?>"><?php echo intval( $pending ); ?></span>
    </a>
    <a href="<?php echo esc_url( add_query_arg( [ 'comment_status' => 'spam' ], $url ) ); ?>" class="sk-review-filter-tab<?php echo $status_class === 'spam' ? ' active' : ''; ?>">
        <?php esc_html_e( 'Spam', 'sk' ); ?>
        <span class="sk-review-count spam<?php echo $spam ? '' : ' is-zero'; ?>"><?php echo intval( $spam ); ?></span>
    </a>
    <a href="<?php echo esc_url( add_query_arg( [ 'comment_status' => 'trash' ], $url ) ); ?>" class="sk-review-filter-tab<?php echo $status_class === 'trash' ? ' active' : ''; ?>">
        <?php esc_html_e( 'Papierkorb', 'sk' ); ?>
        <span class="sk-review-count trash<?php echo $trash ? '' : ' is-zero'; ?>"><?php echo intval( $trash ); ?></span>
    </a>
</div>
