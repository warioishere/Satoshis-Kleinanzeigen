<?php
/**
 * SK-Core PSR-4 Autoloader — replaces Composer's ClassLoader.
 */

( function () {
	$base = dirname( __DIR__ );

	// PSR-4 namespace → directory mappings
	$map = [
		'SK\\Core\\'                         => $base . '/includes/',
		'SK\\Modules\\Geolocation\\'         => $base . '/modules/geolocation/includes/',
		'SK\\Modules\\StoreReviews\\'        => $base . '/modules/store-reviews/classes/',
		'SK\\Modules\\ProductAdvertisement\\' => $base . '/modules/product-adv/includes/',
		'SK\\Modules\\ProductSubscription\\'  => $base . '/modules/subscription/includes/classes/',
		'SK\\Modules\\Payments\\'             => $base . '/modules/sk-payments/includes/',
		'SK\\Modules\\Reputation\\'           => $base . '/modules/sk-reputation/includes/',
		'SK\\Modules\\Notifications\\'        => $base . '/modules/sk-notifications/includes/',
		'SK\\Modules\\Zaps\\'                 => $base . '/modules/sk-zaps/includes/',
		'SK\\Modules\\NostrMarket\\'          => $base . '/modules/sk-nostr-market/includes/',
		'SK\\Modules\\Auth\\'                 => $base . '/modules/sk-auth/includes/',
		'SK\\Modules\\Auth\\Lnurl\\'          => $base . '/modules/sk-auth/includes/',
		'SK\\Modules\\Feed\\'                 => $base . '/modules/sk-feed/includes/',
		'SK\\Modules\\FollowStore\\'          => $base . '/modules/follow-store/includes/',
		'SK\\Modules\\ReportAbuse\\'          => $base . '/modules/report-abuse/includes/',
		'SK\\Modules\\LiveSearch\\'           => $base . '/modules/live-search/classes/',
	];

	spl_autoload_register( function ( $class ) use ( $map ) {
		foreach ( $map as $prefix => $dir ) {
			$len = strlen( $prefix );
			if ( strncmp( $class, $prefix, $len ) !== 0 ) {
				continue;
			}
			$file = $dir . str_replace( '\\', '/', substr( $class, $len ) ) . '.php';
			if ( file_exists( $file ) ) {
				require $file;
				return;
			}
		}
	} );

	// Auto-load function files
	require_once $base . '/includes/functions-rest-api.php';
	require_once $base . '/includes/functions-dashboard-navigation.php';
} )();
