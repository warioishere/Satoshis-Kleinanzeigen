<?php

/**
 * Get help documents for admin
 *
 *
 * @return Object
 */
function sk_admin_get_help() {
    $help_docs = get_transient( 'sk_help_docs', '[]' );

    if ( false === $help_docs ) {
        $help_url  = 'https://sk.co/wp-json/org/help';
        $response  = wp_remote_get( $help_url, [ 'timeout' => 15 ] );
        $help_docs = wp_remote_retrieve_body( $response );

        if ( is_wp_error( $response ) || (int) $response['response']['code'] !== 200 ) {
            $help_docs = '[]';
        }

        set_transient( 'sk_help_docs', $help_docs, 12 * HOUR_IN_SECONDS );
    }

    $help_docs = json_decode( $help_docs );

    return $help_docs;
}
