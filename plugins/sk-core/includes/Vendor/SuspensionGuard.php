<?php

namespace SK\Core\Vendor;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps a suspended vendor's listings offline.
 *
 * Suspending used to draft the existing listings once and then stop caring:
 * nothing consulted the suspension flag afterwards, so the vendor simply hit
 * "publish" again and was back online. This is the part that makes a suspension
 * actually hold.
 *
 * A suspension only blocks publishing. Dashboard and login stay usable on
 * purpose — for unpaid commissions the vendor has to be able to reach the
 * payment page that lifts the suspension.
 */
class SuspensionGuard {

    public function __construct() {
        // Same seams the contact-details publish block uses.
        add_action( 'sk_new_product_added', [ $this, 'force_draft' ], 5, 2 );
        add_action( 'sk_product_updated', [ $this, 'force_draft' ], 5, 2 );
        add_action( 'sk_bulk_product_status_change', [ $this, 'force_bulk_draft' ], 5, 2 );

        // New listings start as drafts for suspended vendors.
        add_filter( 'sk_get_default_product_status', [ $this, 'filter_default_status' ], 5, 2 );
    }

    /**
     * Draft a single listing again if its author is suspended.
     *
     * @param int   $product_id
     * @param array $data
     */
    public function force_draft( $product_id, $data = [] ): void {
        $product_id = (int) $product_id;

        if ( $product_id <= 0 ) {
            return;
        }

        $author = (int) get_post_field( 'post_author', $product_id );

        if ( ! $author || ! Suspension::is_suspended( $author ) ) {
            return;
        }

        if ( ! in_array( get_post_status( $product_id ), [ 'publish', 'pending', 'future', 'private' ], true ) ) {
            return;
        }

        wp_update_post( [ 'ID' => $product_id, 'post_status' => 'draft' ] );

        $this->add_notice();
    }

    /**
     * Same for the bulk status action in the dashboard.
     *
     * @param string $status
     * @param array  $product_ids
     */
    public function force_bulk_draft( $status, $product_ids ): void {
        if ( ! in_array( $status, [ 'publish', 'pending', 'future' ], true ) ) {
            return;
        }

        $blocked = false;

        foreach ( (array) $product_ids as $product_id ) {
            $product_id = (int) $product_id;

            if ( $product_id <= 0 ) {
                continue;
            }

            $author = (int) get_post_field( 'post_author', $product_id );

            if ( ! $author || ! Suspension::is_suspended( $author ) ) {
                continue;
            }

            if ( in_array( get_post_status( $product_id ), [ 'publish', 'pending', 'future', 'private' ], true ) ) {
                wp_update_post( [ 'ID' => $product_id, 'post_status' => 'draft' ] );
                $blocked = true;
            }
        }

        if ( $blocked ) {
            $this->add_notice();
        }
    }

    /**
     * Default status for new listings of a suspended vendor.
     *
     * @param string $status
     * @param int    $seller_id
     * @return string
     */
    public function filter_default_status( $status, $seller_id = 0 ) {
        $seller_id = (int) $seller_id ?: get_current_user_id();

        if ( $seller_id && Suspension::is_suspended( $seller_id ) ) {
            return 'draft';
        }

        return $status;
    }

    private function add_notice(): void {
        if ( ! function_exists( 'sk_add_notice' ) ) {
            return;
        }

        sk_add_notice(
            __( 'Dein Konto ist derzeit gesperrt — Inserate können nicht veröffentlicht werden.', 'sk-core' ),
            'error'
        );
    }
}
