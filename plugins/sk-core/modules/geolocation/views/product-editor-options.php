<div class="sk-geolocation-options sk-edit-row sk-clearfix">
    <div class="sk-section-heading" data-togglehandler="sk_geolocation_options">
        <h2><i class="fas fa-map-marker-alt" aria-hidden="true"></i> Produkt Standort</h2>

        <a href="#" class="sk-section-toggle">
            <i class="fas fa-sort-down fa-flip-vertical" aria-hidden="true"></i>
        </a>

        <div class="sk-clearfix"></div>
    </div>

    <div class="sk-section-content">
        <div class="sk-form-group">
            <?php
                sk_post_input_box(
                    $post_id,
                    '_sk_geolocation_use_store_settings',
                    array(
                        'value' => $use_store_settings,
                        'label' => 'Gleich wie in den Profileinstellungen',
                    ),
                    'checkbox'
                );
            ?>
        </div>

        <?php if ( ! $store_has_settings ): ?>
            <div class="sk-form-group<?php echo ( 'yes' !== $use_store_settings ) ? ' sk-hide' : ''; ?>" id="sk-geolocation-product-location-no-store-settings">
                <p class="sk-error">
                    <?php printf( wp_kses( 'Dein Profil hat noch keine Standort-Einstellungen. Bitte lege diese zuerst in deinen <a href="%s" target="_blank">Profileinstellungen</a> fest.', [ 'a' => [ 'href' => [], 'target' => [] ] ] ), esc_url( $store_settings_url ) ); ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="sk-form-group<?php echo ( 'yes' === $use_store_settings ) ? ' sk-hide' : '' ?>" id="sk-geolocation-product-location">
            <label for="_sk_geolocation_product_location" class="form-label">
                <?php _e( 'Product Location', 'sk' ); ?>
            </label>

            <div class="sk-geolocation-product-location-container">
                <input type="hidden" name="_sk_geolocation_product_sk_geo_latitude" value="<?php echo esc_attr( $sk_geo_latitude ); ?>">
                <input type="hidden" name="_sk_geolocation_product_sk_geo_longitude" value="<?php echo esc_attr( $sk_geo_longitude ); ?>">
                <input type="text" name="_sk_geolocation_product_sk_geo_address" value="<?php echo esc_attr( $sk_geo_address ); ?>" class="sk-form-control" id="_sk_geolocation_product_location">

                <?php if ( is_ssl() || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ): ?>
                    <i class="locate-icon" style="background-image: url(<?php echo SK_GEOLOCATION_URL . '/assets/images/locate.svg'; ?>)"></i>
                <?php endif; ?>
            </div>

            <?php
                $source = sk_get_option( 'map_api_source', 'sk_appearance', 'google_maps' );

                if ( 'mapbox' === $source ) {
                    $access_token = sk_get_option( 'mapbox_access_token', 'sk_appearance', null );
                    ?>
                        <div id="sk-geolocation-product-location-map" class="sk-maps-mapbox"></div>
                        <input type="hidden" name="_sk_geolocation_mapbox_access_token" value="<?php echo $access_token; ?>">
                    <?php
                } else {
                    ?>
                        <div id="sk-geolocation-product-location-map"></div>
                    <?php
                }

            ?>
        </div>
    </div>

    <div class="sk-clearfix"></div>
</div><!-- .sk-geolocation-options -->
