<?php
/**
 * SK Settings Address form Template
 *
 *
 */

$address         = isset( $profile_info['address'] ) ? $profile_info['address'] : '';
$address_street1 = isset( $profile_info['address']['street_1'] ) ? $profile_info['address']['street_1'] : '';
$address_street2 = isset( $profile_info['address']['street_2'] ) ? $profile_info['address']['street_2'] : '';
$address_city    = isset( $profile_info['address']['city'] ) ? $profile_info['address']['city'] : '';
$address_zip     = isset( $profile_info['address']['zip'] ) ? $profile_info['address']['zip'] : '';
$address_country = isset( $profile_info['address']['country'] ) ? $profile_info['address']['country'] : '';
$address_state   = isset( $profile_info['address']['state'] ) ? $profile_info['address']['state'] : '';

$label_class     = sk_is_seller_dashboard() ? 'sk-w3' : 'sk-hide';
$field_class     = sk_is_seller_dashboard() ? 'sk-w5' : '';

?>

<input type="hidden" id="sk_selected_country" value="<?php echo esc_attr( $address_country ); ?>"/>
<input type="hidden" id="sk_selected_state" value="<?php echo esc_attr( $address_state ); ?>"/>
<div class="sk-form-group">
    <label class="<?php echo esc_attr( $label_class ); ?> sk-control-label" for="setting_address"><?php esc_html_e( 'Address', 'sk-core' ); ?></label>

    <div id="sk-address-fields-wrapper" class="<?php echo esc_attr( $field_class ); ?> sk-text-left sk-address-fields">
        <?php if ( $seller_address_fields['street_1'] ) { ?>
            <div class="sk-form-group">
                <label class="control-label" for="sk_address[street_1]"><?php esc_html_e( 'Street ', 'sk-core' ); ?>
                    <?php
                    $required_attr = '';
                    if ( $seller_address_fields['street_1']['required'] ) {
                        $required_attr = 'required';
                        ?>
                        <span class="required"> *</span>
                    <?php } ?>
                </label>
                <input <?php echo esc_attr( $required_attr ); ?> <?php echo esc_attr( $disabled ); ?>
                    id="sk_address[street_1]"
                    value="<?php echo esc_attr( $address_street1 ); ?>"
                    name="sk_address[street_1]"
                    placeholder="<?php esc_attr_e( 'Street address', 'sk-core' ); ?>"
                    class="sk-form-control input-md" type="text">
            </div>
            <?php
        }
        if ( $seller_address_fields['street_2'] ) {
            ?>
            <div class="sk-form-group">
                <label class="control-label" for="sk_address[street_2]"><?php esc_html_e( 'Street 2', 'sk-core' ); ?>
                    <?php
                    $required_attr = '';
                    if ( $seller_address_fields['street_2']['required'] ) {
                        $required_attr = 'required';
                        ?>
                        <span class="required"> *</span>
                    <?php } ?>
                </label>
                <input <?php echo esc_attr( $required_attr ); ?> <?php echo esc_attr( $disabled ); ?>
                    id="sk_address[street_2]"
                    value="<?php echo esc_attr( $address_street2 ); ?>"
                    name="sk_address[street_2]"
                    placeholder="<?php esc_attr_e( 'Apartment, suite, unit etc. (optional)', 'sk-core' ); ?>"
                    class="sk-form-control input-md" type="text">
            </div>
            <?php
        }
        if ( $seller_address_fields['city'] || $seller_address_fields['zip'] ) {
            ?>
            <div class="sk-from-group">
                <?php if ( $seller_address_fields['city'] ) { ?>
                    <div class="sk-form-group sk-w6 sk-left sk-right-margin-30">
                        <label class="control-label" for="sk_address[city]"><?php esc_html_e( 'City', 'sk-core' ); ?>
                            <?php
                            $required_attr = '';
                            if ( $seller_address_fields['city']['required'] ) {
                                $required_attr = 'required';
                                ?>
                                <span class="required"> *</span>
                            <?php } ?>
                        </label>
                        <input <?php echo esc_attr( $required_attr ); ?> <?php echo esc_attr( $disabled ); ?>
                            id="sk_address[city]"
                            value="<?php echo esc_attr( $address_city ); ?>"
                            name="sk_address[city]"
                            placeholder="<?php esc_attr_e( 'Town / City', 'sk-core' ); ?>"
                            class="sk-form-control input-md" type="text">
                    </div>
                    <?php
                }
                if ( $seller_address_fields['zip'] ) {
                    ?>
                    <div class="sk-form-group sk-w5 sk-left">
                        <label class="control-label" for="sk_address[zip]"><?php esc_html_e( 'Post/ZIP Code', 'sk-core' ); ?>
                            <?php
                            $required_attr = '';
                            if ( $seller_address_fields['zip']['required'] ) {
                                $required_attr = 'required';
                                ?>
                                <span class="required"> *</span>
                            <?php } ?>
                        </label>
                        <input <?php echo esc_attr( $required_attr ); ?> <?php echo esc_attr( $disabled ); ?>
                            id="sk_address[zip]"
                            value="<?php echo esc_attr( $address_zip ); ?>"
                            name="sk_address[zip]"
                            placeholder="<?php esc_attr_e( 'Postcode / Zip', 'sk-core' ); ?>"
                            class="sk-form-control input-md" type="text">
                    </div>
                <?php } ?>
                <div class="sk-clearfix"></div>
            </div>
            <?php
        }

        if ( $seller_address_fields['country'] ) {
            $country_obj = new WC_Countries();
            $countries   = $country_obj->get_allowed_countries();
            $states      = $country_obj->states;
            ?>
            <div class="sk-form-group">
                <label class="control-label" for="sk_address[country]"><?php esc_html_e( 'Country ', 'sk-core' ); ?>
                    <?php
                    $required_attr = '';
                    if ( $seller_address_fields['country']['required'] ) {
                        $required_attr = 'required';
                        ?>
                        <span class="required"> *</span>
                    <?php } ?>
                </label>
                <select <?php echo esc_attr( $required_attr ); ?> <?php echo esc_attr( $disabled ); ?> name="sk_address[country]" class="country_to_state sk-form-control" id="sk_address_country">
                    <?php sk_country_dropdown( $countries, $address_country, false ); ?>
                </select>
            </div>
            <?php
        }
        if ( $seller_address_fields['state'] ) {
            $address_state_class = '';
            $is_input            = false;
            $no_states           = false;
            if ( isset( $states[ $address_country ] ) ) {
                if ( empty( $states[ $address_country ] ) ) {
                    $address_state_class = 'sk-hide';
                    $no_states           = true;
                }
            } else {
                $is_input = true;
            }
            ?>
            <div id="sk-states-box" class="sk-form-group">
                <label class="control-label" for="sk_address[state]"><?php esc_html_e( 'State ', 'sk-core' ); ?>
                    <?php
                    $required_attr = '';
                    if ( $seller_address_fields['state']['required'] ) {
                        $required_attr = 'required';
                        ?>
                        <span class="required"> *</span>
                    <?php } ?>
                </label>
                <?php
                if ( $is_input ) {
                    $required_attr = '';
                    if ( $seller_address_fields['state']['required'] ) {
                        $required_attr = 'required';
                    }
                    ?>
                    <input <?php echo esc_attr( $required_attr ); ?> <?php echo esc_attr( $disabled ); ?>
                        name="sk_address[state]"
                        class="sk-form-control <?php echo esc_attr( $address_state_class ); ?>"
                        id="sk_address_state"
                        value="<?php echo esc_attr( $address_state ); ?>"/>
                    <?php
                } else {
                    $required_attr = '';
                    if ( $seller_address_fields['state']['required'] ) {
                        $required_attr = 'required';
                    }
                    ?>
                    <select <?php echo esc_attr( $required_attr ); ?> <?php echo esc_attr( $disabled ); ?> name="sk_address[state]" class="sk-form-control wc-enhanced-select" id="sk_address_state">
                        <?php sk_state_dropdown( $states[ $address_country ], $address_state ); ?>
                    </select>
                <?php } ?>
            </div>
        <?php } ?>

        <?php
        /**
         * Add vendor address verification templates.
         *
         *
         * @param array $address               Vendor address info.
         * @param array $seller_address_fields Vendor required addresses.
         */
        do_action( 'sk_vendor_address_verification_template', $address, $seller_address_fields );

        ?>
    </div>
</div>
