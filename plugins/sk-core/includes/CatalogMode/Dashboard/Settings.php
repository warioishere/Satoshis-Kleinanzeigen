<?php

namespace SK\Core\CatalogMode\Dashboard;

use SK\Core\CatalogMode\Helper;

/**
 * Class Hooks
 *
 * This class will be responsible for admin settings of Catalog Mode feature
 *
 *
 */
class Settings {
    /**
     * Class constructor
     *
     *
     * @return void
     */
    public function __construct() {
        // if admin didn't enabled catalog mode, then return
        if ( ! Helper::is_enabled_by_admin() ) {
            return;
        }

        // Catalog mode fields — now inlined directly in store-form.php template
        // add_action( 'sk_settings_form_bottom', [ $this, 'render_settings_fields' ], 10, 2 );

        //save Catalog Mode settings fields data
        add_filter( 'sk_store_profile_settings_args', [ $this, 'save_settings_fields' ], 10, 2 );
    }

    /**
     * This method will render settings fields for Catalog Mode
     *
     *
     * @param array $store_settings
     * @param int   $user_id
     *
     * @return void
     */
    public function render_settings_fields( $user_id, $store_settings ) {
        // get default store settings
        $defaults = Helper::get_defaults();
        if ( ! isset( $store_settings['catalog_mode'] ) ) {
            $store_settings['catalog_mode'] = $defaults;
        }
        $hide_add_to_cart = ! empty( $store_settings['catalog_mode']['hide_add_to_cart_button'] )
            ? $store_settings['catalog_mode']['hide_add_to_cart_button'] : $defaults['hide_add_to_cart_button'];
        $hide_price       = ! empty( $store_settings['catalog_mode']['hide_product_price'] )
            ? $store_settings['catalog_mode']['hide_product_price'] : $defaults['hide_product_price'];
        ?>
        <?php wp_nonce_field( 'sk_catalog_mode_settings_action', '_sk_catalog_mode_nonce' ); ?>
        <?php if ( Helper::hide_add_to_cart_button_option_is_enabled_by_admin() ) : ?>
            <div class="sk-form-group">
                <label class="sk-w3 sk-control-label"
                        for="catalog_mode_hide_add_to_cart_button"><?php esc_html_e( 'Remove Add to Cart Button', 'sk-core' ); ?></label>
                <div class="sk-w5 sk-text-left">
                    <label for="catalog_mode_hide_add_to_cart_button">
                        <input type="checkbox" id="catalog_mode_hide_add_to_cart_button" value="on" name="catalog_mode[hide_add_to_cart_button]"
                            <?php checked( $hide_add_to_cart, 'on' ); ?> />
                        <span> <?php esc_html_e( 'Check to remove Add to Cart option from your products.', 'sk-core' ); ?></span>
                    </label>
                </div>
            </div>
            <div class="catalog_mode_extra_section">
                <?php if ( Helper::hide_product_price_option_is_enabled_by_admin() ) : ?>
                    <div class="sk-form-group">
                        <label class="sk-w3 sk-control-label" for="catalog_mode_hide_product_price"><?php esc_attr_e( 'Hide Product Price', 'sk-core' ); ?></label>
                        <div class="sk-w5 sk-text-left">
                            <label for="catalog_mode_hide_product_price">
                                <input type="checkbox" id="catalog_mode_hide_product_price" value="on" name="catalog_mode[hide_product_price]"
                                    <?php checked( $hide_price, 'on' ); ?> />
                                <span> <?php esc_html_e( 'Check to hide product price from your products.', 'sk-core' ); ?></span>
                            </label>
                        </div>
                    </div>
                <?php endif; ?>
                <?php do_action( 'sk_catalog_mode_extra_settings_section', $user_id, $store_settings['catalog_mode'] ); ?>
            </div>
        <?php endif; ?>
        <?php
        if ( Helper::hide_add_to_cart_button_option_is_enabled_by_admin() ) :
            ?>
            <script type="text/javascript">
                (function ($) {
                    $(document).ready(function () {
                        $('#catalog_mode_hide_add_to_cart_button').on('change', function () {
                            if ($(this).is(':checked')) {
                                $('div.catalog_mode_extra_section').show();
                            } else {
                                $('div.catalog_mode_extra_section').hide();
                                $('#catalog_mode_hide_product_price').prop('checked', false);
                            }
                        });
                        $('#catalog_mode_hide_add_to_cart_button').trigger('change');
                    });
                })(jQuery);
            </script>
            <?php
        endif;
    }

    /**
     * This method will save settings fields for Catalog Mode
     *
     *
     * @param int   $store_id
     * @param array $sk_settings
     *
     * @return array
     */
    public function save_settings_fields( $sk_settings, $store_id ) {
        if ( ! isset( $_POST['_sk_catalog_mode_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_sk_catalog_mode_nonce'] ), 'sk_catalog_mode_settings_action' ) ) {
            return $sk_settings;
        }

        if ( ! sk_is_user_seller( $store_id ) ) {
            return $sk_settings;
        }

        $sk_settings['catalog_mode']['hide_add_to_cart_button'] = isset( $_POST['catalog_mode']['hide_add_to_cart_button'] ) ? 'on' : 'off';
        $sk_settings['catalog_mode']['hide_product_price']      = isset( $_POST['catalog_mode']['hide_product_price'] ) ? 'on' : 'off';

        // set hide price to off if add to cart button is off
        if ( 'off' === $sk_settings['catalog_mode']['hide_add_to_cart_button'] ) {
            $sk_settings['catalog_mode']['hide_product_price'] = 'off';
        }

        return $sk_settings;
    }
}
