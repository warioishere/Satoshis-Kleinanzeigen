<?php
/**
 *  SK Dahsbaord Template
 *
 *  SK Dahsboard Review Widget Template
 *
 *
 */
?>

<div class="dashboard-widget reviews">
    <div class="widget-title"><i class="fas fa-comments"></i> <?php _e( 'Reviews', 'sk' ); ?></div>

    <ul class="list-unstyled list-count">
        <li>
            <a href="<?php echo $reviews_url; ?>">
                <span class="title"><?php _e( 'All', 'sk' ); ?></span> <span class="count"><?php echo $comment_counts->total; ?></span>
            </a>
        </li>
        <li>
            <a href="<?php echo add_query_arg( array( 'comment_status' => 'hold' ), $reviews_url ); ?>">
                <span class="title"><?php _e( 'Pending', 'sk' ); ?></span> <span class="count"><?php echo $comment_counts->moderated; ?></span>
            </a>
        </li>
        <li>
            <a href="<?php echo add_query_arg( array( 'comment_status' => 'spam' ), $reviews_url ); ?>">
                <span class="title"><?php _e( 'Spam', 'sk' ); ?></span> <span class="count"><?php echo $comment_counts->spam; ?></span>
            </a>
        </li>
        <li>
            <a href="<?php echo add_query_arg( array( 'comment_status' => 'trash' ), $reviews_url ); ?>">
                <span class="title"><?php _e( 'Trash', 'sk' ); ?></span> <span class="count"><?php echo $comment_counts->trash; ?></span>
            </a>
        </li>
    </ul>
</div> <!-- .reviews -->

