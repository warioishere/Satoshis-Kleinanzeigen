<?php
/**
 * Einheit des Inseratspreises.
 *
 * @var int    $post_id
 * @var string $current
 */

defined( 'ABSPATH' ) || exit;

use SK\Modules\ShopImport\PriceUnit;
?>
<?php
$sats = (string) get_post_meta( $post_id, '_regular_price', true );
$fiat = (string) get_post_meta( $post_id, \SK\Modules\ShopImport\Importer::META_FIAT, true );
?>
<?php
// Die Auswahl steckt im gewohnten Zusatz statt ihn zu ersetzen: die Gruppe ist
// eine Tabelle, und ein <select> als Zelle bricht sie in zwei Reihen.
?>
<span class="sk-input-group-addon sk-price-unit-wrap">
    <select name="sk_price_unit" id="sk_price_unit" class="sk-price-unit"
            data-sats="<?php echo esc_attr( $sats ); ?>"
            data-fiat="<?php echo esc_attr( $fiat ); ?>">
        <?php foreach ( PriceUnit::UNITS as $unit ) : ?>
            <option value="<?php echo esc_attr( $unit ); ?>" <?php selected( $current, $unit ); ?>>
                <?php echo esc_html( $unit === 'SATS' ? __( 'Sats', 'sk-core' ) : $unit ); ?>
            </option>
        <?php endforeach; ?>
    </select>
</span>
