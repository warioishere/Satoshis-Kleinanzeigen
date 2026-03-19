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
        <?php if ( $pending ) : ?>
            <span class="sk-review-count pending"><?php echo intval( $pending ); ?></span>
        <?php endif; ?>
    </a>
    <a href="<?php echo esc_url( add_query_arg( [ 'comment_status' => 'spam' ], $url ) ); ?>" class="sk-review-filter-tab<?php echo $status_class === 'spam' ? ' active' : ''; ?>">
        <?php esc_html_e( 'Spam', 'sk' ); ?>
        <?php if ( $spam ) : ?>
            <span class="sk-review-count spam"><?php echo intval( $spam ); ?></span>
        <?php endif; ?>
    </a>
    <a href="<?php echo esc_url( add_query_arg( [ 'comment_status' => 'trash' ], $url ) ); ?>" class="sk-review-filter-tab<?php echo $status_class === 'trash' ? ' active' : ''; ?>">
        <?php esc_html_e( 'Papierkorb', 'sk' ); ?>
        <?php if ( $trash ) : ?>
            <span class="sk-review-count trash"><?php echo intval( $trash ); ?></span>
        <?php endif; ?>
    </a>
</div>
