<?php
/**
 * SK-Core centralized vendor autoloader.
 *
 * Consolidates all third-party PHP dependencies used across sk-core modules:
 *   - swentel/nostr-php v1.9.2 (NWC Client, Nostr Login, Auto Poster)
 *   - bitwasp/bech32 (LNURL Auth, Nostr Login)
 *   - eza/lnurl-php (LNURL Auth)
 *   - endroid/qr-code + bacon/bacon-qr-code (LNURL Auth)
 *   - phrity/websocket (NWC Client WebSocket)
 *   - simplito/elliptic-php (Nostr, LNURL)
 *   - paragonie/ecc (Nostr crypto)
 *   - leigh/chacha20 (Nostr NIP-04)
 *
 * Original authors / upstream repos:
 *   - swentel/nostr-php: https://github.com/swentel/nostr-php
 *   - lnurl-auth: https://github.com/joel-st/lnurl-auth-for-wordpress (Joel Stuedle)
 *   - nostr-login: https://github.com/Yeghro/YEGHRO_NostrLogin (Yeghro)
 */

( function () {
    $dir = __DIR__;

    $map = [
        // Nostr
        'swentel\\nostr\\'       => $dir . '/swentel/nostr-php/src',
        'WebSocket\\'            => $dir . '/phrity/websocket/src',
        'Phrity\\Util\\'         => $dir . '/phrity/util-errorhandler/src',
        'Phrity\\Net\\'          => [ $dir . '/phrity/net-stream/src', $dir . '/phrity/net-uri/src' ],
        'Phrity\\Comparison\\'   => $dir . '/phrity/comparison/src',
        'Psr\\Log\\'             => $dir . '/psr/log/src',
        'Psr\\Http\\Message\\'   => [ $dir . '/psr/http-factory/src', $dir . '/psr/http-message/src' ],
        'Mdanter\\Ecc\\'         => $dir . '/paragonie/ecc/src',
        'FG\\'                   => $dir . '/genkgo/php-asn1/lib',
        'ChaCha20\\'             => $dir . '/leigh/chacha20/lib',

        // Shared crypto
        'Elliptic\\'             => $dir . '/simplito/elliptic-php/lib',
        'BN\\'                   => $dir . '/simplito/bn-php/lib',
        'BI\\'                   => $dir . '/simplito/bigint-wrapper-php/lib',
        'BitWasp\\Bech32\\'      => $dir . '/bitwasp/bech32/src',

        // LNURL Auth
        'eza\\lnurl\\'           => $dir . '/ezadr/lnurl-php/src',
        'Endroid\\QrCode\\'      => $dir . '/endroid/qr-code/src',
        'BaconQrCode\\'          => $dir . '/bacon/bacon-qr-code/src',
        'DASPRiD\\Enum\\'        => $dir . '/dasprid/enum/src',
        'chillerlan\\QRCode\\'   => $dir . '/chillerlan/php-qrcode/src',
        'chillerlan\\Settings\\' => $dir . '/chillerlan/php-settings-container/src',
    ];

    spl_autoload_register( function ( $class ) use ( $map ) {
        foreach ( $map as $prefix => $dirs ) {
            $len = strlen( $prefix );
            if ( strncmp( $class, $prefix, $len ) !== 0 ) {
                continue;
            }
            $relative = str_replace( '\\', '/', substr( $class, $len ) ) . '.php';
            $dirs = is_array( $dirs ) ? $dirs : [ $dirs ];
            foreach ( $dirs as $d ) {
                $file = $d . '/' . $relative;
                if ( file_exists( $file ) ) {
                    require $file;
                    return;
                }
            }
        }
    } );
    // Function files that can't be autoloaded via PSR-4 (no classes).
    // Guard against double-declaration if original plugins are still active.
    if ( ! function_exists( 'BitWasp\\Bech32\\polyMod' ) ) {
        require_once $dir . '/bitwasp/bech32/src/bech32.php';
    }
    if ( ! function_exists( 'eza\\lnurl\\encodeUrl' ) ) {
        require_once $dir . '/ezadr/lnurl-php/src/lnurl.php';
    }
} )();
