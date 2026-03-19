<?php
/**
 * SK Settings Header Template
 *
 *
 */
?>
<header class="sk-dashboard-header">
    <div class="sk-store-settign-header-wrap">
        <h1 class="entry-title">
            <?php echo wp_kses_post( $heading ); ?>
            <small>&rarr; <a href="<?php echo esc_url( sk_get_store_url( sk_get_current_user_id() ) ); ?>"><?php esc_html_e( 'Zum öffentlichen Profil', 'sk-core' ); ?></a></small>
        </h1>
    </div>

    <?php if ( isset( $is_store_setting ) && $is_store_setting ) : ?>
        <span class="sk-update-setting-top">
	        <button type="button" class="sk-update-setting-top-button sk-btn sk-btn-btc sk-right" onclick="document.getElementById('sk-store-form').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}))"><?php esc_html_e( 'Speichern', 'sk-core' ); ?></button>
	    </span>
    <?php endif ?>
</header><!-- .sk-dashboard-header -->
