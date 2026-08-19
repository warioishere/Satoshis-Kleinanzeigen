<?php
/**
 * Abuse reports list template
 *
 * @var array $reports
 * @var int $total_items
 * @var int $total_pages
 * @var int $paged
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_url = admin_url( 'admin.php?page=sk&tab=abuse-reports' );
?>

<div class="sk-abuse-reports-wrap">
    <h2><?php esc_html_e( 'Abuse Reports', 'sk-core' ); ?></h2>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e( 'Product', 'sk-core' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Reason', 'sk-core' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Reporter', 'sk-core' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Reported At', 'sk-core' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Actions', 'sk-core' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $reports ) ) : ?>
                <tr>
                    <td colspan="5"><?php esc_html_e( 'No abuse reports found.', 'sk-core' ); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ( $reports as $report ) : ?>
                    <tr>
                        <td><?php echo esc_html( $report->product_title ?? __( '(deleted)', 'sk-core' ) ); ?></td>
                        <td><?php echo esc_html( $report->reason ?? '' ); ?></td>
                        <td>
                            <?php
                            $name  = ! empty( $report->customer_name ) ? $report->customer_name : '';
                            $email = ! empty( $report->customer_email ) ? $report->customer_email : '';
                            echo esc_html( $name . ( $email ? " ({$email})" : '' ) );
                            ?>
                        </td>
                        <td><?php echo esc_html( ! empty( $report->created_at ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $report->created_at ) ) : '' ); ?></td>
                        <td>
                            <form method="post" style="display: inline;" onsubmit="return confirm('<?php esc_attr_e( 'Delete this report?', 'sk-core' ); ?>');">
                                <?php wp_nonce_field( 'sk_abuse_report_action', 'sk_abuse_report_nonce' ); ?>
                                <input type="hidden" name="report_action" value="delete">
                                <input type="hidden" name="report_id" value="<?php echo esc_attr( $report->id ); ?>">
                                <button type="submit" class="button button-small" style="color: #a00;"><?php esc_html_e( 'Delete', 'sk-core' ); ?></button>
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
