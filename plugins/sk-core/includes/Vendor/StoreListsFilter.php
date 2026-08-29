<?php

namespace SK\Core\Vendor;

/**
 * Store Lists Class
 *
 */
class StoreListsFilter {

    /**
     * WP_User_Query holder
     *
     * @var object
     */
    private $query;

    /**
     * Orderby holder
     *
     * @var string
     */
    private $orderby;

    /**
     * Boot method
     *
     *
     * @return void
     */
    public function __construct() {
        $this->maybe_disable_stote_lists_filter();

        wp_enqueue_style( 'dashicons' );
        add_action( 'sk_store_lists_filter_form', [ $this, 'filter_area' ] );
        add_filter( 'sk_seller_listing_args', [ $this, 'filter_pre_user_query' ], 10, 2 );

        // Pro: category area, featured store, open now filter, rating filter
        add_action( 'sk_before_store_lists_filter_apply_button', [ $this, 'category_area' ] );
        add_action( 'sk_after_store_lists_filter_category', [ $this, 'featured_store' ] );
        add_action( 'pre_get_users', [ $this, 'get_filtered_stores' ] );
    }

    /**
     * Maybe disable the store lists filter form
     *
     *
     * @return void
     */
    public function maybe_disable_stote_lists_filter() {
        $not_valid = class_exists( 'SK_Geolocation' ) && version_compare( sk_ext()->version, '2.9.17', '<' );

        if ( $not_valid ) {
            add_filter( 'sk_store_lists_filter', '__return_false' );
        }
    }

    /**
     * Filter area
     *
     *
     * @param  WP_Users $stores
     *
     * @return void
     */
    public function filter_area( $stores ) {
        sk_get_template_part( 'store-lists-filter', '', [
            'stores'          => $stores,
            'number_of_store' => $stores['count'],
            'sort_filters'    => self::sort_by_options(),
            'sort_by'         => $this->orderby,
        ] );
    }

    /**
     * Get sort by options
     *
     *
     * @return array
     */
    public static function sort_by_options() {
        return apply_filters( 'sk_store_lists_sort_by_options', [
            'most_recent'   => __( 'Most Recent', 'sk-core' ),
            'random'        => __( 'Random', 'sk-core' ),
            'top_rated'     => __( 'Top Rated', 'sk-core' ),
            'most_reviewed' => __( 'Most Reviewed', 'sk-core' ),
            'verified'      => __( 'Verifiziert', 'sk-core' ),
        ] );
    }

    /**
     * Category area template
     *
     *
     * @param  array $stores
     *
     * @return void
     */
    public function category_area( $stores ) {
        sk_get_template_part(
            'store-lists/category-area', '', [
                'pro'        => true,
                'stores'     => $stores,
                'categories' => $this->get_categories(),
            ]
        );
    }

    /**
     * Featured store template
     *
     *
     * @param  array $stores
     * @return void
     */
    public function featured_store( $stores ) {
        sk_get_template_part(
            'store-lists/featured', '', [
                'pro'         => true,
                'stores'      => $stores,
            ]
        );

        sk_get_template_part(
            'store-lists/open-now', '', [
                'pro'    => true,
                'stores' => $stores,
            ]
        );
    }

    /**
     * Get store categories
     *
     *
     * @return array | null on failure
     */
    public function get_categories() {
        if ( ! sk_is_store_categories_feature_on() ) {
            return;
        }

        $categories = get_terms(
            [
                'taxonomy'   => 'store_category',
                'hide_empty' => false,
            ]
        );

        $categories = array_map(
            function( $category ) {
                return [
                    'name' => $category->name,
                    'slug' => $category->slug,
                ];
            }, $categories
        );

        return apply_filters( 'sk_get_store_categories', $categories );
    }

    /**
     * Filter pre user query
     *
     *
     * @param  array $args
     * @param  array $request
     *
     * @return array
     */
    public function filter_pre_user_query( $args, $request ) {
        if ( ! empty( $request['stores_orderby'] ) ) {
            $args['orderby'] = wc_clean( $request['stores_orderby'] );
        } elseif ( empty( $args['orderby'] ) ) {
            $sort_by         = sk_get_option( 'store_list_sort_by', 'sk_appearance', 'most_recent' );
            $args['orderby'] = ( ! array_key_exists( $sort_by, self::sort_by_options() ) ) ? 'most_recent' : $sort_by;
        }

        // Pro: featured, open_now, rating filter args
        if ( ! empty( $request['featured'] ) && 'yes' === $request['featured'] ) {
            $args['featured'] = 'yes';
        }

        if ( ! empty( $request['open_now'] ) && 'yes' === $request['open_now'] ) {
            $args['open_now'] = 'yes';
        }

        if ( ! empty( $request['rating'] ) ) {
            $args['rating'] = intval( $request['rating'] );
        }

        add_action( 'pre_user_query', array( $this, 'filter_user_query' ), 9 );

        return $args;
    }

    /**
     * Filter user query
     *
     *
     * @param  WP_User_Query
     *
     * @return void
     */
    public function filter_user_query( $query ) {
        $this->query   = $query;
        $this->orderby = ! empty( $query->query_vars['orderby'] ) ? $query->query_vars['orderby'] : null;

        do_action( 'sk_before_filter_user_query', $this->query, $this->orderby );

        $this->filter_query_from();
        $this->filter_query_orderby();
    }

    /**
     * Get filtered stores (open now / rating)
     *
     *
     * @param  WP_User_Query $query
     *
     * @return void
     */
    public function get_filtered_stores( $query ) {
        $rating = ! empty( $query->query_vars['rating'] ) ? $query->query_vars['rating'] : 0;

        if ( empty( $rating ) ) {
            return $query;
        }

        $all_stores = get_users(
            apply_filters(
                'sk_pre_get_all_stores_query', [
                    'role__in'      => [ 'seller', 'administrator' ],
                    'number'        => -1,
                    'orderby'       => 'registered',
                    'order'         => 'ASC',
                    'status'        => 'approved',
                    'fields'        => 'ids',
                    'no_found_rows' => true,
                    'meta_query'    => [
                        [
                            'key'     => 'sk_enable_selling',
                            'value'   => 'yes',
                            'compare' => '=',
                        ],
                    ],
                ]
            )
        );

        $store_to_exclude = [];

        foreach ( $all_stores as $store ) {
            if ( $rating ) {
                $vendor         = sk()->vendor->get( $store );
                $vendor_ratings = $vendor->get_rating();
                $vendor_rating  = ! empty( $vendor_ratings['rating'] ) ? $vendor_ratings['rating'] : 0;

                if ( $vendor->get_id() > 0 && $vendor_rating < $rating ) {
                    array_push( $store_to_exclude, $store );
                }
            }
        }

        $query->set( 'exclude', $store_to_exclude );
    }

    /**
     * Filter query form
     *
     *
     * @return void
     */
    private function filter_query_from() {
        global $wpdb;

        // Pro: most_reviewed sorting
        if ( 'most_reviewed' === $this->orderby ) {
            $this->query->query_from .= " LEFT JOIN (
                    SELECT count(post.ID) AS review_count, meta.meta_value AS seller_id
                    FROM {$wpdb->posts} AS post
                    INNER JOIN {$wpdb->postmeta} AS meta ON post.ID = meta.post_id
                    WHERE post.post_type = 'sk_store_reviews'
                    AND meta.meta_key = 'store_id'
                    GROUP BY seller_id
                    ) as review
                    ON ({$wpdb->users}.ID = review.seller_id)";
        }

        /*
         * Nur Verifizierte zeigen.
         *
         * Ein INNER JOIN statt LEFT JOIN: der Eintrag ist damit Bedingung,
         * nicht bloss Sortierhilfe. Verbunden wird ueber das flache
         * Ablaufdatum, nicht ueber die serialisierte Liste der Adressen — die
         * laesst sich in SQL nicht auswerten. Der Vergleich mit der aktuellen
         * Zeit sortiert abgelaufene Bestaetigungen gleich mit aus.
         */
        if ( 'verified' === $this->orderby ) {
            $this->query->query_from .= $wpdb->prepare(
                " INNER JOIN {$wpdb->usermeta} AS sk_verified
                    ON ( {$wpdb->users}.ID = sk_verified.user_id
                         AND sk_verified.meta_key = %s
                         AND sk_verified.meta_value + 0 > %d )",
                \SK\Core\Verification\VerifiedLinks::META_UNTIL,
                time()
            );
        }

        // Pro: top_rated sorting
        if ( 'top_rated' === $this->orderby ) {
            $this->query->query_from .= " LEFT JOIN (
                    SELECT store_id, sum(rating) AS rating
                    FROM
                        (SELECT p.ID,
                            sum( if( m.meta_key = 'store_id', m.meta_value, 0 ) ) AS store_id,
                            sum( if( m.meta_key = 'rating', m.meta_value, 0 ) ) AS rating
                        FROM {$wpdb->postmeta} AS m
                        LEFT JOIN {$wpdb->posts} AS p ON p.ID = m.post_id
                        WHERE p.post_type = 'sk_store_reviews'
                        GROUP BY p.ID) AS vt
                    GROUP BY store_id
                    ORDER BY rating) as rating
                    ON ({$wpdb->users}.ID = rating.store_id)";
        }
    }

    /**
     * Filter query orderby
     *
     *
     * @return void
     */
    private function filter_query_orderby() {
        if ( 'most_recent' === $this->orderby ) {
            $this->query->query_orderby = 'ORDER BY ID DESC';
            return;
        }

        if ( 'random' === $this->orderby ) {
            $order_by = [
                'ID',
                'user_login, ID',
                'user_email',
                'user_registered, ID',
                'user_nicename, ID',
            ];

            $selected_orderby = get_transient( 'sk_store_listing_random_orderby' );

            if ( false === $selected_orderby ) {
                $selected_orderby = $order_by[ array_rand( $order_by, 1 ) ];

                set_transient( 'sk_store_listing_random_orderby', $selected_orderby, MINUTE_IN_SECONDS * 5 );
            }

            $this->query->query_orderby = "ORDER BY $selected_orderby";
            return;
        }

        // Pro: most_reviewed sorting
        if ( 'most_reviewed' === $this->orderby ) {
            $this->query->query_orderby = 'ORDER BY review_count DESC';
            return;
        }

        // Pro: top_rated sorting
        if ( 'top_rated' === $this->orderby ) {
            $this->query->query_orderby = 'ORDER BY rating DESC';
            return;
        }

        // Gefiltert wird schon im JOIN; hier bleibt die uebliche Reihenfolge.
        if ( 'verified' === $this->orderby ) {
            $this->query->query_orderby = 'ORDER BY ID DESC';
            return;
        }
    }
}
