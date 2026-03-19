<?php
/**
 * Admin Settings Page
 *
 * Provides WordPress admin interface for configuring the Unified Auth Connector plugin.
 */
class UAC_Admin_Settings {

    /**
     * Add admin menu page.
     */
    public function add_admin_menu() {
        add_options_page(
            __('Unified Auth Connector', 'unified-auth-connector'),
            __('Unified Auth', 'unified-auth-connector'),
            'manage_options',
            'unified-auth-connector',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        // Main on/off switch (stored as 'yes'/'no' for backwards compatibility)
        register_setting(
            'uac_settings',
            'uac_enabled',
            array(
                'sanitize_callback' => function ( $val ) {
                    return $val === 'yes' ? 'yes' : 'no';
                },
            )
        );

        register_setting(
            'uac_settings',
            'uac_enable_account_linking',
            array(
                'type' => 'boolean',
                'default' => true,
                'sanitize_callback' => 'rest_sanitize_boolean'
            )
        );

        register_setting(
            'uac_settings',
            'uac_allow_unlinking',
            array(
                'type' => 'boolean',
                'default' => true,
                'sanitize_callback' => 'rest_sanitize_boolean'
            )
        );

        add_settings_section(
            'uac_general_section',
            __('General Settings', 'unified-auth-connector'),
            array($this, 'render_general_section'),
            'unified-auth-connector'
        );

        add_settings_field(
            'uac_enabled',
            __('System Status', 'unified-auth-connector'),
            array($this, 'render_system_status_field'),
            'unified-auth-connector',
            'uac_general_section'
        );

        add_settings_field(
            'uac_enable_account_linking',
            __('Enable Account Linking', 'unified-auth-connector'),
            array($this, 'render_enable_field'),
            'unified-auth-connector',
            'uac_general_section'
        );

        add_settings_field(
            'uac_allow_unlinking',
            __('Allow Unlinking', 'unified-auth-connector'),
            array($this, 'render_unlinking_field'),
            'unified-auth-connector',
            'uac_general_section'
        );
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check plugin dependencies
        $lnurl_active = function_exists('lnurl_auth');
        $nostr_active = class_exists('Nostr_Login_Handler');
        $sk_active = class_exists('SK_Lite');

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php if (!$sk_active): ?>
                <div class="notice notice-warning">
                    <p>
                        <strong><?php esc_html_e('Warning:', 'unified-auth-connector'); ?></strong>
                        <?php esc_html_e('SK plugin is not active. The dashboard integration will not be available.', 'unified-auth-connector'); ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if (!$lnurl_active && !$nostr_active): ?>
                <div class="notice notice-error">
                    <p>
                        <strong><?php esc_html_e('Error:', 'unified-auth-connector'); ?></strong>
                        <?php esc_html_e('Neither LNURL-Auth nor Nostr Login plugins are active. Please activate at least one authentication plugin.', 'unified-auth-connector'); ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="card">
                <h2><?php esc_html_e('Plugin Status', 'unified-auth-connector'); ?></h2>
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e('LNURL-Auth Plugin', 'unified-auth-connector'); ?></strong></td>
                            <td>
                                <?php if ($lnurl_active): ?>
                                    <span style="color: green;">✓ <?php esc_html_e('Active', 'unified-auth-connector'); ?></span>
                                <?php else: ?>
                                    <span style="color: red;">✗ <?php esc_html_e('Not Active', 'unified-auth-connector'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Nostr Login Plugin', 'unified-auth-connector'); ?></strong></td>
                            <td>
                                <?php if ($nostr_active): ?>
                                    <span style="color: green;">✓ <?php esc_html_e('Active', 'unified-auth-connector'); ?></span>
                                <?php else: ?>
                                    <span style="color: red;">✗ <?php esc_html_e('Not Active', 'unified-auth-connector'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('SK Plugin', 'unified-auth-connector'); ?></strong></td>
                            <td>
                                <?php if ($sk_active): ?>
                                    <span style="color: green;">✓ <?php esc_html_e('Active', 'unified-auth-connector'); ?></span>
                                <?php else: ?>
                                    <span style="color: orange;">⚠ <?php esc_html_e('Not Active', 'unified-auth-connector'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <form action="options.php" method="post">
                <?php
                settings_fields('uac_settings');
                do_settings_sections('unified-auth-connector');
                submit_button(__('Save Settings', 'unified-auth-connector'));
                ?>
            </form>

            <div class="card">
                <h2><?php esc_html_e('How It Works', 'unified-auth-connector'); ?></h2>
                <ol>
                    <li><?php esc_html_e('Users log in with their preferred authentication method (WordPress, Nostr, or LNURL-Auth).', 'unified-auth-connector'); ?></li>
                    <li><?php esc_html_e('In the SK dashboard, users can navigate to "Authentication" to link additional login methods.', 'unified-auth-connector'); ?></li>
                    <li><?php esc_html_e('Once linked, users can log in using any of their connected authentication methods.', 'unified-auth-connector'); ?></li>
                    <li><?php esc_html_e('All linked methods authenticate to the same WordPress account, preserving profile data.', 'unified-auth-connector'); ?></li>
                </ol>

                <h3><?php esc_html_e('Technical Details', 'unified-auth-connector'); ?></h3>
                <ul>
                    <li><strong><?php esc_html_e('Primary Account:', 'unified-auth-connector'); ?></strong> <?php esc_html_e('The account data from the original authentication method is preserved.', 'unified-auth-connector'); ?></li>
                    <li><strong><?php esc_html_e('Identity Mapping:', 'unified-auth-connector'); ?></strong> <?php esc_html_e('Nostr public keys and LNURL node keys are mapped to the primary WordPress user ID.', 'unified-auth-connector'); ?></li>
                    <li><strong><?php esc_html_e('Login Flow:', 'unified-auth-connector'); ?></strong> <?php esc_html_e('When a linked identity is used to log in, the user is automatically logged into the primary account.', 'unified-auth-connector'); ?></li>
                </ul>
            </div>

            <div class="card">
                <h2><?php esc_html_e('Statistics', 'unified-auth-connector'); ?></h2>
                <?php $this->render_statistics(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render general section description.
     */
    public function render_general_section() {
        echo '<p>' . esc_html__('Configure how users can link their authentication methods.', 'unified-auth-connector') . '</p>';
    }

    /**
     * Render system status (uac_enabled) toggle field.
     */
    public function render_system_status_field() {
        $enabled = get_option( 'uac_enabled', 'no' ) === 'yes';
        ?>
        <label>
            <input type="checkbox" name="uac_enabled" value="yes" <?php checked( $enabled ); ?> />
            <?php if ( $enabled ) : ?>
                <strong style="color:#46b450;">&#10003; <?php esc_html_e( 'Unified Auth ist aktiviert', 'unified-auth-connector' ); ?></strong>
            <?php else : ?>
                <strong style="color:#dc3232;">&#10007; <?php esc_html_e( 'Unified Auth ist deaktiviert', 'unified-auth-connector' ); ?></strong>
            <?php endif; ?>
        </label>
        <p class="description"><?php esc_html_e( 'Nutzer können LNURL-Auth und Nostr Login mit einem WordPress-Account verknüpfen.', 'unified-auth-connector' ); ?></p>
        <?php
    }

    /**
     * Render enable field.
     */
    public function render_enable_field() {
        $value = get_option('uac_enable_account_linking', true);
        ?>
        <label>
            <input type="checkbox" name="uac_enable_account_linking" value="1" <?php checked($value, true); ?> />
            <?php esc_html_e('Allow users to link multiple authentication methods to their account', 'unified-auth-connector'); ?>
        </label>
        <?php
    }

    /**
     * Render unlinking field.
     */
    public function render_unlinking_field() {
        $value = get_option('uac_allow_unlinking', true);
        ?>
        <label>
            <input type="checkbox" name="uac_allow_unlinking" value="1" <?php checked($value, true); ?> />
            <?php esc_html_e('Allow users to unlink authentication methods from their account', 'unified-auth-connector'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('If disabled, users can only add new authentication methods but cannot remove them.', 'unified-auth-connector'); ?>
        </p>
        <?php
    }

    /**
     * Render statistics.
     */
    private function render_statistics() {
        global $wpdb;

        // Count users with linked Nostr
        $nostr_linked = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = 'uac_linked_nostr_pubkey'"
        );

        // Count users with linked LNURL
        $lnurl_linked = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = 'uac_linked_lnurl_node_key'"
        );

        // Count users with both linked
        $both_linked = $wpdb->get_var(
            "SELECT COUNT(DISTINCT um1.user_id)
            FROM {$wpdb->usermeta} um1
            INNER JOIN {$wpdb->usermeta} um2 ON um1.user_id = um2.user_id
            WHERE um1.meta_key = 'uac_linked_nostr_pubkey'
            AND um2.meta_key = 'uac_linked_lnurl_node_key'"
        );

        ?>
        <table class="widefat">
            <tbody>
                <tr>
                    <td><strong><?php esc_html_e('Users with Nostr Linked', 'unified-auth-connector'); ?></strong></td>
                    <td><?php echo intval($nostr_linked); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Users with LNURL Linked', 'unified-auth-connector'); ?></strong></td>
                    <td><?php echo intval($lnurl_linked); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Users with Both Linked', 'unified-auth-connector'); ?></strong></td>
                    <td><?php echo intval($both_linked); ?></td>
                </tr>
            </tbody>
        </table>
        <?php
    }
}
