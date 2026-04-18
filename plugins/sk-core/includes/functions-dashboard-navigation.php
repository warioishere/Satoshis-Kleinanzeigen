<?php

use SK\Core\Utilities\ReportUtil;
use SK\Core\Dashboard\Templates\Dashboard;

/**
 * Sort navigation menu items by position
 *
 *
 * @param array $a first item
 * @param array $b second item
 *
 * @return int
 */
function sk_nav_sort_by_pos( $a, $b ) {
    if ( isset( $a['pos'] ) && isset( $b['pos'] ) ) {
        return intval( $a['pos'] - $b['pos'] );
    } else {
        return 199;
    }
}

/**
 * Get Dashboard Navigation menus
 *
 *
 * @return array
 */
function sk_get_dashboard_nav(): array {
    // All menus — base defaults (Dashboard/Products/Settings) AND module
    // entries — are registered via DashboardRegistry. Modules extending
    // \SK\Core\Dashboard\DashboardModule auto-register; base menus are added
    // in DashboardRegistry::register_base_menus() during bootstrap.

    // Preserve the sk_get_dashboard_settings_nav filter so sk-pro modules
    // can still register their settings pages (payment, shipping, SEO, etc.).
    apply_filters( 'sk_get_dashboard_settings_nav', [] );

    /**
     * Filters nav menu items. Registry injects all registered configs at
     * priority 50; third-party hooks can still add/modify via this filter.
     *
     * @param array<string,array> $menus
     */
    $nav_menus = apply_filters( 'sk_get_dashboard_nav', [] );

    foreach ( $nav_menus as $nav_key => $menu ) {
        if ( ! isset( $menu['pos'] ) ) {
            $nav_menus[ $nav_key ]['pos'] = 190;
        }

        $submenu_items = empty( $menu['submenu'] ) ? [] : $menu['submenu'];

        /**
         * Filters the vendor dashboard submenu item for each menu.
         *
         *
         * @param array<string,array> $submenu_items Associative array of submenu items.
         * @param string              $menu_key      Key of the corresponding menu.
         */
        $submenu_items = apply_filters( 'sk_dashboard_nav_submenu', $submenu_items, $nav_key );

        if ( empty( $submenu_items ) ) {
            continue;
        }

        foreach ( $submenu_items as $key => $submenu ) {
            if ( ! isset( $submenu['pos'] ) ) {
                $submenu['pos'] = 200;
            }

            $submenu_items[ $key ] = $submenu;
        }

        // Sort items according to positional value
        uasort( $submenu_items, 'sk_nav_sort_by_pos' );

        // Filter items according to permissions
        $submenu_items = array_filter( $submenu_items, 'sk_check_menu_permission' );

        // Manage a menu with submenus after permission check
        if ( count( $submenu_items ) < 1 ) {
            unset( $nav_menus[ $nav_key ] );
        } else {
            $nav_menus[ $nav_key ]['submenu'] = $submenu_items;
        }
    }

    // Sort items according to positional value
    uasort( $nav_menus, 'sk_nav_sort_by_pos' );

    // Filter the main menu according to permission
    $nav_menus = array_filter( $nav_menus, 'sk_check_menu_permission' );

    return $nav_menus;
}

/**
 * Checking menu permissions
 *
 *
 * @return boolean
 */
function sk_check_menu_permission( $menu ) {
    if ( isset( $menu['permission'] ) && ! current_user_can( $menu['permission'] ) ) {
        return false;
    }

    return true;
}

/**
 * Renders the SK dashboard menu
 *
 * For settings menu, the active menu format is `settings/menu_key_name`.
 * The active menu will be split at `/` and the `menu_key_name` will be matched
 * with a settings sub menu array. If it's a match, the settings menu will be shown
 * only. Otherwise, the main navigation menu will be shown.
 *
 *
 * @param string $active_menu
 *
 * @return string rendered menu HTML
 */
function sk_dashboard_nav( $active_menu = '' ) {
    $nav_menu          = sk_get_dashboard_nav();
    $active_menu_parts = explode( '/', $active_menu );
    $active_submenu    = '';

    if ( $active_menu && false !== strpos( $active_menu, '/' ) ) {
        $active_menu    = $active_menu_parts[0];
        $active_submenu = $active_menu_parts[1];
    }

    $menu           = '';
    $hamburger_menu = apply_filters( 'sk_load_hamburger_menu', true );

    if ( $hamburger_menu ) {
        $menu      .= '<div id="sk-navigation" aria-label="Menu">';
        $hamburger = apply_filters(
            'sk_vendor_dashboard_menu_hamburger',
            '<label id="mobile-menu-icon" for="toggle-mobile-menu" aria-label="Menu">&#9776;</label><input id="toggle-mobile-menu" type="checkbox" />'
        );

        $menu .= $hamburger;
    }

    $menu .= '<ul class="sk-dashboard-menu">';

    foreach ( $nav_menu as $key => $item ) {
        // If switched off from menu manager
        if ( isset( $item['is_switched_on'] ) && ! $item['is_switched_on'] ) {
            continue;
        }
        /**
         * Filters a menu key according to slug if needed.
         *
         *
         * @param string $menu_key
         */
        $filtered_key = rawurlencode_deep( apply_filters( 'sk_dashboard_nav_menu_key', $key ) );

        $class = $active_menu === $filtered_key || 0 === stripos( $active_menu, $filtered_key ) ? 'active ' . $key : $key;  // checking starts with the key
        $title = __( 'No Title', 'sk-core' );

        if ( ! empty( $item['title'] ) ) {
            $title = $item['title'];
        }

        $title     = apply_filters( 'sk_vendor_dashboard_menu_title', $title, $item );
        $menu_slug = $filtered_key;
        $submenu   = '';

        if ( ! empty( $item['submenu'] ) ) {
            $class        .= ' has-submenu';
            $title        .= ' <i class="fas fa-caret-right menu-dropdown"></i>';
            $submenu      = sprintf( '<ul class="navigation-submenu %s">', $key );
            $subkey_slugs = [];

            foreach ( $item['submenu'] as $sub_key => $sub ) {
                /**
                 * Filters a menu key according to slug if needed.
                 *
                 *
                 * @param string $submenu_key
                 * @param string $menu_key
                 */
                $filtered_subkey = rawurlencode_deep( apply_filters( 'sk_dashboard_nav_submenu_key', $sub_key, $key ) );

                $submenu_class = $active_submenu === $filtered_subkey || 0 === stripos( $active_submenu, $filtered_subkey ) ? "current $sub_key" : $sub_key;
                $submenu_title = __( 'No Title', 'sk-core' );

                if ( ! empty( $sub['title'] ) ) {
                    $submenu_title = $sub['title'];
                }

                $submenu .= sprintf(
                    /* translators: 1) submenu class, 2) submenu route, 3) submenu icon, 4) submenu title */
                    '<li class="submenu-item %1$s"><a href="%2$s" class="submenu-link">%3$s %4$s</a></li>',
                    $submenu_class,
                    $sub['url'] ?? sk_get_navigation_url( "{$key}/{$sub_key}" ),
                    $sub['icon'] ?? '<i class="fab fa-staylinked"></i>',
                    apply_filters( 'sk_vendor_dashboard_menu_title', $submenu_title, $sub )
                );

                $subkey_slugs[] = $filtered_subkey;
            }

            $submenu .= '</ul>';

            // Building parent menu slug pointing to the first submenu item
            if ( isset( $subkey_slugs[0] ) ) {
                $menu_slug = trailingslashit( $menu_slug ) . $subkey_slugs[0];
            }
        }

        $menu .= sprintf(
            /* translators: 1) menu class, 2) menu route, 3) menu url, 4) menu target, 5) menu icon, 6) menu title, 7) submenu */
            '<li class="%1$s"><a href="%2$s" target="%3$s">%4$s %5$s</a>%6$s</li>',
            $class,
            $item['url'] ?? sk_get_navigation_url( $menu_slug ),
            $item['target'] ?? '_self',
            $item['icon'] ?? '<i class="fab fa-staylinked"></i>',
            $title,
            $submenu
        );
    }

    $common_links = '<li class="sk-common-links sk-clearfix">
            <a title="' . __( 'Visit Store', 'sk-core' ) . '" class="tips" data-placement="top" href="' . sk_get_store_url( sk_get_current_user_id() ) . '" target="_blank"><i class="fas fa-external-link-alt"></i></a>
<a title="' . __( 'Log out', 'sk-core' ) . '" class="tips sk-logout-trigger" data-placement="top" href="#"><i class="fas fa-power-off"></i></a>
        </li>';

    $menu .= apply_filters( 'sk_dashboard_nav_common_link', $common_links );

    $menu .= '</ul>';

    if ( $hamburger_menu ) {
        $menu .= '</div>';
    }

    return $menu;
}
