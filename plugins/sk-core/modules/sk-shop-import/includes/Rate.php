<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Fiat nach Sats.
 *
 * Nutzt die vorhandene Kursabfrage aus sk-payments (mempool.space mit Yadio
 * als Rückfall). Das Modul kann abgeschaltet sein — die Klassendatei wird
 * deshalb bei Bedarf direkt geladen, statt sie nachzubauen.
 */
final class Rate {

    private static function ensure_loaded(): bool {
        if ( class_exists( '\SK\Modules\Payments\LNURL\ExchangeRate' ) ) {
            return true;
        }

        $file = SK_CORE_MODULE_DIR . '/sk-payments/includes/LNURL/ExchangeRate.php';
        if ( is_readable( $file ) ) {
            require_once $file;
        }

        return class_exists( '\SK\Modules\Payments\LNURL\ExchangeRate' );
    }

    /**
     * @return int|\WP_Error Sats
     */
    public static function to_sats( float $amount, string $currency ) {
        if ( $amount <= 0 ) {
            return 0;
        }

        if ( ! self::ensure_loaded() ) {
            return new \WP_Error( 'sk_shop_import_rate', __( 'Die Kursabfrage steht nicht zur Verfügung.', 'sk-core' ) );
        }

        return \SK\Modules\Payments\LNURL\ExchangeRate::fiat_to_sats( $amount, $currency );
    }

    /**
     * Aktueller Kurs, nur zur Anzeige.
     *
     * @return float|\WP_Error
     */
    public static function btc_rate( string $currency = 'EUR' ) {
        if ( ! self::ensure_loaded() ) {
            return new \WP_Error( 'sk_shop_import_rate', __( 'Die Kursabfrage steht nicht zur Verfügung.', 'sk-core' ) );
        }

        return \SK\Modules\Payments\LNURL\ExchangeRate::get_btc_rate( $currency );
    }
}
