<?php
/**
 * Compiles a Gettext .po file into the .mo and .l10n.php files WordPress loads.
 *
 * Neither msgfmt nor wp-cli are available on the server, so this uses the
 * PO/MO classes shipped with WordPress. The .l10n.php file is the one WordPress
 * actually reads at runtime, so a .po edited by hand or through Loco has no
 * effect until it is regenerated here.
 *
 * Usage:
 *   php tools/i18n-compile.php <file.po> [<file.po> ...]
 *
 * The ABSPATH of the WordPress install is derived from this file's location and
 * can be overridden with the WP_ABSPATH environment variable.
 */

if ( PHP_SAPI !== 'cli' ) {
    exit( 1 );
}

$abspath = getenv( 'WP_ABSPATH' );

if ( ! $abspath ) {
    $abspath = dirname( __DIR__, 4 ) . '/';
}

$abspath = rtrim( $abspath, '/' ) . '/';

if ( ! is_dir( $abspath . 'wp-includes/pomo' ) ) {
    fwrite( STDERR, "Cannot find wp-includes in {$abspath}. Set WP_ABSPATH.\n" );
    exit( 1 );
}

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', $abspath );
}

// plural-forms.php uses array_last(), which lives in compat.php — without it the
// PO import dies as soon as it reads the Plural-Forms header.
require_once $abspath . 'wp-includes/compat.php';
require_once $abspath . 'wp-includes/pomo/po.php';
require_once $abspath . 'wp-includes/pomo/mo.php';
require_once $abspath . 'wp-includes/l10n/class-wp-translation-file.php';
require_once $abspath . 'wp-includes/l10n/class-wp-translation-file-mo.php';
require_once $abspath . 'wp-includes/l10n/class-wp-translation-file-php.php';

$files = array_slice( $argv, 1 );

if ( ! $files ) {
    fwrite( STDERR, "Usage: php tools/i18n-compile.php <file.po> [<file.po> ...]\n" );
    exit( 1 );
}

$status = 0;

foreach ( $files as $po_file ) {
    if ( ! is_readable( $po_file ) ) {
        fwrite( STDERR, "Not readable: {$po_file}\n" );
        $status = 1;
        continue;
    }

    $po = new PO();

    if ( ! $po->import_from_file( $po_file ) ) {
        fwrite( STDERR, "Not a valid PO file: {$po_file}\n" );
        $status = 1;
        continue;
    }

    $base     = preg_replace( '/\.po$/', '', $po_file );
    $mo_file  = $base . '.mo';
    $php_file = $base . '.l10n.php';

    $mo          = new MO();
    $mo->headers = $po->headers;

    $translated = 0;

    foreach ( $po->entries as $entry ) {
        // Untranslated entries are carried in the .po for translators only.
        if ( ! array_filter( (array) $entry->translations, 'strlen' ) ) {
            continue;
        }

        $mo->add_entry( $entry );
        ++$translated;
    }

    if ( ! $mo->export_to_file( $mo_file ) ) {
        fwrite( STDERR, "Failed to write {$mo_file}\n" );
        $status = 1;
        continue;
    }

    $php = WP_Translation_File::transform( $mo_file, 'php' );

    if ( false === $php || false === file_put_contents( $php_file, $php ) ) {
        fwrite( STDERR, "Failed to write {$php_file}\n" );
        $status = 1;
        continue;
    }

    printf(
        "%s: %d of %d entries translated -> %s, %s\n",
        basename( $po_file ),
        $translated,
        count( $po->entries ),
        basename( $mo_file ),
        basename( $php_file )
    );
}

exit( $status );
