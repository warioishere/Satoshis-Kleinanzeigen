<?php
/**
 * Announcements list template
 *
 * @var \WP_Post[] $announcements
 * @var int $total_items
 * @var int $total_pages
 * @var int $paged
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_url = admin_url( 'admin.php?page=sk&tab=announcements' );
?>

<div class="sk-announcements-wrap">
    <h2>
        <?php esc_html_e( 'Announcements', 'sk' ); ?>
        <a href="<?php echo esc_url( add_query_arg( 'action', 'new', $base_url ) ); ?>" class="page-title-action">
            <?php esc_html_e( 'Add New', 'sk' ); ?>
        </a>
    </h2>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" style="width: 40%;"><?php esc_html_e( 'Title', 'sk' ); ?></th>
                <th scope="col" style="width: 15%;"><?php esc_html_e( 'Status', 'sk' ); ?></th>
                <th scope="col" style="width: 20%;"><?php esc_html_e( 'Date', 'sk' ); ?></th>
                <th scope="col" style="width: 25%;"><?php esc_html_e( 'Actions', 'sk' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $announcements ) ) : ?>
                <tr>
                    <td colspan="4"><?php esc_html_e( 'No announcements found.', 'sk' ); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ( $announcements as $post ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $post->post_title ); ?></strong></td>
                        <td><?php echo esc_html( ucfirst( $post->post_status ) ); ?></td>
                        <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $post->post_date ) ) ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( add_query_arg( [ 'action' => 'edit', 'post_id' => $post->ID ], $base_url ) ); ?>" class="button button-small">
                                <?php esc_html_e( 'Edit', 'sk' ); ?>
                            </a>
                            <form method="post" style="display: inline;" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure?', 'sk' ); ?>');">
                                <?php wp_nonce_field( 'sk_announcement_save', 'sk_announcement_nonce' ); ?>
                                <input type="hidden" name="announcement_action" value="delete">
                                <input type="hidden" name="post_id" value="<?php echo esc_attr( $post->ID ); ?>">
                                <button type="submit" class="button button-small" style="color: #a00;"><?php esc_html_e( 'Delete', 'sk' ); ?></button>
                            </form>
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
