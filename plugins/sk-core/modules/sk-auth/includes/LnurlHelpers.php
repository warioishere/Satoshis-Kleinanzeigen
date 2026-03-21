<?php

namespace SK\Modules\Auth\Lnurl;

/**
 * Helper functions
 *
 * @author Joel Stüdle <joel.stuedle@gmail.com>
 * @since 1.0.0
 */

// https://www.php.net/manual/en/class.allowdynamicproperties.php

class Helpers {

	/**
	 * Get the active admin color scheme to use within plugin admin area
	 *
	 * @since 1.0.0
	 */
	public function get_admin_colors() {
		global $_wp_admin_css_colors;
		$admin_color = get_user_option( 'admin_color' );
		return $_wp_admin_css_colors[ $admin_color ]->colors;
	}

	/**
	 * Converts and hex color to rgb
	 *
	 * @param string $hex hex color
	 * @param boolean|number flase if alpha is not used else input number for rgb alpha value
	 * @return array rgb(a) array
	 * @since 1.0.0
	 */
	public static function validate_color_to_rgba( $hex ) {
		if ( ! str_starts_with( $hex, '#' ) ) {

			if ( str_starts_with( $hex, 'rgb(' ) && str_ends_with( $hex, ')' ) ) {
				$rgb = str_replace( 'rgb(', '', $hex );
				$rgb = str_replace( ')', '', $rgb );
				$rgb = str_replace( ' ', '', $rgb );
				$rgb = explode( ',', $rgb );
				if ( 3 === count( $rgb ) ) {
					return array(
						'r' => $rgb[0],
						'g' => $rgb[1],
						'b' => $rgb[2],
						'a' => hexdec( 0 ),
					);
				}
			}
			if ( str_starts_with( $hex, 'rgba(' ) && str_ends_with( $hex, ')' ) ) {
				$rgba = str_replace( 'rgba(', '', $hex );
				$rgba = str_replace( ')', '', $rgba );
				$rgba = str_replace( ' ', '', $rgba );
				$rgba = explode( ',', $rgba );
				if ( 4 === count( $rgba ) ) {
					return array(
						'r' => (int) $rgba[0],
						'g' => (int) $rgba[1],
						'b' => (int) $rgba[2],
						'a' => (int) round( (float) $rgba[3] * 255 ),
					);
				}
				if ( 3 === count( $rgba ) ) {
					return array(
						'r' => (int) $rgba[0],
						'g' => (int) $rgba[1],
						'b' => (int) $rgba[2],
						'a' => 0,
					);
				}
			}

			return array(
				'r' => hexdec( 0 ),
				'g' => hexdec( 0 ),
				'b' => hexdec( 0 ),
				'a' => hexdec( 0 ),
			);
		}

		$hex    = str_replace( '#', '', $hex );
		$length = strlen( $hex );
		$alpha  = hexdec( 0 );

		if ( 8 === $length ) {
			$alpha = hexdec( substr( $hex, 6, 2 ) );
			$hex   = substr( $hex, 0, 6 );
			$length = 6;
		}

		$rgba['r'] = hexdec( 6 === $length ? substr( $hex, 0, 2 ) : ( 3 === $length ? str_repeat( substr( $hex, 0, 1 ), 2 ) : 0 ) );
		$rgba['g'] = hexdec( 6 === $length ? substr( $hex, 2, 2 ) : ( 3 === $length ? str_repeat( substr( $hex, 1, 1 ), 2 ) : 0 ) );
		$rgba['b'] = hexdec( 6 === $length ? substr( $hex, 4, 2 ) : ( 3 === $length ? str_repeat( substr( $hex, 2, 1 ), 2 ) : 0 ) );
		$rgba['a'] = $alpha;
		return $rgba;
	}

	/**
	 * Minimal javascript minification
	 *
	 * @since    1.0.0
	 */
	public static function minimize_javascript( $javascript ) {
		// remove comments
		$javascript = preg_replace( '#^\s*//.+$#m', '', $javascript );
		// remove spaces
		$javascript = preg_replace( array( "/\s+\n/", "/\n\s+/", '/ +/' ), array( "\n", "\n ", ' ' ), $javascript );
		// remove line breaks
		$javascript = str_replace( "\n", ' ', $javascript );
		return $javascript;
	}

	/**
	 * Minimal css minification
	 *
	 * @since    1.0.0
	 */
	public static function minimize_css( $css ) {
		$css = str_replace( "\n", '', $css );
		$css = str_replace( '  ', ' ', $css );
		$css = str_replace( '  ', ' ', $css );
		$css = str_replace( ' {', '{', $css );
		$css = str_replace( '{ ', '{', $css );
		$css = str_replace( ' }', '}', $css );
		$css = str_replace( '} ', '}', $css );
		$css = str_replace( ', ', ',', $css );
		$css = str_replace( '; ', ';', $css );
		$css = str_replace( ': ', ':', $css );
		return $css;
	}
}
