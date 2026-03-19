<?php
/**
 * SK Header Menu Template
 *
 *
 */
?>

<ul class="nav navbar-nav navbar-right">
    <li>
        <a href="#" class="dropdown-toggle" data-toggle="dropdown">
            <?php
            // translators: 1) cart total amount
            echo wp_kses_post( sprintf( __( 'Cart %s', 'sk-core' ), '<span class="sk-cart-amount-top">(' . WC()->cart->get_cart_total() . ')</span>' ) );
            ?>
            <b class="caret"></b>
        </a>

        <ul class="dropdown-menu">
            <li>
                <div class="widget_shopping_cart_content"></div>
            </li>
        </ul>
    </li>

    <?php if ( is_user_logged_in() ) { ?>

        <?php

        if ( sk_is_user_seller( $user_id ) ) {
            ?>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown"><?php esc_html_e( 'Vendor Dashboard', 'sk-core' ); ?> <b class="caret"></b></a>

                <ul class="dropdown-menu">
                    <li><a href="<?php echo esc_url( sk_get_store_url( $user_id ) ); ?>" target="_blank"><?php esc_html_e( 'Visit your store', 'sk-core' ); ?> <i class="fas fa-external-link-alt"></i></a></li>
                    <li class="divider"></li>
                    <?php
                    foreach ( $nav_urls as $key => $item ) {
                        echo wp_kses_post( sprintf( '<li><a href="%s">%s &nbsp;%s</a></li>', esc_url( $item['url'] ), $item['icon'], $item['title'] ) );
                    }
                    ?>
                </ul>
            </li>
        <?php } ?>

        <li class="dropdown">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown"><?php echo esc_html( $current_user->display_name ); ?> <b class="caret"></b></a>
            <ul class="dropdown-menu">
                <li><a href="<?php echo esc_url( sk_get_page_url( 'my_orders' ) ); ?>"><?php esc_html_e( 'My Orders', 'sk-core' ); ?></a></li>
                <li><a href="<?php echo esc_url( sk_get_page_url( 'myaccount', 'woocommerce' ) ); ?>"><?php esc_html_e( 'My Account', 'sk-core' ); ?></a></li>
                <li><a href="<?php echo esc_url( wc_customer_edit_account_url() ); ?>"><?php esc_html_e( 'Edit Account', 'sk-core' ); ?></a></li>
                <li class="divider"></li>
                <li><a href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address', 'billing', get_permalink( wc_get_page_id( 'myaccount' ) ) ) ); ?>"><?php esc_html_e( 'Billing Address', 'sk-core' ); ?></a></li>
                <li><a href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address', 'shipping', get_permalink( wc_get_page_id( 'myaccount' ) ) ) ); ?>"><?php esc_html_e( 'Shipping Address', 'sk-core' ); ?></a></li>
            </ul>
        </li>

        <li><?php wp_loginout( home_url() ); ?></li>

    <?php } else { ?>
        <li><a href="<?php echo esc_url( sk_get_page_url( 'myaccount', 'woocommerce' ) ); ?>"><?php esc_html_e( 'Log in', 'sk-core' ); ?></a></li>
        <li><a href="<?php echo esc_url( sk_get_page_url( 'myaccount', 'woocommerce' ) ); ?>"><?php esc_html_e( 'Sign Up', 'sk-core' ); ?></a></li>
    <?php } ?>
</ul>
