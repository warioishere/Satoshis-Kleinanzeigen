<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="nostr-import-container">
    <form id="nostr-import-form" method="post">
        <?php wp_nonce_field('nostr_import_nonce', 'nostr_import_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <!-- translators: Label for Nostr public key input field -->
                    <label for="author_pubkey"><?php esc_html_e('Author Pubkey/Npub', 'sk-core'); ?></label>
                </th>
                <td>
                    <input type="text" id="author_pubkey" name="author_pubkey" class="regular-text" required>
                    <!-- translators: Help text for Nostr public key input field -->
                    <p class="description"><?php esc_html_e('Enter the Nostr public key (hex or npub format)', 'sk-core'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <!-- translators: Label for date range start input -->
                    <label for="date_from"><?php echo esc_html__('Date From', 'sk-core'); ?></label>
                </th>
                <td>
                    <input type="date" id="date_from" name="date_from" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <!-- translators: Label for date range end input -->
                    <label for="date_to"><?php echo esc_html__('Date To', 'sk-core'); ?></label>
                </th>
                <td>
                    <input type="date" id="date_to" name="date_to" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <!-- translators: Label for tag filter input -->
                    <label for="tag_filter"><?php echo esc_html__('Tag Filter', 'sk-core'); ?></label>
                </th>
                <td>
                    <input type="text" id="tag_filter" name="tag_filter" class="regular-text">
                    <!-- translators: Help text for tag filter input -->
                    <p class="description"><?php echo esc_html__('Optional: Filter by tag (without #)', 'sk-core'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <!-- translators: Label for post category selection -->
                    <label for="post_category"><?php echo esc_html__('Category', 'sk-core'); ?></label>
                </th>
                <td>
                    <?php
                    $categories = get_categories(array(
                        'hide_empty' => false,
                        'orderby' => 'name',
                        'order' => 'ASC'
                    ));
                    if (!empty($categories)) : ?>
                        <select id="post_category" name="post_category[]" multiple="multiple" class="regular-text">
                            <?php foreach ($categories as $category) : ?>
                                <option value="<?php echo esc_attr($category->term_id); ?>">
                                    <?php echo esc_html($category->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <!-- translators: Help text for category selection -->
                        <p class="description"><?php echo esc_html__('Optional: Select categories for imported posts. Hold Ctrl/Cmd to select multiple.', 'sk-core'); ?></p>
                    <?php else : ?>
                        <div class="notice notice-warning inline">
                            <!-- translators: Message shown when no categories are available -->
                            <p><?php echo esc_html__('No categories found.', 'sk-core'); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (current_user_can('manage_categories')) : ?>
                        <p class="description">
                            <?php 
                            $categories_url = admin_url('edit-tags.php?taxonomy=category');
                            /* translators: %s: URL to WordPress categories management page */
                            echo sprintf(
                                wp_kses(
                                    __('You can manage your categories in the <a href="%s">WordPress Categories</a> section.', 'sk-core'),
                                    array('a' => array('href' => array()))
                                ),
                                esc_url($categories_url)
                            ); 
                            ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <!-- translators: Label for import comments checkbox -->
                    <label for="import_comments"><?php echo esc_html__('Import Comments', 'sk-core'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="import_comments" name="import_comments" value="1">
                    <!-- translators: Help text for import comments checkbox -->
                    <p class="description"><?php echo esc_html__('Import associated comments for each post.', 'sk-core'); ?></p>
                </td>
            </tr>
        </table>

        <div class="nostr-loading-indicator" style="display: none;">
            <span class="spinner is-active"></span>
            <span><?php echo esc_html__('Loading...', 'sk-core'); ?></span>
        </div>

        <p class="submit">
            <button type="submit" class="button button-primary">
                <!-- translators: Button text for previewing posts before import -->
                <?php echo esc_html__('Preview Posts', 'sk-core'); ?>
            </button>
        </p>
    </form>

    <div id="preview-content"></div>
    
    <div id="import-preview" style="display: none;">
        <div id="import-progress" style="display: none;">
            <div class="progress-bar">
                <div class="progress-bar-fill" style="width: 0%"></div>
            </div>
            <div id="import-status"></div>
        </div>
        <p>
            <button id="start-import" class="button button-primary">
                <!-- translators: Button text for starting the import process -->
                <?php echo esc_html__('Import Selected Posts', 'sk-core'); ?>
            </button>
        </p>
    </div>
</div>

