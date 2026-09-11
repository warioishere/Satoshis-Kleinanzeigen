<?php

namespace SK\Core\Wallet;

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
            error_log( '[SK Core] QR-Erzeugung fehlgeschlagen: ' . $e->getMessage() );
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

    /**
     * A payment payload as a REST answer, rate limited.
     *
     * Two endpoints hand out QR images, one for zaps and one for the instant
     * purchase, and they have to stay open — a payer is not logged in. The
     * validation, the limit and the rendering live here, so the two cannot
     * drift apart; one of them used to have no limit at all.
     *
     * @param string $bucket      Rate-limit bucket name of the calling endpoint.
     * @param bool   $allow_bip21 Whether a bitcoin: URI is acceptable too.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public static function rest_answer( string $data, string $bucket, bool $allow_bip21 = false ) {
        $data = trim( $data );
        $ip   = sk_get_client_ip();

        if ( ! sk_rate_limit( $bucket . ':' . md5( $ip !== '' ? $ip : 'unknown' ), 30 ) ) {
            return new \WP_Error( 'qr_rate', __( 'Zu viele Anfragen.', 'sk-core' ), [ 'status' => 429 ] );
        }

        if ( strlen( $data ) > 1000 ) {
            return new \WP_Error( 'qr_too_long', __( 'Payload zu lang.', 'sk-core' ), [ 'status' => 400 ] );
        }

        $is_bolt11 = (bool) preg_match( '/^ln[a-z0-9]{20,}$/i', $data );
        $is_bip21  = $allow_bip21 && preg_match( '/^bitcoin:[a-zA-Z0-9]{20,90}(?:\?[A-Za-z0-9=&.\-_%]*)?$/', $data );

        if ( ! $is_bolt11 && ! $is_bip21 ) {
            return new \WP_Error( 'qr_invalid', __( 'Nur Zahlungsdaten werden gerendert.', 'sk-core' ), [ 'status' => 400 ] );
        }

        $uri = $is_bolt11 ? self::bolt11( $data ) : self::data_uri( $data );

        if ( '' === $uri ) {
            return new \WP_Error( 'qr_failed', __( 'QR-Code konnte nicht erzeugt werden.', 'sk-core' ), [ 'status' => 500 ] );
        }

        return new \WP_REST_Response( [ 'qr' => $uri ], 200 );
    }
}
