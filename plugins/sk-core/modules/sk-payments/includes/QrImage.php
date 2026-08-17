<?php

namespace SK\Modules\Payments;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;

defined( 'ABSPATH' ) || exit;

/**
 * Renders payment QR codes locally as PNG data URIs.
 *
 * Payment QR codes must never be fetched from a third party: the external
 * service would learn every invoice and address, and it controls the image the
 * payer actually scans. endroid/qr-code is loaded via sk-core/lib/autoload.php.
 */
class QrImage {

    /** Pixel size of the rendered PNG. */
    const SIZE = 260;

    /** Quiet-zone margin in pixels. */
    const MARGIN = 8;

    /** @var array<string,string> payload => data URI */
    private static $cache = [];

    /**
     * PNG data URI for a payment payload, or '' if rendering failed.
     */
    public static function data_uri( string $payload ): string {
        if ( $payload === '' ) {
            return '';
        }

        if ( isset( self::$cache[ $payload ] ) ) {
            return self::$cache[ $payload ];
        }

        $uri = '';

        try {
            $qr = QrCode::create( $payload )
                ->setErrorCorrectionLevel( new ErrorCorrectionLevelMedium() )
                ->setRoundBlockSizeMode( new RoundBlockSizeModeMargin() )
                ->setSize( self::SIZE )
                ->setMargin( self::MARGIN )
                ->setForegroundColor( new Color( 0, 0, 0 ) )
                ->setBackgroundColor( new Color( 255, 255, 255 ) );

            $uri = ( new PngWriter() )->write( $qr )->getDataUri();
        } catch ( \Throwable $e ) {
            error_log( '[SK Payments] QR-Erzeugung fehlgeschlagen: ' . $e->getMessage() );
        }

        self::$cache[ $payload ] = $uri;

        return $uri;
    }

    /**
     * QR payload for a bolt11 invoice.
     *
     * Uppercase bech32 lets the encoder use QR alphanumeric mode, which keeps
     * long invoices scannable. Wallets treat bolt11 case-insensitively.
     */
    public static function bolt11( string $bolt11 ): string {
        return self::data_uri( strtoupper( $bolt11 ) );
    }
}
