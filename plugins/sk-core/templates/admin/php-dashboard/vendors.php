<?php
/**
 * Vendors list template
 *
 * @var \WP_User[] $vendors
 * @var int $total_items
 * @var int $total_pages
 * @var int $paged
 * @var int $per_page
 * @var string $search
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_url = admin_url( 'admin.php?page=sk&tab=vendors' );
?>

<div class="sk-vendors-wrap">
    <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
        <input type="hidden" name="page" value="sk">
        <input type="hidden" name="tab" value="vendors">
        <p class="search-box">
            <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search vendors...', 'sk-core' ); ?>">
            <input type="submit" class="button" value="<?php esc_attr_e( 'Search', 'sk-core' ); ?>">
        </p>
    </form>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" style="width: 25%;"><?php esc_html_e( 'Store Name', 'sk-core' ); ?></th>
                <th scope="col" style="width: 20%;"><?php esc_html_e( 'Email', 'sk-core' ); ?></th>
                <th scope="col" style="width: 15%;"><?php esc_html_e( 'Registered', 'sk-core' ); ?></th>
                <th scope="col" style="width: 15%;"><?php esc_html_e( 'Selling Enabled', 'sk-core' ); ?></th>
                <th scope="col" style="width: 25%;"><?php esc_html_e( 'Actions', 'sk-core' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $vendors ) ) : ?>
                <tr>
                    <td colspan="5"><?php esc_html_e( 'No vendors found.', 'sk-core' ); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ( $vendors as $user ) :
                    $store_name     = get_user_meta( $user->ID, 'sk_store_name', true );
                    $selling        = get_user_meta( $user->ID, 'sk_enable_selling', true );
                    $is_selling     = ( $selling === 'yes' );
                    $store_url      = sk_get_store_url( $user->ID );
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html( $store_name ? $store_name : $user->display_name ); ?></strong>
                        </td>
                        <td><?php echo esc_html( $user->user_email ); ?></td>
                        <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $user->user_registered ) ) ); ?></td>
                        <td>
                            <?php if ( $is_selling ) : ?>
                                <span style="color: green; font-weight: bold;"><?php esc_html_e( 'Yes', 'sk-core' ); ?></span>
                            <?php else : ?>
                                <span style="color: red;"><?php esc_html_e( 'No', 'sk-core' ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" style="display: inline;">
                                <?php wp_nonce_field( 'sk_vendor_action', 'sk_vendor_action_nonce' ); ?>
                                <input type="hidden" name="vendor_id" value="<?php echo esc_attr( $user->ID ); ?>">
                                <?php if ( $is_selling ) : ?>
                                    <input type="hidden" name="vendor_action" value="disable_selling">
                                    <button type="submit" class="button button-small"><?php esc_html_e( 'Disable Selling', 'sk-core' ); ?></button>
                                <?php else : ?>
                                    <input type="hidden" name="vendor_action" value="enable_selling">
                                    <button type="submit" class="button button-small button-primary"><?php esc_html_e( 'Enable Selling', 'sk-core' ); ?></button>
                                <?php endif; ?>
                            </form>
                            <?php if ( $store_url ) : ?>
                                <a href="<?php echo esc_url( $store_url ); ?>" class="button button-small" target="_blank"><?php esc_html_e( 'View Store', 'sk-core' ); ?></a>
                            <?php endif; ?>
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
