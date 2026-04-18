<?php
/**
 * SK Forms — declarative form-field render helpers.
 *
 * All helpers render the `sk-settings-field` wrapper schema:
 *
 *   <div class="sk-settings-field">
 *       <label class="sk-settings-label">…</label>
 *       <div class="sk-settings-input">…</div>
 *   </div>
 *
 * Used by settings templates to avoid duplicated HTML. All user input is
 * automatically escaped (esc_attr/esc_html/esc_url as appropriate).
 *
 * @package SK\Core
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Internal helpers
 * ------------------------------------------------------------------------*/

if ( ! function_exists( 'sk_form_tooltip' ) ) {
	/**
	 * Render the standard "?" help tooltip markup.
	 *
	 * @param string $text      Tooltip text.
	 * @param string $placement Tippy placement — 'bottom' (default), 'top', 'left', 'right'.
	 * @return string HTML.
	 */
	function sk_form_tooltip( string $text, string $placement = 'bottom' ): string {
		if ( '' === $text ) {
			return '';
		}
		return sprintf(
			' <span class="sk-tooltips-help tips" data-placement="%1$s" data-original-title="%2$s"><i class="fas fa-question-circle"></i></span>',
			esc_attr( $placement ),
			esc_attr( $text )
		);
	}
}

if ( ! function_exists( 'sk_form_data_attrs' ) ) {
	/**
	 * Serialize a [key=>value] array as HTML data-* attributes.
	 *
	 * @param array $attrs
	 * @return string Leading space + data attrs, or empty string.
	 */
	function sk_form_data_attrs( array $attrs ): string {
		if ( empty( $attrs ) ) {
			return '';
		}
		$out = '';
		foreach ( $attrs as $key => $value ) {
			$out .= sprintf( ' data-%s="%s"', esc_attr( $key ), esc_attr( $value ) );
		}
		return $out;
	}
}

if ( ! function_exists( 'sk_form_required_badge' ) ) {
	/** Render the " *" required indicator next to a label. */
	function sk_form_required_badge( bool $required ): string {
		return $required ? ' <span class="required">*</span>' : '';
	}
}

/* -------------------------------------------------------------------------
 * Public helpers
 * ------------------------------------------------------------------------*/

if ( ! function_exists( 'sk_form_input' ) ) {
	/**
	 * Render a text/email/url/number/tel/hidden input inside sk-settings-field.
	 *
	 * @param array $args {
	 *     @type string $name         (required) Form field name.
	 *     @type string $value        (required) Current value — will be esc_attr()'d.
	 *     @type string $label        Left-side label text. Use '' for hidden or standalone.
	 *     @type string $type         'text' (default) | 'email' | 'url' | 'number' | 'tel' | 'hidden'.
	 *     @type string $id           Defaults to $name.
	 *     @type string $placeholder
	 *     @type bool   $required     Default false.
	 *     @type bool   $disabled     Default false.
	 *     @type string $step         For type=number.
	 *     @type string $min
	 *     @type string $max
	 *     @type string $tooltip      Rendered next to the label as help tooltip.
	 *     @type string $tooltip_placement 'bottom' (default) | 'top' etc.
	 *     @type string $hint         Help text below the input.
	 *     @type string $input_class  Extra classes on <input> (sk-form-control is always applied).
	 *     @type string $label_class  Extra classes on the label.
	 *     @type string $wrapper_class Extra classes on the outer .sk-settings-field.
	 *     @type array  $data_attrs   ['foo' => 'bar'] → data-foo="bar".
	 *     @type string $prefix       Raw HTML rendered BEFORE the <input> (e.g. icon addon).
	 *                                Wraps input+prefix in a .sk-input-group flex row.
	 *     @type string $extras       Raw HTML appended after the <input> inside sk-settings-input.
	 * }
	 * @return void Echoes HTML.
	 */
	function sk_form_input( array $args ): void {
		$name  = $args['name'] ?? '';
		$value = $args['value'] ?? '';
		$type  = $args['type'] ?? 'text';

		if ( 'hidden' === $type ) {
			printf(
				'<input type="hidden" name="%1$s" value="%2$s"%3$s />' . "\n",
				esc_attr( $name ),
				esc_attr( $value ),
				sk_form_data_attrs( (array) ( $args['data_attrs'] ?? [] ) )
			);
			return;
		}

		$label         = $args['label'] ?? '';
		$id            = $args['id'] ?? $name;
		$placeholder   = $args['placeholder'] ?? '';
		$required      = ! empty( $args['required'] );
		$disabled      = ! empty( $args['disabled'] );
		$step          = $args['step'] ?? '';
		$min           = $args['min'] ?? '';
		$max           = $args['max'] ?? '';
		$tooltip       = $args['tooltip'] ?? '';
		$tooltip_pos   = $args['tooltip_placement'] ?? 'bottom';
		$hint          = $args['hint'] ?? '';
		$input_class   = trim( 'sk-form-control ' . ( $args['input_class'] ?? '' ) );
		$label_class   = trim( 'sk-settings-label ' . ( $args['label_class'] ?? '' ) );
		$wrapper_class = trim( 'sk-settings-field ' . ( $args['wrapper_class'] ?? '' ) );
		$data_attrs    = sk_form_data_attrs( (array) ( $args['data_attrs'] ?? [] ) );
		$extras        = $args['extras'] ?? '';
		$prefix        = $args['prefix'] ?? '';

		$attrs = '';
		if ( $placeholder !== '' ) $attrs .= ' placeholder="' . esc_attr( $placeholder ) . '"';
		if ( $required )           $attrs .= ' required';
		if ( $disabled )           $attrs .= ' disabled';
		if ( $step !== '' )        $attrs .= ' step="' . esc_attr( $step ) . '"';
		if ( $min !== '' )         $attrs .= ' min="' . esc_attr( $min ) . '"';
		if ( $max !== '' )         $attrs .= ' max="' . esc_attr( $max ) . '"';
		?>
		<div class="<?php echo esc_attr( $wrapper_class ); ?>">
			<?php if ( $label !== '' ) : ?>
				<label class="<?php echo esc_attr( $label_class ); ?>" for="<?php echo esc_attr( $id ); ?>">
					<?php echo esc_html( $label ); ?><?php echo sk_form_required_badge( $required ); ?><?php echo sk_form_tooltip( $tooltip, $tooltip_pos ); ?>
				</label>
			<?php endif; ?>
			<div class="sk-settings-input">
				<?php if ( $prefix !== '' ) : ?><div class="sk-input-group"><?php echo $prefix; // phpcs:ignore ?><?php endif; ?>
				<input
					type="<?php echo esc_attr( $type ); ?>"
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					class="<?php echo esc_attr( $input_class ); ?>"
					<?php echo $attrs; echo $data_attrs; ?>
				/>
				<?php if ( $prefix !== '' ) : ?></div><?php endif; ?>
				<?php echo $extras; // phpcs:ignore ?>
				<?php if ( $hint !== '' ) : ?>
					<p class="sk-settings-hint"><?php echo wp_kses_post( $hint ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'sk_form_textarea' ) ) {
	/**
	 * Render a <textarea> inside sk-settings-field.
	 *
	 * Accepts same args as sk_form_input() plus:
	 *
	 * @param array $args {
	 *     @type int $rows Default 3.
	 * }
	 */
	function sk_form_textarea( array $args ): void {
		$name          = $args['name'] ?? '';
		$value         = $args['value'] ?? '';
		$label         = $args['label'] ?? '';
		$id            = $args['id'] ?? $name;
		$placeholder   = $args['placeholder'] ?? '';
		$required      = ! empty( $args['required'] );
		$disabled      = ! empty( $args['disabled'] );
		$rows          = (int) ( $args['rows'] ?? 3 );
		$tooltip       = $args['tooltip'] ?? '';
		$tooltip_pos   = $args['tooltip_placement'] ?? 'bottom';
		$hint          = $args['hint'] ?? '';
		$input_class   = trim( 'sk-form-control ' . ( $args['input_class'] ?? '' ) );
		$label_class   = trim( 'sk-settings-label ' . ( $args['label_class'] ?? '' ) );
		$wrapper_class = trim( 'sk-settings-field ' . ( $args['wrapper_class'] ?? '' ) );
		$data_attrs    = sk_form_data_attrs( (array) ( $args['data_attrs'] ?? [] ) );

		$attrs = '';
		if ( $placeholder !== '' ) $attrs .= ' placeholder="' . esc_attr( $placeholder ) . '"';
		if ( $required )           $attrs .= ' required';
		if ( $disabled )           $attrs .= ' disabled';
		?>
		<div class="<?php echo esc_attr( $wrapper_class ); ?>">
			<?php if ( $label !== '' ) : ?>
				<label class="<?php echo esc_attr( $label_class ); ?>" for="<?php echo esc_attr( $id ); ?>">
					<?php echo esc_html( $label ); ?><?php echo sk_form_required_badge( $required ); ?><?php echo sk_form_tooltip( $tooltip, $tooltip_pos ); ?>
				</label>
			<?php endif; ?>
			<div class="sk-settings-input">
				<textarea
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					class="<?php echo esc_attr( $input_class ); ?>"
					rows="<?php echo $rows; ?>"
					<?php echo $attrs; echo $data_attrs; ?>
				><?php echo esc_textarea( $value ); ?></textarea>
				<?php if ( $hint !== '' ) : ?>
					<p class="sk-settings-hint"><?php echo wp_kses_post( $hint ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'sk_form_select' ) ) {
	/**
	 * Render a <select> inside sk-settings-field.
	 *
	 * @param array $args {
	 *     @type string          $name           (required)
	 *     @type string          $value          Currently selected key.
	 *     @type string          $label
	 *     @type string          $id             Defaults to $name.
	 *     @type array|callable  $options        Either ['key' => 'label'] or a callable
	 *                                           that echoes <option>s (called with $value).
	 *     @type bool            $required
	 *     @type bool            $disabled
	 *     @type string          $tooltip
	 *     @type string          $hint
	 *     @type string          $input_class
	 *     @type string          $label_class
	 *     @type string          $wrapper_class
	 *     @type array           $data_attrs
	 * }
	 */
	function sk_form_select( array $args ): void {
		$name          = $args['name'] ?? '';
		$value         = $args['value'] ?? '';
		$label         = $args['label'] ?? '';
		$id            = $args['id'] ?? $name;
		$options       = $args['options'] ?? [];
		$required      = ! empty( $args['required'] );
		$disabled      = ! empty( $args['disabled'] );
		$tooltip       = $args['tooltip'] ?? '';
		$tooltip_pos   = $args['tooltip_placement'] ?? 'bottom';
		$hint          = $args['hint'] ?? '';
		$input_class   = trim( 'sk-form-control ' . ( $args['input_class'] ?? '' ) );
		$label_class   = trim( 'sk-settings-label ' . ( $args['label_class'] ?? '' ) );
		$wrapper_class = trim( 'sk-settings-field ' . ( $args['wrapper_class'] ?? '' ) );
		$data_attrs    = sk_form_data_attrs( (array) ( $args['data_attrs'] ?? [] ) );

		$attrs = '';
		if ( $required ) $attrs .= ' required';
		if ( $disabled ) $attrs .= ' disabled';
		?>
		<div class="<?php echo esc_attr( $wrapper_class ); ?>">
			<?php if ( $label !== '' ) : ?>
				<label class="<?php echo esc_attr( $label_class ); ?>" for="<?php echo esc_attr( $id ); ?>">
					<?php echo esc_html( $label ); ?><?php echo sk_form_required_badge( $required ); ?><?php echo sk_form_tooltip( $tooltip, $tooltip_pos ); ?>
				</label>
			<?php endif; ?>
			<div class="sk-settings-input">
				<select
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					class="<?php echo esc_attr( $input_class ); ?>"
					<?php echo $attrs; echo $data_attrs; ?>
				>
					<?php
					if ( is_callable( $options ) ) {
						call_user_func( $options, $value );
					} else {
						foreach ( (array) $options as $opt_key => $opt_label ) {
							printf(
								'<option value="%s"%s>%s</option>',
								esc_attr( $opt_key ),
								selected( $value, $opt_key, false ),
								esc_html( $opt_label )
							);
						}
					}
					?>
				</select>
				<?php if ( $hint !== '' ) : ?>
					<p class="sk-settings-hint"><?php echo wp_kses_post( $hint ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'sk_form_checkbox' ) ) {
	/**
	 * Render a checkbox (optionally with a hidden fallback input for "off" state)
	 * inside sk-settings-field.
	 *
	 * @param array $args {
	 *     @type string $name            (required)
	 *     @type bool   $checked
	 *     @type string $value           Default 'yes'.
	 *     @type string $label           Left-side label (above/beside the checkbox row).
	 *     @type string $checkbox_label  Text rendered to the right of the checkbox itself.
	 *     @type string $fallback_value  If non-empty, renders a hidden input BEFORE the
	 *                                   checkbox with this value — so unchecked submits
	 *                                   something instead of nothing.
	 *     @type string $id              Defaults to $name.
	 *     @type string $tooltip
	 *     @type string $hint
	 *     @type string $label_class
	 *     @type string $wrapper_class
	 * }
	 */
	function sk_form_checkbox( array $args ): void {
		$name           = $args['name'] ?? '';
		$checked        = ! empty( $args['checked'] );
		$value          = $args['value'] ?? 'yes';
		$label          = $args['label'] ?? '';
		$checkbox_label = $args['checkbox_label'] ?? '';
		$fallback       = $args['fallback_value'] ?? '';
		$id             = $args['id'] ?? $name;
		$tooltip        = $args['tooltip'] ?? '';
		$tooltip_pos    = $args['tooltip_placement'] ?? 'bottom';
		$hint           = $args['hint'] ?? '';
		$label_class    = trim( 'sk-settings-label ' . ( $args['label_class'] ?? '' ) );
		$wrapper_class  = trim( 'sk-settings-field ' . ( $args['wrapper_class'] ?? '' ) );
		?>
		<div class="<?php echo esc_attr( $wrapper_class ); ?>">
			<?php if ( $label !== '' ) : ?>
				<label class="<?php echo esc_attr( $label_class ); ?>" for="<?php echo esc_attr( $id ); ?>">
					<?php echo esc_html( $label ); ?><?php echo sk_form_tooltip( $tooltip, $tooltip_pos ); ?>
				</label>
			<?php endif; ?>
			<div class="sk-settings-input">
				<label class="sk-settings-checkbox">
					<?php if ( $fallback !== '' ) : ?>
						<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $fallback ); ?>">
					<?php endif; ?>
					<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php checked( $checked ); ?>>
					<?php if ( $checkbox_label !== '' ) : ?>
						<span><?php echo esc_html( $checkbox_label ); ?></span>
					<?php endif; ?>
				</label>
				<?php if ( $hint !== '' ) : ?>
					<p class="sk-settings-hint"><?php echo wp_kses_post( $hint ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'sk_form_media_upload' ) ) {
	/**
	 * Render a media upload component (banner or gravatar/image) inside sk-settings-field.
	 *
	 * Two variants are supported:
	 *   - 'banner'   — used for the wide store banner. Uses sk-banner / sk-banner-* JS hooks.
	 *   - 'gravatar' — used for square avatars, logos, OG images. Uses sk-gravatar / sk-gravatar-*.
	 *
	 * JS handlers in sk-core find components by these class names; don't rename them.
	 *
	 * @param array $args {
	 *     @type string $name           (required) Form field name receiving attachment ID.
	 *     @type int    $attachment_id  Current WP attachment ID (0 = none).
	 *     @type string $default_url    Fallback image URL when attachment_id is 0 (shown as preview instead of the upload button).
	 *     @type string $label
	 *     @type string $variant        'banner' | 'gravatar' (required).
	 *     @type string $upload_label   Default 'Upload Photo'.
	 *     @type string $hint
	 *     @type string $wrapper_class
	 * }
	 */
	function sk_form_media_upload( array $args ): void {
		$name          = $args['name'] ?? '';
		$attachment_id = (int) ( $args['attachment_id'] ?? 0 );
		$default_url   = (string) ( $args['default_url'] ?? '' );
		$label         = $args['label'] ?? '';
		$variant       = $args['variant'] ?? 'gravatar';
		$upload_label  = $args['upload_label'] ?? __( 'Upload Photo', 'sk-core' );
		$hint          = $args['hint'] ?? '';
		$wrapper_class = trim( 'sk-settings-field sk-settings-field--media ' . ( $args['wrapper_class'] ?? '' ) );

		// When the user hasn't uploaded an image yet, fall back to the
		// default URL so admins see a preview instead of just the upload
		// button. The button still shows (rendered below) so they can swap.
		$image_url = $attachment_id ? wp_get_attachment_url( $attachment_id ) : $default_url;

		// Map variant → CSS classes (class names preserved for JS compat).
		if ( 'banner' === $variant ) {
			$component_class = 'sk-banner';
			$wrap_class      = 'image-wrap';
			$img_class       = 'sk-banner-img';
			$btn_area_class  = 'button-area';
			$drag_class      = 'sk-banner-drag';
			$remove_class    = 'sk-remove-banner-image';
		} else {
			$component_class = 'sk-gravatar';
			$wrap_class      = 'gravatar-wrap';        // 'sk-left' removed — legacy float conflicted with flex centering.
			$img_class       = 'sk-gravatar-img';
			$btn_area_class  = 'gravatar-button-area';
			$drag_class      = 'sk-gravatar-drag';
			$remove_class    = 'sk-close sk-remove-gravatar-image';
		}
		?>
		<?php
		// Visibility:
		//   image-wrap: shown when we have ANY image to display (uploaded or default).
		//   button-area: HIDDEN only when user has uploaded their own (attachment_id > 0).
		//     With a default shown, the upload button stays visible so the user
		//     can replace it without first having to remove anything.
		$user_uploaded = $attachment_id > 0;
		?>
		<div class="<?php echo esc_attr( $wrapper_class ); ?>">
			<?php if ( $label !== '' ) : ?>
				<div class="sk-settings-label"><?php echo esc_html( $label ); ?></div>
			<?php endif; ?>
			<div class="sk-settings-input <?php echo esc_attr( $component_class ); ?>">
				<div class="<?php echo esc_attr( $wrap_class ); ?><?php echo $image_url ? '' : ' sk-hide'; ?>">
					<input type="hidden" class="sk-file-field" value="<?php echo esc_attr( $attachment_id ); ?>" name="<?php echo esc_attr( $name ); ?>">
					<img alt="<?php echo esc_attr( $label ); ?>" class="<?php echo esc_attr( $img_class ); ?>" src="<?php echo esc_url( $image_url ); ?>">
					<?php if ( $user_uploaded ) : ?>
						<a class="<?php echo esc_attr( $remove_class ); ?>">&times;</a>
					<?php endif; ?>
				</div>
				<div class="<?php echo esc_attr( $btn_area_class ); ?><?php echo $user_uploaded ? ' sk-hide' : ''; ?>">
					<a href="#" class="<?php echo esc_attr( $drag_class ); ?> sk-btn sk-btn-default">
						<i class="fas fa-cloud-upload-alt"></i> <?php echo esc_html( $upload_label ); ?>
					</a>
					<?php if ( $hint !== '' ) : ?>
						<p class="sk-settings-hint"><?php echo wp_kses_post( $hint ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
