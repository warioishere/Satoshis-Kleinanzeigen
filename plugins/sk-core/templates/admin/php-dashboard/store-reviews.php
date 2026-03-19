<?php
/**
 * Store reviews list template
 *
 * @var \WP_Post[] $reviews
 * @var int $total_items
 * @var int $total_pages
 * @var int $paged
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_url = admin_url( 'admin.php?page=sk&tab=store-reviews' );
?>

<div class="sk-store-reviews-wrap">
    <h2><?php esc_html_e( 'Store Reviews', 'sk' ); ?></h2>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e( 'Reviewer', 'sk' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Store', 'sk' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Rating', 'sk' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Content', 'sk' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Status', 'sk' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Date', 'sk' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Actions', 'sk' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $reviews ) ) : ?>
                <tr>
                    <td colspan="7"><?php esc_html_e( 'No store reviews found.', 'sk' ); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ( $reviews as $review ) :
                    $author   = get_userdata( $review->post_author );
                    $store_id = get_post_meta( $review->ID, 'store_id', true );
                    $rating   = get_post_meta( $review->ID, 'rating', true );
                    $store_name = '';
                    if ( $store_id ) {
                        $store_name = get_user_meta( $store_id, 'sk_store_name', true );
                        if ( ! $store_name ) {
                            $store_user = get_userdata( $store_id );
                            $store_name = $store_user ? $store_user->display_name : '';
                        }
                    }
                    ?>
                    <tr>
                        <td><?php echo esc_html( $author ? $author->display_name : __( 'Unknown', 'sk' ) ); ?></td>
                        <td><?php echo esc_html( $store_name ); ?></td>
                        <td><?php echo esc_html( $rating ); ?></td>
                        <td><?php echo esc_html( wp_trim_words( $review->post_content, 15 ) ); ?></td>
                        <td><?php echo esc_html( ucfirst( $review->post_status ) ); ?></td>
                        <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $review->post_date ) ) ); ?></td>
                        <td>
                            <?php if ( $review->post_status !== 'publish' ) : ?>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field( 'sk_review_action', 'sk_review_nonce' ); ?>
                                    <input type="hidden" name="review_action" value="approve">
                                    <input type="hidden" name="post_id" value="<?php echo esc_attr( $review->ID ); ?>">
                                    <button type="submit" class="button button-small button-primary"><?php esc_html_e( 'Approve', 'sk' ); ?></button>
                                </form>
                            <?php endif; ?>
                            <form method="post" style="display: inline;">
                                <?php wp_nonce_field( 'sk_review_action', 'sk_review_nonce' ); ?>
                                <input type="hidden" name="review_action" value="trash">
                                <input type="hidden" name="post_id" value="<?php echo esc_attr( $review->ID ); ?>">
                                <button type="submit" class="button button-small" style="color: #a00;"><?php esc_html_e( 'Trash', 'sk' ); ?></button>
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
