<?php
/**
 * SK Review Content Template
 *
 *
 */
?>
<div class="sk-comments-wrap">

    <?php

        /**
         * sk_review_content_status_filter hook
         *
         * @hooked sk_review_status_filter
         */
        do_action( 'sk_review_content_status_filter', $post_type, $counts );


        /**
         * sk_review_content_listing hook
         *
         * @hook sk_review_content_listing
         */
        do_action( 'sk_review_content_listing', $post_type, $counts );
    ?>

</div>
