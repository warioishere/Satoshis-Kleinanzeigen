<?php

defined( 'ABSPATH' ) || exit;

/**
 * Review Listing Template
 *
 */

    /**
     * sk_manage_reviews_form hook
     *
     */
    do_action( 'sk_manage_reviews_form', $comment_status );
?>

    <div class="sk-reviews-list">
        <?php

        /**
         * sk_review_listing_table_body hook
         *
         * @hooked sk_render_listing_table_body
         */
        do_action( 'sk_review_listing_table_body', $post_type );
        ?>
    </div>

</form>
