<?php

namespace SK\Core\Vendor;

use SK\Core\Cache;
use WP_Error;
use WP_User_Query;
use SK\Core\Vendor\Vendor;

/**
 * Vendor Manager Class
 *
 */
class Manager {

    /**
     * Total vendors found
     *
     * @var integer
     */
    private $total_users;

    /**
     * Get all vendors
     *
     * @param array $args
     *
     *
     * @return array
     */
    public function all( $args = [] ) {
        return $this->get_vendors( $args );
    }

    /**
     * Get vendors
     *
     * @param array $args
     *
     * @return array
     */
    public function get_vendors( $args = [] ) {
        $vendors = [];

        $defaults = [
            'role__in'   => [ 'seller', 'administrator' ],
            'number'     => 10,
            'offset'     => 0,
            'orderby'    => 'ID',
            'order'      => 'ASC',
            'status'     => [ 'approved' ],
            'featured'   => '', // yes or no
            'meta_query' => [],
            'fields'     => 'all',
        ];

        $args = wp_parse_args( $args, $defaults );

        $status = (array) $args['status'];

        $meta_query = [ 'relation' => 'OR' ];

        foreach ( $status as $stat ) {
            if ( $stat === 'all' ) {
                continue;
            }

            $meta_query[] = [
                'key'     => 'sk_enable_selling',
                'value'   => ( $stat == 'approved' ) ? 'yes' : 'no',
                'compare' => '=',
            ];
        }

        if ( ! empty( $args['meta_query'] ) ) {
            $args['meta_query']['relation'] = 'AND';
            $args['meta_query'][]           = $meta_query;
        } else {
            $args['meta_query'] = $meta_query;
        }

        // if featured
        if ( 'yes' == $args['featured'] ) {
            $args['meta_query']['relation'] = 'AND';
            $args['meta_query'][] = [
                'key'     => 'sk_feature_seller',
                'value'   => 'yes',
                'compare' => '=',
            ];
        }

        unset( $args['status'] );
        unset( $args['featured'] );

        $user_query = new WP_User_Query( $args );
        $results    = $user_query->get_results();

        $this->total_users = $user_query->total_users;

        if ( $args['fields'] !== 'all' ) {
            return $results;
        }

        foreach ( $results as $result ) {
            $vendors[] = $this->get( $result );
        }

        return $vendors;
    }

    /**
     * Get total user according to query
     *
     *
     * @return int
     */
    public function get_total() {
        return $this->total_users;
    }

    /**
     * Get single vendor data
     *
     * @param object|integer $vendor
     *
     * @return object|Vendor instance
     */
    public function get( $vendor ) {
        return new Vendor( $vendor );
    }

    /**
     * Create a vendor
     *
     * @param array $data
     *
     * @return Vendor|WP_Error on failure
     */
    public function create( $data = [] ) {
        $defaults = [
            'user_login' => '', // sk_generate_username()
            'user_pass'  => wp_generate_password(),
        ];

        if ( ! empty( $data['email'] ) ) {
            $data['user_email'] = $data['email'];
            unset( $data['email'] );
        }

        $vendor_data = wp_parse_args( $data, $defaults );
        $vendor_data['role'] = 'seller'; // this value can't be edited
        $vendor_id   = wp_insert_user( $vendor_data );

        if ( is_wp_error( $vendor_id ) ) {
            return $vendor_id;
        }

        // send vendor registration email to admin and vendor
        if ( isset( $data['notify_vendor'] ) && sk_validate_boolean( $data['notify_vendor'] ) ) {
            wp_send_new_user_notifications( $vendor_id, 'both' );
        } else {
            wp_send_new_user_notifications( $vendor_id, 'admin' );
        }

        /**
         */
        $store_data = apply_filters( 'sk_vendor_create_data', [
            'store_name'              => ! empty( $data['store_name'] ) ? $data['store_name'] : '',
            'social'                  => ! empty( $data['social'] ) ? $data['social'] : [],
            'payment'                 => ! empty( $data['payment'] ) ? $data['payment'] : [
                'paypal' => [ 'email' ],
                'bank'   => [],
            ],
            'phone'                   => ! empty( $data['phone'] ) ? $data['phone'] : '',
            'show_email'              => ! empty( $data['show_email'] ) ? $data['show_email'] : 'no',
            'address'                 => ! empty( $data['address'] ) ? $data['address'] : [],
            'location'                => ! empty( $data['location'] ) ? $data['location'] : '',
            'banner'                  => ! empty( $data['banner_id'] ) ? $data['banner_id'] : 0,
            'icon'                    => ! empty( $data['icon'] ) ? $data['icon'] : '',
            'gravatar'                => ! empty( $data['gravatar_id'] ) ? $data['gravatar_id'] : 0,
            'enable_tnc'              => ! empty( $data['enable_tnc'] ) ? $data['enable_tnc'] : 'off',
            'store_tnc'               => ! empty( $data['store_tnc'] ) ? $data['store_tnc'] : '',
            'show_min_order_discount' => ! empty( $data['show_min_order_discount'] ) ? $data['show_min_order_discount'] : 'no',
        ], $data );

        $vendor = sk()->vendor->get( $vendor_id );

        if ( ! $vendor instanceof Vendor || $vendor->get_id() === 0 ) {
            return new WP_Error(
                'unable_to_create_vendor',
                __( 'Unable to create vendor', 'sk-core' ),
                400
            );
        }

        if ( current_user_can( 'manage_woocommerce' ) ) {
            if ( isset( $data['enabled'] ) && sk_validate_boolean( $data['enabled'] ) ) {
                $vendor->update_meta( 'sk_enable_selling', 'yes' );
            }

            if ( isset( $data['featured'] ) && sk_validate_boolean( $data['featured'] ) ) {
                $vendor->update_meta( 'sk_feature_seller', 'yes' );
            }

            if ( isset( $data['trusted'] ) && sk_validate_boolean( $data['trusted'] ) ) {
                $vendor->update_meta( 'sk_publishing', 'yes' );
            }

        }

        $vendor->update_meta( 'sk_profile_settings', $store_data );
        $vendor->update_meta( 'sk_store_name', $store_data['store_name'] );
        $vendor->set_store_name( $store_data['store_name'] );

        /**
         */
        do_action( 'sk_before_create_vendor', $vendor->get_id(), $data );

        $vendor->save();

        do_action( 'sk_new_vendor', $vendor_id );

        return $this->get( $vendor_id );
    }

    /**
     * Update a vendor
     *
     * @param int $vendor_id
     *
     * @param array $data
     *
     * @return object
     */
    public function update( $vendor_id, $data = [] ) {
        $vendor = $this->get( $vendor_id );

        if ( ! $data ) {
            return $vendor;
        }

        // default wp based user data
        if ( ! empty( $data['user_pass'] ) && get_current_user_id() === $vendor->get_id() ) {
            wp_update_user(
                [
                    'ID'        => $vendor->get_id(),
                    'user_pass' => $data['user_pass'],
                ]
            );
        }

        if ( ! empty( $data['first_name'] ) ) {
            wp_update_user(
                [
                    'ID'         => $vendor->get_id(),
                    'first_name' => wc_clean( $data['first_name'] ),
                ]
            );
        }

        if ( ! empty( $data['last_name'] ) ) {
            wp_update_user(
                [
                    'ID'        => $vendor->get_id(),
                    'last_name' => wc_clean( $data['last_name'] ),
                ]
            );
        }

        if ( ! empty( $data['user_nicename'] ) ) {
            wp_update_user(
                [
                    'ID'            => $vendor->get_id(),
                    'user_nicename' => wc_clean( $data['user_nicename'] ),
                ]
            );
        }

        if ( ! empty( $data['email'] ) ) {
            if ( ! is_email( $data['email'] ) ) {
                return new WP_Error( 'invalid_email', __( 'Email is not valid', 'sk-core' ) );
            }

            wp_update_user(
                [
                    'ID'         => $vendor->get_id(),
                    'user_email' => sanitize_email( $data['email'] ),
                ]
            );
        }

        // update vendor other metadata | @todo: move all other metadata to 'sk_profile_settings' meta
        if ( current_user_can( 'manage_woocommerce' ) ) {
            if ( isset( $data['enabled'] ) ) {
                $previously_enabled = $vendor->is_enabled();
                $newly_enabled      = sk_validate_boolean( $data['enabled'] );

                if ( $previously_enabled !== $newly_enabled ) {
                    $newly_enabled ? $vendor->make_active() : $vendor->make_inactive();
                }
            }

            if ( isset( $data['featured'] ) && sk_validate_boolean( $data['featured'] ) ) {
                $vendor->update_meta( 'sk_feature_seller', 'yes' );
            } else {
                $vendor->update_meta( 'sk_feature_seller', 'no' );
            }

            if ( isset( $data['trusted'] ) && sk_validate_boolean( $data['trusted'] ) ) {
                $vendor->update_meta( 'sk_publishing', 'yes' );
            } else {
                $vendor->update_meta( 'sk_publishing', 'no' );
            }

            if ( isset( $data['reset_sub_category'] ) && sk_validate_boolean( $data['reset_sub_category'] ) ) {
                $vendor->update_meta( 'reset_sub_category', 'yes' );
            } else {
                $vendor->update_meta( 'reset_sub_category', 'no' );
            }

        }

        // update vendor store data
        if ( ! empty( $data['store_name'] ) ) {
            $vendor->set_store_name( $data['store_name'] );
            $vendor->update_meta( 'sk_store_name', $data['store_name'] );
        }

        if ( ! empty( $data['phone'] ) ) {
            $vendor->set_phone( $data['phone'] );
        }

        if ( isset( $data['show_email'] ) && sk_validate_boolean( $data['show_email'] ) ) {
            $vendor->set_show_email( 'yes' );
        } else {
            $vendor->set_show_email( 'no' );
        }

        if ( isset( $data['gravatar_id'] ) && is_numeric( $data['gravatar_id'] ) ) {
            $vendor->set_gravatar_id( $data['gravatar_id'] );
        }

        if ( isset( $data['banner_id'] ) && is_numeric( $data['banner_id'] ) ) {
            $vendor->set_banner_id( $data['banner_id'] );
        }

        // for backward compatibility we'll allow both `enable_tnc` and `toc_enabled` to set store trams and condition settings
        if ( ( isset( $data['enable_tnc'] ) && sk_validate_boolean( $data['enable_tnc'] ) )
             || ( isset( $data['toc_enabled'] ) && sk_validate_boolean( $data['toc_enabled'] ) ) ) {
            $vendor->set_enable_tnc( 'on' );
        } else {
            $vendor->set_enable_tnc( 'off' );
        }

        if ( ! empty( $data['store_tnc'] ) ) {
            $vendor->set_store_tnc( $data['store_tnc'] );
        }

        if ( ! empty( $data['icon'] ) ) {
            $vendor->set_icon( $data['icon'] );
        }

        if ( ! empty( $data['social'] ) ) {
            $socials = $data['social'];

            foreach ( $socials as $key => $value ) {
                if ( is_callable( [ $vendor, "set_{$key}" ] ) ) {
                    $vendor->{"set_{$key}"}( $value );
                }
            }
        }

        if ( ! empty( $data['payment']['paypal'] ) ) {
            $payments = $data['payment']['paypal'];

            foreach ( $payments as $key => $value ) {
                if ( is_callable( [ $vendor, "set_paypal_{$key}" ] ) ) {
                    $vendor->{"set_paypal_{$key}"}( $value );
                }
            }
        }

        if ( ! empty( $data['payment']['bank'] ) ) {
            $payments = $data['payment']['bank'];

            foreach ( $payments as $key => $value ) {
                if ( is_callable( [ $vendor, "set_bank_{$key}" ] ) ) {
                    $vendor->{"set_bank_{$key}"}( $value );
                }
            }
        }

        if ( ! empty( $data['address'] ) ) {
            $address = $data['address'];

            foreach ( $address as $key => $value ) {
                if ( is_callable( [ $vendor, "set_{$key}" ] ) ) {
                    $vendor->{"set_{$key}"}( $value );
                }
            }
        }

        /**
         * Fires before a vendor is updated.
         *
         *
         * @param int   $vendor_id The ID of the vendor being updated.
         * @param array $data      The array of vendor data being updated.
         */
        do_action( 'sk_before_update_vendor', $vendor->get_id(), $data );

        $vendor->save();

        /**
         * Fires after a vendor has been updated.
         *
         *
         * @param int   $vendor_id The ID of the vendor that was updated.
         * @param array $data      The array of vendor data that was updated.
         */
        do_action( 'sk_update_vendor', $vendor->get_id(), $data );

        return $vendor->get_id();
    }

    /**
     * Delete vendor with reassign data
     *
     * @param $vendor_id
     * @param null $reassign
     *
     *
     * @return array
     */
    public function delete( $vendor_id, $reassign = null ) {
        $vendor = sk()->vendor->get( $vendor_id )->to_array();

        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $vendor_id, $reassign );

        do_action( 'sk_delete_vendor', $vendor_id );

        return $vendor;
    }

    /**
     * Get all featured Vendor
     *
     * @param array $args
     *
     * @return array
     */
    public function get_featured( $args = [] ) {
        $defaults = [
            'number'   => 10,
            'offset'   => 0,
            'featured' => 'yes',
        ];

        $args = wp_parse_args( $args, $defaults );

        return $this->get_vendors( $args );
    }
}
