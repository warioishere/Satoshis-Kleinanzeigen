<?php
/**
 * Template: the store's trust page at /store/{slug}/vertrauen/
 *
 * Every signal with its source and the way to check it yourself.
 */

$custom_store_url = sk_get_option( 'custom_store_url', 'sk_general', 'store' );
$store_name       = get_query_var( $custom_store_url );
$store_user       = get_user_by( 'slug', $store_name );

if ( ! $store_user ) {
    return;
}

$vendor_id    = (int) $store_user->ID;
$vendor       = sk()->vendor->get( $vendor_id );
$map_location = $vendor->get_location();
$store_info   = sk_get_store_info( $vendor_id );
$layout       = get_theme_mod( 'store_layout', 'left' );

get_header( 'shop' );
?>

<?php do_action( 'woocommerce_before_main_content' ); ?>

<div class="sk-store-wrap layout-<?php echo esc_attr( $layout ); ?>">
    <?php if ( 'left' === $layout ) { ?>
        <?php
        sk_get_template_part( 'store', 'sidebar', [
            'store_user'   => $store_user,
            'store_info'   => $store_info,
            'map_location' => $map_location,
        ] );
        ?>
    <?php } ?>

    <div id="primary" class="content-area sk-single-store">
        <div id="sk-content" class="site-content store-review-wrap woocommerce" role="main">

            <?php sk_get_template_part( 'store-header' ); ?>

            <div class="sk-trust-page">

                <div class="sk-trust-card sk-trust-intro">
                    <h2><?php esc_html_e( 'Vertrauen, das du selbst prüfen kannst', 'sk-core' ); ?></h2>
                    <p><?php esc_html_e( 'Diese Seite behauptet nicht, dass der Anbieter vertrauenswürdig ist. Sie zeigt Fakten, die jeder nachrechnen kann: welcher Nostr-Schlüssel nachweislich zu diesem Shop gehört, wer aus deinem eigenen Netzwerk ihm folgt, was er an Zaps erhalten hat und welche Zahlungen belegt sind. Was fehlt, fehlt einfach; es gibt hier keine Abwertung.', 'sk-core' ); ?></p>
                </div>

                <?php include SK_REPUTATION_TEMPLATES . '/trust-signals.php'; ?>
            </div><!-- .sk-trust-page -->

        </div><!-- #content .site-content -->
    </div><!-- #primary .content-area -->

    <div class="sk-clearfix"></div>

    <?php if ( 'right' === $layout ) { ?>
        <?php
        sk_get_template_part( 'store', 'sidebar', [
            'store_user'   => $store_user,
            'store_info'   => $store_info,
            'map_location' => $map_location,
        ] );
        ?>
    <?php } ?>

</div><!-- .sk-store-wrap -->

<?php do_action( 'woocommerce_after_main_content' ); ?>

<?php get_footer(); ?>
