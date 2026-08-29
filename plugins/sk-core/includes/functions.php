<?php

use SK\Core\Cache;
use SK\Core\Utilities\OrderUtil;

/**
 * SK Admin menu position
 *
 *
 * @return string
 */
function sk_admin_menu_position() {
    return apply_filters( 'sk_menu_position', '55.4' );
}

if ( ! function_exists( 'sk_admin_menu_capability' ) ) {
    /**
     * SK Admin menu capability
     *
     *
     * @return string
     */
    function sk_admin_menu_capability() {
        return apply_filters( 'sk_menu_capability', 'manage_woocommerce' );
    }
}

/**
 * SK Get current user id
 *
 *
 * @return int
 */
function sk_get_current_user_id() {
    // This used to map a vendor_staff user onto their vendor via the _vendor_id
    // meta. The vendor-staff module is gone and nothing writes that meta any
    // more, so the branch was dead — and dead branches in a function that
    // decides "whose products may I edit" are the wrong kind of leftover.
    return get_current_user_id();
}

/**
 * Check if a user is seller
 *
 *
 * @param int  $user_id       User ID
 * @param bool $exclude_staff Exclude staff
 *
 * @return bool
 */
function sk_is_user_seller( $user_id, $exclude_staff = false ) {
    if ( $exclude_staff && user_can( $user_id, 'vendor_staff' ) ) {
        return false;
    }

    return user_can( $user_id, 'skdar' );
}


/**
 * Get reserved URL slugs that cannot be used for custom slugs like store base
 *
 *
 * @return array List of reserved slugs
 */
function sk_get_reserved_url_slugs() {
    $reserved_slugs = [
        's',
        'p',
        'page',
        'paged',
        'author',
        'feed',
        'search',
        'post',
        'tag',
        'category',
        'attachment',
        'name',
        'order',
        'orderby',
        'rest',
        'rest_route',
        'wp-json',
        'shop',
        'cart',
        'checkout',
    ];

    /**
     * Filter the list of reserved URL slugs that cannot be used for custom slugs like store base.
     *
     *
     * @param array $reserved_slugs List of reserved slugs.
     */
    return apply_filters( 'sk_reserved_url_slugs', $reserved_slugs );
}

/**
 * Check if current user is the product author
 *
 * @param int      $product_id
 *
 * @return bool
 */
function sk_is_product_author( $product_id = 0 ) {
    global $post;

    if ( ! $product_id ) {
        $author = $post->post_author;
    } else {
        $author = get_post_field( 'post_author', $product_id );
    }

    return absint( $author ) === apply_filters( 'sk_is_product_author', sk_get_current_user_id(), $product_id );
}

/**
 * Check if it's a store page
 *
 * @return bool
 */
function sk_is_store_page() {
    $custom_store_url = sk_get_option( 'custom_store_url', 'sk_general', 'store' );

    if ( get_query_var( $custom_store_url ) ) {
        return true;
    }

    return false;
}


/**
 * Check if it's a Seller Dashboard page
 *
 *
 * @return bool
 */
function sk_is_seller_dashboard() {
    global $wp_query;

    $page_id = apply_filters( 'sk_get_dashboard_page_id', sk_get_option( 'dashboard', 'sk_pages' ) );

    if ( ! $page_id ) {
        return false;
    }

    if ( ! $wp_query ) {
        return false;
    }

    if ( absint( $page_id ) === apply_filters( 'sk_get_current_page_id', $wp_query->queried_object_id ) ) {
        return true;
    }

    return false;
}

/**
 * Check if an sk-core pro module is currently active.
 *
 * Wrapper around sk_ext()->module->is_active() that's safe to call from
 * anywhere (returns false if the container isn't ready yet). Prefer this
 * over raw `class_exists('SK\Modules\X\Y')` which triggers the PSR-4
 * autoloader for deactivated modules and lets static calls leak through.
 *
 * @param string $module_id Module slug, e.g. 'sk_zaps', 'sk_payments'.
 * @return bool
 */
function sk_module_active( string $module_id ): bool {
    if ( ! function_exists( 'sk_ext' ) ) {
        return false;
    }
    $ext = sk_ext();
    if ( ! $ext || ! isset( $ext->module ) || ! is_object( $ext->module ) ) {
        return false;
    }
    return (bool) $ext->module->is_active( $module_id );
}

/**
 * Redirect to login page if not already logged in
 *
 * @return void
 */
function sk_redirect_login() {
    if ( ! is_user_logged_in() ) {
        $url = apply_filters( 'sk_redirect_login', sk_get_page_url( 'myaccount', 'woocommerce' ) );
        wp_safe_redirect( $url );
        exit();
    }
}

/**
 * If the current user is not seller, redirect to homepage
 *
 * @param string $redirect
 */
function sk_redirect_if_not_seller( $redirect = '' ) {
    if ( ! sk_is_user_seller( sk_get_current_user_id() ) ) {
        $redirect = empty( $redirect ) ? home_url( '/' ) : $redirect;

        wp_safe_redirect( $redirect );
        exit();
    }
}

/**
 * Count post type from a user
 *
 * @param string $post_type
 * @param int    $user_id
 * @param array  $exclude_product_types The product types that will be excluded from count
 *
 * @return array
 */
function sk_count_posts( $post_type, $user_id, $exclude_product_types = [ 'booking', 'auction' ] ) {
    // get all function arguments as key => value pairs
    $args = apply_filters( 'sk_count_posts_args', get_defined_vars() );

    $cache_group = "seller_product_data_$user_id";
    $cache_key   = 'count_posts_' . md5( wp_json_encode( $args ) );
    $counts      = Cache::get( $cache_key, $cache_group );

    if ( false === $counts ) {
        $results = apply_filters( 'sk_count_posts', null, $post_type, $user_id, $exclude_product_types );

        if ( ! $results ) {
            global $wpdb;
            $exclude_product_types_text = "'" . implode( "', '", esc_sql( $exclude_product_types ) ) . "'";

            // @codingStandardsIgnoreStart
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT post_status, COUNT( * ) AS num_posts FROM {$wpdb->posts} as posts
                            INNER JOIN {$wpdb->term_relationships} AS term_relationships ON posts.ID = term_relationships.object_id
                            INNER JOIN {$wpdb->term_taxonomy} AS term_taxonomy ON term_relationships.term_taxonomy_id = term_taxonomy.term_taxonomy_id
                            INNER JOIN {$wpdb->terms} AS terms ON term_taxonomy.term_id = terms.term_id
                            WHERE
                                term_taxonomy.taxonomy = 'product_type'
                            AND terms.slug NOT IN ({$exclude_product_types_text})
                            AND posts.post_type = %s
                            AND posts.post_author = %d
                                GROUP BY posts.post_status",
                    $post_type,
                    $user_id
                ),
                ARRAY_A
            );
            // @codingStandardsIgnoreEnd
        }

        $post_status = array_keys( sk_get_post_status() );
        $counts      = array_fill_keys( get_post_stati(), 0 );
        $total       = 0;

        foreach ( $results as $row ) {
            if ( ! in_array( $row['post_status'], $post_status, true ) ) {
                continue;
            }

            $counts[ $row['post_status'] ] = (int) $row['num_posts'];
            $total                         += (int) $row['num_posts'];
        }

        $counts['total'] = $total;
        $counts          = (object) $counts;

        Cache::set( $cache_key, $counts, $cache_group );
    }

    return $counts;
}

/**
 * Count stock product type from a user
 *
 *
 * @param string $post_type
 * @param int    $user_id
 * @param string $stock_type
 * @param array  $exclude_product_types
 *
 * @return int $counts
 */
function sk_count_stock_posts( $post_type, $user_id, $stock_type, $exclude_product_types = [ 'booking', 'auction' ] ) {
    global $wpdb;

    $cache_group = 'seller_product_stock_data_' . $user_id;
    $cache_key   = apply_filters( 'sk_count_stock_posts_cache_key', "count_stock_posts_{$user_id}_{$post_type}_{$stock_type}", $post_type, $user_id, $stock_type );
    $counts      = Cache::get( $cache_key, $cache_group );

    if ( false === $counts ) {
        $results = apply_filters( 'sk_count_posts_' . $stock_type, null, $post_type, $user_id, $stock_type, $exclude_product_types );
        $exclude_product_types_text = "'" . implode( "', '", esc_sql( $exclude_product_types ) ) . "'";

        if ( ! $results ) {
            // @codingStandardsIgnoreStart
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT p.post_status, COUNT( * ) AS num_posts
                    FROM {$wpdb->prefix}posts as p INNER JOIN {$wpdb->prefix}postmeta as pm ON p.ID = pm.post_id
                    WHERE p.post_type = %s
                    AND p.post_author = %d
                    AND pm.meta_key   = '_stock_status'
                    AND pm.meta_value = %s
                    AND p.ID IN (
                        SELECT tr.object_id FROM {$wpdb->prefix}terms AS t
                        LEFT JOIN {$wpdb->prefix}term_taxonomy AS tt ON t.term_id = tt.term_taxonomy_id
                        LEFT JOIN {$wpdb->prefix}term_relationships AS tr ON t.term_id = tr.term_taxonomy_id
                        WHERE tt.taxonomy = 'product_type' AND t.slug NOT IN ({$exclude_product_types_text})
                    )
                    GROUP BY p.post_status",
                    $post_type,
                    $user_id,
                    $stock_type
                ),
                ARRAY_A
            );
            // @codingStandardsIgnoreEnd
        }

        $post_status = array_keys( sk_get_post_status() );
        $total       = 0;

        foreach ( $results as $row ) {
            if ( ! in_array( $row['post_status'], $post_status, true ) ) {
                continue;
            }

            $total += (int) $row['num_posts'];
        }

        $counts = $total;

        Cache::set( $cache_key, $counts, $cache_group );
    }

    return $counts;
}

/**
 * Get comment count based on post type and user id
 *
 * @param string   $post_type
 * @param int      $user_id
 *
 * @return array
 */
function sk_count_comments( $post_type, $user_id ) {
    global $wpdb;

    $cache_group = "count_{$post_type}_comments_{$user_id}";
    $cache_key   = 'comments';
    $counts      = Cache::get( $cache_key, $cache_group );

    if ( $counts === false ) {
        $count = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT c.comment_approved, COUNT( * ) AS num_comments
                FROM $wpdb->comments as c, $wpdb->posts as p
                WHERE p.post_author = %d AND
                    p.post_status = 'publish' AND
                    c.comment_post_ID = p.ID AND
                    p.post_type = %s
                GROUP BY c.comment_approved",
                $user_id,
                $post_type
            ),
            ARRAY_A
        );

        $total    = 0;
        $counts   = [
            'moderated' => 0,
            'approved'  => 0,
            'spam'      => 0,
            'trash'     => 0,
            'total'     => 0,
        ];
        $statuses = [
            '0'            => 'moderated',
            '1'            => 'approved',
            'spam'         => 'spam',
            'trash'        => 'trash',
            'post-trashed' => 'post-trashed',
        ];

        foreach ( $count as $row ) {
            if ( isset( $statuses[ $row['comment_approved'] ] ) ) {
                $counts[ $statuses[ $row['comment_approved'] ] ] = (int) $row['num_comments'];
                $total += (int) $row['num_comments'];
            }
        }
        $counts['total'] = $total;

        $counts = (object) $counts;
        Cache::set( $cache_key, $counts, $cache_group );
    }

    return $counts;
}

/**
 * Get total pageview for a seller
 *
 * @param int   $seller_id
 *
 * @return int
 */
function sk_author_pageviews( $seller_id ) {
    global $wpdb;

    $cache_key = "pageview_{$seller_id}";
    $pageview  = Cache::get( $cache_key );

    if ( false === $pageview ) {
        $count = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT SUM(meta_value) as pageview
                FROM {$wpdb->postmeta} AS meta
                LEFT JOIN {$wpdb->posts} AS p ON p.ID = meta.post_id
                WHERE meta.meta_key = 'pageview' AND p.post_author = %d AND p.post_status IN ('publish', 'pending', 'draft')",
                $seller_id
            )
        );

        $pageview = $count->pageview;

        Cache::set( $cache_key, $pageview );
    }

    return $pageview;
}

/**
 * Get the default product status for new and edited product for seller based on settings
 *
 *
 * @param int|null $seller_id
 *
 * @return string
 */
function sk_get_default_product_status( $seller_id = null ) {
    $seller_id  = null === $seller_id ? sk_get_current_user_id() : $seller_id;
    $is_trusted = sk_is_seller_trusted( $seller_id );
    $status     = 'pending';

    if ( $is_trusted ) {
        $status = 'publish';
    }

    // below code will be removed on a future version of SK Lite
    if ( sk()->is_pro_exists() && version_compare( SK_CORE_VERSION, '3.8.3', '<' ) ) {
        $status = 'publish' === $status ? $status : sk_get_option( 'product_status', 'sk_selling', 'pending' );
    }

    $status = apply_filters_deprecated( 'sk_get_new_post_status', [ $status, $seller_id, $is_trusted ], '3.8.2', 'sk_get_default_product_status' );

    return apply_filters( 'sk_get_default_product_status', $status, $seller_id, $is_trusted );
}

/**
 * Get product status based on user id and settings
 *
 *
 *
 * @param int|null $seller_id
 *
 * @deprecated 3.8.2 use `sk_get_default_product_status` instead
 *
 * @return string
 */
function sk_get_new_post_status( $seller_id = null ) {
    return sk_get_default_product_status( $seller_id );
}

/**
 * Did this request originate from our own site?
 *
 * Login endpoints cannot rely on nonces: the login form is served to logged-out
 * visitors, and a WordPress nonce for user 0 is identical for everybody, so an
 * attacker can simply fetch a valid one. Without an origin check a foreign page
 * can therefore submit a login and leave the visitor signed in as the attacker
 * (login CSRF). Sec-Fetch-Site is set by the browser and cannot be forged by
 * page scripts, which makes it the reliable signal here.
 *
 *
 * @return bool True when the request looks same-origin, or when the client sent
 *              no origin information at all (very old or non-browser clients).
 */
function sk_is_same_origin_request() {
    if ( ! empty( $_SERVER['HTTP_SEC_FETCH_SITE'] ) ) {
        $site = strtolower( trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_FETCH_SITE'] ) ) ) );

        // 'none' is a user-initiated navigation (typed URL, bookmark).
        return in_array( $site, [ 'same-origin', 'same-site', 'none' ], true );
    }

    $host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

    foreach ( [ 'HTTP_ORIGIN', 'HTTP_REFERER' ] as $header ) {
        if ( empty( $_SERVER[ $header ] ) ) {
            continue;
        }

        $candidate = wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ), PHP_URL_HOST );

        if ( ! $candidate ) {
            continue;
        }

        return strtolower( $candidate ) === $host;
    }

    return true;
}

/**
 * Does this account have a password its owner actually knows?
 *
 * Accounts created through Nostr or LNURL get a random password nobody ever
 * saw, so they cannot be asked to confirm it. Everyone else can.
 *
 *
 * @param int $user_id
 * @return bool
 */
function sk_account_has_password( $user_id ) {
    $user_id = (int) $user_id;

    if ( ! $user_id ) {
        return false;
    }

    if ( get_user_meta( $user_id, 'sk_password_set', true ) ) {
        return true;
    }

    // Registered with address + self-chosen password.
    if ( get_user_meta( $user_id, 'btc_address', true ) ) {
        return true;
    }

    $has_key_login = get_user_meta( $user_id, 'nostr_public_key', true )
        || get_user_meta( $user_id, 'lnurl-auth-bjm-id', true );

    // No key-based login means the password is the only way in.
    return ! $has_key_login;
}

/**
 * Client IP address of the current request.
 *
 * Proxy headers (X-Forwarded-For, CF-Connecting-IP, …) are sent by the client
 * and can say anything, so they are only used when the request actually reaches
 * us through a proxy — see sk_is_trusted_proxy(). Anything else would let a
 * visitor pick a fresh IP per request and walk through IP-based rate limits and
 * fraud fingerprints.
 *
 * Canonical implementation: all modules resolve the client IP through this.
 *
 *
 * @return string Validated IP address, or '' if none could be determined.
 */
function sk_get_client_ip() {
    $remote = isset( $_SERVER['REMOTE_ADDR'] )
        ? trim( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) )
        : '';

    if ( sk_is_trusted_proxy( $remote ) ) {
        foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR' ] as $header ) {
            if ( empty( $_SERVER[ $header ] ) ) {
                continue;
            }

            // X-Forwarded-For is a chain; the original client is leftmost.
            $value = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
            foreach ( explode( ',', $value ) as $candidate ) {
                $candidate = trim( $candidate );
                if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
                    return $candidate;
                }
            }
        }
    }

    return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '';
}

/**
 * May the proxy headers of this request be trusted?
 *
 * True for peers listed in the SK_TRUSTED_PROXIES constant (comma separated,
 * set in wp-config.php — needed for Cloudflare and other external proxies),
 * and for private/loopback peers, which mean a local reverse proxy passed the
 * request on. A public peer address is the client talking to us directly, and
 * its forwarding headers are not evidence of anything.
 *
 *
 * @param string $remote_addr
 * @return bool
 */
function sk_is_trusted_proxy( $remote_addr ) {
    if ( ! filter_var( $remote_addr, FILTER_VALIDATE_IP ) ) {
        return false;
    }

    // When the constant is defined it is authoritative — an empty value means
    // "no proxy in front of this site, never trust a forwarding header".
    if ( defined( 'SK_TRUSTED_PROXIES' ) ) {
        $trusted = array_filter( array_map( 'trim', explode( ',', (string) SK_TRUSTED_PROXIES ) ) );

        return in_array( $remote_addr, $trusted, true );
    }

    // Undeclared: a private/loopback peer means a local reverse proxy passed the
    // request on. filter_var returns false for private/reserved ranges here.
    return ! filter_var( $remote_addr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
}

/**
 * Generate an input box based on arguments
 *
 * @param int    $post_id
 * @param string $meta_key
 * @param array  $attr
 * @param string $type
 */
function sk_post_input_box( $post_id, $meta_key, $attr = [], $type = 'text' ) {
    $placeholder   = isset( $attr['placeholder'] ) ? esc_attr( $attr['placeholder'] ) : '';
    $class         = isset( $attr['class'] ) ? esc_attr( $attr['class'] ) : 'sk-form-control';
    $name          = isset( $attr['name'] ) ? esc_attr( $attr['name'] ) : $meta_key;
    $value         = isset( $attr['value'] ) ? $attr['value'] : get_post_meta( $post_id, $meta_key, true );
    $size          = isset( $attr['size'] ) ? $attr['size'] : 30;
    $required      = isset( $attr['required'] ) ? 'required' : '';

    switch ( $type ) {
        case 'text':
            ?>
            <input <?php echo esc_attr( $required ); ?>
                type="text" name="<?php echo esc_attr( $name ); ?>"
                id="<?php echo esc_attr( $name ); ?>"
                value="<?php echo esc_attr( $value ); ?>"
                class="<?php echo esc_attr( $class ); ?>"
                placeholder="<?php echo esc_attr( $placeholder ); ?>">
            <?php
            break;

        case 'price':
            ?>
            <input <?php echo esc_attr( $required ); ?>
                type="text" name="<?php echo esc_attr( $name ); ?>"
                id="<?php echo esc_attr( $name ); ?>"
                value="<?php echo esc_attr( wc_format_localized_price( $value ) ); ?>"
                class="wc_input_price <?php echo esc_attr( $class ); ?>"
                placeholder="<?php echo esc_attr( $placeholder ); ?>">
            <?php
            break;

        case 'decimal':
            ?>
            <input <?php echo esc_attr( $required ); ?>
                type="text" name="<?php echo esc_attr( $name ); ?>"
                id="<?php echo esc_attr( $name ); ?>"
                value="<?php echo esc_attr( wc_format_localized_price( $value ) ); ?>"
                class="wc_input_decimal <?php echo esc_attr( $class ); ?>"
                placeholder="<?php echo esc_attr( $placeholder ); ?>">
            <?php
            break;

        case 'textarea':
            $rows = isset( $attr['rows'] ) ? absint( $attr['rows'] ) : 4;
            ?>
            <textarea <?php echo esc_attr( $required ); ?>
                name="<?php echo esc_attr( $name ); ?>"
                id="<?php echo esc_attr( $name ); ?>"
                rows="<?php echo esc_attr( $rows ); ?>"
                class="<?php echo esc_attr( $class ); ?>"
                placeholder="<?php echo esc_attr( $placeholder ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
            <?php
            break;

        case 'checkbox':
            $label = isset( $attr['label'] ) ? $attr['label'] : '';
            $class = ( $class === 'sk-form-control' ) ? '' : $class;
            ?>

            <label class="<?php echo esc_attr( $class ); ?>" for="<?php echo esc_attr( $name ); ?>">
                <input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="no">
                <input <?php echo esc_attr( $required ); ?> name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" value="yes" type="checkbox"<?php checked( $value, 'yes' ); ?>>
                <?php echo esc_html( $label ); ?>
            </label>

            <?php
            break;

        case 'select':
            $options = is_array( $attr['options'] ) ? $attr['options'] : [];
            ?>
            <select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" class="<?php echo esc_attr( $class ); ?>">
                <?php foreach ( $options as $key => $label ) { ?>
                    <option value="<?php echo esc_attr( $key ); ?>"<?php selected( $value, $key ); ?>><?php echo esc_html( $label ); ?></option>
                <?php } ?>
            </select>

            <?php
            break;

        case 'number':
            $min  = isset( $attr['min'] ) ? $attr['min'] : 0;
            $step = isset( $attr['step'] ) ? $attr['step'] : 'any';
            ?>
            <input <?php echo esc_attr( $required ); ?>
                type="number" name="<?php echo esc_attr( $name ); ?>"
                id="<?php echo esc_attr( $name ); ?>"
                value="<?php echo esc_attr( $value ); ?>"
                class="<?php echo esc_attr( $class ); ?>"
                placeholder="<?php echo esc_attr( $placeholder ); ?>"
                min="<?php echo esc_attr( $min ); ?>"
                step="<?php echo esc_attr( $step ); ?>"
                size="<?php echo esc_attr( $size ); ?>">
            <?php
            break;

        case 'radio':
            $options = is_array( $attr['options'] ) ? $attr['options'] : [];

            foreach ( $options as $key => $label ) {
                ?>
                <label class="<?php echo esc_attr( $class ); ?>" for="<?php echo esc_attr( $key ); ?>">
                    <input name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $key ); ?>" type="radio"<?php checked( $value, $key ); ?>>
                    <?php echo esc_html( $label ); ?>
                </label>

                <?php
            }
            break;
    }
}

/**
 * Get user-friendly post status based on post
 *
 * @param string $status
 *
 * @return string|array
 */
function sk_get_post_status( $status = '' ) {
    $statuses = apply_filters(
        'sk_get_post_status', [
            'publish' => __( 'Online', 'sk-core' ),
            'draft'   => __( 'Draft', 'sk-core' ),
            // Ein noch nicht gespeichertes Inserat hat auto-draft. Ohne
            // Eintrag lieferte sk_get_post_status() dafuer einen leeren String
            // und die Status-Pille im Produktformular blieb leer.
            'auto-draft' => __( 'Draft', 'sk-core' ),
            'pending' => __( 'Pending Review', 'sk-core' ),
            'future'  => __( 'Scheduled', 'sk-core' ),
        ]
    );

    if ( $status ) {
        return isset( $statuses[ $status ] ) ? $statuses[ $status ] : '';
    }

    return $statuses;
}

/**
 * Get product available statuses
 *
 *
 * @args int|object $product_id
 *
 * @return array
 */
if ( ! function_exists( 'sk_get_available_post_status' ) ) {

    function sk_get_available_post_status( $product_id = 0 ) {
        return apply_filters(
            'sk_post_status',
            [
                'publish' => sk_get_post_status( 'publish' ),
                'draft'   => sk_get_post_status( 'draft' ),
                'pending' => sk_get_post_status( 'pending' ),
            ],
            $product_id
        );
    }
}

/**
 * Get user friendly post status label based class
 *
 * @param string $status
 *
 * @return string|array
 */
function sk_get_post_status_label_class( $status = '' ) {
    $labels = apply_filters(
        'sk_get_post_status_label_class', [
            'publish' => 'sk-label-success',
            'draft'   => 'sk-label-default',
            'pending' => 'sk-label-danger',
            'future'  => 'sk-label-warning',
        ]
    );

    if ( $status ) {
        return isset( $labels[ $status ] ) ? $labels[ $status ] : '';
    }

    return $labels;
}

/**
 * Get readable product type based on product
 *
 * @param string $status
 *
 * @return array
 */
function sk_get_product_types( $status = '' ) {
    $types = apply_filters(
        'sk_get_product_types', [
            'simple' => __( 'Simple Product', 'sk-core' ),
        ]
    );

    if ( $status ) {
        return isset( $types[ $status ] ) ? $types[ $status ] : '';
    }

    return $types;
}

/**
 * Get template part implementation for wedocs
 *
 * Looks at the theme directory first
 */
function sk_get_template_part( $slug, $name = '', $args = [] ) {
    $defaults = [
        'pro' => false,
    ];

    $args = wp_parse_args( $args, $defaults );

    if ( $args && is_array( $args ) ) {
        extract( $args ); // phpcs:ignore
    }

    $template = '';

    // Look in yourtheme/sk/slug-name.php and yourtheme/sk/slug.php
    $template_path = ! empty( $name ) ? "{$slug}-{$name}.php" : "{$slug}.php";
    $template      = locate_template( [ sk()->template_path() . $template_path ] );

    /**
     * Change template directory path filter
     *
     */
    $template_path = apply_filters( 'sk_set_template_path', sk()->plugin_path() . '/templates', $template, $args );

    // Get default slug-name.php
    if ( ! $template && $name && file_exists( $template_path . "/{$slug}-{$name}.php" ) ) {
        $template = $template_path . "/{$slug}-{$name}.php";
    }

    if ( ! $template && ! $name && file_exists( $template_path . "/{$slug}.php" ) ) {
        $template = $template_path . "/{$slug}.php";
    }

    // Allow 3rd party plugin filter template file from their plugin
    $template = apply_filters( 'sk_get_template_part', $template, $slug, $name );

    if ( $template ) {
        include $template;
    }
}

/**
 * Get other templates (e.g. product attributes) passing attributes and including the file.
 *
 * @param mixed  $template_name
 * @param array  $args          (default: array())
 * @param string $template_path (default: '')
 * @param string $default_path  (default: '')
 *
 * @return void
 */
function sk_get_template( $template_name, $args = [], $template_path = '', $default_path = '' ) {
    if ( $args && is_array( $args ) ) {
        extract( $args ); // phpcs:ignore
    }

    $located = sk_locate_template( $template_name, $template_path, $default_path );

    if ( ! file_exists( $located ) ) {
        _doing_it_wrong( __FUNCTION__, sprintf( '<code>%s</code> does not exist.', esc_html( $located ) ), '2.1' );

        return;
    }

    do_action( 'sk_before_template_part', $template_name, $template_path, $located, $args );

    include $located;

    do_action( 'sk_after_template_part', $template_name, $template_path, $located, $args );
}

/**
 * Locate a template and return the path for inclusion.
 *
 * This is the load order:
 *
 *      yourtheme       /   $template_path  /   $template_name
 *      yourtheme       /   $template_name
 *      $default_path   /   $template_name
 *
 * @param mixed  $template_name
 * @param string $template_path (default: '')
 * @param string $default_path  (default: '')
 *
 * @return string
 */
function sk_locate_template( $template_name, $template_path = '', $default_path = '', $pro = false ) {
    if ( ! $template_path ) {
        $template_path = sk()->template_path();
    }

    if ( ! $default_path ) {
        $default_path = sk()->plugin_path() . '/templates/';
    }

    // Look within passed path within the theme - this is priority
    $template = locate_template(
        [
            trailingslashit( $template_path ) . $template_name,
        ]
    );

    // Get default template
    if ( ! $template ) {
        $template = $default_path . $template_name;
    }

    // Return what we found
    return apply_filters( 'sk_locate_template', $template, $template_name, $template_path );
}

/**
 * Get page permalink based on context
 *
 * @param string $page
 * @param string $context
 * @param string $subpage
 *
 * @return string url of the page
 */
function sk_get_page_url( $page, $context = 'sk', $subpage = '' ) {
    if ( $context === 'woocommerce' ) {
        $page_id = wc_get_page_id( $page );
    } else {
        $page_id = sk_get_option( $page, 'sk_pages' );
    }

    $url = get_permalink( $page_id );

    if ( $subpage ) {
        $url = sk_add_subpage_to_url( $url, $subpage );
    }

    return apply_filters( 'sk_get_page_url', $url, $page_id, $context, $subpage );
}

/**
 * Add subpage to url: this will add wpml like plugin compatibility
 *
 *
 * @param string $subpage
 *
 * @param string $url
 *
 * @return false|string
 */
function sk_add_subpage_to_url( $url, $subpage ) {
    $url_parts         = wp_parse_url( $url );
    $url_parts['path'] = $url_parts['path'] . $subpage;

    $rebuilt  = ( isset( $url_parts['scheme'] ) ? $url_parts['scheme'] . '://' : '' );
    $rebuilt .= ( isset( $url_parts['user'] ) ? $url_parts['user'] . ( isset( $url_parts['pass'] ) ? ':' . $url_parts['pass'] : '' ) . '@' : '' );
    $rebuilt .= ( isset( $url_parts['host'] ) ? $url_parts['host'] : '' );
    $rebuilt .= ( isset( $url_parts['port'] ) ? ':' . $url_parts['port'] : '' );
    $rebuilt .= ( isset( $url_parts['path'] ) ? $url_parts['path'] : '' );
    $rebuilt .= ( isset( $url_parts['query'] ) ? '?' . $url_parts['query'] : '' );
    $rebuilt .= ( isset( $url_parts['fragment'] ) ? '#' . $url_parts['fragment'] : '' );

    return $rebuilt;
}

/**
 * Get edit product url
 *
 * @param int|WC_Product $product
 * @param bool $is_new_product Is new product. Default `false`.
 *
 * @return string|false on failure
 */
function sk_edit_product_url( $product, bool $is_new_product = false ) {
    if ( ! $product instanceof WC_Product ) {
        $product = wc_get_product( $product );
    }

    if ( ! $product && ! $is_new_product ) {
        return false;
    }

    if ( ! $product && $is_new_product ) {
        $product = new WC_Product();
    }

    $url = add_query_arg(
        [
            'product_id'                => $is_new_product ? 0 : $product->get_id(),
            'action'                    => 'edit',
            '_sk_edit_product_nonce' => wp_create_nonce( 'sk_edit_product_nonce' ),
        ],
        sk_get_navigation_url( 'products' )
    );

    return apply_filters( 'sk_get_edit_product_url', $url, $product );
}

/**
 * Ads additional columns to admin user table
 *
 * @param array $columns
 *
 * @return array
 */
function sk_admin_product_columns( $columns ) {
    $columns['author'] = __( 'Author', 'sk-core' );

    return $columns;
}

add_filter( 'manage_edit-product_columns', 'sk_admin_product_columns' );

/**
 * Get the value of a settings field
 *
 * @param string $option  settings field name
 * @param string $section the section name this field belongs to
 * @param string $default_value default text if it's not found
 *
 * @return mixed
 */
function sk_get_option( $option, $section, $default_value = '' ) {
    [ $option, $section ] = sk_admin_settings_rearrange_map( $option, $section );

    $options = get_option( $section );

    if ( isset( $options[ $option ] ) ) {
        return $options[ $option ];
    }

    return $default_value;
}

/**
 * Redirect users from standard WordPress register page to woocommerce
 * my account page
 *
 * @global string $action
 */
function sk_redirect_to_register() {
    global $action;

    if ( $action === 'register' ) {
        wp_safe_redirect( sk_get_page_url( 'myaccount', 'woocommerce' ) );
        exit;
    }
}

add_action( 'login_init', 'sk_redirect_to_register' );

/**
 * Check if the seller is enabled
 *
 *
 * @param int $user_id
 *
 * @return bool
 */
function sk_is_seller_enabled( $user_id ): bool {
    // Der dritte Parameter von get_user_meta() ist $single, kein Standardwert —
    // 'no' hat hier nur zufaellig als truthy gewirkt.
    return apply_filters(
        'sk_is_seller_enabled',
        'yes' === get_user_meta( $user_id, 'sk_enable_selling', true )
    );
}

/**
 * Check if the seller is trusted
 *
 * @param int $user_id
 *
 * @return bool
 */
function sk_is_seller_trusted( $user_id ) {
    $publishing = get_user_meta( $user_id, 'sk_publishing', true );

    return $publishing === 'yes';
}

/**
 * Get store page url of a seller
 *
 *
 * @param int $user_id
 * @param string $tab Tab endpoint (Optional). Default is empty.
 *
 * @return string
 */
function sk_get_store_url( $user_id, $tab = '' ) {
    if ( ! $user_id ) {
        return '';
    }

    $userdata         = get_userdata( $user_id );
    $user_nicename    = ( false !== $userdata ) ? $userdata->user_nicename : '';
    $custom_store_url = sk_get_option( 'custom_store_url', 'sk_general', 'store' );

    $path = '/' . $custom_store_url . '/' . $user_nicename . '/';
    if ( $tab ) {
        $tab  = untrailingslashit( trim( $tab, " \n\r\t\v\0/\\" ) );
        $path .= $tab;
        $path = trailingslashit( $path );
    }

    /**
     * Filter hook for the store URL before returning.
     *
     *
     * @param string $store_url        The default store URL
     * @param string $custom_store_url The custom store URL
     * @param int    $user_id          The user ID for the store owner
     * @param string $tab              The tab endpoint. Default is empty.
     */
    return apply_filters( 'sk_get_store_url', home_url( $path ), $custom_store_url, $user_id, $tab );
}

/**
 * Get current page URL.
 *
 *
 * @return string
 */
function sk_get_current_page_url() {
    global $wp;

    if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
        return add_query_arg( wc_clean( wp_unslash( $_SERVER['QUERY_STRING'] ) ), '', home_url( $wp->request ) );
    }

    return home_url( $wp->request );
}

/**
 * Check if current page is store review page
 *
 *
 * @return bool
 */
function sk_is_store_review_page() {
    return get_query_var( 'store_review' ) === 'true';
}

/**
 * Helper function for logging
 *
 * For valid levels, see `WC_Log_Levels` class
 *
 * Description of levels:
 *     'emergency': System is unusable.
 *     'alert': Action must be taken immediately.
 *     'critical': Critical conditions.
 *     'error': Error conditions.
 *     'warning': Warning conditions.
 *     'notice': Normal but significant condition.
 *     'info': Informational messages.
 *     'debug': Debug-level messages.
 *
 * @param string $message
 *
 * @return void
 */
function sk_log( $message, $level = 'debug' ) {
    $logger  = wc_get_logger();
    $context = [ 'source' => 'sk' ];

    $logger->log( $level, $message, $context );
}

/**
 * Filter WP Media Manager files if the current user is seller.
 *
 * Do not show other sellers images to a seller. He can see images only by him
 *
 * @param array $args
 *
 * @return array
 */
function sk_media_uploader_restrict( $args ) {
    if ( current_user_can( 'manage_woocommerce' ) ) {
        return $args;
    }

    if ( current_user_can( 'skdar' ) ) {
        $args['author'] = sk_get_current_user_id();

        return $args;
    }

    return $args;
}

add_filter( 'ajax_query_attachments_args', 'sk_media_uploader_restrict' );

/**
 * Get store info based on seller ID
 *
 * @param int $seller_id
 *
 * @return array
 */
function sk_get_store_info( $seller_id ) {
    static $cache = [];

    if ( isset( $cache[ $seller_id ] ) ) {
        return $cache[ $seller_id ];
    }

    $cache[ $seller_id ] = sk()->vendor->get( $seller_id )->get_shop_info();

    return $cache[ $seller_id ];
}

/**
 * Get tabs for showing in a single store page
 *
 *
 * @param int $store_id
 *
 * @return array
 */
function sk_get_store_tabs( $store_id ) {
    $tabs = [
        'products' => [
            'title' => __( 'Inserate', 'sk-core' ),
            'url'   => sk_get_store_url( $store_id ),
        ],
    ];

    return apply_filters( 'sk_store_tabs', $tabs, $store_id );
}


/**
 * Get seller listing
 *
 * @param array $args
 *
 * @return array
 */
function sk_get_sellers( $args = [] ) {
    $vendors    = sk()->vendor;
    $all_vendor = wp_list_pluck( $vendors->get_vendors( $args ), 'data' );

    return [
        'users' => $all_vendor,
        'count' => $vendors->get_total(),
    ];
}


/**
 * Disable selling capability by default once a seller is registered
 *
 * @param int $user_id
 */
function sk_admin_user_register( $user_id ) {
    $user = new WP_User( $user_id );
    $role = reset( $user->roles );

    if ( $role === 'seller' ) {
        $enabled = 'automatically' === sk_get_container()->get( \SK\Core\Utilities\AdminSettings::class )->get_new_seller_enable_selling_status();
        update_user_meta( $user_id, 'sk_enable_selling', $enabled ? 'yes' : 'no' );
    }
}

add_action( 'user_register', 'sk_admin_user_register' );

/**
 * Get percentage based owo two numeric data
 *
 * @param int $this_period
 * @param int $last_period
 *
 * @return array
 */
function sk_get_percentage_of( $this_period = 0, $last_period = 0 ) {
    $parcent     = 0;
    $this_period = intval( $this_period );
    $last_period = intval( $last_period );

    if ( ( 0 === $this_period && 0 === $last_period ) || $this_period === $last_period ) {
        $class = 'up';
    } elseif ( 0 === $this_period ) {
        $parcent = $last_period * 100;
        $class   = 'down';
    } elseif ( 0 === $last_period ) {
        $parcent = $this_period * 100;
        $class   = 'up';
    } elseif ( $this_period > $last_period ) {
        $parcent = ( $this_period - $last_period ) / $last_period * 100;
        $class   = 'up';
    } elseif ( $this_period < $last_period ) {
        $parcent = ( $last_period - $this_period ) / $last_period * 100;
        $class   = 'down';
    }

    $parcent = round( $parcent, 2 );

    return [
        'parcent' => $parcent,
        'class'   => $class,
    ];
}


/**
 * SK prepare date query
 *
 * @param string $from
 * @param string $to
 *
 * @return array
 */
function sk_prepare_date_query( $from, $to ) {
    if ( ! $from || ! $to ) {
        return [];
    }

    $from_date     = date_create( $from );
    $raw_from_date = date_create( $from );
    $to_date       = date_create( $to );
    $raw_to_date   = date_create( $to );

    if ( ! $from_date || ! $to_date ) {
        wp_send_json( __( 'Date is not valid', 'sk-core' ) );
    }

    $from_year  = $from_date->format( 'Y' );
    $from_month = $from_date->format( 'm' );
    $from_day   = $from_date->format( 'd' );

    $to_year  = $to_date->format( 'Y' );
    $to_month = $to_date->format( 'm' );
    $to_day   = $to_date->format( 'd' );

    $date_diff      = date_diff( $from_date, $to_date );
    $last_from_date = $from_date->sub( $date_diff );
    $last_to_date   = $to_date->sub( $date_diff );

    $last_from_year  = $last_from_date->format( 'Y' );
    $last_from_month = $last_from_date->format( 'm' );
    $last_from_day   = $last_from_date->format( 'd' );

    $last_to_year  = $last_to_date->format( 'Y' );
    $last_to_month = $last_to_date->format( 'm' );
    $last_to_day   = $last_to_date->format( 'd' );

    $prepared_data = [
        'from_year'           => $from_year,
        'from_month'          => $from_month,
        'from_day'            => $from_day,
        'to_year'             => $to_year,
        'to_month'            => $to_month,
        'to_day'              => $to_day,
        'from_full_date'      => $raw_from_date->format( 'Y-m-d' ),
        'to_full_date'        => $raw_to_date->format( 'Y-m-d' ),
        'last_from_year'      => $last_from_year,
        'last_from_month'     => $last_from_month,
        'last_from_day'       => $last_from_day,
        'last_from_full_date' => $last_from_date->format( 'Y-m-d' ),
        'last_to_year'        => $last_to_year,
        'last_to_month'       => $last_to_month,
        'last_to_day'         => $last_to_day,
        'last_to_full_date'   => $last_to_date->format( 'Y-m-d' ),
    ];

    return $prepared_data;
}


/**
 * Prevent sellers and customers from seeing the admin bar
 *
 * @param bool $show_admin_bar
 *
 * @return bool
 */
function sk_disable_admin_bar( $show_admin_bar ) {
    global $current_user;

    if ( $current_user->ID !== 0 ) {
        $role = reset( $current_user->roles );
        if ( in_array( $role, [ 'seller', 'customer', 'vendor_staff' ], true ) ) {
            return false;
        }
    }

    return $show_admin_bar;
}

add_filter( 'show_admin_bar', 'sk_disable_admin_bar' );

/**
 * Filter products of current user
 *
 *
 * @param object $query
 *
 * @return object $query
 */
function sk_filter_product_for_current_vendor( $query ) {
    if ( current_user_can( 'manage_woocommerce' ) ) {
        return $query;
    }

    if ( ! isset( $query->query_vars['post_type'] ) ) {
        return $query;
    }

    if ( is_admin() && $query->is_main_query() && $query->query_vars['post_type'] === 'product' ) {
        $query->set( 'author', get_current_user_id() );
    }

    return $query;
}

add_filter( 'pre_get_posts', 'sk_filter_product_for_current_vendor' );

/**
 * Remove sellerdiv metabox when a seller can access the backend
 *
 *
 * @return void
 */
function sk_remove_sellerdiv_metabox() {
    if ( current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    if ( is_admin() && get_post_type() === 'product' && ! defined( 'DOING_AJAX' ) ) {
        remove_meta_box( 'sellerdiv', 'product', 'normal' );
    }
}

add_action( 'do_meta_boxes', 'sk_remove_sellerdiv_metabox' );


/**
 * Filter `get_avatar_url` to retrieve image url from sk profile settings
 * called by `get_avatar_url()` as well as `get_avatar()`
 *
 *
 * @param string $url         avatar url
 * @param mixed  $id_or_email userdata or user_id or user_email
 * @param array  $args        arguments
 *
 * @return string maybe modified url
 */
function sk_get_avatar_url( $url, $id_or_email, $args ) {
    if ( is_numeric( $id_or_email ) ) {
        $user = get_user_by( 'id', $id_or_email );
    } elseif ( is_object( $id_or_email ) ) {
        if ( (int) $id_or_email->user_id !== 0 ) {
            $user = get_user_by( 'id', $id_or_email->user_id );
        } else {
            return $url;
        }
    } else {
        $user = get_user_by( 'email', $id_or_email );
    }

    if ( ! $user ) {
        return $url;
    }

    $vendor = sk()->vendor;

    if ( ! $vendor ) {
        return $url;
    }

    $vendor = $vendor->get( $user->ID );
    if ( ! $vendor->is_vendor() ) {
        return $url;
    }

    $gravatar_id = $vendor->get_avatar_id();

    if ( ! $gravatar_id ) {
        return $url;
    }

    $sk_avatar_url = wp_get_attachment_thumb_url( $gravatar_id );

    if ( empty( $sk_avatar_url ) ) {
        return $url;
    }

    return esc_url( $sk_avatar_url );
}

add_filter( 'get_avatar_url', 'sk_get_avatar_url', 99, 3 );

/**
 * Get navigation url for the sk dashboard
 *
 * @param string $name    endpoint name
 * @param bool   $new_url if true, it will return the new url format
 *
 * @return string url
 */
function sk_get_navigation_url( $name = '', $new_url = false ) {
    $page_id = (int) sk_get_option( 'dashboard', 'sk_pages', 0 );

    if ( ! $page_id ) {
        return '';
    }

    $url = rtrim( get_permalink( $page_id ), '/' ) . '/';

    if ( ! empty( $name ) && ! $new_url ) {
        $url = sk_add_subpage_to_url( $url, $name . '/' );
    }

    if ( $new_url ) {
        $url = sk_add_subpage_to_url( $url, 'new/' );
        $url = $url . '#' . $name . '/';
    }

    return apply_filters( 'sk_get_navigation_url', esc_url( $url ), $name, $new_url );
}


/**
 * Send email to seller and admin when there is no product in stock or low stock
 *
 *
 * @param string $recipient recipients email
 * @param WC_Product $product
 *
 * @return string recipient emails
 */
function sk_wc_email_recipient_add_seller_no_stock( $recipient, $product ) {
    $product_id   = $product->get_id();
    $seller_id    = get_post_field( 'post_author', $product_id );
    $seller_email = sk()->vendor->get( $seller_id )->get_email();

    return $recipient . ', ' . $seller_email;
}

add_filter( 'woocommerce_email_recipient_no_stock', 'sk_wc_email_recipient_add_seller_no_stock', 10, 2 );
add_filter( 'woocommerce_email_recipient_low_stock', 'sk_wc_email_recipient_add_seller_no_stock', 10, 2 );

/**
 * Get all the months of products of a vendor.
 *
 *
 * @param int $user_id
 *
 * @return object
 */
function sk_get_products_listing_months_for_vendor( $user_id ) {
    global $wpdb, $wp_locale;

    $months = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->prepare(
            "SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
            FROM $wpdb->posts
            WHERE post_type = 'product'
            AND post_author = %d
            ORDER BY post_date DESC",
            $user_id
        )
    );

    /**
     * Filter the 'Months' drop-down results.
     *
     *
     * @param object $months the months drop-down query results
     */
    return apply_filters( 'months_dropdown_results', $months, 'product' );
}


/**
 * Display form for filtering product listing on seller dashboard
 *
 */
function sk_product_listing_filter() {
    $template_args = [
        'product_types'       => apply_filters( 'sk_product_types', [ 'simple' => __( 'Simple', 'sk-core' ) ] ),
        'product_cat'         => -1,
        'product_brand'       => -1,
        'product_search_name' => '',
        'date'                => '',
        'product_type'        => '',
        'filter_by_other'     => '',
        'post_status'         => 'all',
    ];

    if ( isset( $_GET['_product_listing_filter_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_product_listing_filter_nonce'] ) ), 'product_listing_filter' ) ) {
        $template_args['product_cat']         = ! empty( $_GET['product_cat'] ) ? intval( wp_unslash( $_GET['product_cat'] ) ) : -1;
        $template_args['product_brand']       = ! empty( $_GET['product_brand'] ) ? intval( wp_unslash( $_GET['product_brand'] ) ) : -1;
        $template_args['product_search_name'] = ! empty( $_GET['product_search_name'] ) ? sanitize_text_field( wp_unslash( $_GET['product_search_name'] ) ) : '';
        $template_args['date']                = ! empty( $_GET['date'] ) ? intval( wp_unslash( $_GET['date'] ) ) : '';
        $template_args['product_type']        = ! empty( $_GET['product_type'] ) ? sanitize_text_field( wp_unslash( $_GET['product_type'] ) ) : '';
        $template_args['filter_by_other']     = ! empty( $_GET['filter_by_other'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_by_other'] ) ) : '';
        $template_args['post_status']         = ! empty( $_GET['post_status'] ) ? sanitize_text_field( wp_unslash( $_GET['post_status'] ) ) : 'all';
    }

    sk_get_template_part( 'products/listing-filter', '', apply_filters( 'sk_product_listing_filter_args', $template_args ) );
}

/**
 * Search by SKU or ID for seller dashboard product listings.
 *
 * @param string $where
 *
 * @return string
 */
function sk_product_search_by_sku( $where ) {
    // nonce checking
    if ( ! isset( $_GET['_product_listing_filter_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_product_listing_filter_nonce'] ) ), 'product_listing_filter' ) ) {
        return $where;
    }

    if ( empty( $_GET['product_search_name'] ) ) {
        return $where;
    }

    global $wpdb;

    $search_ids = [];
    $terms      = explode( ',', wc_clean( wp_unslash( $_GET['product_search_name'] ) ) );

    foreach ( $terms as $term ) {
        if ( is_numeric( $term ) ) {
            $search_ids[] = $term;
        }

        // Attempt to get a SKU
        $wild = '%';
        $find = wc_clean( $term );
        $like = $wild . $wpdb->esc_like( $find ) . $wild;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $sku_to_id = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_sku' AND meta_value LIKE %s", $like ) );

        if ( $sku_to_id && count( $sku_to_id ) > 0 ) {
            $search_ids = array_merge( $search_ids, $sku_to_id );
        }
    }

    $search_ids = array_filter( array_map( 'absint', $search_ids ) );

    if ( count( $search_ids ) > 0 ) {
        $where = str_replace( ')))', ") OR ({$wpdb->posts}.ID IN (" . implode( ',', $search_ids ) . '))))', $where );
    }

    return $where;
}

add_filter( 'posts_search', 'sk_product_search_by_sku' );

/**
 * SK Social Profile fields
 *
 *
 * @return array
 */
function sk_get_social_profile_fields() {
    $fields = [
        'fb'        => [
            'icon'  => 'facebook-square',
            'title' => __( 'Facebook', 'sk-core' ),
        ],
        'twitter'   => [
            'icon'  => 'fa-brands fa-square-x-twitter',
            'title' => __( 'X', 'sk-core' ),
        ],
        'pinterest' => [
            'icon'  => 'pinterest-square',
            'title' => __( 'Pinterest', 'sk-core' ),
        ],
        'linkedin'  => [
            'icon'  => 'linkedin',
            'title' => __( 'LinkedIn', 'sk-core' ),
        ],
        'youtube'   => [
            'icon'  => 'youtube-square',
            'title' => __( 'Youtube', 'sk-core' ),
        ],
        'tiktok'    => [
            'icon'  => 'tiktok',
            'title' => __( 'TikTok', 'sk-core' ),
        ],
        'instagram' => [
            'icon'  => 'instagram',
            'title' => __( 'Instagram', 'sk-core' ),
        ],
        'flickr'    => [
            'icon'  => 'flickr',
            'title' => __( 'Flickr', 'sk-core' ),
        ],
        'threads'   => [
            'icon'  => 'fa-brands fa-threads',
            'title' => __( 'Threads', 'sk-core' ),
        ],
    ];

    return apply_filters( 'sk_profile_social_fields', $fields );
}

/**
 * Generate Address string | array for given seller id or current user
 *
 *
 * @param int $seller_id, defaults to current_user_id
 * @param bool $get_array, if true returns array instead of string
 *
 * @return string|array Address | array Address
 */
function sk_get_seller_address( $seller_id = 0, $get_array = false ) {
    if ( empty( $seller_id ) ) {
        $seller_id = sk_get_current_user_id();
    }

    $profile_info = sk_get_store_info( $seller_id );

    if ( isset( $profile_info['address'] ) ) {
        $address = $profile_info['address'];

        $country_obj = new WC_Countries();
        $countries   = $country_obj->countries;
        $states      = $country_obj->states;

        $street_1 = isset( $address['street_1'] ) ? $address['street_1'] : '';
        $street_2 = isset( $address['street_2'] ) ? $address['street_2'] : '';
        $city     = isset( $address['city'] ) ? $address['city'] : '';

        $zip          = isset( $address['zip'] ) ? $address['zip'] : '';
        $country_code = isset( $address['country'] ) ? $address['country'] : '';
        $state_code   = isset( $address['state'] ) ? ( $address['state'] === 'N/A' ) ? '' : $address['state'] : '';

        $country_name = isset( $countries[ $country_code ] ) ? $countries[ $country_code ] : '';
        $state_name   = isset( $states[ $country_code ][ $state_code ] ) ? $states[ $country_code ][ $state_code ] : $state_code;
    } else {
        return 'N/A';
    }

    if ( $get_array === true ) {
        $address = [
            'street_1' => $street_1,
            'street_2' => $street_2,
            'city'     => $city,
            'zip'      => $zip,
            'country'  => $country_name,
            'state'    => isset( $states[ $country_code ][ $state_code ] ) ? $states[ $country_code ][ $state_code ] : $state_code,
        ];

        return apply_filters( 'sk_get_seller_address', $address, $profile_info );
    }

    $country           = new WC_Countries();
    $formatted_address = $country->get_formatted_address(
        [
            'address_1' => $street_1,
            'address_2' => $street_2,
            'city'      => $city,
            'postcode'  => $zip,
            'state'     => $state_code,
            'country'   => $country_code,
        ]
    );

    return apply_filters( 'sk_get_seller_formatted_address', $formatted_address, $profile_info );
}

/**
 * SK get seller short formatted address
 *
 *
 * @param int  $store_id
 * @param bool $line_break
 *
 * @return string
 */
function sk_get_seller_short_address( $store_id, $line_break = true ) {
    $store_address     = sk_get_seller_address( $store_id, true );
    $address_classes   = [
        'street_1',
        'street_2',
        'city',
        'state',
        'country',
    ];
    $short_address     = [];
    $formatted_address = '';

    if ( ! empty( $store_address['street_1'] ) && empty( $store_address['street_2'] ) ) {
        $short_address[] = "<span class='{$address_classes[0]}'> {$store_address['street_1']},</span>";
    } elseif ( empty( $store_address['street_1'] ) && ! empty( $store_address['street_2'] ) ) {
        $short_address[] = "<span class='{$address_classes[1]}'> {$store_address['street_2']},</span>";
    } elseif ( ! empty( $store_address['street_1'] ) && ! empty( $store_address['street_2'] ) ) {
        $short_address[] = "<span class='{$address_classes[0]} {$address_classes[1]}'> {$store_address['street_1']}, {$store_address['street_2']}</span>";
    }

    if ( ! empty( $store_address['city'] ) && ! empty( $store_address['city'] ) ) {
        $short_address[] = "<span class='{$address_classes[2]}'> {$store_address['city']},</span>";
    }

    if ( ! empty( $store_address['state'] ) && ! empty( $store_address['country'] ) ) {
        $short_address[] = "<span class='{$address_classes[3]}'> {$store_address['state']},</span><span class='{$address_classes[4]}'> {$store_address['country']} </span>";
    } elseif ( ! empty( $store_address['country'] ) ) {
        $short_address[] = "<span class='{$address_classes[4]}'> {$store_address['country']} </span>";
    }

    if ( ! empty( $short_address ) && $line_break ) {
        $formatted_address = implode( '<br>', $short_address );
    } else {
        $formatted_address = implode( ' ', $short_address );
    }

    return apply_filters( 'sk_store_header_adress', $formatted_address, $store_address, $short_address );
}

/**
 * Login Redirect
 *
 *
 * @param string  $redirect_to [url]
 * @param WP_User $user
 *
 * @return string [url]
 */
function sk_after_login_redirect( $redirect_to, $user ) {
    // get the redirect url from $_GET
    if ( ! empty( $_GET['redirect_to'] ) ) { // phpcs:ignore
        $redirect_to = esc_url( wp_unslash( $_GET['redirect_to'] ) ); // phpcs:ignore
    } elseif ( user_can( $user, 'skdar' ) && wc_get_page_permalink( 'checkout' ) !== $redirect_to ) {
        $seller_dashboard = (int) sk_get_option( 'dashboard', 'sk_pages' );

        if ( $seller_dashboard !== - 1 ) {
            $redirect_to = get_permalink( $seller_dashboard );
        }
    }

    return $redirect_to;
}

add_filter( 'woocommerce_login_redirect', 'sk_after_login_redirect', 1, 2 );


add_action( 'wp', 'sk_set_is_home_false_on_store' );

function sk_set_is_home_false_on_store() {
    global $wp_query;

    if ( sk_is_store_page() ) {
        $wp_query->is_home = false;
    }
}

/**
 * Register sk store widget
 *
 * @return void
 */
function sk_register_store_widget() {
    register_sidebar(
        apply_filters(
            'sk_store_widget_args', [
                'name'          => __( 'SK Store Sidebar', 'sk-core' ),
                'id'            => 'sidebar-store',
                'before_widget' => '<aside id="%1$s" class="widget sk-store-widget %2$s">',
                'after_widget'  => '</aside>',
                'before_title'  => '<h3 class="widget-title">',
                'after_title'   => '</h3>',
            ]
        )
    );
}

add_action( 'widgets_init', 'sk_register_store_widget' );

add_action( 'delete_user', 'sk_delete_user_details', 10, 2 );

/**
 * Delete user's details when the user is deleted
 *
 *
 * @param int $user_id , int $reassign
 *
 * @return void
 */
function sk_delete_user_details( $user_id, $reassign ) {
    if ( ! sk_is_user_seller( $user_id ) ) {
        return;
    }

    if ( is_null( $reassign ) ) {
        $args = [
            'numberposts' => - 1,
            'post_type'   => 'any',
            'author'      => $user_id,
        ];

        // get all posts by this user
        $user_posts = get_posts( $args );

        if ( empty( $user_posts ) ) {
            return;
        }

        // delete all the posts
        foreach ( $user_posts as $user_post ) {
            wp_delete_post( $user_post->ID, true );
        }
    }
}

/**
 * Get a vendor
 *
 *
 * @param int $vendor_id
 *
 * @return SK\Core\Vendor\Vendor
 */
function sk_get_vendor( $vendor_id = null ) {
    if ( ! $vendor_id ) {
        $vendor_id = wp_get_current_user();
    }

    return sk()->vendor->get( $vendor_id );
}

/**
 * Get all cap related to seller
 *
 *
 * @return array
 */
function sk_get_all_caps() {
    $capabilities = [
        'overview' => [
            'sk_view_sales_overview'        => __( 'View sales overview', 'sk-core' ),
            'sk_view_sales_report_chart'    => __( 'View sales report chart', 'sk-core' ),
            'sk_view_announcement'          => __( 'View announcement', 'sk-core' ),
            'sk_view_order_report'          => __( 'View order report', 'sk-core' ),
            'sk_view_review_reports'        => __( 'View review report', 'sk-core' ),
            'sk_view_product_status_report' => __( 'View product status report', 'sk-core' ),
        ],
        'report'   => [
            'sk_view_overview_report'    => __( 'View overview report', 'sk-core' ),
            'sk_view_daily_sale_report'  => __( 'View daily sales report', 'sk-core' ),
            'sk_view_top_selling_report' => __( 'View top selling report', 'sk-core' ),
            'sk_view_top_earning_report' => __( 'View top earning report', 'sk-core' ),
            'sk_view_statement_report'   => __( 'View statement report', 'sk-core' ),
        ],
        'order'    => [
            'sk_view_order'        => __( 'View order', 'sk-core' ),
            'sk_manage_order'      => __( 'Manage order', 'sk-core' ),
            'sk_manage_order_note' => __( 'Manage order note', 'sk-core' ),
            'sk_manage_refund'     => __( 'Manage refund', 'sk-core' ),
            'sk_export_order'      => __( 'Export order', 'sk-core' ),
        ],
        'coupon'   => [
            'sk_add_coupon'    => __( 'Add coupon', 'sk-core' ),
            'sk_edit_coupon'   => __( 'Edit coupon', 'sk-core' ),
            'sk_delete_coupon' => __( 'Delete coupon', 'sk-core' ),
        ],
        'review'   => [
            'sk_view_reviews'   => __( 'View reviews', 'sk-core' ),
            'sk_manage_reviews' => __( 'Manage reviews', 'sk-core' ),
        ],

        'product'  => [
            'sk_add_product'       => __( 'Add product', 'sk-core' ),
            'sk_edit_product'      => __( 'Edit product', 'sk-core' ),
            'sk_delete_product'    => __( 'Delete product', 'sk-core' ),
            'sk_view_product'      => __( 'View product', 'sk-core' ),
            'sk_duplicate_product' => __( 'Duplicate product', 'sk-core' ),
            'sk_import_product'    => __( 'Import product', 'sk-core' ),
            'sk_export_product'    => __( 'Export product', 'sk-core' ),
        ],
        'menu'     => [
            'sk_view_overview_menu'       => __( 'View overview menu', 'sk-core' ),
            'sk_view_product_menu'        => __( 'View product menu', 'sk-core' ),
            'sk_view_review_menu'         => __( 'View review menu', 'sk-core' ),
            'sk_view_store_settings_menu' => __( 'View store settings menu', 'sk-core' ),
            'sk_view_store_social_menu'   => __( 'View social settings menu', 'sk-core' ),
        ],
    ];

    return apply_filters( 'sk_get_all_cap', $capabilities );
}

/**
 * Get translated capability
 *
 *
 * @param string $cap
 *
 * @return string
 */
function sk_get_all_cap_labels( $cap ) {
    $caps = apply_filters(
        'sk_get_all_cap_labels', [
            'overview' => __( 'Overview', 'sk-core' ),
            'report'   => __( 'Report', 'sk-core' ),
            'order'    => __( 'Order', 'sk-core' ),
            'coupon'   => __( 'Coupon', 'sk-core' ),
            'review'   => __( 'Review', 'sk-core' ),
            'product'  => __( 'Product', 'sk-core' ),
            'menu'     => __( 'Menu', 'sk-core' ),
        ]
    );

    return ! empty( $caps[ $cap ] ) ? $caps[ $cap ] : '';
}

/**
 * Merge user defined arguments into defaults array.
 *
 * This function is similiar to WordPress wp_parse_args().
 * It's support multidimensional array.
 *
 * @param array $args
 * @param array $defaults optional
 *
 * @return array
 */
function sk_parse_args( &$args, $defaults = [] ) {
    $args     = (array) $args;
    $defaults = (array) $defaults;
    $r        = $defaults;

    foreach ( $args as $k => &$v ) {
        if ( is_array( $v ) && isset( $r[ $k ] ) ) {
            $r[ $k ] = sk_parse_args( $v, $r[ $k ] );
        } else {
            $r[ $k ] = $v;
        }
    }

    return $r;
}


/**
 * SK get translated days
 *
 *
 * @param string|null $days
 * @maram string/null $form
 *
 * @return string|array
 */
function sk_get_translated_days( $day = '', $form = 'long' ) {

    $all_days = [
        'sunday'    => __( 'Sunday', 'sk-core' ),
        'monday'    => __( 'Monday', 'sk-core' ),
        'tuesday'   => __( 'Tuesday', 'sk-core' ),
        'wednesday' => __( 'Wednesday', 'sk-core' ),
        'thursday'  => __( 'Thursday', 'sk-core' ),
        'friday'    => __( 'Friday', 'sk-core' ),
        'saturday'  => __( 'Saturday', 'sk-core' ),
    ];

    if ( 'short' === $form ) {
        $all_days = [
			'sunday'    => __( 'Sun', 'sk-core' ),
			'monday'    => __( 'Mon', 'sk-core' ),
			'tuesday'   => __( 'Tue', 'sk-core' ),
			'wednesday' => __( 'Wed', 'sk-core' ),
			'thursday'  => __( 'Thu', 'sk-core' ),
			'friday'    => __( 'Fri', 'sk-core' ),
			'saturday'  => __( 'Sat', 'sk-core' ),
        ];
    }

    $week_starts_on = get_option( 'start_of_week', 0 );
    $day_keys       = array_keys( $all_days );

    // Make our start day of the week using by week starts settings.
    for ( $i = 0; $i < $week_starts_on; $i++ ) {
        $shifted_key   = $day_keys[ $i ];
        $shifted_value = $all_days[ $shifted_key ];

        // Unset days and sets in the last.
        unset( $all_days[ $shifted_key ] );
        $all_days[ $shifted_key ] = $shifted_value;
    }

    // Get days array if our $days is true.
    if ( empty( $day ) ) {
        return $all_days;
    }

    if ( isset( $all_days[ $day ] ) ) {
        return $all_days[ $day ];
    }

    return apply_filters( 'sk_get_translated_days', '', $day );
}

/**
 * SK get pro buy now url
 *
 *
 * @return string [url]
 */
function sk_pro_buynow_url() {
    $link = 'https://sk.co/wordpress/pricing/';

    if ( $aff = get_option( '_sk_aff_ref' ) ) { // phpcs:ignore
        $link = add_query_arg( [ 'ref' => $aff ], $link );
    }

    return $link;
}


/**
 * SK get variable product earnings
 *
 * @deprecated 2.9.21
 *
 * @param int  $product_id
 * @param bool $formated
 * @param bool $deprecated
 *
 * @return float|string
 */
function sk_get_variable_product_earning( $product_id, $formated = true, $deprecated = false ) {
    if ( $deprecated ) {
        wc_deprecated_argument( 'seller_id', '2.9.21', 'sk_get_variable_product_earning() does not require a seller_id anymore.' );
    }

    $product = wc_get_product( $product_id );

    if ( ! $product ) {
        return null;
    }

    $variations = $product->get_children();

    if ( empty( $variations ) || ! is_array( $variations ) ) {
        return null;
    }

    $earnings = array_map(
        function ( $id ) {
            return 0;
        }, $variations
    );

    if ( empty( $earnings ) || ! is_array( $earnings ) ) {
        return null;
    }

    if ( count( $earnings ) === 1 ) {
        return $formated ? wc_price( $earnings[0] ) : $earnings[0];
    }

    $min_earning = $formated ? wc_price( min( $earnings ) ) : min( $earnings );
    $max_earning = $formated ? wc_price( max( $earnings ) ) : max( $earnings );
    $seperator   = apply_filters( 'sk_get_variable_product_earning_seperator', ' - ', $product_id );
    $earning     = $min_earning . $seperator . $max_earning;

    return $earning;
}


/**
 * Check if it's store listing page
 *
 *
 * @return bool
 */
function sk_is_store_listing() {
    $page_id = get_the_ID();
    $found   = false;

    if ( $page_id === intval( sk_get_option( 'store_listing', 'sk_pages' ) ) ) {
        $found = true;
    }

    if ( ! $found ) {
        $post = get_post( $page_id );

        if ( $post && false !== strpos( $post->post_content, '[sk-stores' ) ) {
            $found = true;
        }
    }

    return apply_filters( 'sk_is_store_listing', $found, $page_id );
}

/**
 * SK generate username
 *
 * @param string $name
 *
 * @return string
 */
function sk_generate_username( $name = 'store' ) {
    static $i = 1;

    $name = implode( '', explode( ' ', $name ) );

    if ( ! username_exists( $name ) ) {
        return $name;
    }

    $new_name = sprintf( '%s-%d', $name, $i++ );

    if ( ! username_exists( $new_name ) ) {
        return $new_name;
    }

    return call_user_func( __FUNCTION__, $name );
}

/**
 * Replaces placeholders with links to policy pages.
 *
 *
 * @param string $text text to find/replace within
 *
 * @return string
 */
function sk_replace_policy_page_link_placeholders( $text ) {
    $privacy_page_id = sk_get_option( 'privacy_page', 'sk_privacy' );
    $privacy_link    = $privacy_page_id ? '<a href="' . esc_url( get_permalink( $privacy_page_id ) ) . '" class="sk-privacy-policy-link" target="_blank">' . __( 'privacy policy', 'sk-core' ) . '</a>' : __( 'privacy policy', 'sk-core' );

    $find_replace = [
        '[sk_privacy_policy]' => $privacy_link,
    ];

    return str_replace( array_keys( $find_replace ), array_values( $find_replace ), $text );
}

/**
 * SK privacy policy text
 *
 *
 * @param bool $return
 *
 * @return string
 */
function sk_privacy_policy_text( $return = false ) {
    $is_enabled   = 'on' === sk_get_option( 'enable_privacy', 'sk_privacy' );
    $privacy_page = sk_get_option( 'privacy_page', 'sk_privacy' );
    $privacy_text = sk_get_option( 'privacy_policy', 'sk_privacy', __( 'Your personal data will be used to support your experience throughout this website, to manage access to your account, and for other purposes described in our [sk_privacy_policy]', 'sk-core' ) );

    if ( ! $is_enabled || ! $privacy_page ) {
        return '';
    }

    $text = wpautop( sk_replace_policy_page_link_placeholders( $privacy_text ), true );

    if ( $return ) {
        return wp_kses_post( $text );
    }

    echo wp_kses_post( $text );
}

/**
 * Validate a boolean variable
 *
 *
 * @param mixed $var
 *
 * @return bool
 */
function sk_validate_boolean( $var ) {
    return filter_var( $var, FILTER_VALIDATE_BOOLEAN );
}

/**
 * Backward compatibile settings option map
 *
 *
 * @param string $option
 * @param string $section
 *
 * @return array
 */
function sk_admin_settings_rearrange_map( $option, $section ) {
    $id = $option . '_' . $section;

    $map = apply_filters(
        'sk_admin_settings_rearrange_map', [
            'store_map_sk_general'                  => [ 'store_map', 'sk_appearance' ],
            'contact_seller_sk_general'             => [ 'contact_seller', 'sk_appearance' ],
            'enable_theme_store_sidebar_sk_general' => [ 'enable_theme_store_sidebar', 'sk_appearance' ],
        ]
    );

    if ( isset( $map[ $id ] ) ) {
        return $map[ $id ];
    }

    return [ $option, $section ];
}

/**
 * SK get terms and condition page url
 *
 *
 * @return string | null on failure
 */
function sk_get_terms_condition_url() {
    $page_id = sk_get_option( 'reg_tc_page', 'sk_pages' );

    if ( ! $page_id ) {
        return null;
    }

    return apply_filters( 'sk_get_terms_condition_url', get_permalink( $page_id ), $page_id );
}

if ( ! function_exists( 'sk_get_seller_status_count' ) ) {
    /**
     * Get Seller status counts, used in admin area
     *
     *
     * @return array
     */
    function sk_get_seller_status_count() {
        $active_users = new WP_User_Query(
            [
                'role__in'   => [ 'seller', 'administrator' ],
                'meta_key'   => 'sk_enable_selling', // phpcs:ignore
                'meta_value' => 'yes', // phpcs:ignore
                'fields'     => 'ID',
            ]
        );

        $all_users      = new WP_User_Query(
            [
                'role__in' => [ 'seller', 'administrator' ],
                'fields'   => 'ID',
            ]
        );
        $active_count   = $active_users->get_total();
        $inactive_count = $all_users->get_total() - $active_count;

        return apply_filters(
            'sk_get_seller_status_count', [
                'total'    => $active_count + $inactive_count,
                'active'   => $active_count,
                'inactive' => $inactive_count,
            ]
        );
    }
}

/**
 * Install a plugin from wp.org
 *
 * Example:
 * To download WooCommerce `sk_install_wp_org_plugin( 'woocommerce' )`
 * To download plugin like sk-lite that has different slug and main plugin file,
 * `sk_install_wp_org_plugin( 'sk-core', 'sk.php' )`
 *
 *
 * @param string $plugin_slug
 * @param string $main_file
 *
 * @return bool|\WP_Error
 */
function sk_install_wp_org_plugin( $plugin_slug, $main_file = null ) {
    $plugin = $plugin_slug . '/' . ( $main_file ? $main_file : $plugin_slug . '.php' );

    if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {
        include_once ABSPATH . 'wp-admin/includes/file.php';
        include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        $api = plugins_api(
            'plugin_information', [
                'slug'   => $plugin_slug,
                'fields' => [
                    'sections' => false,
                ],
            ]
        );

        if ( is_wp_error( $api ) ) {
            return new WP_Error(
                'sk_install_wp_org_plugin_error_api',
                // translators: 1) plugin slug
                sprintf( __( 'Unable to fetch plugin information from wordpress.org for %s.', 'sk-core' ), $plugin_slug )
            );
        }

        $upgrader  = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
        $installed = $upgrader->install( $api->download_link );

        if ( is_wp_error( $installed ) ) {
            return $installed;
        } elseif ( ! $installed ) {
            return new WP_Error(
                'sk_install_wp_org_plugin_error',
                // translators: 1) plugin slug
                sprintf( __( 'Unable to install %s from wordpress.org', 'sk-core' ), $plugin_slug )
            );
        }
    }

    $activate_plugin = activate_plugin( $plugin );

    if ( is_wp_error( $activate_plugin ) ) {
        return $activate_plugin;
    }

    return true;
}


/**
 * SK generate star ratings
 *
 *
 * @param int $rating Number of rating point
 * @param int $starts Total number of stars
 *
 * @return string
 */
function sk_generate_ratings( $rating, $stars ) {
    $result = '';
    $rating = wc_format_decimal( floatval( $rating ), 2 );

    for ( $i = 1; $i <= $stars; $i++ ) {
        if ( $rating >= $i ) {
            $result .= "<i class='dashicons dashicons-star-filled'></i>";
        } elseif ( $rating > ( $i - 1 ) && $rating < $i ) {
            $result .= "<i class='dashicons dashicons-star-half'></i>";
        } else {
            $result .= "<i class='dashicons dashicons-star-empty'></i>";
        }
    }

    return apply_filters( 'sk_generate_ratings', $result );
}

/**
 * Check if current PHP version met the minimum requried PHP version for WooCommerce
 *
 *
 * @param string $required_version
 *
 * @return bool
 */
function sk_met_minimum_php_version_for_wc( $required_version = '7.0' ) {
    return apply_filters( 'sk_met_minimum_php_version_for_wc', version_compare( PHP_VERSION, $required_version, '>=' ), $required_version );
}

/**
 * Checks if SK settings has map api key
 *
 *
 * @return bool
 */
function sk_has_map_api_key() {
    $sk_appearance = get_option( 'sk_appearance', [] );

    return ! empty( $sk_appearance['mapbox_access_token'] );
}


/**
 * Check which vendor info should be hidden
 *
 *
 * @param string $option
 *
 * @return bool|array if no param is passed
 */
function sk_is_vendor_info_hidden( $option = null ) {
    $options = sk_get_option( 'hide_vendor_info', 'sk_appearance' );

    if ( is_null( $option ) ) {
        return $options;
    }

    return ! empty( $options[ $option ] );
}

/**
 * Function current_datetime() compatibility for wp version < 5.3
 *
 *
 * @return DateTimeImmutable
 */
function sk_current_datetime() {
    if ( function_exists( 'current_datetime' ) ) {
        return current_datetime();
    }

    return new DateTimeImmutable( 'now', sk_wp_timezone() );
}

/**
 * Function wp_timezone() compatibility for wp version < 5.3
 *
 *
 * @return DateTimeZone
 */
function sk_wp_timezone() {
    if ( function_exists( 'wp_timezone' ) ) {
        return wp_timezone();
    }

    return new DateTimeZone( sk_wp_timezone_string() );
}

/**
 * Function wp_timezone_string() compatibility for wp version < 5.3
 *
 *
 * @return string
 */
function sk_wp_timezone_string() {
    if ( function_exists( 'wp_timezone_string' ) ) {
        return wp_timezone_string();
    }

    $timezone_string = get_option( 'timezone_string' );

    if ( $timezone_string ) {
        return $timezone_string;
    }

    $offset  = (float) get_option( 'gmt_offset' );
    $hours   = (int) $offset;
    $minutes = ( $offset - $hours );

    $sign      = ( $offset < 0 ) ? '-' : '+';
    $abs_hour  = abs( $hours );
    $abs_mins  = abs( $minutes * 60 );
    $tz_offset = sprintf( '%s%02d:%02d', $sign, $abs_hour, $abs_mins );

    return $tz_offset;
}

/**
 * Get a formatted date, time from WordPress format
 *
 *
 * @param string|bool                  $format date format string or false for default WordPress date
 * @param string|int|DateTimeImmutable $date   the date string or timestamp or DateTimeImmutable object
 *
 * @return string|false The date, translated if locale specifies it. False on invalid timestamp input.
 */
function sk_format_datetime( $date = '', $format = false ) {
    // if no format is specified, get default WordPress date format
    if ( ! $format ) {
        $format = wc_date_format() . ' ' . wc_time_format();
    }

    // if date is empty, get current datetime timestamp
    if ( empty( $date ) ) {
        $timestamp = sk_current_datetime()->getTimestamp();
        // if date is not timestamp, convert it to timestamp
    } elseif ( $date instanceof DateTimeImmutable ) {
        $timestamp = $date->getTimestamp();
        // if the date param is string, convert it to timestamp
    } elseif ( is_numeric( $date ) ) {
        $timestamp = $date;
    } elseif ( is_string( $date ) && strtotime( $date ) ) {
        $timestamp = sk_current_datetime()->modify( $date )->getTimestamp();
        // if date is already timestamp, just use it
    } else {
        // we couldn't recognize the $date argument
        return false;
    }

    if ( function_exists( 'wp_date' ) ) {
        return wp_date( $format, $timestamp );
    }

    return date_i18n( $format, $timestamp );
}

/**
 * Get a formatted date from WordPress format
 *
 *
 * @param string|int|DateTimeImmutable $date   the date string or timestamp or DateTimeImmutable object
 * @param string|bool                  $format date format string or false for default WordPress date
 *
 * @return string|false The date, translated if locale specifies it. False on invalid timestamp input.
 */
function sk_format_date( $date = '', $format = false ) {
    // if no format is specified, get default WordPress date format
    if ( ! $format ) {
        $format = wc_date_format();
    }

    return sk_format_datetime( $date, $format );
}


/**
 * Create an expected date time format from a given format.
 *
 *
 * @param string $format      Date string format
 * @param string $date_string Date time string
 *
 * @return DateTimeImmutable|false
 */
function sk_create_date_from_format( $format, $date_string ) {
    return \DateTimeImmutable::createFromFormat(
        $format,
        $date_string,
        new \DateTimeZone( sk_wp_timezone_string() )
    );
}


/**
 * Get vendor store banner width
 *
 * Added new filter hook for vendor store
 * banner width size @hook sk_store_banner_default_width
 *
 *
 * @return int $width Banner width
 */
function sk_get_vendor_store_banner_width() {
    $width = absint( apply_filters( 'sk_store_banner_default_width', sk_get_option( 'store_banner_width', 'sk_appearance', 625 ) ) );

    return ( $width !== 0 ) ? $width : 625;
}

/**
 * Get vendor store banner height
 *
 * Added new filter hook for vendor
 * store banner height size @hook sk_store_banner_default_height
 *
 *
 * @return int $height Banner height
 */
function sk_get_vendor_store_banner_height() {
    $height = absint( apply_filters( 'sk_store_banner_default_height', sk_get_option( 'store_banner_height', 'sk_appearance', 300 ) ) );

    return ( $height !== 0 ) ? $height : 300;
}


/**
 * Converts a 'on' or 'off' to boolean
 *
 *
 * @param string $value
 *
 * @return bool
 */
function sk_string_to_bool( $value ) {
    return is_bool( $value ) ? $value : ( in_array( strtolower( $value ?? '' ), [ 'yes', 1, '1', 'true', 'on' ], true ) );
}

/**
 * Converts a boolean value to a 'on' or 'off'.
 *
 *
 * @param bool $bool
 *
 * @return string
 */
function sk_bool_to_on_off( $bool ) {
    if ( ! is_bool( $bool ) ) {
        $bool = sk_string_to_bool( $bool );
    }

    return true === $bool ? 'on' : 'off';
}


/**
 * Sanitize phone number.
 * Allows only numbers and "+" (plus sign) "." (full stop) "(" ")" "-".
 *
 *
 * @param string $phone Phone number.
 *
 * @return string
 */
function sk_sanitize_phone_number( $phone ) {
	return preg_replace( '/[^0-9()._+-]/', '', $phone );
}

/**
 * SK override author ID from admin
 *
 *
 * @param  WC_Product $product
 * @param  integer $seller_id
 *
 * @return void
 */
function sk_override_product_author( $product, $seller_id ) {
    wp_update_post(
        [
            'ID'          => $product->get_id(),
            'post_author' => $seller_id,
        ]
    );

    do_action( 'sk_after_override_product_author', $product, $seller_id );
}

/**
 * Handle user update from customer to seller.
 *
 *
 * @param object $user User Object
 * @param array  $data Data to Update
 *
 * @return void
 */
if ( ! function_exists( 'sk_user_update_to_seller' ) ) {

    function sk_user_update_to_seller( $user, $data ) {
        if ( ! is_a( $user, WP_User::class ) || sk_is_user_seller( $user->ID ) ) {
            return;
        }

        $user_id       = $user->ID;
        $current_roles = (array) $user->roles;

        // Remove role
        $user->remove_role( 'customer' );
        if ( is_array( $current_roles ) ) {
            foreach ( $current_roles as $current_role ) {
                $user->remove_role( $current_role );
            }
        }

        // Add role
        $user->add_role( 'seller' );

        $user_id = wp_update_user(
            [
                'ID'            => $user_id,
                'user_nicename' => $data['shopurl'],
            ]
        );
        update_user_meta( $user_id, 'first_name', $data['fname'] );
        update_user_meta( $user_id, 'last_name', $data['lname'] );

        /**
         * @var $vendor \SK\Core\Vendor\Vendor
         */
        $vendor = sk()->vendor->get( $user_id );
        $vendor->set_store_name( $data['shopname'] );
        $vendor->set_phone( $data['phone'] );
        $vendor->set_address( $data['address'] );
        $vendor->save();

        if ( 'automatically' === sk_get_container()->get( \SK\Core\Utilities\AdminSettings::class )->get_new_seller_enable_selling_status() ) {
            $vendor->make_active();
        } else {
            $vendor->make_inactive();
        }

        update_user_meta( $user_id, 'sk_publishing', 'no' );

        do_action( 'sk_new_seller_created', $user_id, $vendor->get_shop_info() );
    }
}


/**
 * Abzeichen fuer einen Nutzer mit bestaetigter Adresse.
 *
 * Zeigt, wo der Nachweis herkommt: der Titel nennt die bestaetigten Hosts,
 * damit das Abzeichen eine ueberpruefbare Aussage ist und kein Ornament.
 *
 * @return string Leer, wenn nichts bestaetigt ist.
 */
function sk_verified_badge( int $user_id ): string {
    if ( ! class_exists( \SK\Core\Verification\VerifiedLinks::class ) ) {
        return '';
    }

    $bestaetigt = \SK\Core\Verification\VerifiedLinks::confirmed( $user_id );

    if ( empty( $bestaetigt ) ) {
        return '';
    }

    $hosts = implode( ', ', \SK\Core\Verification\VerifiedLinks::confirmed_hosts( $user_id ) );
    $ziel  = (string) ( reset( $bestaetigt )['url'] ?? '' );

    $titel = sprintf(
        /* translators: %s: Liste der bestaetigten Adressen. */
        __( 'Bestätigt: %s', 'sk-core' ),
        $hosts
    );

    /*
     * Das Abzeichen fuehrt auf die Seite, mit der sich der Anbieter
     * bestaetigt hat — nachpruefbar statt bloss behauptet. Bei mehreren
     * Adressen auf die erste; alle stehen im Titel.
     */
    if ( $ziel === '' ) {
        return sprintf(
            '<span class="sk-verify-badge" title="%s"><i class="fas fa-circle-check" aria-hidden="true"></i><span class="screen-reader-text">%s</span></span>',
            esc_attr( $titel ),
            esc_html__( 'verifiziert', 'sk-core' )
        );
    }

    return sprintf(
        '<a class="sk-verify-badge" href="%1$s" target="_blank" rel="noopener nofollow" title="%2$s"><i class="fas fa-circle-check" aria-hidden="true"></i><span class="screen-reader-text">%3$s</span></a>',
        esc_url( $ziel ),
        esc_attr( $titel ),
        esc_html__( 'verifiziert', 'sk-core' )
    );
}

/**
 * Filter vendor listing to only show vendors with a non-empty store name.
 */
add_filter( 'sk_seller_listing_args', function ( $args, $requested_data ) {
    if ( ! isset( $args['meta_query'] ) ) {
        $args['meta_query'] = [];
    }
    if ( ! empty( $args['meta_query'] ) && ! isset( $args['meta_query']['relation'] ) ) {
        $args['meta_query']['relation'] = 'AND';
    }
    $args['meta_query'][] = [
        'key'     => 'sk_store_name',
        'value'   => '',
        'compare' => '!=',
    ];
    return $args;
}, 10, 2 );

/**
 * Get seller/vendor ID from an order.
 *
 * Checks the _sk_vendor_id order meta, then falls back to the product author
 * of the first line item.
 *
 * @param int|\WC_Order $order Order ID or WC_Order instance.
 *
 * @return int Seller user ID, or 0 if not found.
 */
function sk_get_seller_id_by_order( $order ) {
    if ( is_numeric( $order ) ) {
        $order_id = (int) $order;
        $order    = wc_get_order( $order_id );
    }

    if ( ! $order instanceof \WC_Order ) {
        return 0;
    }

    // Parent orders with sub-orders don't belong to a single vendor.
    if ( $order->get_meta( 'has_sub_order' ) ) {
        return 0;
    }

    $seller_id = absint( $order->get_meta( '_sk_vendor_id' ) );
    if ( $seller_id ) {
        return $seller_id;
    }

    // Fallback: product author from first line item.
    $items = $order->get_items( 'line_item' );
    foreach ( $items as $item ) {
        $product_id = $item->get_product_id();
        $seller_id  = absint( get_post_field( 'post_author', $product_id ) );
        if ( $seller_id ) {
            return $seller_id;
        }
    }

    return 0;
}

/**
 * Get order line-item details for vendor email templates.
 *
 * @param int      $order_id  WC Order ID.
 * @param int|null $vendor_id Vendor user ID (unused, kept for compat).
 *
 * @return array
 */
function sk_get_vendor_order_details( $order_id, $vendor_id = null ) {
    $order      = wc_get_order( $order_id );
    $order_info = [];

    if ( ! $order || $order->get_meta( 'has_sub_order' ) ) {
        return $order_info;
    }

    foreach ( $order->get_items( 'line_item' ) as $item ) {
        $order_info[] = [
            'product'  => $item['name'],
            'quantity' => $item['quantity'],
            'total'    => $item['total'],
        ];
    }

    return $order_info;
}

/**
 * Cache-busting asset version derived from the newest file in a given directory.
 *
 * Recursively scans $dir (usually a module's assets/ folder) and returns the
 * highest filemtime found. Result is cached per request.
 *
 * @param string $dir Absolute directory path to scan.
 * @return string mtime as string (or current time if dir missing).
 */
function sk_assets_version( string $dir ): string {
    static $cache = [];
    if ( isset( $cache[ $dir ] ) ) {
        return $cache[ $dir ];
    }

    $max = 0;
    if ( is_dir( $dir ) ) {
        try {
            $iter = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS )
            );
            foreach ( $iter as $file ) {
                if ( $file->isFile() ) {
                    $m = $file->getMTime();
                    if ( $m > $max ) {
                        $max = $m;
                    }
                }
            }
        } catch ( Exception $e ) {
            $max = 0;
        }
    }

    return $cache[ $dir ] = (string) ( $max ?: time() );
}

/**
 * Set a vendor's store name in the one place that owns it.
 *
 * The name lives in sk_profile_settings['store_name'], but the vendor search,
 * the store listing, the REST controller and the admin user search all query
 * the denormalised copy in sk_store_name. Writing only one of the two has
 * caused real bugs twice: a shop named through the Nostr profile sync was
 * invisible to the vendor search, and the Nostr banner sync wrote the whole
 * array to a meta key nothing reads.
 *
 * Every path that changes the name should go through here.
 *
 * @param int    $user_id Vendor user ID.
 * @param string $name    New store name, unsanitised.
 *
 * @return string The stored name.
 */
function sk_set_store_name( int $user_id, string $name ): string {
    $name = sanitize_text_field( $name );

    if ( ! $user_id ) {
        return $name;
    }

    $settings = get_user_meta( $user_id, 'sk_profile_settings', true );

    if ( ! is_array( $settings ) ) {
        $settings = [];
    }

    $settings['store_name'] = $name;

    update_user_meta( $user_id, 'sk_profile_settings', $settings );
    update_user_meta( $user_id, 'sk_store_name', $name );

    return $name;
}

/**
 * Simple per-key rate limit backed by a transient.
 *
 * Nine hand-rolled variants of this had grown across the payment, zap, feed and
 * geolocation code, each with its own key scheme. Consumes one slot and reports
 * whether the caller may proceed.
 *
 * @param string $bucket Identifier, e.g. 'geocode:' . $ip_hash.
 * @param int    $max    Allowed calls within the window.
 * @param int    $window Window length in seconds.
 *
 * @return bool True while below the limit.
 */
function sk_rate_limit( string $bucket, int $max, int $window = MINUTE_IN_SECONDS ): bool {
    $key   = 'sk_rl_' . md5( $bucket );
    $count = (int) get_transient( $key );

    if ( $count >= $max ) {
        return false;
    }

    set_transient( $key, $count + 1, $window );

    return true;
}

/**
 * Turn a site-local MySQL datetime into a UTC timestamp.
 *
 * WordPress runs PHP in UTC while current_time('mysql') writes site time, so
 * strtotime() on a stored timestamp is off by the site's offset — measured at
 * 7200 seconds on this install. The same mistake had been made independently in
 * the reputation, on-chain and commission code.
 *
 * @param string|null $site_local Datetime as stored by current_time('mysql').
 *
 * @return int Unix timestamp, 0 when the input is empty or unparsable.
 */
function sk_to_utc_timestamp( ?string $site_local ): int {
    if ( empty( $site_local ) ) {
        return 0;
    }

    return (int) strtotime( get_gmt_from_date( $site_local ) . ' UTC' );
}
