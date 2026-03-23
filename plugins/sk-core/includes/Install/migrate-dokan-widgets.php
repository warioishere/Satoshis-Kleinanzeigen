<?php
/**
 * SK-Core — Dokan Widget Migration
 *
 * Einmalig beim Go-Live ausführen: wp eval-file wp-content/plugins/sk-core/includes/Install/migrate-dokan-widgets.php
 * Oder als Admin via: /wp-admin/?sk_migrate_widgets=1
 *
 * Migriert alle dokan-* Widget-IDs auf sk-* in:
 *   1. sidebars_widgets (aktive Widget-Zuordnungen)
 *   2. widget_dokan-* Options (Widget-Settings)
 */

defined( 'ABSPATH' ) || exit;

function sk_migrate_dokan_widgets() {
	// Mapping: old dokan widget id_base → new sk widget id_base
	$map = [
		'dokan-store-location'                     => 'sk-store-location',
		'dokan-store-contact-widget'               => 'sk-store-contact-widget',
		'dokan-store-menu'                         => 'sk-store-menu',
		'dokan-category-menu'                      => 'sk-category-menu',
		'dokan-best-selling-widget'                => 'sk-best-selling-widget',
		'dokan-feature-seller-widget'              => 'sk-feature-seller-widget',
		'dokan-top-rated'                          => 'sk-top-rated',
		'dokan-best-seller-widget'                 => 'sk-best-selling-widget',
		'dokan-filter-product'                     => 'sk-filter-product',
		'dokan-store-open-close-widget'            => 'sk-store-open-close-widget',
		'dokan-store-support-widget'               => 'sk-store-support-widget',
		'dokan-verification-list'                  => 'sk-verification-list',
		'dokan-geolocation-widget-filters'         => 'sk-geolocation-widget-filters',
		'dokan-geolocation-widget-product-location' => 'sk-geolocation-widget-product-location',
		'dokan_product_advertisement_widget'        => 'sk_product_advertisement_widget',
		'dokan_seller_badges'                       => 'sk_seller_badges',
	];

	$log = [];

	// ── 1. Fix sidebars_widgets ──
	$sidebars = get_option( 'sidebars_widgets', [] );
	$sidebar_changed = false;

	foreach ( $sidebars as $area => &$widgets ) {
		if ( ! is_array( $widgets ) ) {
			continue;
		}
		foreach ( $widgets as &$widget_id ) {
			foreach ( $map as $old_base => $new_base ) {
				if ( strpos( $widget_id, $old_base ) === 0 ) {
					$new_id = str_replace( $old_base, $new_base, $widget_id );
					$log[]  = "[sidebar:{$area}] {$widget_id} → {$new_id}";
					$widget_id = $new_id;
					$sidebar_changed = true;
				}
			}
		}
	}
	unset( $widgets, $widget_id );

	if ( $sidebar_changed ) {
		update_option( 'sidebars_widgets', $sidebars );
	}

	// ── 2. Migrate widget settings (widget_dokan-* → widget_sk-*) ──
	foreach ( $map as $old_base => $new_base ) {
		$old_option = 'widget_' . $old_base;
		$new_option = 'widget_' . $new_base;

		$old_settings = get_option( $old_option, [] );
		if ( empty( $old_settings ) ) {
			continue;
		}

		$new_settings = get_option( $new_option, [] );

		// Merge: alte Instanzen in neue Option übernehmen (ohne bestehende zu überschreiben)
		foreach ( $old_settings as $key => $value ) {
			if ( $key === '_multiwidget' ) {
				$new_settings['_multiwidget'] = 1;
				continue;
			}
			if ( ! isset( $new_settings[ $key ] ) ) {
				$new_settings[ $key ] = $value;
				$log[] = "[settings] {$old_option}[{$key}] → {$new_option}[{$key}]";
			}
		}

		update_option( $new_option, $new_settings );
		delete_option( $old_option );
		$log[] = "[cleanup] Deleted option {$old_option}";
	}

	return $log;
}

// Auto-run when loaded directly (wp eval-file) or via admin URL param
if ( php_sapi_name() === 'cli' || ( is_admin() && current_user_can( 'manage_options' ) && isset( $_GET['sk_migrate_widgets'] ) ) ) {
	$results = sk_migrate_dokan_widgets();
	if ( empty( $results ) ) {
		echo "No dokan widgets to migrate.\n";
	} else {
		echo "Migrated " . count( $results ) . " entries:\n";
		foreach ( $results as $line ) {
			echo "  {$line}\n";
		}
	}
}
