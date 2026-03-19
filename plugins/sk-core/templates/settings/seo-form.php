<?php
/**
 * SK Dashboard Settings SEO Form Template
 *
 *
 */
?>

<div class="sk-alert sk-hide" id="sk-seo-feedback"></div>

<form method="post" id="sk-store-seo-form"  action="" class="sk-form-horizontal">

    <div class="sk-form-group">
        <label class="sk-w3 sk-control-label" for="sk-seo-meta-title"><?php _e( 'SEO Title :', 'sk' ); ?>
            <span class="sk-tooltips-help tips" title="" data-placement="bottom" data-original-title="<?php _e( 'SEO Title is shown as the title of your store page', 'sk' ); ?>">
                <i class="fas fa-question-circle"></i>
            </span>
        </label>
        <div class="sk-w5 sk-text-left">
            <input id="sk-seo-meta-title" value="<?php echo $seo->print_saved_meta( $seo_meta['sk-seo-meta-title'] ) ?>" name="sk-seo-meta-title" placeholder=" " class="sk-form-control input-md" type="text">
        </div>
    </div>

    <div class="sk-form-group">
        <label class="sk-w3 sk-control-label" for="sk-seo-meta-desc"><?php _e( 'Meta Description :', 'sk' ); ?>
            <span class="sk-tooltips-help tips" title="" data-placement="bottom" data-original-title="<?php _e( 'The meta description is often shown as the black text under the title in a search result. For this to work it has to contain the keyword that was searched for and should be less than 156 chars.', 'sk' ); ?>">
                <i class="fas fa-question-circle"></i>
            </span>
        </label>
        <div class="sk-w5 sk-text-left">
            <textarea class="sk-form-control" rows="3" id="sk-seo-meta-desc" name="sk-seo-meta-desc"><?php echo $seo->print_saved_meta( $seo_meta['sk-seo-meta-desc'] ) ?></textarea>
        </div>
    </div>

    <div class="sk-form-group">
        <label class="sk-w3 sk-control-label" for="sk-seo-meta-keywords"><?php _e( 'Meta Keywords :', 'sk' ); ?>
            <span class="sk-tooltips-help tips" title="" data-placement="bottom" data-original-title="<?php _e( 'Insert some comma separated keywords for better ranking of your store page.', 'sk' ); ?>">
                <i class="fas fa-question-circle"></i>
            </span>
        </label>
        <div class="sk-w5 sk-text-left">
            <input id="sk-seo-meta-keywords" value="<?php echo $seo->print_saved_meta( $seo_meta['sk-seo-meta-keywords'] ) ?>" name="sk-seo-meta-keywords" placeholder=" " class="sk-form-control input-md" type="text">
        </div>
    </div>

    <div class="sk-form-group">
        <label class="sk-w3 sk-control-label" for="sk-seo-og-title"><?php _e( 'Facebook Title :', 'sk' ); ?></label>
        <div class="sk-w5 sk-text-left">
            <input id="sk-seo-og-title" value="<?php echo $seo->print_saved_meta( $seo_meta['sk-seo-og-title'] ) ?>" name="sk-seo-og-title" placeholder=" " class="sk-form-control input-md" type="text">
        </div>
    </div>

    <div class="sk-form-group">
        <label class="sk-w3 sk-control-label" for="sk-seo-og-desc"><?php _e( 'Facebook Description :', 'sk' ); ?></label>
        <div class="sk-w5 sk-text-left">
            <textarea class="sk-form-control" rows="3" id="sk-seo-og-desc" name="sk-seo-og-desc"><?php echo $seo->print_saved_meta( $seo_meta['sk-seo-og-desc'] ) ?></textarea>
        </div>
    </div>
    <?php
    $og_image     = $seo_meta['sk-seo-og-image'] ? $seo_meta['sk-seo-og-image'] : 0;
    $og_image_url = $og_image ? wp_get_attachment_thumb_url( $og_image ) : '';
    ?>
    <div class="sk-form-group ">
        <label class="sk-w3 sk-control-label" for="sk-seo-og-image"><?php _e( 'Facebook Image :', 'sk' ); ?></label>
        <div class="sk-w5 sk-gravatar sk-seo-image">
            <div class="sk-left gravatar-wrap<?php echo $og_image ? '' : ' sk-hide'; ?>">
                <input type="hidden" class="sk-file-field" value="<?php echo $og_image; ?>" name="sk-seo-og-image">
                <img class="sk-gravatar-img" src="<?php echo esc_url( $og_image_url ); ?>">
                <a class="sk-close sk-remove-gravatar-image">&times;</a>
            </div>

            <div class="gravatar-button-area <?php echo $og_image ? ' sk-hide' : ''; ?>">
                <a href="#" class="sk-gravatar-drag sk-btn sk-btn-default sk-left"><i class="fas fa-cloud-upload-alt"></i> <?php _e( 'Upload Photo', 'sk' ); ?></a>
            </div>
        </div>
    </div>

    <div class="sk-form-group">
        <label class="sk-w3 sk-control-label" for="sk-seo-twitter-title"><?php _e( 'Twitter Title :', 'sk' ); ?></label>
        <div class="sk-w5 sk-text-left">
            <input id="sk-seo-twitter-title" value="<?php echo $seo->print_saved_meta( $seo_meta['sk-seo-twitter-title'] ) ?>" name="sk-seo-twitter-title" placeholder=" " class="sk-form-control input-md" type="text">
        </div>
    </div>

    <div class="sk-form-group">
        <label class="sk-w3 sk-control-label" for="sk-seo-twitter-desc"><?php _e( 'Twitter Description :', 'sk' ); ?></label>
        <div class="sk-w5 sk-text-left">
            <textarea class="sk-form-control" rows="3" id="sk-seo-twitter-desc" name="sk-seo-twitter-desc"><?php echo $seo->print_saved_meta( $seo_meta['sk-seo-twitter-desc'] ) ?></textarea>
        </div>
    </div>
    <?php
    $twitter_image     = $seo_meta['sk-seo-twitter-image'] ? $seo_meta['sk-seo-twitter-image'] : 0;
    $twitter_image_url = $twitter_image ? wp_get_attachment_thumb_url( $twitter_image ) : '';
    ?>
    <div class="sk-form-group ">
        <label class="sk-w3 sk-control-label" for="sk-seo-twitter-image"><?php _e( 'Twitter Image :', 'sk' ); ?></label>
        <div class="sk-w5 sk-gravatar sk-seo-image">
            <div class="sk-left gravatar-wrap<?php echo $twitter_image ? '' : ' sk-hide'; ?>">
                <input type="hidden" class="sk-file-field" value="<?php echo $twitter_image; ?>" name="sk-seo-twitter-image">
                <img class="sk-gravatar-img" src="<?php echo esc_url( $twitter_image_url ); ?>">
                <a class="sk-close sk-remove-gravatar-image">&times;</a>
            </div>

            <div class="gravatar-button-area <?php echo $twitter_image ? ' sk-hide' : ''; ?>">
                <a href="#" class="sk-gravatar-drag sk-btn sk-btn-default sk-left"><i class="fas fa-cloud-upload-alt"></i> <?php _e( 'Upload Photo', 'sk' ); ?></a>
            </div>
        </div>
    </div>

    <?php wp_nonce_field( 'sk_store_seo_form_action', 'sk_store_seo_form_nonce' ); ?>

    <div class="sk-form-group" style="margin-left: 23%">
        <input type="submit" id='sk-store-seo-form-submit' class="sk-left sk-btn sk-btn-theme" value="<?php esc_attr_e( 'Save Changes', 'sk' ); ?>">
    </div>
</form>
