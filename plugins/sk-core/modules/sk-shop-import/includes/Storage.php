<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Storage for uploaded files.
 *
 * A dedicated folder under uploads with random names and access lockdown:
 * a shop catalog is business data and shouldn't be publicly reachable on
 * the web just because it happens to live under uploads.
 */
final class Storage {

    const DIR = 'sk-shop-import';

    public static function dir(): string {
        $uploads = wp_upload_dir();
        $dir     = trailingslashit( $uploads['basedir'] ) . self::DIR;

        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }

        // Lock down access as far as the server configuration allows.
        if ( ! file_exists( $dir . '/.htaccess' ) ) {
            file_put_contents( $dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
        }
        if ( ! file_exists( $dir . '/index.php' ) ) {
            file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
        }

        return $dir;
    }

    /**
     * Accept the uploaded file.
     *
     * @return string|\WP_Error Path
     */
    public static function accept( array $file, int $vendor_id ) {
        if ( ! isset( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
            return new \WP_Error( 'sk_upload', __( 'Es wurde keine Datei hochgeladen.', 'sk-core' ) );
        }

        if ( (int) ( $file['size'] ?? 0 ) > 20 * MB_IN_BYTES ) {
            return new \WP_Error( 'sk_upload_size', __( 'Die Datei ist grösser als 20 MB.', 'sk-core' ) );
        }

        $name = strtolower( (string) ( $file['name'] ?? '' ) );
        if ( substr( $name, -4 ) !== '.csv' && substr( $name, -4 ) !== '.txt' ) {
            return new \WP_Error( 'sk_upload_type', __( 'Bitte eine CSV-Datei hochladen.', 'sk-core' ) );
        }

        $target = self::dir() . '/' . $vendor_id . '-' . wp_generate_password( 16, false, false ) . '.csv';

        if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) {
            return new \WP_Error( 'sk_upload_move', __( 'Die Datei liess sich nicht ablegen.', 'sk-core' ) );
        }

        return $target;
    }

    /**
     * Store a fetched catalog.
     *
     * Same folder and same naming pattern as for uploads, so belongs_to()
     * applies here too and the job can find the file again later.
     *
     * @return string|\WP_Error Path
     */
    public static function put_catalog( string $json, int $vendor_id ) {
        if ( trim( $json ) === '' ) {
            return new \WP_Error( 'sk_catalog_empty', __( 'Der geholte Katalog ist leer.', 'sk-core' ) );
        }

        $target = self::dir() . '/' . $vendor_id . '-' . wp_generate_password( 16, false, false ) . '.json';

        if ( file_put_contents( $target, $json ) === false ) {
            return new \WP_Error( 'sk_catalog_write', __( 'Der Katalog liess sich nicht ablegen.', 'sk-core' ) );
        }

        return $target;
    }

    /**
     * Does this file belong to this vendor?
     *
     * Without this check, a vendor could read another vendor's file via a
     * manipulated path.
     */
    public static function belongs_to( string $path, int $vendor_id ): bool {
        $real = realpath( $path );
        $dir  = realpath( self::dir() );

        if ( ! $real || ! $dir || strpos( $real, $dir ) !== 0 ) {
            return false;
        }

        return strpos( basename( $real ), $vendor_id . '-' ) === 0;
    }

    public static function forget( string $path ): void {
        if ( is_file( $path ) ) {
            @unlink( $path );
        }
    }
}
