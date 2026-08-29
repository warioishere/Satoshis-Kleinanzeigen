<?php
/**
 * SK Settings Header Template
 *
 * Kopf wie auf den uebrigen Dashboardseiten (Merkliste, Gesuche):
 * sk-review-page-header mit h2 und Symbol. Vorher stand hier ein
 * h1.entry-title in einem sk-dashboard-header, der eine Trennlinie mitbrachte
 * und groesser war als alles Vergleichbare.
 */
?>
<div class="sk-review-page-header">
    <h2>
        <i class="fas fa-gear"></i>
        <?php echo wp_kses_post( $heading ); ?>
        <small>&rarr; <a href="<?php echo esc_url( sk_get_store_url( sk_get_current_user_id() ) ); ?>"><?php esc_html_e( 'Zum öffentlichen Profil', 'sk-core' ); ?></a></small>
    </h2>

    <?php if ( isset( $is_store_setting ) && $is_store_setting ) : ?>
        <span class="sk-update-setting-top">
	        <button type="button" class="sk-update-setting-top-button sk-btn sk-btn-btc sk-right" onclick="document.getElementById('sk-store-form').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}))"><?php esc_html_e( 'Speichern', 'sk-core' ); ?></button>
	    </span>
    <?php endif ?>
</div><!-- .sk-review-page-header -->
