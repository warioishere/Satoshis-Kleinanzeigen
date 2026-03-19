<?php

$seller_id = ( isset( $_POST['store_id'] ) ) ? $_POST['store_id'] : 0;
$store_info = sk_get_store_info( $seller_id );

$current_user      = wp_get_current_user();

$rtl = is_rtl() ? 'true' : 'false';
$rating = isset( $post->ID ) ? get_post_meta( $post->ID, 'rating', true) : 1;
?>

<div class="sk-add-review-wrapper">
    <strong><?php printf( __( 'Hi, %s', 'sk' ), $current_user->display_name ) ?></strong>

<div class="sk-seller-rating-intro-text">
    <?php printf( __( "Share your Experience with <a href='%s' target='_blank'>%s</a>", 'sk' ), sk_get_store_url( $seller_id ), $store_info['store_name'] ) ?>
</div>
    <form class="sk-form-container" id="sk-add-review-form" data-rtl="<?php echo $rtl;?>" data-rating="<?php echo $rating;?>">
        <div id="sk-seller-rating"></div>
            <div class="sk-form-group">
                <label class="sk-form-label" for="sk-review-title"><?php _e( 'Title :', 'sk' ) ?></label>
                <input required class="sk-form-control" type="text" name='sk-review-title' id='sk-review-title'/>
            </div>

            <div class="sk-form-group">
                <label class="sk-form-label" for="sk-review-details"><?php _e( 'Your Review :', 'sk' ) ?></label>
                <textarea required class="sk-form-control" name='sk-review-details' rows="5" id='sk-review-details'></textarea>
            </div>
            <input type="hidden" name='store_id' value="<?php echo $seller_id; ?>" />

            <?php wp_nonce_field( 'sk-seller-rating-form-action', 'sk-seller-rating-form-nonce' ); ?>
            <div class="sk-form-group">
                <input id='support-submit-btn' type="submit" value="<?php _e( 'Submit', 'sk' ) ?>" class="sk-w5 sk-btn sk-btn-theme"/>
            </div>
        </form>
</div>
<div class="sk-clearfix"></div>
