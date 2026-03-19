<?php
$product_action = 'listing';

if ( isset( $_GET['_sk_edit_product_nonce'] ) && wp_verify_nonce( sanitize_key( $_GET['_sk_edit_product_nonce'] ), 'sk_edit_product_nonce' ) && ! empty( $_GET['action'] ) ) {
    $product_action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
}

if ( 'edit' === $product_action ) {
    do_action( 'sk_render_product_edit_template', $product_action );
} else {
    do_action( 'sk_render_product_listing_template', $product_action );
}
