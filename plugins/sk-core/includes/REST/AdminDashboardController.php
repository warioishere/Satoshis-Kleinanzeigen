<?php

namespace SK\Core\REST;

use SK\Core\Admin\Status\Status;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;
use SK\Core\Abstracts\SkRESTAdminController;

/**
 * Admin Dashboard
 *
 *
 */
class AdminDashboardController extends SkRESTAdminController {

    /**
     * Route base.
     *
     * @var string
     */
    protected $base = 'dashboard';

    /**
     * Register all routes releated with stores
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace, '/' . $this->base . '/feed', array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_feeds' ),
                    'permission_callback' => array( $this, 'check_permission' ),
                    'args'                => array(
                        'items' => array(
                            'type'        => 'integer',
                            'description' => __( 'Number of feed item', 'sk-core' ),
                            'required'    => false,
                            'default'     => 5,
                        ),
                        'show_summary' => array(
                            'type'        => 'boolean',
                            'description' => __( 'Flag for showing summary', 'sk-core' ),
                            'required'    => false,
                            'default'     => false,
                        ),
                        'show_author' => array(
                            'type'        => 'boolean',
                            'description' => __( 'Flag for showing author', 'sk-core' ),
                            'required'    => false,
                            'default'     => false,
                        ),
                        'show_date' => array(
                            'type'        => 'boolean',
                            'description' => __( 'Flag for showing date', 'sk-core' ),
                            'required'    => false,
                            'default'     => true,
                        ),
                    ),
                ),
            )
        );
        register_rest_route(
            $this->namespace, '/' . $this->base . '/status', array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_status' ),
                    'permission_callback' => array( $this, 'check_permission' ),
                    'args'                => array(),
                ),
            )
        );
    }

    /**
     * Get rss feeds
     *
     *
     * @return WP_REST_Response|WP_Error
     */
    public function get_feeds( $request ) {
        // Disabled: no external feed.
        return rest_ensure_response( [] );
        $items = (int) $request['items'];

        if ( $items < 1 || 20 < $items ) {
            $items = 10;
        }

        $show_summary  = (int) $request['show_summary'];
        $show_author   = (int) $request['show_author'];
        $show_date     = (int) $request['show_date'];

        add_filter( 'http_response', array( self::class, 'sk_compat_simple_pie_after_five_point_five' ), 10, 3 );

        $url = '';
        $rss = fetch_feed( $url );

        remove_filter( 'http_response', array( self::class, 'sk_compat_simple_pie_after_five_point_five' ), 10, 3 );

        if ( is_wp_error( $rss ) ) {
            return $rss;
        }

        if ( ! $rss->get_item_quantity() ) {
            $rss->__destruct();
            unset( $rss );
            return new WP_Error( 'error', __( 'An error has occurred, which probably means the feed is down. Try again later.', 'sk-core' ) );
        }

        $feeds = array();
        foreach ( $rss->get_items( 0, $items ) as $item ) {
            $link = $item->get_link();
            while ( stristr( $link, 'http' ) !== $link ) {
                $link = substr( $link, 1 );
            }
            $link = esc_url( $link );

            $title = sanitize_text_field( trim( $item->get_title() ) );
            if ( empty( $title ) ) {
                $title = __( 'Untitled', 'sk-core' );
            }

            $desc = html_entity_decode( $item->get_description(), ENT_QUOTES, get_option( 'blog_charset' ) );
            $desc = esc_attr( wp_trim_words( $desc, 55, ' [&hellip;]' ) );

            $summary = '';
            $date    = '';
            $author  = '';
            if ( $show_summary ) {
                $summary = $desc;

                //Changing existing [...] to [&hellip;]. // phpcs:ignore
                if ( '[...]' === substr( $summary, -5 ) ) {
                    $summary = substr( $summary, 0, -5 ) . '[&hellip;]';
                }

                $summary = esc_html( $summary );
            }

            if ( $show_date ) {
                $date = $item->get_date( 'U' );

                if ( $date ) {
                    $date = date_i18n( get_option( 'date_format' ), $date );
                }
            }

            if ( $show_author ) {
                $author = $item->get_author();

                if ( is_object( $author ) ) {
                    $author = $author->get_name();
                    $author = sanitize_text_field( $author );
                }
            }

            $feeds[] = array(
                'link'    => $link,
                'title'   => $title,
                'desc'    => $desc,
                'summary' => $summary,
                'date'    => $date,
                'author'  => $author,
            );
        }

        $rss->__destruct();
        unset( $rss );

        return rest_ensure_response( $feeds );
    }

    public function get_status( $request ) {
        /**
         * @var Status $status
         */
        $status = sk_get_container()->get( Status::class );
        $content = $status->render();

        return rest_ensure_response( $content );
    }

    /**
     * Support SimplePie class in WP 5.5+
     *
     *
     * @param array  $response    HTTP response.
     * @param array  $parsed_args HTTP request arguments.
     * @param string $url         The request URL.
     *
     * @return array
     */
    public static function sk_compat_simple_pie_after_five_point_five( $response, $parsed_args, $url ) {
        if (
            version_compare( get_bloginfo( 'version' ), '5.5', '>=' )
            && '' === $url
            && isset( $response['headers']['link'] )
            && is_array( $response['headers']['link'] )
        ) {
            $response['headers']['link'] = $response['headers']['link'][0];
        }

        return $response;
    }
}
