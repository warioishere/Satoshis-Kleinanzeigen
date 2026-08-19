<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once ABSPATH . WPINC . '/formatting.php';

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>
<div id="sk-follow-store">
    <h1 id="sk-follow-store-title">
        <?php esc_html_e( 'Updates from your favorite stores', 'sk-core' ); ?>
    </h1>

    <table style="width: 100%; border-collapse: collapse;">
        <tbody>
            <?php foreach ( $data['vendors'] as $vendor ) : ?>
                <?php if ( $vendor->products->have_posts() || ! empty( $vendor->coupons ) ) : ?>
                    <tr>
                        <td valign="middle">
                            <div class="content">
                                <h3>
                                    <a href="<?php echo esc_url( $vendor->get_shop_url() ); ?>">
                                        <?php echo esc_html( $vendor->get_shop_name() ); ?>
                                    </a>
                                </h3>

                                <?php
                                if ( $vendor->products->have_posts() ) :
                                    $products = $vendor->products->posts;
                                    ?>
                                    <p class="section-title"><strong><?php esc_html_e( 'New Products', 'sk-core' ); ?></strong></p>

                                    <table class="vendor-products">
                                        <tbody>
                                            <tr>
                                                <?php
                                                foreach ( $products as $i => $product ) :
                                                    $product = wc_get_product( $product );
                                                    ?>
                                                    <td>
                                                        <a href="<?php echo esc_url( $product->get_permalink() ); ?>">
                                                            <?php echo $product->get_image( 'thumbnail' ); ?>

                                                            <span class="product-name">
                                                                <?php echo esc_html( wp_trim_words( $product->get_name(), 4, '...' ) ); ?>
                                                            </span>
                                                        </a>
                                                    </td>
                                                <?php endforeach; ?>
                                                <td class="show-all-products">
                                                    <a href="<?php echo esc_url( $vendor->get_shop_url() ); ?>">
                                                        <?php esc_html_e( 'See all', 'sk-core' ); ?>
                                                    </a>
                                                </td>

                                                <?php
                                                $total = count( $products );
                                                if ( $total < 3 ) :
                                                    for ( $i = 0; $i < ( 3 - $total ); $i++ ) :
                                                        ?>
                                                        <td></td>
                                                    <?php endfor; ?>
                                                <?php endif; ?>
                                            </tr>
                                        </tbody>
                                    </table>
                                <?php endif; ?>

                                <?php
                                if ( ! empty( $vendor->coupons ) ) :
                                    $coupons = $vendor->coupons;
                                    ?>
                                    <p class="section-title"><strong><?php esc_html_e( 'Coupons', 'sk-core' ); ?></strong></p>

                                    <ul class="vendor-coupons">
                                        <?php
                                        $coupons_count = count( $coupons );
                                        for ( $i = 0; $i < $coupons_count; $i++ ) :
                                            $coupon = $coupons[ $i ];
                                            ?>
                                            <li>
                                                <span class="coupon-name">
                                                    <?php echo esc_html( $coupon->get_code() ); ?>
                                                </span>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<style type="text/css">
    #sk-follow-store-title {
        text-align: center;
    }

    #sk-follow-store table {
        width: 100%;
        border-collapse: collapse;
    }

    #sk-follow-store .content {
        border: 1px solid #ddd;
        border-radius: 3px;
        height: 100%;
        padding: 20px;
    }

    #sk-follow-store .content h3 {
        margin-top: 0;
    }

    #sk-follow-store .content a {
        text-decoration: none;
        font-weight: bold;
    }

    #sk-follow-store .content p.section-title {
        margin: 0 0 5px;
    }

    #sk-follow-store .content .vendor-products td {
        width: 25%;
        padding: 0 10px 0 0;
    }

    #sk-follow-store .content .vendor-products td.show-all-products {
        vertical-align: top;
        line-height: 100px;
        text-align: center;
    }

    #sk-follow-store .content .vendor-products a {
        display: block;
        font-size: 13px;
        font-weight: 400;
    }

    #sk-follow-store .content .vendor-products img {
        border: 1px solid #f1f1f1;
        width: 100%;
        margin: 0 0 10px;
        border-radius: 2px;
    }

    #sk-follow-store .content .vendor-products .product-name {
        display: block;
        width: 100%;
        height: 55px;
    }

    #sk-follow-store .content .vendor-coupons {
        padding: 0;
        list-style-type: none;
        display: inline-block;
        margin: 0;
    }

    #sk-follow-store .content .vendor-coupons li {
        display: inline-block;
        background-color: #7d7d7d;
        border: 1px dashed #cacaca;
        color: #ffffff;
        padding: 10px;
        margin: 0 5px 7px 0;
        font-size: 13px;
        line-height: 1;
    }
</style>
<?php
/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
