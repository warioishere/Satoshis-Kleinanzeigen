<?php

namespace SK\Modules\Zaps;

defined( 'ABSPATH' ) || exit;

/**
 * SK Zaps — Lightning Zap Button for Vendor Stores + Products.
 *
 * Uses NIP-57 (Lightning Zaps) when buyer has Nostr extension (Alby Hub).
 * Falls back to simple LNURL-pay tip when no extension available.
 *
 * Requirements for vendor:
 *   - Nostr Public Key (for NIP-57 zap request p tag)
 *   - Lightning Address (for LNURL-pay invoice)
 */
final class Module {

    public $version;

    public function __construct() {
        $this->version = sk_assets_version( __DIR__ . '/assets' );
        define( 'SK_ZAPS_VERSION', $this->version );
        define( 'SK_ZAPS_PATH', dirname( __FILE__ ) );
        define( 'SK_ZAPS_URL', plugins_url( '', __FILE__ ) );

        require_once SK_ZAPS_PATH . '/includes/ZapSettings.php';
        new ZapSettings();

        if ( sk_get_option( 'sk_zaps_enabled', 'sk_zaps', 'off' ) !== 'on' ) {
            return;
        }

        require_once SK_ZAPS_PATH . '/includes/ZapButton.php';
        new ZapButton();
    }
}
