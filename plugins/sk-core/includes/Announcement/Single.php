<?php

namespace SK\Core\Announcement;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class Single
 *
 *
 */
class Single {
    /**
     *
     * @var array $data
     */
    private $data = [
        'id'                  => 0,
        'notice_id'           => 0,
        'vendor_id'           => 0,
        'title'               => '',
        'content'             => '',
        'status'              => '',
        'read_status'         => '',
        'date'                => '',
        'date_gmt'            => '',
        'human_readable_date' => '',
    ];

    /**
     * Single constructor.
     *
     * @param array $args
     */
    public function __construct( $args = [] ) {
        $this->data = wp_parse_args( $args, $this->data );
        $this->set_human_readable_date();
    }

    /**
     * Get announcement data
     *
     *
     * @return array
     */
    public function get_data() {
        return [
            'id'                  => $this->get_id(),
            'notice_id'           => $this->get_notice_id(),
            'vendor_id'           => $this->get_vendor_id(),
            'title'               => $this->get_title(),
            'content'             => $this->get_content(),
            'status'              => $this->get_status(),
            'read_status'         => $this->get_read_status(),
            'date'                => $this->get_date(),
            'date_gmt'            => $this->get_date_gmt(),
            'human_readable_date' => $this->get_human_readable_date(),
        ];
    }

    /**
     * Get announcement id
     *
     *
     * @return int
     */
    public function get_id(): int {
        // if notice id is set, dynamically set id as notice id
        // otherwise, this might be confusing determining if the current item is a notice or announcement
        // the general rules is, if vendor id is provided, notice will be provided, otherwise announcement
        return $this->get_notice_id() ? $this->get_notice_id() : absint( $this->data['id'] );
    }

    /**
     * Get vendor notice id
     *
     *
     * @return int
     */
    public function get_notice_id(): int {
        return absint( $this->data['notice_id'] );
    }

    /**
     * Get vendor id
     *
     *
     * @return int
     */
    public function get_vendor_id(): int {
        return absint( $this->data['vendor_id'] );
    }

    /**
     * Get announcement title
     *
     *
     * @return string
     */
    public function get_title(): string {
        return $this->data['title'];
    }

    /**
     * Get announcement content
     *
     *
     * @return string
     */
    public function get_content(): string {
        return $this->data['content'];
    }

    /**
     * Get announcement status
     *
     *
     * @return string
     */
    public function get_status(): string {
        return $this->data['status'];
    }

    /**
     * Get announcement read status
     *
     *
     * @return string
     */
    public function get_read_status(): string {
        return $this->data['read_status'];
    }

    /**
     * Get announcement date
     *
     *
     * @return string
     */
    public function get_date(): string {
        return $this->data['date'];
    }

    /**
     * Get announcement date in GMT
     *
     *
     * @return string
     */
    public function get_date_gmt(): string {
        return $this->data['date_gmt'];
    }

    /**
     * Get human-readable announcement date
     *
     *
     * @return string
     */
    public function get_human_readable_date(): string {
        return $this->data['human_readable_date'];
    }

    /**
     * Set announcement id
     *
     *
     * @param int $id
     *
     * @return $this
     */
    public function set_id( $id ): Single {
        $this->data['id'] = absint( $id );

        return $this;
    }

    /**
     * Set vendor single notice id
     *
     *
     * @param int $id
     *
     * @return $this
     */
    public function set_notice_id( $id ): Single {
        $this->data['notice_id'] = absint( $id );

        return $this;
    }

    /**
     * Set vendor id
     *
     *
     * @param int $id
     *
     * @return $this
     */
    public function set_vendor_id( $id ): Single {
        $this->data['vendor_id'] = absint( $id );

        return $this;
    }

    /**
     * Set announcement title
     *
     *
     * @param string $title
     *
     * @return $this
     */
    public function set_title( $title ): Single {
        $this->data['title'] = $title;

        return $this;
    }

    /**
     * Set announcement content
     *
     *
     * @param string $content
     *
     * @return $this
     */
    public function set_content( $content ): Single {
        $this->data['content'] = $content;

        return $this;
    }

    /**
     * Set announcement status
     *
     *
     * @param string $status
     *
     * @return $this
     */
    public function set_status( $status ): Single {
        $this->data['status'] = in_array( $status, [ 'publish', 'future', 'draft' ], true ) ? $status : 'draft';

        return $this;
    }

    /**
     * Set announcement read status
     *
     *
     * @param string $read_status
     *
     * @return $this
     */
    public function set_read_status( $read_status ): Single {
        $this->data['read_status'] = in_array( $read_status, [ 'read', 'unread', 'trash' ], true ) ? $read_status : 'unread';

        return $this;
    }

    /**
     * Set human-readable announcement date
     *
     *
     * @return $this
     */
    public function set_human_readable_date(): Single {
        if ( empty( $this->get_date() ) ) {
            return $this;
        }

        $this->data['human_readable_date'] = sprintf(
        // translators: %s, Time elapsed from announcement creation.
            __( '%s ago', 'sk-core' ),
            human_time_diff(
                sk_current_datetime()->modify( $this->get_date() )->getTimestamp(),
                sk_current_datetime()->getTimestamp()
            )
        );

        return $this;
    }

    /**
     * Check if the current item is a notice
     *
     *
     * @return bool
     */
    public function is_notice(): bool {
        return (bool) $this->get_notice_id();
    }

    /**
     * Check if the current item is an announcement
     *
     *
     * @return bool
     */
    public function is_announcement(): bool {
        return ! $this->get_notice_id();
    }
}
