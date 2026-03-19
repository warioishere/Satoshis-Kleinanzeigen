<?php
if (! defined('ABSPATH')) exit;  // if direct access 	


class wcps_class_settings
{


    public function __construct()
    {

        add_action('admin_menu', array($this, 'admin_menu'), 12);
    }


    public function admin_menu()
    {

        add_submenu_page('edit.php?post_type=wcps', __('Settings', 'woocommerce-products-slider'), __('Settings', 'woocommerce-products-slider'), 'manage_options', 'settings', array($this, 'settings'));
        add_submenu_page('edit.php?post_type=wcps', __('Import layouts', 'woocommerce-products-slider'), __('Import layouts', 'woocommerce-products-slider'), 'manage_options', 'import_layouts', array($this, 'import_layouts'));
        // add_submenu_page('edit.php?post_type=wcps', __('Dashboard', 'wcps'), __('Dashboard', 'wcps'), 'manage_options', 'wcps-dashboard', array($this, 'dashboard'));
        add_submenu_page('edit.php?post_type=wcps', __('wcps Builder', 'woocommerce-products-slider'), __('Builder', 'woocommerce-products-slider'), 'manage_options', 'wcps-builder', array($this, 'builder'));
    }
    public function builder()
    {
        include('menu/builder.php');
    }
    public function settings()
    {

        //include( 'menu/settings-old.php' );
        include('menu/settings.php');
    }

    public function dashboard()
    {
        include('menu/dashboard.php');
    }

    public function import_layouts()
    {
        include('menu/import-layouts.php');
    }
}


new wcps_class_settings();
