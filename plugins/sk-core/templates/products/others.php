<?php
$post_statuses = sk_get_available_post_status( $post->ID );
?>

<div class="sk-other-options sk-edit-row sk-clearfix <?php echo esc_attr( $class ); ?>">
    <div class="sk-section-heading" data-togglehandler="sk_other_options">
        <h2><i class="fas fa-cog" aria-hidden="true"></i> <?php esc_html_e( 'Other Options', 'sk-core' ); ?></h2>
        <p><?php esc_html_e( 'Set your extra product options', 'sk-core' ); ?></p>
        <a href="#" class="sk-section-toggle">
            <i class="fas fa-sort-down fa-flip-vertical" aria-hidden="true"></i>
        </a>
        <div class="sk-clearfix"></div>
    </div>

    <div class="sk-section-content">
        <div class="sk-form-group content-half-part">
            <label for="post_status" class="form-label"><?php esc_html_e( 'Product Status', 'sk-core' ); ?></label>
            <select id="post_status" class="sk-form-control" name="post_status">
                <?php foreach ( $post_statuses as $status => $label ) : // phpcs:ignore ?>
                    <option value="<?php echo esc_attr( $status ); ?>" <?php selected( $status, $post_status ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="sk-form-group content-half-part">
            <label for="_visibility" class="form-label"><?php esc_html_e( 'Visibility', 'sk-core' ); ?></label>
            <select name="_visibility" id="_visibility" class="sk-form-control">
                <?php foreach ( $visibility_options as $name => $label ) : ?>
                    <option value="<?php echo esc_attr( $name ); ?>" <?php selected( $_visibility, $name ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="sk-clearfix"></div>

        <div class="sk-form-group">
            <label for="_purchase_note" class="form-label"><?php esc_html_e( 'Purchase Note', 'sk-core' ); ?></label>
            <?php sk_post_input_box( $post_id, '_purchase_note', array( 'placeholder' => __( 'Customer will get this info in their order email', 'sk-core' ) ), 'textarea' ); ?>
        </div>

        <div class="sk-form-group">
            <?php
            sk_post_input_box(
                $post_id,
                '_enable_reviews',
                [
                    'value' => 'open' === $post->comment_status ? 'yes' : 'no',
                    'label' => __( 'Enable product reviews', 'sk-core' ),
                ],
                'checkbox'
            );
            ?>
        </div>

        <?php if ( class_exists( 'SK\Modules\NostrMarket\Module' ) && sk_get_option( 'sk_nostr_market_enabled', 'sk_nostr_market', 'off' ) === 'on' ) :
            $vendor_id = get_current_user_id();
            $vendor_settings = get_user_meta( $vendor_id, 'sk_profile_settings', true );
            $vendor_nostr_enabled = is_array( $vendor_settings ) && ! empty( $vendor_settings['nostr_market_enabled'] ) && $vendor_settings['nostr_market_enabled'] === '1';

            if ( $vendor_nostr_enabled ) :
                $nostr_post = get_post_meta( $post_id, '_sk_nostr_market_post', true );
                $nostr_checked = ( $nostr_post === '' ) ? true : ( $nostr_post === '1' ); // Default: on wenn Vendor es aktiviert hat.
        ?>
        <div class="sk-form-group">
            <label>
                <input type="hidden" name="_sk_nostr_market_post" value="0" />
                <input type="checkbox" name="_sk_nostr_market_post" value="1" <?php checked( $nostr_checked ); ?>>
                <?php esc_html_e( 'Auf Nostr Marketplace posten', 'sk-core' ); ?>
            </label>
        </div>
        <?php endif; endif; ?>

    </div>
</div><!-- .sk-other-options -->
