<?php

namespace SK\Core\Shipping;

use SK\Core\Cache;

/**
 * SK Shipping Zone Class
 *
 */
class ShippingZone {

    /**
     * Get All Zone
     *
     *
     * @return array
     */
    public static function get_zones() {
        $data_store = \WC_Data_Store::load( 'shipping-zone' );
        $raw_zones  = $data_store->get_zones();
        $zones      = array();
        $seller_id  = sk_get_current_user_id();

        foreach ( $raw_zones as $raw_zone ) {
            $zone              = new \WC_Shipping_Zone( $raw_zone );
            $enabled_methods   = $zone->get_shipping_methods( true );
            $methods_ids       = wp_list_pluck( $enabled_methods, 'id' );

            if ( ! $zone ) {
                continue;
            }

            $available_methods = self::available_shipping_methods( $zone );
            $locations         = array();

            foreach ( $zone->get_zone_locations() as $location ) {
                if ( 'postcode' !== $location->type ) {
                    $locations[] = $location->type . ':' . $location->code;
                }
            }

            if (
                in_array( 'sk_vendor_shipping', $methods_ids, true ) ||
                ( sk_ext()->module->is_active( 'table_rate_shipping' ) &&
                    array_intersect( [ 'sk_table_rate_shipping', 'sk_distance_rate_shipping' ], $methods_ids )
                )
            ) {
                $zones[ $zone->get_id() ]                            = $zone->get_data();
                $zones[ $zone->get_id() ]['zone_id']                 = $zone->get_id();
                $zones[ $zone->get_id() ]['formatted_zone_location'] = $zone->get_formatted_location();
                $zones[ $zone->get_id() ]['shipping_methods']        = self::get_shipping_methods( $zone->get_id(), $seller_id );
                $zones[ $zone->get_id() ]['available_methods']       = $available_methods;
            }
        }

        // Everywhere zone if has method called vendor shipping
        $overall_zone      = new \WC_Shipping_Zone( 0 );
        $enabled_methods   = $overall_zone->get_shipping_methods( true );
        $methods_ids       = wp_list_pluck( $enabled_methods, 'id' );
        $available_methods = self::available_shipping_methods( $overall_zone );

        if (
                in_array( 'sk_vendor_shipping', $methods_ids, true ) ||
                ( sk_ext()->module->is_active( 'table_rate_shipping' ) &&
                    array_intersect( [ 'sk_table_rate_shipping', 'sk_distance_rate_shipping' ], $methods_ids )
                )
            ) {
            $zones[ $overall_zone->get_id() ]                            = $overall_zone->get_data();
            $zones[ $overall_zone->get_id() ]['zone_id']                 = $overall_zone->get_id();
            $zones[ $overall_zone->get_id() ]['formatted_zone_location'] = $overall_zone->get_formatted_location();
            $zones[ $overall_zone->get_id() ]['shipping_methods']        = self::get_shipping_methods( $overall_zone->get_id(), $seller_id );
            $zones[ $overall_zone->get_id() ]['available_methods']       = $available_methods;
        }

        return $zones;
    }

    /**
     * Get single zone info
     *
     *
     * @return array
     */
    public static function get_zone( $zone_id ) {
        $zone      = array();
        $seller_id = sk_get_current_user_id();
        $zone_obj  = \WC_Shipping_Zones::get_zone_by( 'zone_id', $zone_id );

		if ( ! $zone_obj ) {
			return $zone;
		}

        $zone['data']                    = $zone_obj->get_data();
        $zone['formatted_zone_location'] = $zone_obj->get_formatted_location();
        $zone['shipping_methods']        = self::get_shipping_methods( $zone_id, $seller_id );
        $zone['locations']               = self::get_locations( $zone_id );
        $zone['available_methods']       = self::available_shipping_methods( $zone_obj );

        return $zone;
    }

    /**
     * Add Shipping Method for a zone
     *
     *
     * @return int|\WP_Error
     */
    public static function add_shipping_methods( $data ) {
        global $wpdb;

        $table_name = "{$wpdb->prefix}sk_shipping_zone_methods";

        if ( empty( $data['method_id'] ) ) {
            return new \WP_Error( 'no-method-id', __( 'No shipping method found for adding', 'sk' ) );
        }

        $result = $wpdb->insert(
            $table_name,
            array(
                'method_id'  => $data['method_id'],
                'zone_id'    => $data['zone_id'],
                'seller_id'  => sk_get_current_user_id(),
                'is_enabled' => 1,
                'settings'   => maybe_serialize( $data['settings'] ),
            ),
            array(
                '%s',
                '%d',
                '%d',
                '%d',
                '%s',
            )
        );

        if ( ! $result ) {
            return new \WP_Error( 'method-not-added', __( 'Shipping method not added successfully', 'sk' ) );
        }

        \WC_Cache_Helper::invalidate_cache_group( 'shipping_zones' ); // needed to invalidate cache set in get_zone_matching_package method
        \WC_Cache_Helper::get_transient_version( 'shipping', true ); // if vendor update shipping settings, need to invalidate transient

        return $wpdb->insert_id;
    }

    /**
     * Delete shipping method
     *
     *
     * @return bool|\mysqli_result|\WP_Error
     */
    public static function delete_shipping_methods( $data ) {
        global $wpdb;

        $table_name = "{$wpdb->prefix}sk_shipping_zone_methods";

        $result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table_name} WHERE zone_id=%d AND seller_id=%d AND instance_id=%d", $data['zone_id'], sk_get_current_user_id(), $data['instance_id'] ) );

        if ( ! $result ) {
            return new \WP_Error( 'method-not-deleted', __( 'Shipping method not deleted', 'sk' ) );
        }

        /**
         * Add a action for shipping method delete by vendor
         *
         *
         * @param int Zone id
         * @param int Instance id
         */
        do_action( 'sk_delete_shipping_zone_methods', $data['zone_id'], $data['instance_id'] );

        \WC_Cache_Helper::invalidate_cache_group( 'shipping_zones' ); // needed to invalidate cache set in get_zone_matching_package method
        \WC_Cache_Helper::get_transient_version( 'shipping', true ); // if vendor update shipping settings, need to invalidate transient

        return $result;
    }

    /**
     * Get Shipping Methods for a zone
     *
     *
     * @return array
     */
    public static function get_shipping_methods( $zone_id, $seller_id ) {
        global $wpdb;

        $sql               = "SELECT * FROM {$wpdb->prefix}sk_shipping_zone_methods WHERE `zone_id`={$zone_id} AND `seller_id`={$seller_id}";
        $results           = $wpdb->get_results( $sql );
        $method            = array();
        $zone_obj          = \WC_Shipping_Zones::get_zone_by( 'zone_id', $zone_id );
        $shipping_methods  = $zone_obj->get_shipping_methods( true );
        $available_methods = self::available_shipping_methods( $zone_obj );
        $is_tax_status     = '';

        if ( $shipping_methods ) {
            foreach ( $shipping_methods as $shipping_method ) {
                if ( 'sk_vendor_shipping' === $shipping_method->id ) {
                    $is_tax_status = $shipping_method->tax_status;
                    break;
                }
            }
        }

        foreach ( $results as $key => $result ) {
            if ( ! array_key_exists( $result->method_id, $available_methods ) ) {
                continue;
            }

            $default_settings = array(
                'title'       => self::get_method_label( $result->method_id ),
                'description' => __( 'Lets you charge a rate for shipping', 'sk' ),
                'cost'        => '0',
                'tax_status'  => 'none',
            );

            $method_id = $result->method_id . ':' . $result->instance_id;
            $settings = ! empty( $result->settings ) ? maybe_unserialize( $result->settings ) : array();
            $settings = wp_parse_args( $settings, $default_settings );

            $method[ $method_id ]['instance_id']   = $result->instance_id;
            $method[ $method_id ]['id']            = $result->method_id;
            $method[ $method_id ]['enabled']       = ( $result->is_enabled ) ? 'yes' : 'no';
            $method[ $method_id ]['title']         = apply_filters( 'sk_shipping_method_translatable_title', $settings['title'], $result->instance_id );
            $method[ $method_id ]['settings']      = array_map( 'stripslashes_deep', maybe_unserialize( $settings ) );
            $method[ $method_id ]['is_tax_status'] = $is_tax_status === 'none' ? 'no' : 'yes';

            if ( 'flat_rate' === $result->method_id && ! isset( $method[ $method_id ]['settings']['calculation_type'] ) ) {
                $method[ $method_id ]['settings']['calculation_type'] = 'class';
            }
        }

        return $method;
    }

    /**
     * Update shipping method settings
     *
     *
     * @return array|false
     */
    public static function update_shipping_method( $args ) {
        global $wpdb;

        $sanitizer = new SanitizeCost();

        if ( isset( $args['settings']['title'] ) ) {
			$args['settings']['title'] = sanitize_text_field( $args['settings']['title'] );
            do_action( 'sk_shipping_method_title_update', $args['settings']['title'], $args['instance_id'] );
		}
        if ( isset( $args['settings']['description'] ) ) {
			$args['settings']['description'] = sanitize_text_field( $args['settings']['description'] );
		}
        if ( isset( $args['settings']['cost'] ) ) {
            try {
                $args['settings']['cost'] = $sanitizer->sanitize_cost( $args['settings']['cost'] );
            } catch ( \Exception $e ) {
                wp_send_json_error( $e->getMessage() );
            }
		}

        $data = [
            'method_id' => $args['method_id'],
            'zone_id'   => $args['zone_id'],
            'seller_id' => empty( $args['seller_id'] ) ? sk_get_current_user_id() : $args['seller_id'],
            'settings'  => maybe_serialize( $args['settings'] ),
        ];

        $table_name = "{$wpdb->prefix}sk_shipping_zone_methods";
        $updated = $wpdb->update( $table_name, $data, array( 'instance_id' => $args['instance_id'] ), array( '%s', '%d', '%d', '%s' ) );

        if ( $updated ) {
            \WC_Cache_Helper::invalidate_cache_group( 'shipping_zones' ); // needed to invalidate cache set in get_zone_matching_package method
            \WC_Cache_Helper::get_transient_version( 'shipping', true ); // if vendor update shipping settings, need to invalidate transient
        }

        return false !== $updated ? $data : false;
    }

    /**
     * Toggle shipping method
     *
     *
     * @return bool|\WP_Error
     */
    public static function toggle_shipping_method( $data ) {
        global $wpdb;
        $table_name = "{$wpdb->prefix}sk_shipping_zone_methods";
        $updated    = $wpdb->update(
            $table_name, array( 'is_enabled' => $data['checked'] ), array(
                'instance_id' => $data['instance_id'],
                'zone_id' => $data['zone_id'],
                'seller_id' => sk_get_current_user_id(),
            ), array( '%d' )
        );

        if ( ! $updated ) {
            return new \WP_Error( 'method-not-toggled', __( 'Method enable or disable not working', 'sk' ) );
        }

        \WC_Cache_Helper::invalidate_cache_group( 'shipping_zones' ); // needed to invalidate cache set in get_zone_matching_package method
        \WC_Cache_Helper::get_transient_version( 'shipping', true ); // if vendor update shipping settings, need to invalidate transient

        return true;
    }

    /**
     * Get zone locations
     *
     *
     * @return array
     */
    public static function get_locations( $zone_id, $seller_id = null ) {
        global $wpdb;

        if ( ! $seller_id ) {
            $seller_id = sk_get_current_user_id();
        }

        $table_name = "{$wpdb->prefix}sk_shipping_zone_locations";
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE zone_id=%d AND seller_id=%d",
                array( $zone_id, $seller_id )
            )
        );

        $locations = array();

        if ( $results ) {
            foreach ( $results as $key => $result ) {
                $locations[] = [
                    'code' => $result->location_code,
                    'type' => $result->location_type,
                ];
            }
        }

        return $locations;
    }

    /**
     * Save zone location for seller
     *
     *
     * @return bool
     */
    public static function save_location( $location, $zone_id, $seller_id = 0 ) {
        global $wpdb;

        // Setup arrays for Actual Values, and Placeholders
        $values        = array();
        $place_holders = array();
        $seller_id     = empty( $seller_id ) ? sk_get_current_user_id() : $seller_id;
        $table_name    = "{$wpdb->prefix}sk_shipping_zone_locations";

        $query = "INSERT INTO {$table_name} (seller_id, zone_id, location_code, location_type) VALUES ";

        if ( ! empty( $location ) ) {
            foreach ( $location as $key => $value ) {
                array_push( $values, $seller_id, $zone_id, $value['code'], $value['type'] );
                $place_holders[] = "('%d', '%d', '%s', '%s')";
            }

            $query .= implode( ', ', $place_holders );

            $wpdb->query( $wpdb->prepare( "DELETE FROM {$table_name} WHERE zone_id=%d AND seller_id=%d", $zone_id, $seller_id ) );

            if ( $wpdb->query( $wpdb->prepare( "$query ", $values ) ) ) {
                return true;
            }
        } else {
            if ( $wpdb->query( $wpdb->prepare( "DELETE FROM {$table_name} WHERE zone_id=%d AND seller_id=%d", $zone_id, $seller_id ) ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get Shipping method label
     *
     *
     * @return void
     */
    public static function get_method_label( $method_id ) {
        if ( 'flat_rate' === $method_id ) {
            return __( 'Flat Rate', 'sk' );
        } elseif ( 'local_pickup' === $method_id ) {
            return __( 'Local Pickup', 'sk' );
        } elseif ( 'free_shipping' === $method_id ) {
            return __( 'Free Shipping', 'sk' );
        } elseif ( 'sk_table_rate_shipping' === $method_id ) {
            return apply_filters( 'sk_table_rate_shipping_label', __( 'Table Rate', 'sk' ) );
        } elseif ( 'sk_distance_rate_shipping' === $method_id ) {
            return apply_filters( 'sk_distance_rate_shipping_label', __( 'Distance Rate', 'sk' ) );
        } else {
            return __( 'Custom Shipping', 'sk' );
        }
    }

    /**
     * Find a matching zone for a given package.
     *
     * @param  array $package Shipping package.
     *
     * @return \WC_Shipping_Zone
     */
    public static function get_zone_matching_package( $package ) {
        $country          = strtoupper( wc_clean( $package['destination']['country'] ) );
        $state            = strtoupper( wc_clean( $package['destination']['state'] ) );
        $postcode         = wc_normalize_postcode( wc_clean( $package['destination']['postcode'] ) );
        $cache_key        = \WC_Cache_Helper::get_cache_prefix( 'shipping_zones' ) . 'sk_shipping_zone_' . md5( sprintf( '%s+%s+%s+%d', $country, $state, $postcode, $package['seller_id'] ) );
        $matching_zone_id = wp_cache_get( $cache_key, 'shipping_zones' ); // As this comes from WooCommerce

        if ( false === $matching_zone_id ) {
            $matching_zone_id = self::get_zone_id_from_package( $package );
            wp_cache_set( $cache_key, $matching_zone_id, 'shipping_zones' );
        }

        return new \WC_Shipping_Zone( $matching_zone_id ? $matching_zone_id : 0 );
    }

    /**
     * Find a matching zone ID for a given package.
     *
     * @param  object $package Package information.
     *
     * @return int
     */
    public static function get_zone_id_from_package( $package ) {
        global $wpdb;

        $country   = strtoupper( wc_clean( $package['destination']['country'] ) );
        $state     = strtoupper( wc_clean( $package['destination']['state'] ) );
        $continent = strtoupper( wc_clean( WC()->countries->get_continent_code_for_country( $country ) ) );
        $postcode  = wc_normalize_postcode( wc_clean( $package['destination']['postcode'] ) );
        $vendor_id = self::get_vendor_id_from_package( $package );

        // Work out criteria for our zone search.
        $criteria   = array();
        $criteria[] = $wpdb->prepare( "( ( locations.location_type = 'country' AND locations.location_code = %s )", $country );
        $criteria[] = $wpdb->prepare( "OR ( locations.location_type = 'state' AND locations.location_code = %s )", $country . ':' . $state );
        $criteria[] = $wpdb->prepare( "OR ( locations.location_type = 'continent' AND locations.location_code = %s )", $continent );
        $criteria[] = $wpdb->prepare( "OR ( locations.location_type = 'postcode' AND locations.location_code = %s )", $postcode );
        $criteria[] = 'OR ( locations.location_type IS NULL ) )';

        // Postcode range and wildcard matching.
        $postcode_locations = $wpdb->get_results(
            "SELECT zone_id, location_code
            FROM {$wpdb->prefix}sk_shipping_zone_locations
            WHERE location_type = 'postcode'
                AND seller_id = {$vendor_id}"
        );

        if ( $postcode_locations ) {
            $zone_ids_with_postcode_rules = array_map( 'absint', wp_list_pluck( $postcode_locations, 'zone_id' ) );
            $matches                      = wc_postcode_location_matcher( $postcode, $postcode_locations, 'zone_id', 'location_code', $country );
            $do_not_match                 = array_unique( array_diff( $zone_ids_with_postcode_rules, array_keys( $matches ) ) );

            if ( ! empty( $do_not_match ) ) {
                $criteria[] = 'AND locations.zone_id NOT IN (' . implode( ',', $do_not_match ) . ')';
            }
        }

        $criteria = implode( ' ', $criteria );

        $shipping_zone_ids = $wpdb->get_col(
            "SELECT locations.zone_id
            FROM {$wpdb->prefix}sk_shipping_zone_locations as locations
            LEFT JOIN {$wpdb->prefix}sk_shipping_zone_methods as methods ON locations.zone_id = methods.zone_id
            LEFT JOIN {$wpdb->prefix}woocommerce_shipping_zones as wc_zones ON locations.zone_id = wc_zones.zone_id
            WHERE
                methods.is_enabled = 1
                AND methods.settings IS NOT NULL
                AND wc_zones.zone_id IS NOT NULL
                AND locations.seller_id = {$vendor_id}
                AND {$criteria}
            GROUP BY locations.zone_id
            ORDER BY wc_zones.zone_order ASC"
        );

        $shipping_zones         = [];
        $zone_id_from_package   = 0;
        $customer_country_state = $country . ':' . $state;

        if ( ! empty( $shipping_zone_ids ) ) {
            $zone_locations = $wpdb->get_results(
                "SELECT locations.zone_id, locations.location_code, locations.location_type
                FROM {$wpdb->prefix}sk_shipping_zone_locations as locations
                LEFT JOIN {$wpdb->prefix}woocommerce_shipping_zones as wc_zones ON locations.zone_id = wc_zones.zone_id
                WHERE seller_id={$vendor_id} AND locations.zone_id IN (" . implode( ', ', $shipping_zone_ids ) . ')
                ORDER BY wc_zones.zone_order ASC',
                ARRAY_A
            );

            if ( ! empty( $zone_locations ) ) {
                foreach ( $zone_locations as $location ) {
                    $zone_id = $location['zone_id'];

                    if ( ! isset( $shipping_zones[ $zone_id ] ) ) {
                        $shipping_zones[ $zone_id ] = [
                            'zone_id'   => $zone_id,
                            'continent' => [],
                            'country'   => [],
                            'state'     => [],
                            'postcode'  => [],
                        ];
                    }

                    if ( 'postcode' === $location['location_type'] ) {
                        $shipping_zones[ $zone_id ]['postcode'][] = (object) [
                            'zone_id'       => $zone_id,
                            'location_code' => $location['location_code'],
                        ];
                    } else {
                        $shipping_zones[ $zone_id ][ $location['location_type'] ][] = $location['location_code'];
                    }
                }

                // Use cases similar to Truth Table
                $use_cases = [
                    // Continent, Country, State, Postcode
                    [ 1, 1, 1, 1 ],
                    [ 0, 1, 1, 1 ],
                    [ 1, 0, 1, 1 ],
                    [ 0, 0, 1, 1 ],
                    [ 1, 1, 0, 1 ],
                    [ 0, 1, 0, 1 ],
                    [ 1, 0, 0, 1 ],
                    [ 0, 0, 0, 1 ],
                    [ 1, 1, 1, 0 ],
                    [ 0, 1, 1, 0 ],
                    [ 1, 0, 1, 0 ],
                    [ 0, 0, 1, 0 ],
                    [ 1, 1, 0, 0 ],
                    [ 0, 1, 0, 0 ],
                    [ 1, 0, 0, 0 ],
                    [ 0, 0, 0, 0 ],
                ];

                foreach ( $shipping_zones as $shipping_zone ) {
                    foreach ( $use_cases as $use_case ) {
                        if ( $use_case[0] ) {
                            $check_continent = in_array( $continent, $shipping_zone['continent'], true );
                        } else {
                            $check_continent = empty( $shipping_zone['continent'] );
                        }

                        if ( $use_case[1] ) {
                            $check_country = in_array( $country, $shipping_zone['country'], true );
                        } else {
                            $check_country = empty( $shipping_zone['country'] );
                        }

                        if ( $use_case[2] ) {
                            $check_state = in_array( $customer_country_state, $shipping_zone['state'], true );
                        } else {
                            $check_state = empty( $shipping_zone['state'] );
                        }

                        if ( $use_case[3] ) {
                            $matches = wc_postcode_location_matcher( $postcode, $shipping_zone['postcode'], 'zone_id', 'location_code', $country );
                            reset( $matches );
                            $matched_zone_id = key( $matches );
                            $check_postcode  = absint( $matched_zone_id ) === absint( $shipping_zone['zone_id'] );
                        } else {
                            $check_postcode = empty( $shipping_zone['postcode'] );
                        }

                        if ( $check_postcode && $check_state && $check_country && $check_continent ) {
                            $zone_id_from_package = $shipping_zone['zone_id'];
                            break;
                        }
                    }

                    if ( $zone_id_from_package ) {
                        break;
                    }
                }
            }
        }

        if ( ! $zone_id_from_package ) {
            $zone_id_from_package = $wpdb->get_var(
                "SELECT `zone`.`zone_id` FROM `{$wpdb->prefix}woocommerce_shipping_zones` as `zone`
                LEFT JOIN {$wpdb->prefix}sk_shipping_zone_locations as `location` on `zone`.`zone_id` = `location`.`zone_id`
                LEFT JOIN {$wpdb->prefix}sk_shipping_zone_methods as method on `zone`.`zone_id` = `method`.`zone_id`
                WHERE `location`.`zone_id` is NULL
                AND `method`.`is_enabled` = 1
                AND `method`.`seller_id` = {$vendor_id}
                ORDER BY zone.zone_order ASC LIMIT 1"
            );
        }

        return apply_filters( 'sk_get_zone_id_from_package', $zone_id_from_package, $package );
    }

    /**
     * Get vendor id from package
     *
     * @param  int $package
     *
     * @return int
     */
    public static function get_vendor_id_from_package( $package ) {
        if ( ! $package ) {
            return 0;
        }

        $vendor_id = isset( $package['seller_id'] ) ? $package['seller_id'] : '';

        if ( ! $vendor_id ) {
            return 0;
        }

        return $vendor_id;
    }

    /**
     * Get all the zone ids of a vendor
     *
     * @param  object $package
     *
     * @return array
     */
    public static function get_vendor_all_zone_ids( $package ) {
        global $wpdb;
        $vendor_id = isset( $package['seller_id'] ) ? $package['seller_id'] : '';

        if ( ! $vendor_id ) {
            return 0;
        }

        $table_name = "{$wpdb->prefix}sk_shipping_zone_methods";
        $results    = $wpdb->get_results( $wpdb->prepare( "SELECT zone_id FROM {$table_name} WHERE seller_id=%d", $vendor_id ) );

        $zone_ids = array_map(
            function( $zone ) {
                return (int) $zone->zone_id;
            }, $results
        );

        return apply_filters( 'sk_get_vendor_all_zone_ids', $zone_ids, $package );
    }

    /**
     * Get zone id by postcode
     *
     *
     * @param  int $postcode
     * @param  int $vendor_id Deprecated
     *
     * @return int|false on failure
     */
    public static function get_zone_id_by_postcode( $postcode, $vendor_id = false ) {
        global $wpdb;

        if ( $vendor_id ) {
            /**
             * We had to deprecate `vendor_id` cause, we may have multiple vendor on the cart, whith different postcode.
             * Suppose Vendor_One ships porduct to zip code 10001 and Vendor_Two ships to 10002. If customer inserts 10002 and Vendor_Two's
             * Product is in the bottom on the cart then shipping won't be available for Vendor_Two as Vendor_One's shipping is false. So we will have to show Vendor_Two's available shipping anyway.
             */
            wc_deprecated_argument( 'vendor_id', '3.0.0', __CLASS__ . '::get_zone_id_by_postcode() doesn\'t require $vendor_id anymore.' );
        }

        $wc_shipping_zones    = "{$wpdb->prefix}woocommerce_shipping_zones";
        $sk_shipping_zones = "{$wpdb->prefix}sk_shipping_zone_locations";
        $vendor_zone_id       = $wpdb->get_var( $wpdb->prepare( "SELECT zone_id FROM {$sk_shipping_zones} WHERE location_code=%s AND location_type=%s", $postcode, 'postcode' ) );

        /**
         * We are making sure that `vendor_zone_id` is exsits in woocommerce_zone_ids to avoid `Uncaught Exception: Invalid data store`.
         *
         */
        $wc_zone_ids = $wpdb->get_results( "select zone_id from {$wc_shipping_zones}" );
        $wc_zone_ids = array_map(
            function( $zone ) {
                return intval( $zone->zone_id );
            }, $wc_zone_ids
        );

        $zone_id = in_array( $vendor_zone_id, $wc_zone_ids, true ) ? $vendor_zone_id : '';

        return apply_filters( 'sk_get_zone_id_by_postcode', $zone_id, $postcode );
    }

    /**
     * Get all avilable shipping methods
     *
     * @param object $zone
     *
     * @return array
     */
    public static function available_shipping_methods( $zone ) {
        $available_methods = [
            'flat_rate'     => __( 'Flat Rate', 'sk' ),
            'local_pickup'  => __( 'Local Pickup', 'sk' ),
            'free_shipping' => __( 'Free Shipping', 'sk' ),
        ];

        return apply_filters( 'sk_available_shipping_methods', $available_methods, $zone );
    }

    /**
     * Get shipping zone by order
     *
     *
     * @param \WC_Order $order     Order object.
     * @param int       $vendor_id Vendor ID.
     *
     * @return \WC_Shipping_Zone
     */
    public static function get_shipping_zone_by_order( \WC_Order $order, int $vendor_id ): \WC_Shipping_Zone {
        $zone = new \WC_Shipping_Zone( 0 );
        if ( $order->has_shipping_address() ) {
            $address = $order->get_address( 'shipping' );
        } elseif ( $order->has_billing_address() ) {
            $address = $order->get_address( 'billing' );
        } else {
            return $zone;
        }

        $package = array(
            'destination' => array(
                'country'   => $address['country'],
                'state'     => $address['state'],
                'postcode'  => $address['postcode'],
                'city'      => $address['city'],
                'address_1' => $address['address_1'],
                'address_2' => $address['address_2'],
            ),
            'seller_id'   => $vendor_id,
        );

        return self::get_zone_matching_package( $package );
    }
}
