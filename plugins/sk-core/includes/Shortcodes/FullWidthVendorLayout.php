<?php

namespace SK\Core\Shortcodes;

use SK\Core\Contracts\Hookable;

/**
 * Fullwidth vendor layout
 */
class FullWidthVendorLayout implements Hookable {

    /**
     * Register hooks.
     */
    public function register_hooks(): void {
        add_action( 'sk_setup_wizard_styles', [ $this, 'update_layout_style' ] );
    }

    /**
     * Update vendor layout style option.
     */
    public function update_layout_style(): void {
        if ( ! is_admin() ) {
            return;
        }

        $appearance                        = get_option( 'sk_appearance', [] );
        $appearance['vendor_layout_style'] = 'latest';

        update_option( 'sk_appearance', $appearance );
    }
}
