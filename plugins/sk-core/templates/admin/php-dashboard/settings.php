<?php
/**
 * Settings form template
 *
 * @var array $sections Settings sections
 * @var string $current_section Active section ID
 * @var array $fields Field definitions for current section
 * @var array $values Saved values for current section
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="sk-settings-wrap">
    <h2 class="nav-tab-wrapper">
        <?php foreach ( $sections as $section ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=sk&tab=settings&section=' . $section['id'] ) ); ?>"
               class="nav-tab <?php echo $current_section === $section['id'] ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html( $section['title'] ); ?>
            </a>
        <?php endforeach; ?>
    </h2>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=sk&tab=settings&section=' . $current_section ) ); ?>">
        <?php wp_nonce_field( 'sk_php_settings_save', 'sk_php_settings_nonce' ); ?>
        <input type="hidden" name="sk_settings_section" value="<?php echo esc_attr( $current_section ); ?>">

        <table class="form-table">
            <tbody>
                <?php foreach ( $fields as $field ) :
                    $type    = $field['type'] ?? 'text';
                    $name    = $field['name'] ?? '';
                    $label   = $field['label'] ?? '';
                    $desc    = $field['desc'] ?? '';
                    $default = $field['default'] ?? '';
                    $value   = $values[ $name ] ?? $default;
                    $tooltip = $field['tooltip'] ?? '';

                    // show_if conditional
                    $show_if_attr = '';
                    if ( ! empty( $field['show_if'] ) ) {
                        $show_if_attr = ' data-show-if="' . esc_attr( wp_json_encode( $field['show_if'] ) ) . '"';
                    }

                    if ( $type === 'sub_section' ) : ?>
                        <tr>
                            <td colspan="2"><h3><?php echo esc_html( $label ); ?></h3></td>
                        </tr>
                    <?php continue;
                    endif;
                    ?>

                    <tr<?php echo $show_if_attr; ?>>
                        <th scope="row">
                            <label for="<?php echo esc_attr( $current_section . '_' . $name ); ?>">
                                <?php echo esc_html( $label ); ?>
                            </label>
                            <?php if ( $tooltip ) : ?>
                                <span class="dashicons dashicons-info" title="<?php echo esc_attr( $tooltip ); ?>" style="color:#999; cursor:help;"></span>
                            <?php endif; ?>
                        </th>
                        <td>
                            <?php
                            switch ( $type ) :
                                case 'text':
                                    ?>
                                    <input type="text"
                                           id="<?php echo esc_attr( $current_section . '_' . $name ); ?>"
                                           name="<?php echo esc_attr( $current_section . '[' . $name . ']' ); ?>"
                                           value="<?php echo esc_attr( $value ); ?>"
                                           class="regular-text"
                                           <?php if ( ! empty( $field['placeholder'] ) ) : ?>placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"<?php endif; ?>>
                                    <?php
                                    break;

                                case 'number':
                                    $min  = isset( $field['min'] ) ? ' min="' . esc_attr( $field['min'] ) . '"' : '';
                                    $max  = isset( $field['max'] ) ? ' max="' . esc_attr( $field['max'] ) . '"' : '';
                                    $step = isset( $field['step'] ) ? ' step="' . esc_attr( $field['step'] ) . '"' : '';
                                    ?>
                                    <input type="number"
                                           id="<?php echo esc_attr( $current_section . '_' . $name ); ?>"
                                           name="<?php echo esc_attr( $current_section . '[' . $name . ']' ); ?>"
                                           value="<?php echo esc_attr( $value ); ?>"
                                           class="small-text"
                                           <?php echo $min . $max . $step; ?>>
                                    <?php
                                    break;

                                case 'switcher':
                                    $checked = ( $value === 'on' ) ? 'checked' : '';
                                    ?>
                                    <input type="hidden" name="<?php echo esc_attr( $current_section . '[' . $name . ']' ); ?>" value="off">
                                    <label>
                                        <input type="checkbox"
                                               id="<?php echo esc_attr( $current_section . '_' . $name ); ?>"
                                               name="<?php echo esc_attr( $current_section . '[' . $name . ']' ); ?>"
                                               value="on"
                                               <?php echo $checked; ?>
                                               data-field-name="<?php echo esc_attr( $name ); ?>">
                                        <?php echo esc_html( $desc ); ?>
                                    </label>
                                    <?php $desc = ''; // Already shown inline.
                                    break;

                                case 'select':
                                    $options = $field['options'] ?? [];
                                    ?>
                                    <select id="<?php echo esc_attr( $current_section . '_' . $name ); ?>"
                                            name="<?php echo esc_attr( $current_section . '[' . $name . ']' ); ?>"
                                            data-field-name="<?php echo esc_attr( $name ); ?>">
                                        <?php foreach ( $options as $opt_value => $opt_label ) : ?>
                                            <option value="<?php echo esc_attr( $opt_value ); ?>" <?php selected( $value, $opt_value ); ?>>
                                                <?php echo esc_html( $opt_label ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php
                                    break;

                                case 'radio':
                                    $options = $field['options'] ?? [];
                                    ?>
                                    <fieldset>
                                        <?php foreach ( $options as $opt_value => $opt_label ) : ?>
                                            <label style="display: block; margin-bottom: 5px;">
                                                <input type="radio"
                                                       name="<?php echo esc_attr( $current_section . '[' . $name . ']' ); ?>"
                                                       value="<?php echo esc_attr( $opt_value ); ?>"
                                                       <?php checked( $value, $opt_value ); ?>>
                                                <?php echo esc_html( $opt_label ); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </fieldset>
                                    <?php
                                    break;

                                case 'radio_image':
                                    $options = $field['options'] ?? [];
                                    ?>
                                    <fieldset style="display: flex; gap: 15px; flex-wrap: wrap;">
                                        <?php foreach ( $options as $opt_value => $opt_data ) :
                                            $opt_label = is_array( $opt_data ) ? ( $opt_data['label'] ?? $opt_value ) : $opt_data;
                                            $opt_image = is_array( $opt_data ) ? ( $opt_data['image'] ?? '' ) : '';
                                            ?>
                                            <label style="text-align: center; cursor: pointer;">
                                                <?php if ( $opt_image ) : ?>
                                                    <img src="<?php echo esc_url( $opt_image ); ?>" alt="<?php echo esc_attr( $opt_label ); ?>" style="display:block; max-width:120px; margin-bottom:5px; border: 2px solid <?php echo $value === $opt_value ? '#0073aa' : '#ddd'; ?>; border-radius: 4px;">
                                                <?php endif; ?>
                                                <input type="radio"
                                                       name="<?php echo esc_attr( $current_section . '[' . $name . ']' ); ?>"
                                                       value="<?php echo esc_attr( $opt_value ); ?>"
                                                       <?php checked( $value, $opt_value ); ?>>
                                                <?php echo esc_html( $opt_label ); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </fieldset>
                                    <?php
                                    break;

                                case 'multicheck':
                                    $options       = $field['options'] ?? [];
                                    $saved_checks  = is_array( $value ) ? $value : [];
                                    ?>
                                    <fieldset>
                                        <?php foreach ( $options as $opt_value => $opt_label ) : ?>
                                            <label style="display: block; margin-bottom: 5px;">
                                                <input type="checkbox"
                                                       name="<?php echo esc_attr( $current_section . '[' . $name . '][' . $opt_value . ']' ); ?>"
                                                       value="<?php echo esc_attr( $opt_value ); ?>"
                                                       <?php checked( isset( $saved_checks[ $opt_value ] ) ); ?>>
                                                <?php echo esc_html( $opt_label ); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </fieldset>
                                    <?php
                                    break;

                                case 'file':
                                    ?>
                                    <input type="text"
                                           id="<?php echo esc_attr( $current_section . '_' . $name ); ?>"
                                           name="<?php echo esc_attr( $current_section . '[' . $name . ']' ); ?>"
                                           value="<?php echo esc_attr( $value ); ?>"
                                           class="regular-text">
                                    <button type="button" class="button sk-upload-btn"
                                            data-target="#<?php echo esc_attr( $current_section . '_' . $name ); ?>">
                                        <?php esc_html_e( 'Upload', 'sk-core' ); ?>
                                    </button>
                                    <?php
                                    break;

                                case 'wpeditor':
                                    $editor_id = str_replace( [ '[', ']' ], '_', $current_section . '_' . $name );
                                    wp_editor( $value, $editor_id, [
                                        'textarea_name' => $current_section . '[' . $name . ']',
                                        'textarea_rows' => 10,
                                        'media_buttons' => false,
                                        'teeny'         => true,
                                    ] );
                                    break;

                                case 'charges':
                                    // Withdraw charges table per method.
                                    $methods = $value;
                                    if ( ! is_array( $methods ) ) {
                                        $methods = [];
                                    }
                                    ?>
                                    <table class="widefat" style="max-width: 600px;">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e( 'Method', 'sk-core' ); ?></th>
                                                <th><?php esc_html_e( 'Fixed', 'sk-core' ); ?></th>
                                                <th><?php esc_html_e( 'Percentage', 'sk-core' ); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ( $methods as $method_key => $charges ) : ?>
                                                <tr>
                                                    <td><?php echo esc_html( $method_key ); ?></td>
                                                    <td>
                                                        <input type="text"
                                                               name="<?php echo esc_attr( $current_section . '[' . $name . '][' . $method_key . '][fixed]' ); ?>"
                                                               value="<?php echo esc_attr( $charges['fixed'] ?? '' ); ?>"
                                                               class="small-text">
                                                    </td>
                                                    <td>
                                                        <input type="text"
                                                               name="<?php echo esc_attr( $current_section . '[' . $name . '][' . $method_key . '][percentage]' ); ?>"
                                                               value="<?php echo esc_attr( $charges['percentage'] ?? '' ); ?>"
                                                               class="small-text">
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php
                                    break;

                                default:
                                    ?>
                                    <input type="text"
                                           id="<?php echo esc_attr( $current_section . '_' . $name ); ?>"
                                           name="<?php echo esc_attr( $current_section . '[' . $name . ']' ); ?>"
                                           value="<?php echo esc_attr( is_array( $value ) ? '' : $value ); ?>"
                                           class="regular-text">
                                    <?php
                                    break;
                            endswitch;

                            if ( ! empty( $desc ) ) : ?>
                                <p class="description"><?php echo wp_kses_post( $desc ); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php submit_button( __( 'Save Changes', 'sk-core' ) ); ?>
    </form>
</div>

<script>
(function() {
    // show_if conditional display
    document.querySelectorAll('[data-show-if]').forEach(function(row) {
        var condition = JSON.parse(row.getAttribute('data-show-if'));
        var keys = Object.keys(condition);

        function checkVisibility() {
            var visible = true;
            keys.forEach(function(key) {
                var expected = condition[key];
                var el = document.querySelector('[data-field-name="' + key + '"]');
                if (!el) {
                    var input = document.querySelector('[name$="[' + key + ']"]');
                    if (input) el = input;
                }
                if (el) {
                    var val;
                    if (el.type === 'checkbox') {
                        val = el.checked ? 'on' : 'off';
                    } else {
                        val = el.value;
                    }
                    if (Array.isArray(expected)) {
                        if (expected.indexOf(val) === -1) visible = false;
                    } else if (val !== expected) {
                        visible = false;
                    }
                }
            });
            row.style.display = visible ? '' : 'none';
        }

        checkVisibility();

        keys.forEach(function(key) {
            var el = document.querySelector('[data-field-name="' + key + '"]');
            if (!el) {
                el = document.querySelector('[name$="[' + key + ']"]');
            }
            if (el) {
                el.addEventListener('change', checkVisibility);
            }
        });
    });

    // WP Media uploader for file fields
    document.querySelectorAll('.sk-upload-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var target = document.querySelector(btn.getAttribute('data-target'));
            if (!target) return;

            if (typeof wp !== 'undefined' && wp.media) {
                var frame = wp.media({
                    title: 'Select File',
                    button: { text: 'Use this file' },
                    multiple: false
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    target.value = attachment.url;
                });
                frame.open();
            }
        });
    });
})();
</script>
