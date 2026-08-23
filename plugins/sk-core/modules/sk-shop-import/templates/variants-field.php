<?php
/**
 * Ausführungen im Produkteditor.
 *
 * @var int    $post_id
 * @var bool   $allowed
 * @var array  $variants
 * @var array|null $pack
 * @var string $currency
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="sk-form-group sk-clearfix sk-variants-field">
    <label class="sk-w3 sk-control-label"><?php esc_html_e( 'Ausführungen', 'sk-core' ); ?></label>

    <div class="sk-w9">
        <?php if ( ! $allowed ) : ?>

            <p class="sk-variants-field__locked">
                <?php
                if ( $pack ) {
                    printf(
                        /* translators: 1: pack name, 2: price in sats */
                        esc_html__( 'Mehrere Ausführungen mit eigenen Preisen gibt es ab dem Paket %1$s (%2$s Sats).', 'sk-core' ),
                        esc_html( $pack['name'] ),
                        esc_html( number_format_i18n( $pack['price'] ) )
                    );
                } else {
                    esc_html_e( 'Mehrere Ausführungen mit eigenen Preisen gibt es ab einem grösseren Paket.', 'sk-core' );
                }
                ?>
                <a href="<?php echo esc_url( sk_get_navigation_url( 'subscription' ) ); ?>"><?php esc_html_e( 'Zu den Abos', 'sk-core' ); ?></a>
            </p>

            <?php if ( ! empty( $variants ) ) : ?>
                <p class="sk-variants-field__kept">
                    <?php printf( esc_html__( 'Die %d bereits hinterlegten Ausführungen bleiben erhalten und werden weiter angezeigt.', 'sk-core' ), count( $variants ) ); ?>
                </p>
            <?php endif; ?>

        <?php else : ?>

            <p class="sk-variants-field__hint">
                <?php esc_html_e( 'Zum Beispiel Grössen oder Ausstattungen. Der Inseratspreis ist automatisch der günstigste und wird als „ab" gezeigt.', 'sk-core' ); ?>
            </p>

            <input type="hidden" name="sk_variant_currency" value="<?php echo esc_attr( $currency ); ?>">

            <ul class="sk-variants-rows" id="sk-variants-rows">
                <?php
                $rows = $variants;
                if ( empty( $rows ) ) {
                    $rows = [ [ 'name' => '', 'price' => '' ] ];
                }
                foreach ( $rows as $variant ) :
                    ?>
                    <li class="sk-variants-row">
                        <input type="text" class="sk-form-control sk-variants-row__name" name="sk_variant_name[]"
                               value="<?php echo esc_attr( $variant['name'] ?? '' ); ?>"
                               placeholder="<?php esc_attr_e( 'Bezeichnung', 'sk-core' ); ?>">
                        <input type="text" class="sk-form-control sk-variants-row__price" name="sk_variant_price[]"
                               value="<?php echo esc_attr( $variant['price'] !== null ? $variant['price'] : '' ); ?>"
                               placeholder="<?php echo esc_attr( $currency ); ?>">
                        <button type="button" class="sk-btn sk-btn-default sk-variants-row__remove" aria-label="<?php esc_attr_e( 'Entfernen', 'sk-core' ); ?>">&times;</button>
                    </li>
                <?php endforeach; ?>
            </ul>

            <button type="button" class="sk-btn sk-btn-default" id="sk-variants-add"><?php esc_html_e( 'Ausführung hinzufügen', 'sk-core' ); ?></button>

        <?php endif; ?>
    </div>
</div>
