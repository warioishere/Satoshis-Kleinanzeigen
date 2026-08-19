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
     * Schema version of the announcement table.
     *
     * @var string
     */
    const TABLE_VERSION = '3';

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
     * Add the lookup index the announcement table shipped without.
     *
     * Every vendor dashboard load joins on `post_id` and filters `user_id`, which
     * was a full table scan. Installations created before this run only get the
     * index on plugin activation, so it is added here as well.
     *
     * @return void
     */
    public function maybe_upgrade_table() {
        if ( self::TABLE_VERSION === get_option( 'sk_announcement_table_version' ) ) {
            return;
        }

        global $wpdb;

        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->announcement_table ) ) !== $this->announcement_table ) {
            return;
        }

        $table = $this->announcement_table;

        // @codingStandardsIgnoreStart
        // A vendor can only ever hold one notice per announcement. Carry a read or
        // trashed state over to the surviving row before dropping the copies, so
        // deduplicating cannot mark something unread again.
        $wpdb->query(
            "UPDATE {$table} a
                JOIN {$table} b ON a.user_id = b.user_id AND a.post_id = b.post_id AND a.id < b.id
                SET a.status = b.status
              WHERE a.status = 'unread' AND b.status <> 'unread'"
        );

        // Keep the lowest id: notice ids are what the announcement mails link to.
        $wpdb->query(
            "DELETE a FROM {$table} a
                JOIN {$table} b ON a.user_id = b.user_id AND a.post_id = b.post_id AND a.id > b.id"
        );

        $index = $wpdb->get_row( "SHOW INDEX FROM {$table} WHERE Key_name = 'user_post'" );

        // An earlier version added this as a plain key; it has to be unique now.
        if ( $index && (int) $index->Non_unique === 1 ) {
            $wpdb->query( "ALTER TABLE {$table} DROP INDEX user_post" );
            $index = null;
        }

        if ( ! $index ) {
            $wpdb->query( "ALTER TABLE {$table} ADD UNIQUE KEY user_post (user_id, post_id)" );
        }

        if ( ! $wpdb->get_var( "SHOW INDEX FROM {$table} WHERE Key_name = 'post_id'" ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD KEY post_id (post_id)" );
        }

        // Announcements written before the Dokan rename kept the old post type, so
        // every query missed them and their notices became unreachable. They were
        // delivered long ago — mark them read before making them visible again,
        // otherwise ancient announcements resurface with an unread badge.
        $wpdb->query(
            "UPDATE {$table} a
                JOIN {$wpdb->posts} p ON p.ID = a.post_id
                SET a.status = 'read'
              WHERE p.post_type = 'dokan_announcement' AND a.status = 'unread'"
        );

        $wpdb->query(
            "UPDATE {$wpdb->posts} SET post_type = 'sk_announcement' WHERE post_type = 'dokan_announcement'"
        );

        // Notices of users that no longer exist can never be read or displayed.
        $wpdb->query(
            "DELETE a FROM {$table} a
                LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
              WHERE u.ID IS NULL"
        );
        // @codingStandardsIgnoreEnd

        if ( $wpdb->last_error ) {
            return;
        }

        delete_option( 'sk_announcement_table_indexed' );
        update_option( 'sk_announcement_table_version', self::TABLE_VERSION );
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
            // Appending these to a COUNT() would produce an aggregate mixed with
            // non-aggregated columns, which errors out under ONLY_FULL_GROUP_BY.
            if ( 'count' !== $args['return'] ) {
                $fields .= ', a.id as notice_id, a.user_id as vendor_id, a.status AS read_status';
            }

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
        } elseif ( empty( $args['id'] ) ) {
            // 'all' — and anything unrecognised — means every status but the trash.
            // A lookup by id stays unfiltered so trashed announcements can still be
            // restored or force-deleted.
            $where        .= ' AND p.post_status != %s';
            $query_args[] = 'trash';
        }

        if ( ! empty( $args['search'] ) ) {
            $search = trim( sanitize_text_field( $args['search'] ) );
            $like   = '%' . $wpdb->esc_like( $search ) . '%';
            $where  .= $wpdb->prepare( ' AND ( p.post_title LIKE %s OR p.post_content LIKE %s )', $like, $like );
        }

        // Since PHP 8.3 modify() throws on an unparseable string instead of
        // returning false, so the falsy check alone no longer catches bad input.
        $now       = sk_current_datetime();
        $from_date = '';
        if ( ! empty( $args['from'] ) ) {
            try {
                $from_date = $now->modify( $args['from'] );
                $from_date = $from_date ? $from_date->setTimezone( new DateTimeZone( 'UTC' ) )->setTime( 0, 0, 0 )->format( 'Y-m-d H:i:s' ) : '';
            } catch ( \Exception $e ) {
                $from_date = '';
            }
        }

        $to_date = '';
        if ( ! empty( $args['to'] ) ) {
            try {
                $to_date = $now->modify( $args['to'] );
                $to_date = $to_date ? $to_date->setTimezone( new DateTimeZone( 'UTC' ) )->setTime( 23, 59, 59 )->format( 'Y-m-d H:i:s' ) : '';
            } catch ( \Exception $e ) {
                $to_date = '';
            }
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

        if ( false === $results ) {
            $sql = "SELECT $fields FROM $from $join WHERE %d=%d $where";

            if ( 'count' !== $args['return'] ) {
                $sql .= " $groupby ORDER BY $orderby $limits";
            }

            // @codingStandardsIgnoreStart
            $prepared = $wpdb->prepare( $sql, $query_args );

            switch ( $args['return'] ) {
                case 'count':
                    $data = (int) $wpdb->get_var( $prepared );
                    break;

                case 'ids':
                    // get_col returns an empty array both on error and on no result,
                    // so last_error below is what tells the two apart.
                    $data = $wpdb->get_col( $prepared );
                    break;

                default:
                    $data = $wpdb->get_results( $prepared, ARRAY_A );
                    break;
            }
            // @codingStandardsIgnoreEnd

            if ( $wpdb->last_error ) {
                $error_message = sprintf(
                    '%1$s %2$s',
                    __( 'Announcement: Something went wrong while querying data.', 'sk-core' ),
                    current_user_can( 'manage_options' ) ? ': ' . $wpdb->last_error : ''
                );

                return new WP_Error( 'announcement_db_error', $error_message );
            }

            if ( 'count' === $args['return'] || 'ids' === $args['return'] ) {
                $results = $data;
            } else {
                $results = [];

                if ( ! empty( $data ) && 1 === (int) $args['per_page'] ) {
                    // we need to return a single object
                    $results = new Single( reset( $data ) );
                } elseif ( ! empty( $data ) ) {
                    foreach ( $data as $single ) {
                        $results[] = new Single( $single );
                    }
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
            return new WP_Error( 'no_announcement', __( 'No announcement found with given id.', 'sk-core' ) );
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

        $per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : 10;
        $per_page = $per_page > 0 ? $per_page : 10;

        $total = $this->all( $args );
        if ( is_wp_error( $total ) ) {
            return [
                'total_count' => 0,
                'per_page'    => $per_page,
                'total_pages' => 0,
            ];
        }

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
        $has_title = isset( $args['title'] ) && '' !== trim( (string) $args['title'] );

        // On update every field is optional, but an explicitly emptied title is not.
        if ( ( ! $update || isset( $args['title'] ) ) && ! $has_title ) {
            return new WP_Error( 'no_title', __( 'Announcement title is required.', 'sk-core' ) );
        }

        // Only fields that were actually supplied get written, otherwise an update
        // that omits them would blank the content or reset the author.
        $data = [ 'post_type' => 'sk_announcement' ];

        if ( $has_title ) {
            $data['post_title'] = sanitize_text_field( $args['title'] );
        }

        if ( isset( $args['content'] ) ) {
            $data['post_content'] = wp_kses_post( $args['content'] );
        } elseif ( ! $update ) {
            $data['post_content'] = '';
        }

        if ( ! empty( $args['status'] ) ) {
            $data['post_status'] = $args['status'];
        } elseif ( ! $update ) {
            $data['post_status'] = 'draft';
        }

        if ( isset( $args['author'] ) ) {
            $data['post_author'] = absint( $args['author'] );
        } elseif ( ! $update ) {
            $data['post_author'] = get_current_user_id();
        }

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
            $data['ID'] = absint( $args['id'] );
            $post_id    = wp_update_post( $data );
        }

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // An update that omits the type must not silently re-target the announcement
        // at every seller.
        $announcement_type = ! empty( $args['announcement_type'] ) ? $args['announcement_type'] : '';

        if ( '' === $announcement_type && $update ) {
            $announcement_type = (string) get_post_meta( $post_id, '_announcement_type', true );
        }

        if ( '' === $announcement_type ) {
            $announcement_type = 'all_seller';
        }

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
        } elseif ( empty( $assigned_sellers ) && $update ) {
            // Keep the stored selection when an update does not carry one.
            $assigned_sellers = get_post_meta( $post_id, '_announcement_selected_user', true );
        }

        // Vendor ids arrive as strings from the database and as integers from the
        // REST schema. Without one type the strict comparison in
        // process_seller_announcement_data() treats every recipient as new and
        // re-creates all rows, resetting read status and notice ids.
        $assigned_sellers = array_values( array_unique( array_map( 'absint', array_filter( (array) $assigned_sellers, 'is_numeric' ) ) ) );

        // Remove excluded sellers ids
        if ( ! empty( $args['exclude_seller_ids'] ) && is_array( $args['exclude_seller_ids'] ) ) {
            $assigned_sellers = array_values( array_diff( $assigned_sellers, array_map( 'absint', $args['exclude_seller_ids'] ) ) );
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
        $user_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT user_id FROM {$this->announcement_table} WHERE `post_id`= %d $status_where",
                $announcment_id
            )
        );
        // @codingStandardsIgnoreEnd

        // get_col() returns strings; callers compare these strictly against ids.
        return array_map( 'absint', $user_ids );
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

        if ( '' === $values ) {
            return;
        }

        // IGNORE, because the unique key on (user_id, post_id) would otherwise let a
        // single already-assigned vendor abort the whole batch. An existing row keeps
        // its notice id and read status, which is exactly what should happen.
        // @codingStandardsIgnoreStart
        $sql = "INSERT IGNORE INTO {$this->announcement_table} (`user_id`, `post_id`, `status` ) VALUES $values";
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
        $announcement = $this->get_single_announcement( $id );

        // Bail before wp_delete_post()/wp_trash_post() get their hands on an id
        // that belongs to some other post type — they would happily delete it.
        if ( is_wp_error( $announcement ) ) {
            return $announcement;
        }

        $supports_trash = apply_filters( 'sk_announcement_trashable', ( EMPTY_TRASH_DAYS > 0 ), $announcement );

        // delete individual announcement cache
        sk_ext()->announcement->delete_announcement_cache( [], $id );

        // If we're forcing, then delete permanently.
        if ( $force ) {
            $result = wp_delete_post( $id, true );
            if ( $result ) {
                $this->delete_announcement_data( $id );
            } else {
                $result = new WP_Error( 'delete_announcement_error', __( 'Error while deleting announcement.', 'sk-core' ) );
            }

            return $result;
        }

        // If we don't support trashing for this type, error out.
        if ( ! $supports_trash ) {
            /* translators: %s: force=true */
            return new WP_Error( 'announcement_trash_not_supported', sprintf( __( "The post does not support trashing. Set '%s' to delete.", 'sk-core' ), 'force=true' ), [ 'status' => 501 ] );
        }

        // Otherwise, only trash if we haven't already.
        if ( 'trash' === $announcement->get_status() ) {
            return new WP_Error( 'announcement_already_trashed', __( 'The announcement has already been trashed.', 'sk-core' ), [ 'status' => 410 ] );
        }

        // (Note that internally this falls through to `wp_delete_post` if
        // the trash is disabled.)
        $result = wp_trash_post( $id );
        if ( ! $result ) {
            return new WP_Error( 'delete_announcement_error', __( 'Error while adding announcement to trash.', 'sk-core' ) );
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
        $announcement = $this->get_single_announcement( $announcement_id );
        if ( is_wp_error( $announcement ) ) {
            return $announcement;
        }

        $result = wp_untrash_post( $announcement_id );
        if ( ! $result ) {
            return new WP_Error( 'untrash_announcement_error', __( 'Error in untrashing announcement.', 'sk-core' ) );
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
            return new WP_Error( 'no_notice', __( 'No notice found with given id.', 'sk-core' ) );
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
            return new WP_Error( 'update_notice_error', __( 'Error while updating notice status.', 'sk-core' ) );
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
            return new WP_Error( 'update_notice_error', __( 'Error while deleting notice status.', 'sk-core' ) );
        }

        // clear cache
        sk_ext()->announcement->delete_announcement_cache( [ $vendor_id ], $notice->get_id() );

        return true;
    }
}
