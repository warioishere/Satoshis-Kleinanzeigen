<?php

namespace SK\Core;

use WC_Abstract_Privacy;
use WP_User;

/*
 * Privacy/GDPR related functionality which ties into WordPress functionality.
 *
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Abstract_Privacy' ) ) {
    return;
}

/**
 * SK_Privacy Class.
 */
class Privacy extends WC_Abstract_Privacy {

    /**
     * Init - hook into events.
     */
    public function __construct() {
        parent::__construct( __( 'SK', 'sk-core' ) );

        // This hook registers WooCommerce data exporters.
        $this->add_exporter( 'sk-vendor-data', __( 'Vendor Data', 'sk-core' ), [ $this, 'vendor_data_exporter' ] );

        // This hook registers WooCommerce data erasers.
        $this->add_eraser( 'sk-vendor-data', __( 'Vendor Data', 'sk-core' ), [ $this, 'vendor_data_eraser' ] );

        // Handles custom anonomization types not included in core.
        add_filter( 'wp_privacy_anonymize_data', [ $this, 'anonymize_custom_data_types' ], 10, 3 );
    }

    /**
     * Add privacy policy content for the privacy policy page.
     *
     */
    public function get_privacy_message() {
        $content = '
            <div class="wp-suggested-text">' .
            '<p class="privacy-policy-tutorial">' .
                __( 'This sample privacy policy includes the basics around what personal data your multivendor store may be collecting, storing and sharing, as well as who may have access to that data. Depending on what settings are enabled and which additional plugins are used, the specific information shared by your store will vary. We recommend consulting with a lawyer when deciding what information to disclose on your privacy policy.', 'sk-core' ) .
            '</p>' .
            '<p>' . __( 'We collect information about you during the checkout process on our store.', 'sk-core' ) . '</p>' .
            '<h2>' . __( 'What we collect and store', 'sk-core' ) . '</h2>' .
            '<p>' . __( 'While you visit our site, we’ll track:', 'sk-core' ) . '</p>' .
            '<ul>' .
                '<li>' . __( 'Stores you’ve viewed: we’ll use this to, for example, show you vendor stores you’ve recently viewed', 'sk-core' ) . '</li>' .
                '<li>' . __( 'Products you’ve viewed: we’ll use this to, for example, show you products you’ve recently viewed', 'sk-core' ) . '</li>' .
                '<li>' . __( 'Location, IP address and browser type: we’ll use this for purposes like estimating taxes and shipping', 'sk-core' ) . '</li>' .
                '<li>' . __( 'Shipping address: we’ll ask you to enter this so we can, for instance, estimate shipping before you place an order, and send you the order!', 'sk-core' ) . '</li>' .
            '</ul>' .
            '<p>' . __( 'We’ll also use cookies to keep track of cart contents while you’re browsing our site.', 'sk-core' ) . '</p>' .
            '<p class="privacy-policy-tutorial">' . __( 'Note: you may want to further detail your cookie policy, and link to that section from here.', 'sk-core' ) . '</p>' .
            '<p>' . __( 'When you purchase from us, we’ll ask you to provide information including your name, billing address, shipping address, email address, phone number, credit card/payment details and optional account information like username and password. We’ll use this information for purposes, such as, to:', 'sk-core' ) . '</p>' .
            '<ul>' .
                '<li>' . __( 'Send you information about your account and order', 'sk-core' ) . '</li>' .
                '<li>' . __( 'Respond to your requests, including refunds and complaints', 'sk-core' ) . '</li>' .
                '<li>' . __( 'Process payments and prevent fraud', 'sk-core' ) . '</li>' .
                '<li>' . __( 'Set up your account for our store', 'sk-core' ) . '</li>' .
                '<li>' . __( 'Comply with any legal obligations we have, such as calculating taxes', 'sk-core' ) . '</li>' .
                '<li>' . __( 'Improve our store offerings', 'sk-core' ) . '</li>' .
                '<li>' . __( 'Send you marketing messages, if you choose to receive them', 'sk-core' ) . '</li>' .
            '</ul>' .
            '<p>' . __( 'If you create an account, we will store your name, address, email and phone number, which will be used to populate the checkout for future orders.', 'sk-core' ) . '</p>' .
            '<p>' . __( 'We generally store information about you for as long as we need the information for the purposes for which we collect and use it, and we are not legally required to continue to keep it. For example, we will store order information for XXX years for tax and accounting purposes. This includes your name, email address and billing and shipping addresses.', 'sk-core' ) . '</p>' .
            '<p>' . __( 'We will also store comments or reviews, if you choose to leave them.', 'sk-core' ) . '</p>' .
            '<h2>' . __( 'Who on our team has access', 'sk-core' ) . '</h2>' .
            '<p>' . __( 'Members of our team have access to the information you provide us. For example, both Administrators and Shop Managers can access:', 'sk-core' ) . '</p>' .
            '<ul>' .
                '<li>' . __( 'Order information like what was purchased, when it was purchased and where it should be sent, and', 'sk-core' ) . '</li>' .
                '<li>' . __( 'Customer information like your name, email address, and billing and shipping information.', 'sk-core' ) . '</li>' .
            '</ul>' .
            '<p>' . __( 'Our team members have access to this information to help fulfill orders, process refunds and support you.', 'sk-core' ) . '</p>' .
            '<h2>' . __( 'What we share with others', 'sk-core' ) . '</h2>' .
            '<p class="privacy-policy-tutorial">' . __( 'In this section you should list who you’re sharing data with, and for what purpose. This could include, but may not be limited to, analytics, marketing, payment gateways, shipping providers, and third party embeds.', 'sk-core' ) . '</p>' .
            '<p>' . __( 'We share information with third parties who help us provide our orders and store services to you; for example --', 'sk-core' ) . '</p>' .
            '<h3>' . __( 'Payments', 'sk-core' ) . '</h3>' .
            '<p class="privacy-policy-tutorial">' . __( 'In this subsection you should list which third party payment processors you’re using to take payments on your store since these may handle customer data. We’ve included PayPal as an example, but you should remove this if you’re not using PayPal.', 'sk-core' ) . '</p>' .
            '<p>' . __( 'We accept payments through PayPal. When processing payments, some of your data will be passed to PayPal, including information required to process or support the payment, such as the purchase total and billing information.', 'sk-core' ) . '</p>' .
            '<p>' . __( 'Please see the <a href="https://www.paypal.com/us/webapps/mpp/ua/privacy-full">PayPal Privacy Policy</a> for more details.', 'sk-core' ) . '</p>' .
            '<h3>' . __( 'Modules', 'sk-core' ) . '</h3>' .
            '<p class="privacy-policy-tutorial">' . __( 'SK has premium modules that perform specific and special purpose tasks. Each of the modules collect additional information. Also third party extensions and integrations collect data that is applicable to the each of their individual privacy policy.', 'sk-core' ) . '</p>' .
            '</div>';

        return apply_filters( 'sk_privacy_policy_content', $content );
    }

    /**
     * Handle some custom types of data and anonymize them.
     *
     * @param string $anonymous anonymized string
     * @param string $type      type of data
     * @param string $data      the data being anonymized
     *
     * @return string anonymized string
     */
    public function anonymize_custom_data_types( $anonymous, $type, $data ) {
        switch ( $type ) {
            case 'address_state':
            case 'address_country':
                $anonymous = ''; // Empty string - we don't want to store anything after removal.
                break;

            case 'phone':
                $anonymous = preg_replace( '/\d/u', '0', $data );
                break;

            case 'numeric_id':
                $anonymous = 0;
                break;
        }

        return $anonymous;
    }

    /**
     * Export vendor personal data
     *
     *
     * @return void
     */
    public function vendor_data_exporter( $email_address, $page ) {
        $user           = get_user_by( 'email', $email_address ); // Check if user has an ID in the DB to load stored personal data.
        $data_to_export = [];

        if ( ! user_can( $user->ID, 'skdar' ) ) {
            return [
                'data' => $data_to_export,
                'done' => true,
            ];
        }

        if ( $user instanceof WP_User ) {
            $data_to_export[] = [
                'group_id'          => 'sk_vendor',
                'group_label'       => __( 'Vendor Data', 'sk-core' ),
                'group_description' => __( 'SK vendor personal data.', 'sk-core' ),
                'item_id'           => 'user',
                'data'              => $this->get_vendor_personal_data( $user ),
            ];
        }

        return [
            'data' => $data_to_export,
            'done' => true,
        ];
    }

    /**
     * Get vendor pers
     *
     *
     * @return void
     */
    public function get_vendor_personal_data( $user ) {
        $personal_data = [];
        $vendor        = sk()->vendor->get( $user->ID );

        if ( ! $vendor ) {
            return [];
        }

        $props_to_export = apply_filters(
            'sk_privacy_export_vendor_personal_data_props',
            [
                'store_name' => __( 'Store Name', 'sk-core' ),
                'social'     => __( 'Social', 'sk-core' ),
                'phone'      => __( 'Phone', 'sk-core' ),
                'address'    => __( 'Address', 'sk-core' ),
                'location'   => __( 'GEO Locations', 'sk-core' ),
                'banner'     => __( 'Banner Url', 'sk-core' ),
                'gravatar'   => __( 'Gravatar Url', 'sk-core' ),
            ],
            $vendor
        );

        $shop_data = $vendor->get_shop_info();

        foreach ( $props_to_export as $prop => $description ) {
            $value = '';

            if ( isset( $shop_data[ $prop ] ) && ! is_array( $shop_data[ $prop ] ) ) {
                $value = $shop_data[ $prop ];
            }

            if ( 'social' === $prop ) {
                $social_data  = [];
                $social_field = [
                    'fb'        => __( 'Facebook', 'sk-core' ),
                    'twitter'   => __( 'X', 'sk-core' ),
                    'pinterest' => __( 'Pinterest', 'sk-core' ),
                    'linkedin'  => __( 'Linkedin', 'sk-core' ),
                    'youtube'   => __( 'Youtube', 'sk-core' ),
                    'instagram' => __( 'Instagram', 'sk-core' ),
                    'flickr'    => __( 'Flickr', 'sk-core' ),
                ];

                foreach ( $social_field as $social_key => $social_data_title ) {
                    if ( ! empty( $shop_data['social'][ $social_key ] ) ) {
                        $social_data[] = sprintf( '%1$s: <a href="%2$s">%2$s</a>', $social_data_title, $shop_data['social'][ $social_key ] );
                    }
                }

                $value = implode( ', ', $social_data );
            }

            if ( 'address' === $prop ) {
                $address_data  = [];
                $address_field = [
                    'street_1' => __( 'Address 1', 'sk-core' ),
                    'street_2' => __( 'Address 2', 'sk-core' ),
                    'city'     => __( 'City', 'sk-core' ),
                    'zip'      => __( 'Postal Code', 'sk-core' ),
                    'country'  => __( 'Country', 'sk-core' ),
                    'state'    => __( 'State', 'sk-core' ),
                ];

                foreach ( $address_field as $address_key => $address_data_title ) {
                    if ( ! empty( $shop_data['address'][ $address_key ] ) ) {
                        if ( 'country' === $address_key ) {
                            $countries      = WC()->countries->get_countries();
                            $country_name   = ! empty( $countries[ $shop_data['address'][ $address_key ] ] ) ? $countries[ $shop_data['address'][ $address_key ] ] : '';
                            $address_data[] = $address_data_title . ': ' . $country_name;
                        } elseif ( 'state' === $address_key ) {
                            $states         = WC()->countries->get_states( $shop_data['address']['country'] );
                            $state_name     = isset( $states[ $shop_data['address'][ $address_key ] ] ) ? $states[ $shop_data['address'][ $address_key ] ] : $shop_data['address'][ $address_key ];
                            $address_data[] = $address_data_title . ': ' . $state_name;
                        } else {
                            $address_data[] = $address_data_title . ': ' . $shop_data['address'][ $address_key ];
                        }
                    }
                }

                $value = implode( ', ', $address_data );
            }

            if ( in_array( $prop, [ 'banner', 'gravatar' ], true ) ) {
                $attachment_url = wp_get_attachment_url( $shop_data[ $prop ] );
                $value          = sprintf( '<a href="%1$s">%1$s</a>', $attachment_url );
            }

            $value = apply_filters( 'sk_privacy_export_vendor_personal_data_prop_value', $value, $prop, $vendor );

            if ( $value ) {
                $personal_data[] = [
                    'name'  => $description,
                    'value' => $value,
                ];
            }
        }

        $payment_data    = [];
        $payment_profile = $vendor->get_payment_profiles();

        foreach ( $payment_profile as $payment_method => $method_data ) {
            $value = '';

            if ( 'bank' === $payment_method ) {
                $bank_data   = [];
                $name        = __( 'Bank Details', 'sk-core' );
                $bank_fields = [
                    'ac_name'        => __( 'Account Name', 'sk-core' ),
                    'ac_number'      => __( 'Account Number', 'sk-core' ),
                    'bank_name'      => __( 'Bank Name', 'sk-core' ),
                    'bank_addr'      => __( 'Bank Address', 'sk-core' ),
                    'routing_number' => __( 'Routing Number', 'sk-core' ),
                    'iban'           => __( 'IBAN', 'sk-core' ),
                    'swift'          => __( 'Swift Code', 'sk-core' ),
                ];

                foreach ( $bank_fields as $field_key => $field_value ) {
                    $bank_data[] = $field_value . ': ' . $payment_profile['bank'][ $field_key ];
                }

                $value = implode( ', ', $bank_data );
            }

            if ( 'paypal' === $payment_method ) {
                $name  = __( 'PayPal Email', 'sk-core' );
                $value = isset( $method_data['email'] ) ? $method_data['email'] : '';
            }

            if ( 'skrill' === $payment_method ) {
                $name  = __( 'Skrill Email', 'sk-core' );
                $value = isset( $method_data['email'] ) ? $method_data['email'] : '';
            }

            if ( $value ) {
                $payment_data[] = [
                    'name'  => $name,
                    'value' => $value,
                ];
            }

            $payment_data = apply_filters( 'sk_privacy_export_vendor_payment_data', $payment_data, $value, $name, $payment_profile );
        }

        $personal_data = array_merge( $personal_data, $payment_data );

        /**
         * Allow extensions to register their own personal data for this vendor for the export.
         *
         *
         * @param array    $personal_data array of name value pairs
         * @param WC_Order $order         a vendor object
         */
        $personal_data = apply_filters( 'sk_privacy_export_vendor_personal_data', $personal_data, $vendor );

        return $personal_data;
    }

    /**
     * Vendor Data Eraser.
     *
     *
     * @return array
     */
    public function vendor_data_eraser( $email_address, $page ) {
        $response = [
            'items_removed'  => false,
            'items_retained' => false,
            'message'        => [],
            'done'           => true,
        ];

        $user = get_user_by( 'email', $email_address ); // Check if user has an ID in the DB to load stored personal data.

        $vendor = sk()->vendor->get( $user->ID );

        if ( ! $vendor ) {
            return;
        }

        $shop_data = $vendor->get_shop_info();

        if ( ! is_array( $shop_data ) || empty( $shop_data ) ) {
            return;
        }

        $this->erase_array_data( $shop_data );

        $update_data  = [];
        $updated_data = $shop_data;

        if ( is_array( $updated_data ) && ! empty( $updated_data ) ) {
            $erased = true;
        } else {
            $erased = false;
        }

        if ( $erased ) {
            update_user_meta( $user->ID, 'sk_profile_settings', $updated_data );

            /* translators: vendor name. */
            $response['messages'][]    = sprintf( __( 'Vendor %s data is removed.', 'sk-core' ), $vendor->get_name() );
            $response['items_removed'] = true;
        }

        /*
         * Allow extensions to remove data for this vendor and adjust the response.
         *
         *
         * @param array    $response Array resonse data. Must include messages, num_items_removed, num_items_retained, done.
         */
        return apply_filters( 'sk_privacy_erase_personal_data_vendor', $response, $vendor );
    }

    /**
     * Errase array data
     *
     *
     * @param array
     *
     * @return array
     */
    public function erase_array_data( &$data ) {
        if ( ! is_array( $data ) ) {
            return;
        }

        foreach ( $data as $key => &$value ) {
            if ( is_array( $value ) ) {
                $this->erase_array_data( $value );
            } else {
                $value = '';
            }
        }
    }
}
