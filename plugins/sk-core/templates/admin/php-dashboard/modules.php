<?php
/**
 * Modules grid template
 *
 * @var array $all_modules
 * @var array $active_modules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

wp_enqueue_script( 'sk-php-dashboard-modules' );
wp_localize_script(
    'sk-php-dashboard-modules',
    'skPhpModules',
    [
        'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
        'nonce'         => wp_create_nonce( 'sk_php_toggle_module' ),
        'activeLabel'   => __( 'Active', 'sk-core' ),
        'inactiveLabel' => __( 'Inactive', 'sk-core' ),
        'errorMessage'  => __( 'Failed to toggle module.', 'sk-core' ),
    ]
);
?>

<div class="sk-modules-wrap">
    <h2><?php esc_html_e( 'Modules', 'sk-core' ); ?></h2>

    <?php if ( empty( $all_modules ) ) : ?>
        <p><?php esc_html_e( 'No modules available.', 'sk-core' ); ?></p>
    <?php else : ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 15px;">
            <?php foreach ( $all_modules as $module_id => $module ) :
                $name        = is_array( $module ) ? ( $module['name'] ?? $module_id ) : $module_id;
                $description = is_array( $module ) ? ( $module['description'] ?? '' ) : '';
                $is_active   = in_array( $module_id, $active_modules, true );
                ?>
                <div class="card" style="max-width: 100%; padding: 15px;">
                    <h3 style="margin-top: 0;"><?php echo esc_html( $name ); ?></h3>
                    <?php if ( $description ) : ?>
                        <p style="color: #666;"><?php echo esc_html( $description ); ?></p>
                    <?php endif; ?>
                    <label style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox"
                               class="sk-module-toggle"
                               data-module-id="<?php echo esc_attr( $module_id ); ?>"
                               <?php checked( $is_active ); ?>>
                        <span><?php echo $is_active ? esc_html__( 'Active', 'sk-core' ) : esc_html__( 'Inactive', 'sk-core' ); ?></span>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
