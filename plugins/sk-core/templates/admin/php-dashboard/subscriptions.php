<?php
/**
 * Subscriptions list template
 *
 * @var \WC_Product[] $products
 * @var int $total_items
 * @var int $total_pages
 * @var int $paged
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_url = admin_url( 'admin.php?page=sk&tab=subscriptions' );
?>

<div class="sk-subscriptions-wrap">
    <h2><?php esc_html_e( 'Subscription Packages', 'sk' ); ?></h2>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e( 'Package Name', 'sk' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Price', 'sk' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Validity (Days)', 'sk' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Status', 'sk' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Actions', 'sk' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $products ) ) : ?>
                <tr>
                    <td colspan="5"><?php esc_html_e( 'No subscription packages found.', 'sk' ); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ( $products as $product ) :
                    $validity = get_post_meta( $product->get_id(), '_pack_validity', true );
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html( $product->get_name() ); ?></strong></td>
                        <td><?php echo wp_kses_post( wc_price( $product->get_price() ) ); ?></td>
                        <td><?php echo esc_html( $validity ? $validity : __( 'Unlimited', 'sk' ) ); ?></td>
                        <td><?php echo esc_html( ucfirst( $product->get_status() ) ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( get_edit_post_link( $product->get_id() ) ); ?>" class="button button-small">
                                <?php esc_html_e( 'Edit', 'sk' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ( $total_pages > 1 ) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links( [
                    'base'      => add_query_arg( 'paged', '%#%', $base_url ),
                    'format'    => '',
                    'current'   => $paged,
                    'total'     => $total_pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                ] );
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>
