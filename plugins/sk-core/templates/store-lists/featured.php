<?php
/**
 * The template for displaying featured area in store lists filter
 *
 * This template can be overridden by copying it to yourtheme/sk/store-lists/featured.php
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="featured item">
    <label for="featured">
        <?php esc_html_e( 'Featured', 'sk' ); ?>:
    </label>
    <input type="checkbox" class="sk-toogle-checkbox" id="featured" name="featured">
</div>