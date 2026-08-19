<?php

namespace SK\Core;

/**
 * Extended Assets — i18n and admin localization.
 *
 * Note: this used to ship the complete translation catalogue to the browser as
 * sk_pro_i18n (roughly 1.7 MB inline on every dashboard and admin page). That was
 * consumed by the Vue/React frontend which has since been replaced by PHP
 * templates. Nothing in wp-content reads it any more, so it is no longer emitted.
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
        ];
        return array_merge( $default_script, $localize_script );
    }
}
