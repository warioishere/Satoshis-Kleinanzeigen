<?php

namespace SK\Core\Announcement;

use DateTimeZone;
use stdClass;
use SK\Core\Cache;
use WP_Error;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Manager {

    /**
     *
     * @var string $post_table
     */
    private $post_table;

    /**
     *
     * @var string $announcement_table
     */
    private $announcement_table;

    /**
     * Manager constructor.
     *
     */
    public function __construct() {
        global $wpdb;

        $this->post_table         = $wpdb->prefix . 'posts';
        $this->announcement_table = $wpdb->prefix . 'sk_announcement';
    }

    /**
     * Get announcement
     *
     *
     * @param array $args
     *
     * @return int|Single[]|int[]|WP_Error
     */
    public function all( $args = [] ) {
        $defaults = [
            'id'          => 0, // this is announcement id
            'notice_id'   => 0, // this is single vendor notice id
            'vendor_id'   => 0,
            'page'        => 1,
            'per_page'    => apply_filters( 'sk_announcement_list_number', 10 ),
            'search'      => '',
            'status'      => 'all', // publish, future, draft
            'read_status' => 'all', // read, unread
            'from'        => '',
            'to'          => '',
            'return'      => 'all', // all, count, ids
        ];

        $args = wp_parse_args( $args, $defaults );

        global $wpdb;

        $fields      = '';
        $from        = "$this->post_table AS p";
        $join        = '';
        $where       = ' AND p.post_type = %s';
        $inner_where = '';
        $groupby     = '';
        $orderby     = ' p.post_date DESC';
        $limits      = '';
        $query_args  = [ 1, 1, 'sk_announcement' ];
        $status      = '';

        if ( 'ids' === $args['return'] ) {
            $fields = 'p.ID';
        } elseif ( 'count' === $args['return'] ) {
            $fields = 'COUNT(p.ID)';
        } else {
            $fields = 'p.ID AS id, p.post_title AS title, p.post_content AS content, p.post_status as status, p.post_date_gmt AS date_gmt, p.post_date AS date';
        }

        if ( ! empty( $args['id'] ) ) {
            $where            .= ' AND p.ID = %d';
            $query_args[]     = absint( $args['id'] );
            $args['page']     = 1;
            $args['per_page'] = 1;
        }

        if ( ! empty( $args['vendor_id'] ) ) {
            $fields       .= ', a.id as notice_id, a.user_id as vendor_id, a.status AS read_status';
            $join         .= "INNER JOIN $this->announcement_table AS a ON p.ID = a.post_id";
            $where        .= ' AND a.user_id = %d AND a.status != %s';
            $query_args[] = absint( $args['vendor_id'] );
            $query_args[] = 'trash';
        }

        if ( ! empty( $args['vendor_id'] ) && ! empty( $args['notice_id'] ) ) {
            $where            .= ' AND a.id = %d';
            $query_args[]     = absint( $args['notice_id'] );
            $args['page']     = 1;
            $args['per_page'] = 1;
        }

        if ( ! empty( $args['vendor_id'] ) && in_array( $args['read_status'], [ 'read', 'unread' ], true ) ) {
            $where        .= ' AND a.status = %s';
            $query_args[] = $args['read_status'];
        }

        if ( ! empty( $args['status'] ) && in_array( $args['status'], [ 'publish', 'pending', 'draft', 'future', 'trash' ], true ) ) {
            $where        .= ' AND p.post_status = %s';
            $query_args[] = $args['status'];
        } elseif ( empty( $args['status'] ) || ( ! empty( $args['status'] ) && 'trash' === $args['status'] ) ) {
            $where        .= ' AND p.post_status != %s';
            $query_args[] = 'trash';
        }

        if ( ! empty( $args['search'] ) ) {
            $search = trim( sanitize_text_field( $args['search'] ) );
            $like   = '%' . $wpdb->esc_like( $search ) . '%';
            $where  .= $wpdb->prepare( ' AND ( p.post_title LIKE %s OR p.post_content LIKE %s )', $like, $like );
        }

        $now       = sk_current_datetime();
        $from_date = '';
        if ( ! empty( $args['from'] ) ) {
            $from_date = $now->modify( $args['from'] );
            $from_date = $from_date ? $from_date->setTimezone( new DateTimeZone( 'UTC' ) )->setTime( 0, 0, 0 )->format( 'Y-m-d H:i:s' ) : '';
        }

        $to_date = '';
        if ( ! empty( $args['to'] ) ) {
            $to_date = $now->modify( $args['to'] );
            $to_date = $to_date ? $to_date->setTimezone( new DateTimeZone( 'UTC' ) )->setTime( 23, 59, 59 )->format( 'Y-m-d H:i:s' ) : '';
        }

        if ( ! empty( $from_date ) && ! empty( $to_date ) ) {
            $where        .= ' AND ( p.post_date_gmt BETWEEN %s AND %s )';
            $query_args[] = $from_date;
            $query_args[] = $to_date;
        } elseif ( ! empty( $from_date ) ) {
            $where        .= ' AND p.post_date_gmt >= %s';
            $query_args[] = $from_date;
        } elseif ( ! empty( $to_date ) ) {
            $where        .= ' AND p.post_date_gmt <= %s';
            $query_args[] = $to_date;
        }

        // pagination param
        if ( 'count' !== $args['return'] && ! empty( $args['per_page'] ) && - 1 !== intval( $args['per_page'] ) ) {
            $limit  = absint( $args['per_page'] );
            $page   = absint( $args['page'] );
            $page   = $page ? $page : 1;
            $offset = ( $page - 1 ) * $limit;

            $limits       = 'LIMIT %d, %d';
            $query_args[] = $offset;
            $query_args[] = $limit;
        }

        $cache_group = 'announcement'; // caching for admin announcement lists
        if ( ! empty( $args['vendor_id'] ) ) {
            $cache_group = "seller_announcement_{$args['vendor_id']}"; // caching for seller announcement lists
        }
        $cache_key = 'get_announcement_' . md5( wp_json_encode( $args ) );
        $results   = Cache::get( $cache_key, $cache_group );

        if ( false === $results && 'count' === $args['return'] ) {
            // @codingStandardsIgnoreStart
            $results = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT $fields FROM $from $join WHERE %d=%d $where",
                    $query_args
                )
            );
            // @codingStandardsIgnoreEnd
            if ( $wpdb->last_error ) {
                // database query error
                $db_error      = $wpdb->last_error;
                $error_message = sprintf(
                    '%1$s %2$s',
                    __( 'Announcement: Something went wrong while querying data.', 'sk' ),
                    current_user_can( 'manage_options' ) ? ': ' . $db_error : ''
                );

                return new WP_Error( 'announcement_count_db_error', $error_message );
            }

            // store on cache
            Cache::set( $cache_key, $results, $cache_group );
        } elseif ( false === $results && 'ids' === $args['return'] ) {
            // @codingStandardsIgnoreStart
            $results = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT $fields FROM $from $join WHERE %d=%d $where $groupby ORDER BY $orderby $limits",
                    $query_args
                )
            );
            // @codingStandardsIgnoreEnd
            if ( $wpdb->last_error ) { // get_col returns empty array in case of error or no result
                // database query error
                $db_error      = $wpdb->last_error;
                $error_message = sprintf(
                    '%1$s %2$s',
                    __( 'Announcement: Something went wrong while querying data.', 'sk' ),
                    current_user_can( 'manage_options' ) ? ': ' . $db_error : ''
                );

                return new WP_Error( 'announcement_db_error', $error_message );
            }

            // store on cache
            Cache::set( $cache_key, $results, $cache_group );
        } elseif ( false === $results ) {
            // @codingStandardsIgnoreStart
            $data = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT $fields FROM $from $join WHERE %d=%d $where $groupby ORDER BY $orderby $limits",
                    $query_args
                ),
                ARRAY_A
            );
            // @codingStandardsIgnoreEnd
            if ( $wpdb->last_error ) {
                // database query error
                $db_error      = $wpdb->last_error;
                $error_message = sprintf(
                    '%1$s %2$s',
                    __( 'Announcement: Something went wrong while querying data.', 'sk' ),
                    current_user_can( 'manage_options' ) ? ': ' . $db_error : ''
                );

                return new WP_Error( 'announcement_count_db_error', $error_message );
            }

            $results = [];
            if ( ! empty( $data ) && 1 === (int) $args['per_page'] ) {
                // we need to return a single object
                $data    = reset( $data );
                $results = new Single( $data );
            } elseif ( ! empty( $data ) ) {
                foreach ( $data as $single ) {
                    $results[] = new Single( $single );
                }
            }

            // store on cache
            Cache::set( $cache_key, $results, $cache_group );
        }

        return $results;
    }

    /**
     * Get a single announcement
     *
     *
     * @param int $id
     *
     * @return Single|WP_Error
     */
    public function get_single_announcement( $id ) {
        $args = [
            'id'     => $id,
            'return' => 'all',
        ];

        $result = $this->all( $args );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( empty( $result ) ) {
            return new WP_Error( 'no_announcement', __( 'No announcement found with given id.', 'sk' ) );
        }

        return $result;
    }

    /**
     * Get a single announcement
     *
     *
     * @param int $id
     *
     * @return string[]|WP_Error
     */
    public function get_pagination_data( $args = [] ) {
        $args['return'] = 'count';

        $total = $this->all( $args );
        if ( is_wp_error( $total ) ) {
            return [
                'total_count' => 0,
                'per_page'    => $args['per_page'],
                'total_pages' => 0,
            ];
        }

        $per_page   = $args['per_page'];
        $per_page   = $per_page > 0 ? $per_page : 10;
        $total_page = ceil( $total / $per_page );

        return [
            'total_count' => $total,
            'per_page'    => $per_page,
            'total_pages' => $total_page,
        ];
    }

    /**
     * Create announcement
     *
     *
     * @param array $args
     * @param bool  $update
     *
     * @return int|WP_Error
     */
    public function create_announcement( $args = [], $update = false ) {
        if ( empty( trim( $args['title'] ) ) ) {
            return new WP_Error( 'no_title', __( 'Announcement title is required.', 'sk' ) );
        }

        $data = [
            'post_title'   => sanitize_text_field( $args['title'] ),
            'post_content' => ! empty( $args['content'] ) ? wp_kses_post( $args['content'] ) : '',
            'post_status'  => ! empty( $args['status'] ) ? $args['status'] : 'draft',
            'post_type'    => 'sk_announcement',
            'post_author'  => isset( $args['author'] ) ? absint( $args['author'] ) : get_current_user_id(),
        ];

        if ( ! empty( $args['date'] ) ) {
            $data['post_date'] = $args['date'];
        }

        // if an announcement is `scheduled`, but want to publish it now
        // and set post_date_gmt to `0000-00-00 00:00:00`
        if ( ! empty( $args['date_gmt'] ) ) {
            $data['post_date_gmt'] = $args['date_gmt'];
        }

        if ( ! $update ) {
            $post_id = wp_insert_post( $data );
        } else {
            $data['ID'] = $args['id'];
            $post_id    = wp_update_post( $data );
        }

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        $announcement_type = ! empty( $args['announcement_type'] ) ? $args['announcement_type'] : 'all_seller';
        $sender_ids        = ! empty( $args['sender_ids'] ) ? $args['sender_ids'] : [];

        $assigned_sellers   = ! empty( $args['sender_ids'] ) ? $args['sender_ids'] : [];
        $announcement_types = apply_filters( 'sk_announcement_seller_types', [ 'all_seller', 'enabled_seller', 'disabled_seller', 'featured_seller' ] );

        if ( 'selected_seller' !== $announcement_type && in_array( $announcement_type, $announcement_types, true ) ) {
            $seller_args = [
                'fields' => 'ID',
                'number' => -1,
            ];

            switch ( $announcement_type ) {
                case 'enabled_seller':
                    $seller_args['status'] = [ 'approved' ];
                    break;

                case 'disabled_seller':
                    $seller_args['status'] = [ 'pending' ];
                    break;

                case 'featured_seller':
                    $seller_args['featured'] = 'yes';
                    break;

                default:
                    $seller_args['status'] = [ 'all' ];
            }

            $assigned_sellers = sk()->vendor->all( $seller_args );
        }

        // Remove excluded sellers ids
        if ( ! empty( $args['exclude_seller_ids'] ) && is_array( $args['exclude_seller_ids'] ) ) {
            $assigned_sellers = array_diff( $assigned_sellers, $args['exclude_seller_ids'] );
        }

        $this->process_seller_announcement_data( $assigned_sellers, $post_id );
        update_post_meta( $post_id, '_announcement_type', $announcement_type );
        update_post_meta( $post_id, '_announcement_selected_user', $assigned_sellers );

        do_action( 'sk_after_announcement_saved', $post_id, $assigned_sellers );

        // clear cache
        sk_ext()->announcement->delete_announcement_cache( $assigned_sellers, $post_id );

        return $post_id;
    }

    /**
     * Process seller announcement data
     *
     *
     * @param array   $announcement_seller
     * @param integer $announcment_id
     *
     * @return void
     */
    protected function process_seller_announcement_data( $announcement_seller, $announcment_id ) {
        // delete old cache
        sk_ext()->announcement->delete_announcement_cache( $announcement_seller );

        $db = $this->get_assigned_seller_from_db( $announcment_id );

        $sellers         = $announcement_seller;
        $existing_seller = $new_seller = $del_seller = []; // phpcs:ignore

        foreach ( $sellers as $seller ) {
            if ( in_array( $seller, $db, true ) ) {
                $existing_seller[] = $seller;
            } else {
                $new_seller[] = $seller;
            }
        }

        $del_seller = array_diff( $db, $existing_seller );

        if ( $del_seller ) {
            $this->delete_assigned_seller( $del_seller, $announcment_id );
        }

        if ( $new_seller ) {
            $this->insert_assigned_seller( $new_seller, $announcment_id );
        }
    }

    /**
     * Get assign seller
     *
     *
     * @param int  $announcment_id
     * @param bool $exclude_trash
     *
     * @return int[]|stdClass[]
     */
    public function get_assigned_seller_from_db( $announcment_id, $exclude_trash = false ) {
        global $wpdb;

        $status_where = $exclude_trash ? " AND status != 'trash'" : '';

        // @codingStandardsIgnoreStart
        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT user_id FROM {$this->announcement_table} WHERE `post_id`= %d $status_where",
                $announcment_id
            )
        );
        // @codingStandardsIgnoreEnd
    }

    /**
     * Insert assigned seller
     *
     *
     * @param int[] $seller_array
     * @param int   $announcment_id
     *
     * @return void
     */
    protected function insert_assigned_seller( $seller_array, $announcment_id ) {
        global $wpdb;

        $values = '';
        $i      = 0;

        foreach ( $seller_array as $seller_id ) {
            $sep    = ( $i === 0 ) ? '' : ',';
            $values .= sprintf( "%s ( %d, %d, '%s')", $sep, $seller_id, $announcment_id, 'unread' );

            ++$i;
        }

        // @codingStandardsIgnoreStart
        $sql = "INSERT INTO {$this->announcement_table} (`user_id`, `post_id`, `status` ) VALUES $values";
        $wpdb->query( $sql );
        // @codingStandardsIgnoreEnd
    }

    /**
     * Delete assign seller
     *
     *
     * @param int[] $seller_array
     * @param int   $announcment_id
     *
     * @return void
     */
    protected function delete_assigned_seller( $seller_array, $announcment_id ) {
        if ( ! is_array( $seller_array ) ) {
            return;
        }

        global $wpdb;

        $values = '';
        $i      = 0;

        foreach ( $seller_array as $seller_id ) {
            $sep    = ( $i === 0 ) ? '' : ',';
            $values .= sprintf( '%s( %d, %d )', $sep, $seller_id, $announcment_id );

            ++$i;
        }

        // @codingStandardsIgnoreStart
        $sql = "DELETE FROM {$this->announcement_table} WHERE (`user_id`, `post_id` ) IN ($values)";

        if ( $values ) {
            $wpdb->query( $sql );
        }
        // @codingStandardsIgnoreEnd
    }

    /**
     * Delete a single announcement
     *
     *
     * @param int  $id
     * @param bool $force
     *
     * @return WP_Post|WP_Error Post data on success, WP_Error on failure.
     */
    public function delete_announcement( $id, $force = false ) {
        $announcement   = $this->get_single_announcement( $id );
        $supports_trash = apply_filters( 'sk_announcement_trashable', ( EMPTY_TRASH_DAYS > 0 ), $announcement );

        // delete individual announcement cache
        sk_ext()->announcement->delete_announcement_cache( [], $id );

        // If we're forcing, then delete permanently.
        if ( $force ) {
            $result = wp_delete_post( $id, true );
            if ( $result ) {
                $this->delete_announcement_data( $id );
            } else {
                $result = new WP_Error( 'delete_announcement_error', __( 'Error while deleting announcement.', 'sk' ) );
            }

            return $result;
        }

        // If we don't support trashing for this type, error out.
        if ( ! $supports_trash ) {
            /* translators: %s: force=true */
            return new WP_Error( 'announcement_trash_not_supported', sprintf( __( "The post does not support trashing. Set '%s' to delete.", 'sk' ), 'force=true' ), [ 'status' => 501 ] );
        }

        // Otherwise, only trash if we haven't already.
        if ( 'trash' === $announcement->get_status() ) {
            return new WP_Error( 'announcement_already_trashed', __( 'The announcement has already been trashed.', 'sk' ), [ 'status' => 410 ] );
        }

        // (Note that internally this falls through to `wp_delete_post` if
        // the trash is disabled.)
        $result = wp_trash_post( $id );
        if ( ! $result ) {
            return new WP_Error( 'delete_announcement_error', __( 'Error while adding announcement to trash.', 'sk' ) );
        }

        return $result;
    }

    /**
     * Delete announcement relational table data
     *
     *
     * @return void
     */
    protected function delete_announcement_data( $post_id ) {
        global $wpdb;

        $wpdb->delete( $this->announcement_table, [ 'post_id' => $post_id ], [ '%d' ] );
    }

    /**
     * Trash a single announcement
     *
     *
     * @param int $announcement_id
     *
     * @return WP_Post|WP_Error Post data on success, WP_Error on failure.
     */
    public function untrash_announcement( $announcement_id ) {
        $result = wp_untrash_post( $announcement_id );
        if ( ! $result ) {
            return new WP_Error( 'untrash_announcement_error', __( 'Error in untrashing announcement.', 'sk' ) );
        }

        // delete individual announcement cache
        sk_ext()->announcement->delete_announcement_cache( [], $announcement_id );

        return $result;
    }

    /**
     * Get a single notice
     *
     *
     * @param int $notice_id
     *
     * @return Single|WP_Error
     */
    public function get_notice( $notice_id, $vendor_id = null ) {
        $vendor_id = $vendor_id ? $vendor_id : sk_get_current_user_id();
        $notice    = $this->all(
            [
                'notice_id' => $notice_id,
                'vendor_id' => $vendor_id,
                'return'    => 'all',
            ]
        );

        if ( is_wp_error( $notice ) ) {
            return $notice;
        }

        if ( empty( $notice ) ) {
            return new WP_Error( 'no_notice', __( 'No notice found with given id.', 'sk' ) );
        }

        return $notice;
    }

    /**
     * Update notice read status
     *
     *
     * @param int    $notice_id
     * @param string $read_status read,unread,trash
     * @param int    $vendor_id   vendor id is required to ensure that the request is coming from the same vendor
     *
     * @return bool|WP_Error true on success, WP_Error on failure.
     */
    public function update_read_status( $notice_id, $read_status, $vendor_id = null ) {
        global $wpdb;

        $vendor_id = $vendor_id ? $vendor_id : sk_get_current_user_id();

        // get notice data
        $notice = $this->get_notice( $notice_id, $vendor_id );
        if ( is_wp_error( $notice ) ) {
            return $notice;
        }

        $updated = $wpdb->update(
            $this->announcement_table,
            [
                'status' => $read_status,
            ],
            [
                'id'      => $notice_id,
                'user_id' => $vendor_id,
            ],
            [ '%s' ],
            [ '%d', '%d' ]
        );

        if ( false === $updated ) {
            return new WP_Error( 'update_notice_error', __( 'Error while updating notice status.', 'sk' ) );
        }

        // clear cache
        sk_ext()->announcement->delete_announcement_cache( [ $vendor_id ], $notice->get_notice_id() );

        return true;
    }

    /**
     * Delete a single notice
     *
     * @param int $notice_id
     * @param int|null $vendor_id
     *
     * @return bool|WP_Error true on success, WP_Error on failure.
     */
    public function delete_notice( $notice_id, $vendor_id = null ) {
        global $wpdb;

        $vendor_id = $vendor_id ? $vendor_id : sk_get_current_user_id();
        // get notice data
        $notice = $this->get_notice( $notice_id, $vendor_id );
        if ( is_wp_error( $notice ) ) {
            return $notice;
        }

        $result = $wpdb->delete(
            $this->announcement_table, [
				'id' => $notice_id,
				'user_id' => $vendor_id,
			], [ '%d', '%d' ]
        );

        if ( false === $result ) {
            return new WP_Error( 'update_notice_error', __( 'Error while deleting notice status.', 'sk' ) );
        }

        // clear cache
        sk_ext()->announcement->delete_announcement_cache( [ $vendor_id ], $notice->get_id() );

        return true;
    }
}
