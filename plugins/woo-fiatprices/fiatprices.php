<?php
/**
 * Plugin Name: BTC Preisumrechner (Geo-basiert)
 * Description: Zeigt automatisch den umgerechneten Sats-Preis in EUR oder CHF je nach Standort des Besuchers.
 * Version: 0.1
 * Author: Wario
 */

add_action('wp_enqueue_scripts', 'btcpreis_enqueue_script');

function btcpreis_enqueue_script() {
    wp_enqueue_script('btcpreis-geo', plugin_dir_url(__FILE__) . 'geo-preis.js', [], filemtime( __DIR__ . '/geo-preis.js' ), true);
}
