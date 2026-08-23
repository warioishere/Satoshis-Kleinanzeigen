<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Ablage der hochgeladenen Dateien.
 *
 * Eigener Ordner unter uploads mit Zufallsnamen und Zugriffssperre: Ein
 * Shop-Katalog ist Geschaeftsdaten und gehoert nicht oeffentlich abrufbar
 * ins Web, nur weil er zufaellig unter uploads liegt.
 */
final class Storage {

    const DIR = 'sk-shop-import';

    public static function dir(): string {
        $uploads = wp_upload_dir();
        $dir     = trailingslashit( $uploads['basedir'] ) . self::DIR;

        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }

        // Zugriff sperren, so gut es die Serverkonfiguration zulaesst.
        if ( ! file_exists( $dir . '/.htaccess' ) ) {
            file_put_contents( $dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
        }
        if ( ! file_exists( $dir . '/index.php' ) ) {
            file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
        }

        return $dir;
    }

    /**
     * Hochgeladene Datei uebernehmen.
     *
     * @return string|\WP_Error Pfad
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
     * Gehoert diese Datei diesem Verkaeufer?
     *
     * Ohne die Pruefung koennte ein Verkaeufer ueber einen manipulierten Pfad
     * die Datei eines anderen einlesen.
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
