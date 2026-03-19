<?php
/**
 * Advertisements list template
 *
 * @var array $advertisements
 * @var int $total_items
 * @var int $total_pages
 * @var int $paged
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_url = admin_url( 'admin.php?page=sk&tab=advertisements' );
?>

<div class="sk-advertisements-wrap">
    <h2><?php esc_html_e( 'Advertisements', 'sk' ); ?></h2>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e( 'Product', 'sk' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Vendor', 'sk' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Created Via', 'sk' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Price', 'sk' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Status', 'sk' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Expires At', 'sk' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Added', 'sk' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $advertisements ) ) : ?>
                <tr>
                    <td colspan="7"><?php esc_html_e( 'No advertisements found.', 'sk' ); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ( $advertisements as $ad ) :
                    $vendor_name = '';
                    if ( ! empty( $ad->vendor_id ) ) {
                        $vendor_name = get_user_meta( $ad->vendor_id, 'sk_store_name', true );
                        if ( ! $vendor_name ) {
                            $vendor_user = get_userdata( $ad->vendor_id );
                            $vendor_name = $vendor_user ? $vendor_user->display_name : '';
                        }
                    }
                    ?>
                    <tr>
                        <td><?php echo esc_html( $ad->product_title ?? __( '(deleted)', 'sk' ) ); ?></td>
                        <td><?php echo esc_html( $vendor_name ); ?></td>
                        <td><?php echo esc_html( $ad->created_via ?? '' ); ?></td>
                        <td><?php echo esc_html( $ad->price ?? '' ); ?></td>
                        <td><?php echo esc_html( ucfirst( $ad->status ?? '' ) ); ?></td>
                        <td><?php echo esc_html( ! empty( $ad->expires_at ) ? date_i18n( get_option( 'date_format' ), strtotime( $ad->expires_at ) ) : __( 'N/A', 'sk' ) ); ?></td>
                        <td><?php echo esc_html( ! empty( $ad->created_at ) ? date_i18n( get_option( 'date_format' ), strtotime( $ad->created_at ) ) : '' ); ?></td>
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
