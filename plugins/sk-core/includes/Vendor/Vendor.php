<?php

namespace SK\Core\Vendor;

use Automattic\WooCommerce\Utilities\NumberUtil;
use WC_Order;
use SK\Core\Cache;
use SK\Core\Utilities\VendorUtil;
use WP_Error;
use WP_User;

/**
 * SK Vendor
 *
 */
#[\AllowDynamicProperties]
class Vendor {
    /**
     * Set class public properties
     *
     *
     * @return void
     */
    public function __set( $key, $value ) {
        // exclude private properties from accessing directly
        if ( in_array( $key, [ 'shop_data', 'changes' ], true ) ) {
            return;
        }
        $this->{$key} = $value;
    }

    /**
     * Get public properties
     *
     *
     * @return mixed|null
     */
    public function __get( $key ) {
        // exclude private properties from accessing directly
        if ( in_array( $key, [ 'shop_data', 'changes' ], true ) ) {
            return null;
        }
        // check isset
        if ( isset( $this->{$key} ) ) {
            return $this->{$key};
        }
        return null;
    }

    /**
     * The vendor ID
     *
     * @var integer
     */
    public $id = 0;

    /**
     * Holds the user data object
     *
     * @var null|WP_User
     */
    public $data = null;

    /**
     * Holds the store info
     *
     * @var array
     */
    private $shop_data = array();

    /**
     * Holds the chanages data
     *
     * @var array
     */
    private $changes = array();

    /**
     * The constructor
     *
     * @param int|WP_User $vendor
     */
    public function __construct( $vendor = null ) {
        if ( is_numeric( $vendor ) ) {

            $the_user = get_user_by( 'id', $vendor );;

            if ( $the_user ) {
                $this->id   = $the_user->ID;
                $this->data = $the_user;
            }

        } elseif ( is_a( $vendor, 'WP_User' ) ) {
            $this->id   = $vendor->ID;
            $this->data = $vendor;
        }

        do_action( 'sk_vendor', $this );
    }

    /**
     * Magic method to access vendor properties
     *
     * When you try to access a property by calling a method
     * with 'get_' prefixed, this magic method will look into
     * shop_data for that property.
     *
     * @param string $name
     * @param array  $param
     *
     * @return mixed|void
     */
    public function __call( $name, $param ) {
        if ( strpos( $name, 'get_' ) === 0 ) {
            $function_name  = str_replace('get_', '', $name );

            if ( empty( $this->shop_data ) ) {
                $this->popluate_store_data();
            }

            return ! empty( $this->shop_data[$function_name] ) ? $this->shop_data[$function_name] : null;
        }
    }

    /**
     * Vendor info to array
     *
     *
     * @return array
     */
    public function to_array() {
        $data = array(
            'id'                    => $this->get_id(),
            'store_name'            => $this->get_shop_name(),
            'first_name'            => $this->get_first_name(),
            'last_name'             => $this->get_last_name(),
            'email'                 => $this->get_email(),
            'social'                => $this->get_social_profiles(),
            'phone'                 => $this->get_phone(),
            'show_email'            => $this->show_email(),
            'address'               => $this->get_address(),
            'location'              => $this->get_location(),
            'banner'                => $this->get_banner(),
            'banner_id'             => $this->get_banner_id(),
            'gravatar'              => $this->get_avatar(),
            'gravatar_id'           => $this->get_avatar_id(),
            'shop_url'              => $this->get_shop_url(),
            'toc_enabled'           => $this->toc_enabled(),
            'store_toc'             => $this->get_toc(),
            'featured'              => $this->is_featured(),
            'rating'                => $this->get_rating(),
            'enabled'               => $this->is_enabled(),
            'registered'            => $this->get_register_date(),
            'payment'               => $this->get_payment_profiles(),
            'trusted'               => $this->is_trusted(),
            'reset_sub_category'    => $this->get_reset_sub_category(),
        );

        return apply_filters( 'sk_vendor_to_array', $data, $this );
    }

    /**
     * Check if key is exist
     *
     * @param $key
     *
     * @return string
     */
    public function get_value( $key ) {
        return ! empty( $key ) ? $key : '';
    }

    /**
     * Check if the user is vendor
     *
     * @return boolean
     */
    public function is_vendor() {
        return sk_is_user_seller( $this->id );
    }

    /**
     * If the selling capacity is enabled
     *
     * @return boolean
     */
    public function is_enabled() {
        return sk_is_seller_enabled( $this->id );
    }

    /**
     * If the vendor is marked as trusted
     *
     * @return boolean
     */
    public function is_trusted() {
        return sk_is_seller_trusted( $this->id );
    }

    /**
     * If the vendor is marked as featured
     *
     * @return boolean
     */
    public function is_featured() {
        return 'yes' == get_user_meta( $this->id, 'sk_feature_seller', true );
    }

    /**
     * If reset sub category is enabled
     *
     * @return boolean
     */
    public function get_reset_sub_category() {
        return 'no' !== get_user_meta( $this->id, 'reset_sub_category', true );
    }

    /**
     * Populate store info
     *
     * @return void
     */
    public function popluate_store_data() {
        $defaults = array(
            'store_name'              => '',
            'social'                  => array(),
            'payment'                 => array( 'paypal' => array( 'email' ), 'bank' => array() ),
            'phone'                   => '',
            'show_email'              => 'no',
            'address'                 => array(),
            'location'                => '',
            'banner'                  => 0,
            'icon'                    => 0,
            'gravatar'                => 0,
            'show_min_order_discount' => 'no',
        );

        if ( ! $this->id ) {
            $this->shop_data = $defaults;
            return;
        }

        $shop_info = get_user_meta( $this->id, 'sk_profile_settings', true );
        $shop_info = is_array( $shop_info ) ? $shop_info : array();
        $shop_info = wp_parse_args( $shop_info, $defaults );
        $shop_info['address'] = empty( $shop_info['address'] ) ? []: $shop_info['address']; // Empty vendor address save issue fix

        $this->shop_data = apply_filters( 'sk_vendor_shop_data', $shop_info, $this );
    }

    /*
    |--------------------------------------------------------------------------
    | Getters
    |--------------------------------------------------------------------------
    */

    /**
     * Get the store info by lazyloading
     *
     * @return array
     */
    public function get_shop_info() {

        // return if already populated
        if ( ! empty( $this->shop_data ) ) {
            return $this->shop_data;
        }

        $this->popluate_store_data();

        return $this->shop_data;
    }

    /**
     * Get store info by key
     *
     * @param  string $item
     *
     * @return mixed
     */
    public function get_info_part( $item ) {
        $info = $this->get_shop_info();

        if ( is_array( $info ) && array_key_exists( $item, $info ) ) {
            return $info[ $item ];
        }
    }

    /**
     * Get store ID
     *
     *
     * @return int
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get the vendor name
     *
     * @return string
     */
    public function get_name() {
        if ( $this->id ) {
            return $this->get_value( $this->data->display_name );
        }
    }

    /**
     * Get the shop name
     *
     * @return string
     */
    public function get_shop_name() {
        return $this->get_info_part( 'store_name' );
    }

    /**
     * Get the shop URL
     *
     * @return string
     */
    public function get_shop_url() {
        return sk_get_store_url( $this->id );
    }

    /**
     * Get email address
     *
     * @return string
     */
    public function get_email() {
        if ( $this->id ) {
            return $this->get_value( $this->data->user_email );
        }
    }

    /**
     * Get first name
     *
     *
     * @return string
     */
    public function get_first_name() {
        if ( $this->id ) {
            return $this->get_value( $this->data->first_name );
        }
    }

    /**
     * Get last name
     *
     *
     * @return string
     */
    public function get_last_name() {
        if ( $this->id ) {
            return $this->get_value( $this->data->last_name );
        }
    }

    /**
     * Get last name
     *
     *
     * @return string
     */
    public function get_register_date() {
        if ( $this->id ) {
            return $this->get_value( $this->data->user_registered );
        }
    }

    /**
     * Get the shop name
     *
     * @return array
     */
    public function get_social_profiles() {
        return $this->get_info_part( 'social' );
    }

    /**
     * Get the shop payment profiles
     *
     * @return array
     */
    public function get_payment_profiles() {
        return $this->get_info_part( 'payment' );
    }

    /**
     * Get the phone name
     *
     * @return string
     */
    public function get_phone() {
        return $this->get_info_part( 'phone' );
    }

    /**
     * Get the shop address
     *
     * @return array
     */
    public function get_address() {
        return $this->get_info_part( 'address' );
    }

    /**
     * Get the shop location
     *
     * @return array
     */
    public function get_location() {
        $default  = array( 'lat' => 0, 'long' => 0 );
        $location = $this->get_info_part( 'location' );

        if ( $location ) {
            [ $default['lat'], $default['long'] ] = explode( ',', $location );
        }

        return $location;
    }

    /**
     * Get the store banner URL.
     *
     * This method first checks if a specific banner ID is set for the store and retrieves it. If not set,
     * it falls back to the default store banner defined in the SK settings.
     *
     *
     * @return string
     */
    public function get_banner(): string {
        // Check if a specific banner ID is set and return its URL.
        if ( $this->get_banner_id() ) {
            return wp_get_attachment_url( $this->get_banner_id() );
        }

        // Retrieve the default banner URL from settings, with fallback of the plugin's default banner.
        $banner_url = VendorUtil::get_vendor_default_banner_url();

        /**
         * Filters for the store banner URL.
         *
         * Allows overriding of the store banner URL via external plugins or themes.
         * This is particularly useful if there is a need to dynamically change the banner based on specific conditions or configurations.
         *
         *
         * @param string $banner_url The URL of the default banner.
         * @param Vendor $this       Instance of the current class.
         */
        return apply_filters( 'sk_get_banner_url', $banner_url, $this );
    }

    /**
     * Get the shop banner id
     *
     *
     * @return int
     */
    public function get_banner_id() {
        $banner_id = (int) $this->get_info_part( 'banner' );

        return $banner_id ? $banner_id : 0;
    }

    /**
     * Get the shop profile icon.
     *
     *
     * @return string
     */
    public function get_avatar() {
        $avatar_id = $this->get_avatar_id();

        // Check if a specific avatar ID is set and return its URL.
        if ( $avatar_id ) {
            return wp_get_attachment_url( $avatar_id );
        }

        // Retrieve the default avatar URL from settings, with fallback of the plugin's default avatar.
        $avatar_url = VendorUtil::get_vendor_default_avatar_url();

        /**
         * Filters for the store avatar URL.
         *
         * Allows overriding of the store avatar URL via external plugins or themes.
         * This is particularly useful if there is a need to dynamically change the avatar based on specific conditions or configurations.
         *
         *
         * @param string $avatar_url The URL of the default avatar.
         * @param Vendor $this       Instance of the current class.
         */
        return apply_filters( 'sk_get_avatar_url', $avatar_url, $this );
    }

    /**
     * Get shop gravatar id
     *
     *
     * @return int
     */
    public function get_avatar_id() {
        $avatar_id = (int) $this->get_info_part( 'gravatar' );

        return $avatar_id ? $avatar_id : 0;
    }

    /**
     * If should show the email
     *
     * @return boolean
     */
    public function show_email() {
        return 'yes' === $this->get_info_part( 'show_email' );
    }

    /**
     * Check if terms and conditions enabled
     *
     *
     * @return boolean
     */
    public function toc_enabled() {
        return 'on' === $this->get_info_part( 'enable_tnc' );
    }

    /**
     * Get terms and conditions
     *
     *
     * @return string
     */
    public function get_toc() {
        return $this->get_info_part( 'store_tnc' );
    }

    /**
     * Get a vendor products
     *
     * @return object
     */
    public function get_products() {
        $products = sk()->product->all( [ 'author' => $this->id ] );

        if ( ! $products ) {
            return null;
        }

        return $products;
    }

    /**
     * Get a vendor all published products
     *
     *
     * @return array
     */
    public function get_published_products() {
        $transient_group = "seller_product_data_{$this->get_id()}";
        $transient_key   = "get_published_products_{$this->get_id()}";

        if ( false === ( $products = Cache::get_transient( $transient_key, $transient_group ) ) ) {
            $products = sk()->product->all(
                [
                    'author'      => $this->id,
                    'post_status' => 'publish',
                    'fields'      => 'ids',
                ]
            );
            $products = $products->posts;
            Cache::set_transient( $transient_key, $products, $transient_group );
        }

        return $products;
    }

    /**
     * Get a vendor all published products
     *
     *
     * @return array
     */
    public function get_best_selling_products() {
        $transient_group = "seller_product_data_{$this->get_id()}";
        $transient_key   = "get_best_selling_products_{$this->get_id()}";

        if ( false === ( $products = Cache::get_transient( $transient_key, $transient_group ) ) ) {
            $args = [
                'author'         => $this->id,
                'post_status'    => 'publish',
                'fields'         => 'ids',
                'posts_per_page' => -1,
            ];

            $args['meta_query'] = [ //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                [
                    'key'     => '_stock_status',
                    'value'   => 'outofstock',
                    'compare' => '!=',
                ],
            ];

            $products = sk()->product->best_selling( $args );
            $products = $products->posts;
            Cache::set_transient( $transient_key, $products, $transient_group );
        }

        return $products;
    }

    /**
     * Get a vendor store published products categories
     *
     * @param bool $best_selling
     *
     *
     * @return array
     */
    public function get_store_categories( $best_selling = false ) {
        $transient_group = "seller_product_data_{$this->get_id()}";
        $transient_key = function_exists( 'wpml_get_current_language' ) ? 'get_store_categories_' . wpml_get_current_language() . '_' . $this->get_id()  : 'get_store_categories_' . $this->get_id();
        if ( $best_selling ) {
            $transient_key = function_exists( 'wpml_get_current_language' ) ? 'get_best_selling_categories_' . wpml_get_current_language() . '_' . $this->get_id() : 'get_best_selling_categories_' . $this->get_id();
        }

        if ( false === ( $all_categories = Cache::get_transient( $transient_key, $transient_group ) ) ) {
            $products = true === $best_selling ? $this->get_best_selling_products() : $this->get_published_products();
            if ( empty( $products ) ) {
                return [];
            }

            // Batch-load all terms for all products in one query.
            $terms = wp_get_object_terms( $products, 'product_cat', [ 'orderby' => 'name' ] );

            if ( empty( $terms ) || is_wp_error( $terms ) ) {
                return [];
            }

            // Deduplicate terms.
            $unique_terms = [];
            foreach ( $terms as $term ) {
                if ( ! isset( $unique_terms[ $term->term_id ] ) ) {
                    $unique_terms[ $term->term_id ] = $term;
                }
            }

            // Batch-load all term meta in one query.
            $term_ids = array_keys( $unique_terms );
            update_term_meta_cache( $term_ids );

            $all_categories = [];
            foreach ( $unique_terms as $term ) {
                $display_type        = get_term_meta( $term->term_id, 'display_type', true );
                $thumbnail_id        = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) );
                $category_icon       = get_term_meta( $term->term_id, 'sk_cat_icon', true );
                $category_icon_color = get_term_meta( $term->term_id, 'sk_cat_icon_color', true );

                if ( $thumbnail_id ) {
                    $thumbnail = wp_get_attachment_thumb_url( $thumbnail_id );
                    $image     = wp_get_attachment_url( $thumbnail_id );
                } else {
                    $image = $thumbnail = wc_placeholder_img_src();
                }

                $term->thumbnail    = $thumbnail;
                $term->image        = $image;
                $term->icon         = $category_icon;
                $term->icon_color   = $category_icon_color;
                $term->display_type = $display_type;

                $all_categories[] = $term;
            }

            Cache::set_transient( $transient_key, $all_categories, $transient_group );
        }

        return $all_categories;
    }

    /**
     * Get vendor used terms list.
     *
     *
     * @param $vendor_id
     * @param $taxonomy
     *
     * @return array|mixed
     */
    public function get_vendor_used_terms_list( $vendor_id, $taxonomy ){
        $transient_group = "seller_taxonomy_widget_data_{$this->get_id()}";
        $transient_key = function_exists( 'wpml_get_current_language' ) ? 'product_taxonomy_'. $taxonomy .'_' . wpml_get_current_language() : 'product_taxonomy_'. $taxonomy;

        $products = $this->get_published_products();
        if ( empty( $products ) ) {
            return [];
        }

        $author_terms = Cache::get_transient( $transient_key, $transient_group );

        if ( false !== $author_terms ) {
            return $author_terms;
        }

        // Single query: get all unique terms with vendor-specific product counts.
        global $wpdb;

        $product_ids  = implode( ',', array_map( 'absint', $products ) );
        $taxonomy_sql = $wpdb->prepare( '%s', $taxonomy );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_results(
            "SELECT t.term_id, t.name, t.slug, tt.taxonomy, tt.description, tt.parent,
                    COUNT( DISTINCT tr.object_id ) AS count
             FROM {$wpdb->term_relationships} tr
             INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
             WHERE tr.object_id IN ( {$product_ids} )
               AND tt.taxonomy = {$taxonomy_sql}
             GROUP BY t.term_id, t.name, t.slug, tt.taxonomy, tt.description, tt.parent
             ORDER BY t.name ASC"
        );

        $author_terms = [];
        if ( $results ) {
            foreach ( $results as $row ) {
                $term = new \WP_Term( $row );
                $term->count = (int) $row->count;
                $author_terms[] = $term;
            }
        }

        Cache::set_transient( $transient_key, $author_terms, $transient_group );

        return $author_terms;
    }

    /**
     * Get vendor orders
     *
     *
     * @return WP_Error|WC_Order[] objects
     */
    public function get_orders( $args = [] ) {
        $args['seller_id'] = empty( $args['seller_id'] ) ? $this->get_id() : $args['seller_id'];
        return wc_get_orders( $args );
    }

    /**
     * Get total pageview for all the products
     *
     * @return integer
     */
    public function get_product_views() {
        return (int) sk_author_pageviews( $this->id );
    }

    /**
     * Get vendor rating
     *
     *
     * @return array
     */
    public function get_rating() {
        global $wpdb;

        $result = $wpdb->get_row( $wpdb->prepare(
            "SELECT AVG(cm.meta_value) as average, COUNT(wc.comment_ID) as count FROM $wpdb->posts p
            INNER JOIN $wpdb->comments wc ON p.ID = wc.comment_post_ID
            LEFT JOIN $wpdb->commentmeta cm ON cm.comment_id = wc.comment_ID
            WHERE p.post_author = %d AND p.post_type = 'product' AND p.post_status = 'publish'
            AND ( cm.meta_key = 'rating' OR cm.meta_key IS NULL) AND wc.comment_approved = 1
            ORDER BY wc.comment_post_ID", $this->id ) );

        $rating_value = apply_filters( 'sk_seller_rating_value', array(
            'rating' => number_format( (float) $result->average, 2 ),
            'count'  => (int) $result->count
        ), $this->id );

        return $rating_value;
    }

    /**
     * Get vendor readable rating
     *
     *
     * @return void|string
     */
    public function get_readable_rating( $display = true ) {
        $rating = $this->get_rating( $this->id );

        if ( ! $rating['count'] ) {
            $html = __( 'No ratings found yet!', 'sk-core' );
        } else {
            $long_text   = _n( '%s rating from %d review', '%s rating from %d reviews', $rating['count'], 'sk-core' );
            $text        = sprintf( __( 'Rated %s out of %d', 'sk-core' ), $rating['rating'], number_format( 5 ) );
            $width       = ( $rating['rating']/5 ) * 100;
            $review_text = sprintf( $long_text, $rating['rating'], $rating['count'] );

            if ( function_exists( 'sk_get_review_url' ) ) {
                $review_text = sprintf( '<a href="%s">%s</a>', esc_url( sk_get_review_url( $this->id ) ), $review_text );
            }
            $stars = wc_get_rating_html( $rating['rating'], $rating['count'] );
            $html = '<span class="text">' . $review_text . '</span>' . '<span class="seller-rating">' . $stars . '</span>';
        }

        if ( ! $display ) {
            return $html;
        }

        echo esc_html( $html );
    }

    /**
     * Make vendor active
     *
     *
     * @return array
     */
    public function make_active() {
        $this->update_meta( 'sk_enable_selling', 'yes' );

        // change product status to publish
        $this->change_product_status( 'revert' );

        do_action( 'sk_vendor_enabled', $this->get_id() );

        return $this->to_array();
    }

    /**
     * Make vendor active
     *
     *
     * @return array
     */
    public function make_inactive() {
        $this->update_meta( 'sk_enable_selling', 'no' );

        // change product status to pending
        $this->change_product_status( 'change_status' );

        do_action( 'sk_vendor_disabled', $this->get_id() );

        return $this->to_array();
    }

    /**
     * Change product status when toggling seller active status
     *
     *
     * @param string $task_type
     *
     * @return void
     */
    public function change_product_status( $task_type ) {
        $product_status_changer = sk()->bg_process->change_vendor_product_status;
        $product_status_changer->reset();
        $product_status_changer->set_vendor_id( $this->get_id() );
        $product_status_changer->add_to_queue( $task_type );
    }

    /*
    |--------------------------------------------------------------------------
    | Setters
    |--------------------------------------------------------------------------
    */

    /**
     * Set enable tnc
     *
     * @param int value
     */
    public function set_enable_tnc( $value ) {
        $this->set_prop( 'enable_tnc', wc_clean( $value ) );
    }

    /**
     * Set store tnc
     *
     *
     * @param string
     *
     * @return void
     */
    public function set_store_tnc( $value ) {
        $this->set_prop( 'store_tnc', wc_clean( $value ) );
    }

    /**
     * Set gravatar
     *
     * @param int value
     */
    public function set_gravatar_id( $value ) {
        $this->set_prop( 'gravatar', (int) $value );
    }

    /**
     * Set banner
     *
     * @param int value
     */
    public function set_banner_id( $value ) {
        $this->set_prop( 'banner', (int) $value );
    }

    /**
     * Set banner
     *
     * @param int value
     */
    public function set_icon( $value ) {
        $this->set_prop( 'icon', (int) $value );
    }

    /**
     * Set store name
     *
     * @param string
     */
    public function set_store_name( $value ) {
        $this->set_prop( 'store_name', wc_clean( $value ) );
    }

    /**
     * Set phone
     *
     * @param string
     */
    public function set_phone( $value ) {
        $this->set_prop( 'phone', wc_clean( $value ) );
    }

    /**
     * Set show email
     *
     * @param string
     */
    public function set_show_email( $value ) {
        $this->set_prop( 'show_email', wc_clean( $value ) );
    }

    /**
     * Set show email
     *
     * @param string
     */
    public function set_fb( $value ) {
        $this->set_social_prop( 'fb', 'social', esc_url_raw( $value ) );
    }

    /**
     * Set show email
     *
     * @param string
     */
    public function set_gplus( $value ) {
        $this->set_social_prop( 'gplus', 'social', esc_url_raw( $value ) );
    }

    /**
     * Set show email
     *
     * @param string
     */
    public function set_twitter( $value ) {
        $this->set_social_prop( 'twitter', 'social', esc_url_raw( $value ) );
    }

    /**
     * Set show email
     *
     * @param string
     */
    public function set_pinterest( $value ) {
        $this->set_social_prop( 'pinterest', 'social', esc_url_raw( $value ) );
    }

    /**
     * Set show email
     *
     * @param string
     */
    public function set_linkedin( $value ) {
        $this->set_social_prop( 'linkedin', 'social', esc_url_raw( $value ) );
    }

    /**
     * Set show email
     *
     * @param string
     */
    public function set_youtube( $value ) {
        $this->set_social_prop( 'youtube', 'social', esc_url_raw( $value ) );
    }

    /**
     * Set TikTok
     *
     * @param string
     */
    public function set_tiktok( $value ) {
        $this->set_social_prop( 'tiktok', 'social', esc_url_raw( $value ) );
    }

    /**
     * Set show email
     *
     * @param string
     */
    public function set_instagram( $value ) {
        $this->set_social_prop( 'instagram', 'social', esc_url_raw( $value ) );
    }

    /**
     * Set threads
     *
     * @param string
     */
    public function set_threads( $value ) {
        $this->set_social_prop( 'threads', 'social', esc_url_raw( $value ) );
    }

    /**
     * Set flickr
     *
     * @param string
     */
    public function set_flickr( $value ) {
        $this->set_social_prop( 'flickr', 'social', esc_url_raw( $value ) );
    }

    /**
     * Set paypal email
     *
     * @param string $value
     */
    public function set_paypal_email( $value ) {
        $this->set_payment_prop( 'email', 'paypal', sanitize_email( $value ) );
    }

    /**
     * Set bank ac name
     *
     * @param string $value
     */
    public function set_bank_ac_name( $value ) {
        $this->set_payment_prop( 'ac_name', 'bank', wc_clean( $value ) );
    }

    /**
     * Set bank ac type
     *
     * @param string $value
     */
    public function set_bank_ac_type( $value ) {
        $this->set_payment_prop( 'ac_type', 'bank', wc_clean( $value ) );
    }

    /**
     * Set bank ac number
     *
     * @param string $value
     */
    public function set_bank_ac_number( $value ) {
        $this->set_payment_prop( 'ac_number', 'bank', wc_clean( $value ) );
    }

    /**
     * Set bank name
     *
     * @param string $value
     */
    public function set_bank_bank_name( $value ) {
        $this->set_payment_prop( 'bank_name', 'bank', wc_clean( $value ) );
    }

    /**
     * Set bank address
     *
     * @param string value
     */
    public function set_bank_bank_addr( $value ) {
        $this->set_payment_prop( 'bank_addr', 'bank', wc_clean( $value ) );
    }

    /**
     * Set bank routing number
     *
     * @param string value
     */
    public function set_bank_routing_number( $value ) {
        $this->set_payment_prop( 'routing_number', 'bank', wc_clean( $value ) );
    }

    /**
     * Set bank iban
     *
     * @param string $value
     */
    public function set_bank_iban( $value ) {
        $this->set_payment_prop( 'iban', 'bank', wc_clean( $value ) );
    }

    /**
     * Set bank swtif number
     *
     * @param string $value
     */
    public function set_bank_swift( $value ) {
        $this->set_payment_prop( 'swift', 'bank', wc_clean( $value ) );
    }

    public function set_address( $value ) {
        $this->set_prop( 'address', wc_clean( $value ) );
    }

    /**
     * Set street 1
     *
     * @param string $value
     */
    public function set_street_1( $value ) {
        $this->set_address_prop( 'street_1', 'address', wc_clean( $value ) );
    }

    /**
     * Set street 2
     *
     * @param string $value
     */
    public function set_street_2( $value ) {
        $this->set_address_prop( 'street_2', 'address', wc_clean( $value ) );
    }

    /**
     * Set city
     *
     * @param string $value
     */
    public function set_city( $value ) {
        $this->set_address_prop( 'city', 'address', wc_clean( $value ) );
    }

    /**
     * Set zip
     *
     * @param string $value
     */
    public function set_zip( $value ) {
        $this->set_address_prop( 'zip', 'address', wc_clean( $value ) );
    }

    /**
     * Set state
     *
     * @param string $value
     */
    public function set_state( $value ) {
        $this->set_address_prop( 'state', 'address', wc_clean( $value ) );
    }

    /**
     * Set country
     *
     * @param string $value
     */
    public function set_country( $value ) {
        $this->set_address_prop( 'country', 'address', wc_clean( $value ) );
    }

    /**
     * Sets a prop for a setter method.
     *
     * This stores changes in a special array so we can track what needs saving
     * the the DB later.
     *
     *
     * @param string $prop Name of prop to set.
     * @param mixed  $value Value of the prop.
     */
    protected function set_prop( $prop, $value ) {
        if ( ! $this->shop_data ) {
            $this->popluate_store_data();
        }

        if ( array_key_exists( $prop, $this->shop_data ) ) {
            if ( $value !== $this->shop_data[ $prop ] || array_key_exists( $prop, $this->changes ) ) {
                $this->changes[ $prop ] = $value;
            }
        }
    }

    /**
     * Get vendor meta data
     *
     *
     * @param string $key
     * @param bool $single  Whether to return a single value
     *
     * @return mixed|null|false
     */
    public function get_meta( $key, $single = false ) {
        return get_user_meta( $this->get_id(), $key, $single );
    }

    /**
     * Update vendor meta data
     *
     *
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    public function update_meta( $key, $value ) {
        update_user_meta( $this->get_id(), $key, $value );
    }

    /**
     * Update meta data
     *
     *
     * @return void
     */
    public function update_meta_data() {
        if ( ! $this->changes ) {
            return;
        }

        if ( ! empty( $this->changes['store_name'] ) ) {
            $this->update_meta( 'sk_store_name', $this->changes['store_name'] );
        }
    }

    /**
     * Sets a prop for a setter method.
     *
     *
     * @param string $prop    Name of prop to set.
     * @param string $social Name of social settings to set, fb, twitter
     * @param string $value
     */
    protected function set_social_prop( $prop, $social = 'social', $value = '' ) {
        if ( ! $this->shop_data ) {
            $this->popluate_store_data();
        }

        if ( ! isset( $this->shop_data[ $social ][ $prop ] ) ) {
            $this->shop_data[ $social ][ $prop ] = null;
        }

        if ( $value !== $this->shop_data[ $social ][ $prop ] || ( isset( $this->changes[ $social ] ) && array_key_exists( $prop, $this->changes[ $social ] ) ) ) {
            $this->changes[ $social ][ $prop ] = $value;
        }
    }

    /**
     * Set address props
     *
     * @param string $prop
     * @param string $address
     * @param string value
     */
    protected function set_address_prop( $prop, $address = 'address', $value = '' ) {
        $this->set_social_prop( $prop, $address, $value );
    }

    /**
     * Set payment props
     *
     * @param string $prop
     * @param string $paypal
     * @param mix value
     */
    protected function set_payment_prop( $prop, $paypal = 'paypal', $value = '' ) {
        if ( ! $this->shop_data ) {
            $this->popluate_store_data();
        }

        if ( ! isset( $this->shop_data[ 'payment' ][ $paypal ][ $prop ] ) ) {
            $this->shop_data[ 'payment' ][ $paypal ][ $prop ] = null;
        }

        if ( $value !== $this->shop_data[ 'payment' ][ $paypal ][ $prop ] || ( isset( $this->changes[ 'payment' ] ) && array_key_exists( $prop, $this->changes[ 'payment' ] ) ) ) {
            $this->changes[ 'payment' ][ $paypal ][ $prop ] = $value;
        }
    }

    /**
     * Merge changes with data and clear.
     *
     */
    public function apply_changes() {
        $this->shop_data = array_replace_recursive( $this->shop_data, $this->changes );
        $this->update_meta( 'sk_profile_settings', $this->shop_data );
        $this->update_meta_data();

        $this->changes = [];
    }

    /**
     * Save the object
     *
     */
    public function save() {
        $this->apply_changes();
    }

    /**
     * Get vendor profile url for admin
     *
     *
     * @return string
     */
    public function get_profile_url(): string {
        $is_pro   = sk()->is_pro_exists();
        $url_path = $is_pro ? 'admin.php?page=sk#/vendors/' : 'user-edit.php?user_id=';

        return apply_filters( 'sk_vendor_profile_url', admin_url( $url_path . $this->get_id() ), $is_pro );
    }
}
