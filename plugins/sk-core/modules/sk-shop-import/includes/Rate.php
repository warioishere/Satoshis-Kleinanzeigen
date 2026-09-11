<?php

namespace SK\Modules\ShopImport;

defined( 'ABSPATH' ) || exit;

/**
 * Fiat to Sats.
 *
 * Uses the core rate lookup (mempool.space with Yadio as a fallback).
 */
final class Rate {

    /**
     * @return int|\WP_Error Sats
     */
    public static function to_sats( float $amount, string $currency ) {
        if ( $amount <= 0 ) {
            return 0;
        }

        return \SK\Core\Wallet\LNURL\ExchangeRate::fiat_to_sats( $amount, $currency );
    }

    /**
     * Current rate, for display only.
     *
     * @return float|\WP_Error
     */
    public static function btc_rate( string $currency = 'EUR' ) {
        return \SK\Core\Wallet\LNURL\ExchangeRate::get_btc_rate( $currency );
    }
}
