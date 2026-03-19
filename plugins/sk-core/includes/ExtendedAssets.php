<?php

namespace SK\Core;

/**
 * Extended Assets — i18n and admin localization.
 */
class ExtendedAssets {

    public function __construct() {
        if ( is_admin() ) {
            add_filter( 'sk_admin_localize_script', [ $this, 'add_localized_data' ], 5 );
        } else {
            add_filter( 'sk_localized_args', [ $this, 'add_i18_localized_data' ], 5 );
        }
    }

    public function add_localized_data( $data ) {
        $data['sk_pro_i18n']        = array( 'sk' => sk_get_jed_locale_data( 'sk', SK_CORE_DIR . '/languages/' ) );
        $data['current_plan']       = sk_ext()->license->get_plan();
        $data['active_modules']     = sk_ext()->module->get_active_modules();
        $data['pro_has_license_key'] = sk_ext()->license->has_license_key();
        return $data;
    }

    public function add_i18_localized_data( $default_script ) {
        $localize_script = [
            'i18n_location_name'  => __( 'Please provide a location name!', 'sk' ),
            'i18n_location_state' => __( 'Please provide', 'sk' ),
            'i18n_country_name'   => __( 'Please provide a country!', 'sk' ),
            'i18n_invalid'        => __( 'Failed! Something went wrong', 'sk' ),
            'i18n_gravater'       => __( 'Upload a Photo', 'sk' ),
            'sk_pro_i18n'         => array( 'sk' => sk_get_jed_locale_data( 'sk', SK_CORE_DIR . '/languages/' ) ),
        ];
        return array_merge( $default_script, $localize_script );
    }
}
