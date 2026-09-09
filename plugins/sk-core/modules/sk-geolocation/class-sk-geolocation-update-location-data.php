<?php

use SK\Core\Abstracts\SkBackgroundProcesses;

/**
 * Update vendor and product geolocation data
 *
 */
class SK_Geolocation_Update_Location_Data extends SkBackgroundProcesses {

    /**
     * Action
     *
     *
     * @var string
     */
    protected $action = 'SK_Geolocation_Update_Location_Data';

    /**
     * Perform updates
     *
     *
     * @param mixed $item
     *
     * @return mixed
     */
    public function task( $item ) {
        if ( empty( $item ) ) {
            return false;
        }

        if ( 'vendors' === $item['updating'] ) {
            return $this->update_vendors( $item['paged'] );
        } elseif ( 'products' === $item['updating'] ) {
            return $this->update_products( $item['paged'] );
        } elseif ( 'vendor_products' === $item['updating'] ) {
			$this->update_products( $item['paged'], $item['vendor_id'] ?? 0 );
        }

        return false;
    }

    /**
     * Update vendors
     *
     *
     * @param int $paged
     *
     * @return array
     */
    private function update_vendors( $paged ) {
        $args = array(
            'role'   => 'seller',
            'number' => 50,
            'paged'  => $paged,
        );

        $query = new WP_User_Query( $args );

        $vendors = $query->get_results();

        if ( empty( $vendors ) ) {
            return array(
                'updating' => 'products',
                'paged'    => 1,
            );
        }

        foreach ( $vendors as $vendor ) {
            $sk_geo_latitude = get_user_meta( $vendor->ID, 'sk_geo_latitude', true );

            if ( ! empty( $sk_geo_latitude ) ) {
                continue;
            }

            $profile_settings = get_user_meta( $vendor->ID, 'sk_profile_settings', true );

            if ( ! empty( $profile_settings['location'] ) && ! empty( $profile_settings['find_address'] ) ) {
                $location = explode( ',', $profile_settings['location'] );

                if ( 2 !== count( $location ) ) {
                    continue;
                }

                update_user_meta( $vendor->ID, 'sk_geo_latitude', $location[0] );
                update_user_meta( $vendor->ID, 'sk_geo_longitude', $location[1] );
                update_user_meta( $vendor->ID, 'sk_geo_public', 1 );
                update_user_meta( $vendor->ID, 'sk_geo_address', $profile_settings['find_address'] );
            }
        }

        return array(
            'updating' => 'vendors',
            'paged'    => ++$paged,
        );
    }

    /**
     * Update products
     *
     *
     * @param int $paged
     * @param int $vendor_id
     *
     * @return array|bool
     */
    private function update_products( $paged, $vendor_id = 0 ) {
        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => 50,
            'post_status'    => 'any',
            'paged'          => $paged,
        );

		if ( $vendor_id ) {
			$args['author'] = $vendor_id;
		}

        $query = new WP_Query( $args );

        if ( empty( $query->posts ) ) {
            return false;
        } else {
            foreach ( $query->posts as $post ) {
                $sk_geo_latitude = get_post_meta( $post->ID, 'sk_geo_latitude', true );

                if ( empty( $sk_geo_latitude ) ) {
                    $vendor_sk_geo_latitude  = get_user_meta( $post->post_author, 'sk_geo_latitude', true );
                    $vendor_sk_geo_longitude = get_user_meta( $post->post_author, 'sk_geo_longitude', true );
                    $vendor_sk_geo_address   = get_user_meta( $post->post_author, 'sk_geo_address', true );

                    if ( ! empty( $vendor_sk_geo_latitude ) && ! empty( $vendor_sk_geo_longitude ) ) {
                        update_post_meta( $post->ID, 'sk_geo_latitude', $vendor_sk_geo_latitude );
                        update_post_meta( $post->ID, 'sk_geo_longitude', $vendor_sk_geo_longitude );
                        update_post_meta( $post->ID, 'sk_geo_public', 1 );
                        update_post_meta( $post->ID, 'sk_geo_address', $vendor_sk_geo_address );
                    }
                }
            }
        }

        return array(
            'updating' => 'products',
            'paged'    => ++$paged,
        );
    }
}
