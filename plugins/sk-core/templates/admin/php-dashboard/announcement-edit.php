<?php
/**
 * Announcement create/edit form
 *
 * @var \WP_Post|null $announcement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_edit = ! empty( $announcement );
$title   = $is_edit ? $announcement->post_title : '';
$content = $is_edit ? $announcement->post_content : '';
$status  = $is_edit ? $announcement->post_status : 'draft';
$post_id = $is_edit ? $announcement->ID : 0;
?>

<div class="sk-announcement-edit-wrap">
    <h2>
        <?php echo $is_edit ? esc_html__( 'Edit Announcement', 'sk' ) : esc_html__( 'New Announcement', 'sk' ); ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=sk&tab=announcements' ) ); ?>" class="page-title-action">
            <?php esc_html_e( 'Back to List', 'sk' ); ?>
        </a>
    </h2>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=sk&tab=announcements' ) ); ?>">
        <?php wp_nonce_field( 'sk_announcement_save', 'sk_announcement_nonce' ); ?>
        <input type="hidden" name="announcement_action" value="save">
        <input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">

        <table class="form-table">
            <tr>
                <th scope="row"><label for="announcement_title"><?php esc_html_e( 'Title', 'sk' ); ?></label></th>
                <td>
                    <input type="text" id="announcement_title" name="announcement_title"
                           value="<?php echo esc_attr( $title ); ?>" class="regular-text" required>
                </td>
            </tr>
            <tr>
                <th scope="row"><label><?php esc_html_e( 'Content', 'sk' ); ?></label></th>
                <td>
                    <?php wp_editor( $content, 'announcement_content', [
                        'textarea_name' => 'announcement_content',
                        'textarea_rows' => 15,
                        'media_buttons' => true,
                    ] ); ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="announcement_status"><?php esc_html_e( 'Status', 'sk' ); ?></label></th>
                <td>
                    <select id="announcement_status" name="announcement_status">
                        <option value="publish" <?php selected( $status, 'publish' ); ?>><?php esc_html_e( 'Published', 'sk' ); ?></option>
                        <option value="draft" <?php selected( $status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'sk' ); ?></option>
                    </select>
                </td>
            </tr>
        </table>

        <?php submit_button( $is_edit ? __( 'Update Announcement', 'sk' ) : __( 'Create Announcement', 'sk' ) ); ?>
    </form>
</div>
