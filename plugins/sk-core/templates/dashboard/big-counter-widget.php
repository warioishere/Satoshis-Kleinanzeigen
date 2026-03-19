<?php
/**
 *  Dashboard Widget Template
 *
 *  Dashboard Big Counter widget template
 *
 *
 * @author  SK
 *
 */
?>
<div class="dashboard-widget big-counter">
    <ul class="list-inline">
        <li>
            <div class="title"><?php esc_html_e( 'Net Sales', 'sk-core' ); ?></div>
            <div class="count"><?php echo wp_kses_post( wc_price( $earning ) ); ?></div>
        </li>
        <li>
            <div class="title"><?php esc_html_e( 'Earning', 'sk-core' ); ?></div>
            <div class="count"><?php echo wp_kses_post( sk_get_seller_earnings( sk_get_current_user_id() ) ); ?></div>
        </li>
        <li>
            <div class="title"><?php esc_html_e( 'Pageview', 'sk-core' ); ?></div>
            <div class="count"><?php echo esc_html( sk_number_format( $pageviews ) ); ?></div>
        </li>
        <li>
            <div class="title"><?php esc_html_e( 'Order', 'sk-core' ); ?></div>
            <div class="count">
                <?php
                $total = 0;

                if ( is_object( $orders_count ) ) {
                    foreach ( get_object_vars( $orders_count ) as $status_count ) {
                        $total += (int) $status_count;
                    }
                }

                echo esc_html( number_format_i18n( $total, 0 ) );
                ?>
            </div>
        </li>

        <?php do_action( 'sk_seller_dashboard_widget_counter' ); ?>
    </ul>
</div> <!-- .big-counter -->
