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
?>

<div class="sk-modules-wrap">
    <h2><?php esc_html_e( 'Modules', 'sk' ); ?></h2>

    <?php if ( empty( $all_modules ) ) : ?>
        <p><?php esc_html_e( 'No modules available.', 'sk' ); ?></p>
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
                        <span><?php echo $is_active ? esc_html__( 'Active', 'sk' ) : esc_html__( 'Inactive', 'sk' ); ?></span>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
(function() {
    var nonce = '<?php echo esc_js( wp_create_nonce( 'sk_php_toggle_module' ) ); ?>';

    document.querySelectorAll('.sk-module-toggle').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            var moduleId = this.getAttribute('data-module-id');
            var active = this.checked ? '1' : '0';
            var label = this.nextElementSibling;

            var data = new FormData();
            data.append('action', 'sk_php_toggle_module');
            data.append('nonce', nonce);
            data.append('module_id', moduleId);
            data.append('active', active);

            fetch(ajaxurl, {
                method: 'POST',
                body: data,
                credentials: 'same-origin'
            }).then(function(response) {
                return response.json();
            }).then(function(result) {
                if (result.success) {
                    label.textContent = active === '1' ? '<?php echo esc_js( __( 'Active', 'sk' ) ); ?>' : '<?php echo esc_js( __( 'Inactive', 'sk' ) ); ?>';
                } else {
                    checkbox.checked = !checkbox.checked;
                    alert('<?php echo esc_js( __( 'Failed to toggle module.', 'sk' ) ); ?>');
                }
            }).catch(function() {
                checkbox.checked = !checkbox.checked;
                alert('<?php echo esc_js( __( 'Failed to toggle module.', 'sk' ) ); ?>');
            });
        });
    });
})();
</script>
