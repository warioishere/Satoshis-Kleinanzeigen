<?php $_sold_individually = get_post_meta( $post_id, '_sold_individually', true ); ?>
<div class="sk-form-horizontal">
    <div class="sk-form-group">
        <label class="sk-w4 sk-control-label" for="_purchase_note"><?php _e( 'Purchase Note', 'sk' ); ?></label>
        <div class="sk-w6 sk-text-left">
            <?php sk_post_input_box( $post->ID, '_purchase_note', array(), 'textarea' ); ?>
        </div>
    </div>

    <div class="sk-form-group">
        <label class="sk-w4 sk-control-label" for="_enable_reviews"><?php _e( 'Reviews', 'sk' ); ?></label>
        <div class="sk-w4 sk-text-left">
            <?php $_enable_reviews = ( $post->comment_status == 'open' ) ? 'yes' : 'no'; ?>
            <?php sk_post_input_box( $post->ID, '_enable_reviews', array('value' => $_enable_reviews, 'label' => __( 'Enable Reviews', 'sk' ) ), 'checkbox' ); ?>
        </div>
    </div>

    <div class="sk-form-group">
        <label class="sk-w4 sk-control-label" for="_purchase_note"><?php _e( 'Visibility', 'sk' ); ?></label>
        <div class="sk-w6 sk-text-left">
            <select name="_visibility" id="_visibility" class="sk-form-control">
                <?php foreach ( $visibility_options = sk_get_product_visibility_options() as $name => $label ): ?>
                    <option value="<?php echo $name; ?>" <?php selected( $_visibility, $name ); ?>><?php echo $label; ?></option>
                <?php endforeach ?>
            </select>
        </div>
    </div>

    <div class="sk-form-group">
        <label class="sk-w4 sk-control-label" for="_sold_individually"><?php _e( 'Sold Individually', 'sk' ); ?></label>
        <div class="sk-w7 sk-text-left">
            <input name="_sold_individually" id="_sold_individually" value="yes" type="checkbox" <?php checked( $_sold_individually, 'yes' ); ?>>
            <?php _e( 'Allow oooooo only one quantity of this product to be bought in a single order', 'sk' ) ?>
        </div>
    </div>

</div> <!-- .form-horizontal -->