<?php

namespace SK\Core\Admin;

use SK\Core\Utilities\VendorUtil;

/**
 * User profile related tasks for wp-admin
 *
 */
class UserProfile {

    public function __construct() {
        add_action( 'show_user_profile', array( $this, 'add_meta_fields' ), 20 );
        add_action( 'edit_user_profile', array( $this, 'add_meta_fields' ), 20 );

        add_action( 'personal_options_update', array( $this, 'save_meta_fields' ) );
        add_action( 'edit_user_profile_update', array( $this, 'save_meta_fields' ) );

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Enqueue Script in admin profile
     *
     * @param  string $page
     *
     * @return void
     */
    public function enqueue_scripts( $page ) {
        if ( in_array( $page, array( 'profile.php', 'user-edit.php' ), true ) ) {
            wp_enqueue_media();

            $admin_admin_script = array(
                'ajaxurl'     => admin_url( 'admin-ajax.php' ),
                'nonce'       => wp_create_nonce( 'sk_reviews' ),
                'ajax_loader' => SK_CORE_ASSETS . '/images/ajax-loader.gif',
                'seller'      => array(
                    'available'    => __( 'Available', 'sk-core' ),
                    'notAvailable' => __( 'Not Available', 'sk-core' ),
                ),
            );

            wp_enqueue_style( 'sk-admin-user-profile' );
            wp_enqueue_script( 'speaking-url' );
            wp_localize_script( 'jquery', 'sk_user_profile', $admin_admin_script );
        }
    }

    /**
     * Add fields to user profile
     *
     * @param \WP_User $user
     *
     * @return void|false
     */
    public function add_meta_fields( $user ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        if ( ! user_can( $user, 'skdar' ) ) {
            return;
        }

        $selling        = get_user_meta( $user->ID, 'sk_enable_selling', true );
        $publishing     = get_user_meta( $user->ID, 'sk_publishing', true );
        $store_settings = sk_get_store_info( $user->ID );
        $banner         = ! empty( $store_settings['banner'] ) ? absint( $store_settings['banner'] ) : 0;
        $banner_url     = $banner ? wp_get_attachment_url( $banner ) : VendorUtil::get_vendor_default_banner_url();
        $feature_seller = get_user_meta( $user->ID, 'sk_feature_seller', true );

        $social_fields = sk_get_social_profile_fields();

        $address           = isset( $store_settings['address'] ) ? $store_settings['address'] : '';
        $address_street1   = isset( $store_settings['address']['street_1'] ) ? $store_settings['address']['street_1'] : '';
        $address_street2   = isset( $store_settings['address']['street_2'] ) ? $store_settings['address']['street_2'] : '';
        $address_city      = isset( $store_settings['address']['city'] ) ? $store_settings['address']['city'] : '';
        $address_zip       = isset( $store_settings['address']['zip'] ) ? $store_settings['address']['zip'] : '';
        $address_country   = isset( $store_settings['address']['country'] ) ? $store_settings['address']['country'] : '';
        $address_state     = isset( $store_settings['address']['state'] ) ? $store_settings['address']['state'] : '';
        $banner_width      = sk_get_vendor_store_banner_width();
        $banner_height     = sk_get_vendor_store_banner_height();

        $shop_slug = $user->data->user_nicename ?? '';
        if ( user_can( $user->ID, 'vendor_staff' ) ) {
            $vendor    = new \WP_User( get_user_meta( $user->ID, '_vendor_id', true ) );
            $shop_slug = $vendor->data->user_nicename ?? '';
        }

        $country_state = array(
            'country' => array(
                'label'       => __( 'Country', 'sk-core' ),
                'description' => '',
                'class'       => 'js_field-country',
                'type'        => 'select',
                'options'     => array( '' => __( 'Select a country&hellip;', 'sk-core' ) ) + WC()->countries->get_allowed_countries(),
            ),
            'state' => array(
                'label'       => __( 'State/County', 'sk-core' ),
                'description' => __( 'State/County or state code', 'sk-core' ),
                'class'       => 'js_field-state',
            ),
        );
        ?>
        <h3><?php esc_html_e( 'SK Options', 'sk-core' ); ?></h3>

        <table class="form-table">
            <tbody>
                <tr>
                    <th><?php esc_html_e( 'Banner', 'sk-core' ); ?></th>
                    <td>
                        <div class="sk-banner">
                            <div class="image-wrap<?php echo esc_attr( $banner ) ? '' : ' sk-hide'; ?>">
                                <input type="hidden" class="sk-file-field" value="<?php echo esc_attr( $banner ); ?>" name="sk_banner">
                                <img class="sk-banner-img" src="<?php echo esc_url( $banner_url ); ?>">

                                <a class="close sk-remove-banner-image">&times;</a>
                            </div>

                            <div class="button-area<?php echo esc_attr( $banner ) ? ' sk-hide' : ''; ?>">
                                <a href="#" class="sk-banner-drag button button-primary"><?php esc_html_e( 'Upload banner', 'sk-core' ); ?></a>
                                <p class="description">
                                    <?php
                                    printf(
                                        /* translators: %1$s: banner width, %2$s: banner height in integers */
                                        esc_attr__( 'Upload a banner for your store. Banner size is (%1$sx%2$s) pixels.', 'sk-core' ),
                                        esc_attr( $banner_width ),
                                        esc_attr( $banner_height )
                                    );
                                    ?>
                                </p>
                            </div>
                        </div> <!-- .sk-banner -->
                    </td>
                </tr>

                <tr>
                    <th><?php esc_html_e( 'Store name', 'sk-core' ); ?></th>
                    <td>
                        <input type="text" name="sk_store_name" class="regular-text" value="<?php echo esc_attr( $store_settings['store_name'] ); ?>">
                    </td>
                </tr>

                    <tr>
                        <th><?php esc_html_e( 'Store URL', 'sk-core' ); ?></th>
                        <td>
                            <?php if ( ! user_can( $user, 'vendor_staff' ) ) : ?>
                                <input type="text" name="sk_store_url" data-vendor="<?php echo esc_attr( $user->ID ); ?>" class="regular-text" id="seller-url" value="<?php echo esc_attr( $user->data->user_nicename ); ?>"><strong id="url-alart-mgs"></strong>
                            <?php endif; ?>
                            <p><small><?php echo esc_url( home_url() . '/' . sk_get_option( 'custom_store_url', 'sk_general', 'store' ) ); ?>/<strong id="url-alart"><?php echo esc_attr( $shop_slug ); ?></strong></small></p>
                        </td>
                    </tr>

                <tr>
                    <th><?php esc_html_e( 'Address 1', 'sk-core' ); ?></th>
                    <td>
                        <input type="text" name="sk_store_address[street_1]" class="regular-text" value="<?php echo esc_attr( $address_street1 ); ?>">
                    </td>
                </tr>

                <tr>
                    <th><?php esc_html_e( 'Address 2', 'sk-core' ); ?></th>
                    <td>
                        <input type="text" name="sk_store_address[street_2]" class="regular-text" value="<?php echo esc_attr( $address_street2 ); ?>">
                    </td>
                </tr>

                <tr>
                    <th><?php esc_html_e( 'Town/City', 'sk-core' ); ?></th>
                    <td>
                        <input type="text" name="sk_store_address[city]" class="regular-text" value="<?php echo esc_attr( $address_city ); ?>">
                    </td>
                </tr>

                <tr>
                    <th><?php esc_html_e( 'Zip Code', 'sk-core' ); ?></th>
                    <td>
                        <input type="text" name="sk_store_address[zip]" class="regular-text" value="<?php echo esc_attr( $address_zip ); ?>">
                    </td>
                </tr>

                <?php foreach ( $country_state as $key => $field ) : ?>
                    <tr>
                        <th><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
                        <td>
                            <?php if ( ! empty( $field['type'] ) && 'select' === (string) $field['type'] ) : ?>
                            <select name="sk_store_address[<?php echo esc_attr( $key ); ?>]" id="<?php echo esc_attr( $key ); ?>" class="<?php echo ( ! empty( $field['class'] ) ? esc_attr( $field['class'] ) : '' ); ?>" style="width: 25em;">
                                    <?php
									if ( 'country' === (string) $key ) {
										$selected = esc_attr( $address_country );
									} else {
										$selected = esc_attr( $address_state );
									}
									foreach ( $field['options'] as $option_key => $option_value ) :
                                        ?>
                                        <option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $selected, $option_key, true ); ?>><?php echo esc_attr( $option_value ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else : ?>
                                <?php
                                if ( 'country' === (string) $key ) {
                                    $value = esc_attr( $address_country );
                                } else {
                                    $value = esc_attr( $address_state );
                                }
                                ?>
                            <input type="text" name="sk_store_address[<?php echo esc_attr( $key ); ?>]" id="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" class="<?php echo ( ! empty( $field['class'] ) ? esc_attr( $field['class'] ) : 'regular-text' ); ?>" />
                            <?php endif; ?>

                            <span class="description"><?php echo wp_kses_post( $field['description'] ); ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>


                <tr>
                    <th><?php esc_html_e( 'Phone Number', 'sk-core' ); ?></th>
                    <td>
                        <input type="text" name="sk_store_phone" class="regular-text" value="<?php echo esc_attr( $store_settings['phone'] ); ?>">
                    </td>
                </tr>

                <?php
                /**
                 */
                do_action( 'sk_user_profile_after_phone_number', $store_settings, $user );
                ?>

                <?php foreach ( $social_fields as $key => $value ) { ?>

                    <tr>
                        <th><?php echo esc_attr( $value['title'] ); ?></th>
                        <td>
                            <input type="text" name="sk_social[<?php echo esc_attr( $key ); ?>]" class="regular-text" value="<?php echo isset( $store_settings['social'][ $key ] ) ? esc_url( $store_settings['social'][ $key ] ) : ''; ?>">
                        </td>
                    </tr>

                <?php } ?>

                <tr>
                    <th><?php esc_html_e( 'Payment Options : ', 'sk-core' ); ?></th>
                </tr>

                <?php if ( isset( $store_settings['payment']['paypal']['email'] ) ) { ?>
                    <tr>
                        <th><?php esc_html_e( 'Paypal Email ', 'sk-core' ); ?></th>
                        <td>
                            <input type="text" disabled class="regular-text" value="<?php echo esc_attr( $store_settings['payment']['paypal']['email'] ); ?>">
                        </td>
                    </tr>
                <?php } ?>
                <?php if ( isset( $store_settings['payment']['skrill']['email'] ) ) { ?>
                    <tr>
                        <th><?php esc_html_e( 'Skrill Email ', 'sk-core' ); ?></th>
                        <td>
                            <input type="text" disabled class="regular-text" value="<?php echo esc_attr( $store_settings['payment']['skrill']['email'] ); ?>">
                        </td>
                    </tr>
                <?php } ?>

                <?php if ( isset( $store_settings['payment']['bank'] ) ) { ?>
                    <tr>
                        <th><?php esc_html_e( 'Bank name ', 'sk-core' ); ?></th>
                        <td>
                            <input type="text" disabled class="regular-text" value="<?php echo isset( $store_settings['payment']['bank']['bank_name'] ) ? esc_attr( $store_settings['payment']['bank']['bank_name'] ) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Account Name ', 'sk-core' ); ?></th>
                        <td>
                            <input type="text" disabled class="regular-text" value="<?php echo isset( $store_settings['payment']['bank']['ac_name'] ) ? esc_attr( $store_settings['payment']['bank']['ac_name'] ) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Account Number ', 'sk-core' ); ?></th>
                        <td>
                            <input type="text" disabled class="regular-text" value="<?php echo isset( $store_settings['payment']['bank']['ac_number'] ) ? esc_attr( $store_settings['payment']['bank']['ac_number'] ) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Bank Address ', 'sk-core' ); ?></th>
                        <td>
                            <input type="text" disabled class="regular-text" value="<?php echo isset( $store_settings['payment']['bank']['bank_addr'] ) ? esc_attr( $store_settings['payment']['bank']['bank_addr'] ) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Routing Number', 'sk-core' ); ?></th>
                        <td>
                            <input type="text" disabled class="regular-text" value="<?php echo isset( $store_settings['payment']['bank']['routing_number'] ) ? esc_attr( $store_settings['payment']['bank']['routing_number'] ) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Bank IBAN ', 'sk-core' ); ?></th>
                        <td>
                            <input type="text" disabled class="regular-text" value="<?php echo isset( $store_settings['payment']['bank']['iban'] ) ? esc_attr( $store_settings['payment']['bank']['iban'] ) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Bank Swift ', 'sk-core' ); ?></th>
                        <td>
                            <input type="text" disabled class="regular-text" value="<?php echo isset( $store_settings['payment']['bank']['swift'] ) ? esc_attr( $store_settings['payment']['bank']['swift'] ) : ''; ?>">
                        </td>
                    </tr>
                <?php } ?>
                <tr>
                    <th><?php esc_html_e( 'Selling', 'sk-core' ); ?></th>
                    <td>
                        <label for="sk_enable_selling">
                            <input type="hidden" name="sk_enable_selling" value="no">
                            <input name="sk_enable_selling" type="checkbox" id="sk_enable_selling" value="yes" <?php checked( $selling, 'yes' ); ?> />
                            <?php esc_html_e( 'Enable Adding Products', 'sk-core' ); ?>
                        </label>

                        <p class="description"><?php esc_html_e( 'Enable or disable product adding capability', 'sk-core' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th><?php esc_html_e( 'Publishing', 'sk-core' ); ?></th>
                    <td>
                        <label for="sk_publish">
                            <input type="hidden" name="sk_publish" value="no">
                            <input name="sk_publish" type="checkbox" id="sk_publish" value="yes" <?php checked( $publishing, 'yes' ); ?> />
                            <?php esc_html_e( 'Publish product directly', 'sk-core' ); ?>
                        </label>

                        <p class="description"><?php esc_html_e( 'Bypass pending, publish products directly', 'sk-core' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th><?php esc_html_e( 'Featured vendor', 'sk-core' ); ?></th>
                    <td>
                        <label for="sk_feature">
                            <input type="hidden" name="sk_feature" value="no">
                            <input name="sk_feature" type="checkbox" id="sk_feature" value="yes" <?php checked( $feature_seller, 'yes' ); ?> />
                            <?php esc_html_e( 'Mark as featured vendor', 'sk-core' ); ?>
                        </label>

                        <p class="description"><?php esc_html_e( 'This vendor will be marked as a featured vendor.', 'sk-core' ); ?></p>
                    </td>
                </tr>

                <?php do_action( 'sk_seller_meta_fields', $user ); ?>

                <?php
                    wp_nonce_field( 'sk_update_user_profile_info', 'sk_update_user_profile_info_nonce' );
                ?>
            </tbody>
        </table>


        <script type="text/javascript">
        jQuery(function($){
            var SK_Settings = {

                init: function() {
                    $('a.sk-banner-drag').on('click', this.imageUpload);
                    $('a.sk-remove-banner-image').on('click', this.removeBanner);
                },

                imageUpload: function(e) {
                    e.preventDefault();

                    var file_frame,
                        self = $(this);

                    if ( file_frame ) {
                        file_frame.open();
                        return;
                    }

                    // Create the media frame.
                    file_frame = wp.media.frames.file_frame = wp.media({
                        title: jQuery( this ).data( 'uploader_title' ),
                        button: {
                            text: jQuery( this ).data( 'uploader_button_text' )
                        },
                        multiple: false
                    });

                    file_frame.on( 'select', function() {
                        var attachment = file_frame.state().get('selection').first().toJSON();

                        var wrap = self.closest('.sk-banner');
                        wrap.find('input.sk-file-field').val(attachment.id);
                        wrap.find('img.sk-banner-img').attr('src', attachment.url);
                        $('.image-wrap', wrap).removeClass('sk-hide');

                        $('.button-area').addClass('sk-hide');
                    });

                    file_frame.open();

                },

                removeBanner: function(e) {
                    e.preventDefault();

                    var self = $(this);
                    var wrap = self.closest('.image-wrap');
                    var instruction = wrap.siblings('.button-area');

                    wrap.find('input.sk-file-field').val('0');
                    wrap.addClass('sk-hide');
                    instruction.removeClass('sk-hide');
                }
            };

            SK_Settings.init();

            $('#seller-url').on( 'keydown', function(e) {
                var text = $(this).val();

                // Allow: backspace, delete, tab, escape, enter and .
                if ($.inArray(e.keyCode, [46, 8, 9, 27, 13, 91, 109, 110, 173, 189, 190]) !== -1 ||
                    // Allow: Ctrl+A
                    (e.keyCode == 65 && e.ctrlKey === true) ||
                    // Allow: home, end, left, right
                    (e.keyCode >= 35 && e.keyCode <= 39)) {
                        // let it happen, don't do anything
                        return;
                }

                if ((e.shiftKey || (e.keyCode < 65 || e.keyCode > 90) && (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105) ) {
                    e.preventDefault();
                }
            });

            $('#seller-url').on( 'keyup', function(e) {
                $('#url-alart').text( getSlug( $(this).val() ) );
            });

            $('#seller-url').on('focusout', function() {
                var self = $(this),
                data = {
                    action : 'shop_url',
                    url_slug : self.val(),
                    vendor_id: self.data('vendor'),
                    _nonce : sk_user_profile.nonce,
                };

                if ( self.val() === '' ) {
                    return;
                }

                var row = self.closest('td');

                row.block({ message: null, overlayCSS: { background: '#f1f1f1 url(' + sk_user_profile.ajax_loader + ') no-repeat center', opacity: 0.3 } });

                $.post( sk_user_profile.ajaxurl, data, function(resp) {

                    if ( resp.success === true ) {
                        $('#url-alart').removeClass('text-danger').addClass('text-success');
                        $('#url-alart-mgs').removeClass('text-danger').addClass('text-success').text(sk_user_profile.seller.available);
                    } else {
                        $('#url-alart').removeClass('text-success').addClass('text-danger');
                        $('#url-alart-mgs').removeClass('text-success').addClass('text-danger').text(sk_user_profile.seller.notAvailable);
                    }

                    row.unblock();
                } );
            });
        });
        </script>
        <?php
    }

    /**
     * Save user data
     *
     * @param int $user_id
     *
     * @return void
     */
    public function save_meta_fields( $user_id ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        if ( ! isset( $_POST['sk_update_user_profile_info_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['sk_update_user_profile_info_nonce'] ) ), 'sk_update_user_profile_info' ) ) {
            return;
        }

        if ( ! isset( $_POST['sk_enable_selling'] ) ) {
            return;
        }

        $selling         = sanitize_text_field( wp_unslash( $_POST['sk_enable_selling'] ) );
        $publishing      = isset( $_POST['sk_publish'] ) ? sanitize_text_field( wp_unslash( $_POST['sk_publish'] ) ) : '';
        $feature_seller  = isset( $_POST['sk_feature'] ) ? sanitize_text_field( wp_unslash( $_POST['sk_feature'] ) ) : '';
        $store_settings  = sk_get_store_info( $user_id );

        $store_settings['banner']     = isset( $_POST['sk_banner'] ) ? intval( $_POST['sk_banner'] ) : '';
        $store_settings['store_name'] = isset( $_POST['sk_store_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sk_store_name'] ) ) : '';
        $store_settings['address']    = isset( $_POST['sk_store_address'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['sk_store_address'] ) ) : [];
        $store_settings['phone']      = isset( $_POST['sk_store_phone'] ) ? sk_sanitize_phone_number( wp_unslash( $_POST['sk_store_phone'] ) ) : '';

        // social settings
        $social        = isset( $_POST['sk_social'] ) ? array_map( 'esc_url_raw', (array) wp_unslash( $_POST['sk_social'] ) ) : [];
        $social_fields = sk_get_social_profile_fields();
        foreach ( $social as $key => $value ) {
            if ( isset( $social_fields[ $key ] ) ) {
                $store_settings['social'][ $key ] = $social[ $key ];
            }
        }

        if ( isset( $_POST['sk_store_url'] ) ) {
            wp_update_user(
                array(
                    'ID'            => $user_id,
                    'user_nicename' => sanitize_title( wp_unslash( $_POST['sk_store_url'] ) ),
                )
            );
        }

        update_user_meta( $user_id, 'sk_profile_settings', $store_settings );
        update_user_meta( $user_id, 'sk_enable_selling', $selling );
        update_user_meta( $user_id, 'sk_publishing', $publishing );
        update_user_meta( $user_id, 'sk_feature_seller', $feature_seller );
        sk_set_store_name( $user_id, $store_settings['store_name'] );

        do_action( 'sk_process_seller_meta_fields', $user_id );
    }
}
