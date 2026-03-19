<?php
if (!defined('ABSPATH')) exit;

class WEO_SK {
  public function __construct() {
    add_action('init', [$this,'handle_treuhand_settings_post'], 10);
    add_filter('sk_get_dashboard_nav', [$this,'dashboard_nav']);
    add_filter('sk_query_var_filter', [$this,'register_query_var']);
    add_filter('sk_dashboard_nav_active', [$this,'highlight_nav'], 10, 3);
    add_action('sk_load_custom_template', [$this,'maybe_render_treuhand_template']);
    add_action('sk_product_edit_after_pricing', [$this,'product_field'], 10, 2);
    add_action('sk_process_product_meta', [$this,'save_product_meta'], 10, 1);
    add_filter('woocommerce_is_purchasable', [$this,'is_purchasable'], 10, 2);
    add_filter('woocommerce_loop_add_to_cart_link', [$this,'maybe_hide_add_to_cart'], 10, 3);
  }

  public function dashboard_nav($navs) {
    $context = $this->current_user_treuhand_context();
    if (!$context) return $navs;

    if ($context === 'admin') {
      $permission = current_user_can('manage_woocommerce') ? 'manage_woocommerce' : 'manage_options';
    } else {
      $permission = 'sk_view_overview_menu';
    }

    $navs['treuhand'] = [
      'title' => __('Treuhand Service','weo'),
      'icon'  => '<i class="fas fa-handshake"></i>',
      'url'   => function_exists('sk_get_navigation_url') ? sk_get_navigation_url('treuhand') : home_url('/sk-dashboard/treuhand/'),
      'pos'   => 55,
      'permission' => $permission,
    ];
    return $navs;
  }

  public function register_query_var($vars) {
    if (!$this->treuhand_globally_enabled()) return $vars;
    if (!in_array('treuhand', $vars, true)) {
      $vars[] = 'treuhand';
    }
    return $vars;
  }

  public function highlight_nav($active_menu, $request, $active) {
    if (!$this->current_user_treuhand_context()) return $active_menu;
    if (isset($request) && strpos((string)$request, 'treuhand') !== false) {
      return 'treuhand';
    }

    if (!empty($active) && in_array('treuhand', (array)$active, true)) {
      return 'treuhand';
    }

    if (get_query_var('treuhand')) {
      return 'treuhand';
    }

    return $active_menu;
  }

  public function maybe_render_treuhand_template($query_vars) {
    $context = $this->current_user_treuhand_context();
    if (!$context) return;
    if (!isset($query_vars['treuhand'])) return;

    $treuhand_content = $this->render_treuhand_content();
    $template = WEO_DIR.'templates/dashboard-treuhand.php';

    if (file_exists($template)) {
      $treuhand_context = $context;
      if (is_array($treuhand_content)) {
        $treuhand_data = $treuhand_content;
      } else {
        $treuhand_content = (string)$treuhand_content;
      }
      include $template;
    } else {
      if (is_array($treuhand_content)) {
        echo $this->render_treuhand_view_markup($treuhand_content);
      } else {
        echo $treuhand_content;
      }
    }

    return;
  }

  public function handle_treuhand_settings_post() {
    // Skip in WordPress admin backend - only run in Dokan dashboard
    if (is_admin()) return;
    if (!$this->current_user_treuhand_context()) return;
    if ('POST' !== $_SERVER['REQUEST_METHOD']) return;
    if (!isset($_POST['weo_vendor_xpub']) && !isset($_POST['weo_payout_address']) && !isset($_POST['weo_vendor_escrow_enabled'])) return;
    $user_id = get_current_user_id();
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'weo_sk_xpub')) {
      $this->add_notice(__('Ungültiger Sicherheits-Token','weo'),'error');
      $ref = wp_get_referer();
      wp_safe_redirect($ref ? $ref : home_url('/'));
      exit;
    }
    $xpub_raw   = wp_unslash($_POST['weo_vendor_xpub']);
    $payout_raw = isset($_POST['weo_payout_address']) ? wp_unslash($_POST['weo_payout_address']) : '';
    $escrow     = isset($_POST['weo_vendor_escrow_enabled']) ? '1' : '';
    $errors     = false;

    if ($xpub_raw !== '') {
      $xpub = weo_normalize_xpub($xpub_raw);
      if (is_wp_error($xpub)) {
        $this->add_notice(__('Ungültiges xpub','weo'),'error');
        $errors = true;
      } else {
        update_user_meta($user_id,'weo_vendor_xpub',$xpub);
      }
    } else {
      delete_user_meta($user_id,'weo_vendor_xpub');
    }

    if ($payout_raw !== '') {
      if (!weo_validate_btc_address($payout_raw)) {
        $this->add_notice(__('Ungültige Adresse','weo'),'error');
        $errors = true;
      } else {
        update_user_meta($user_id,'weo_payout_address', weo_sanitize_btc_address($payout_raw));
      }
    } else {
      delete_user_meta($user_id,'weo_payout_address');
    }

    if ($escrow) update_user_meta($user_id,'weo_vendor_escrow_enabled','1');
    else delete_user_meta($user_id,'weo_vendor_escrow_enabled');

    if (!$errors) {
      $this->add_notice(__('Escrow-Daten gespeichert','weo'),'success');
    }
    $ref = wp_get_referer();
    if (!$ref) {
      $ref = home_url(wp_unslash($_SERVER['REQUEST_URI']));
    }
    wp_safe_redirect($ref);
    exit;
  }

  public function render_treuhand_content() {
    if (!is_user_logged_in()) return '';
    $context = $this->current_user_treuhand_context();
    if (!$context) return '';

    $treuhand_available = ($context === 'admin') ? true : $this->vendor_features_enabled();

    $user_id = get_current_user_id();

    wp_enqueue_style('weo-css', WEO_URL.'assets/admin.css', [], '1.0');
    wp_enqueue_style('weo-sk-treuhand', WEO_URL.'assets/sk-treuhand.css', ['weo-css'], '1.0');
    wp_enqueue_script('weo-qr', WEO_URL.'assets/qr.min.js', [], '1.0', true);
    wp_enqueue_script('weo-sk-treuhand', WEO_URL.'assets/sk-treuhand.js', [], '1.0', true);

    $active_tab = isset($_GET['weo_tab']) ? sanitize_key(wp_unslash($_GET['weo_tab'])) : 'settings';
    $valid_tabs = ['settings', 'products', 'orders-seller', 'orders-buyer'];
    if (!in_array($active_tab, $valid_tabs, true)) {
      $active_tab = 'settings';
    }

    $psbt_notice = '';

    if ($treuhand_available || $context === 'admin') {
      if ('POST' === $_SERVER['REQUEST_METHOD']) {
        if (!empty($_POST['weo_action']) && !empty($_POST['order_id'])) {
          $order_id = intval($_POST['order_id']);
          $order = wc_get_order($order_id);
          if (!$order) {
            $this->add_notice(__('Bestellung nicht gefunden','weo'),'error');
          } else {
            $act = sanitize_text_field(wp_unslash($_POST['weo_action']));
            $vendor_id = intval($order->get_meta('_weo_vendor_id'));
            $buyer_id  = $order->get_user_id();

            if ($act === 'mark_shipped') {
              if (!wp_verify_nonce($_POST['weo_nonce'] ?? '', 'weo_ship_'.$order_id)) {
                $this->add_notice(__('Ungültiger Sicherheits-Token','weo'),'error');
              } elseif ($user_id !== $vendor_id) {
                $this->add_notice(__('Keine Berechtigung','weo'),'error');
              } else {
                $order->update_meta_data('_weo_shipped', time());
                $order->save();
                do_action('weo_order_shipped', $order_id);
                $this->add_notice(__('Versand markiert','weo'),'success');
              }
            } elseif ($act === 'mark_received') {
              if (!wp_verify_nonce($_POST['weo_nonce'] ?? '', 'weo_recv_'.$order_id)) {
                $this->add_notice(__('Ungültiger Sicherheits-Token','weo'),'error');
              } elseif ($user_id !== $buyer_id) {
                $this->add_notice(__('Keine Berechtigung','weo'),'error');
              } else {
                $order->update_meta_data('_weo_received', time());
                $order->save();
                do_action('weo_order_received', $order_id);
                $this->add_notice(__('Empfang bestätigt','weo'),'success');
              }
            } else {
              if (!wp_verify_nonce($_POST['weo_nonce'] ?? '', 'weo_psbt_'.$order_id)) {
                $this->add_notice(__('Ungültiger Sicherheits-Token','weo'),'error');
              } elseif ($act === 'build_psbt_refund') {
                if (!current_user_can('manage_options')) {
                  $this->add_notice(__('Keine Berechtigung','weo'),'error');
                } else {
                  $res = WEO_Psbt::build_refund_psbt($order_id);
                  if (is_array($res)) {
                    $psbt_notice = '<div class="sk-alert sk-alert-success"><p><strong>'.esc_html__('PSBT (Base64)','weo').':</strong></p><textarea rows="4" style="width:100%;">'.$res['psbt'].'</textarea>'.$res['details'].'</div>';
                  } else {
                    $this->add_notice($res->get_error_message(),'error');
                  }
                }
              } else {
                if ($vendor_id !== $user_id) {
                  $this->add_notice(__('Keine Berechtigung','weo'),'error');
                } else {
                  if ($act === 'build_psbt_payout') {
                    $res = WEO_Psbt::build_payout_psbt($order_id);
                    if (is_array($res)) {
                      $psbt_notice = '<div class="sk-alert sk-alert-success"><p><strong>'.esc_html__('PSBT (Base64)','weo').':</strong></p><textarea rows="4" style="width:100%;">'.$res['psbt'].'</textarea>'.$res['details'].'</div>';
                    } else {
                      $this->add_notice($res->get_error_message(),'error');
                    }
                  }
                }
              }
            }
          }
        }
      }
    }

    $orders = [
      'seller' => [],
      'buyer'  => [],
    ];

    if ($treuhand_available || $context === 'admin') {
      $vendor_orders = wc_get_orders([
        'limit'         => -1,
        'customer'      => 0,
        'meta_key'      => '_weo_vendor_id',
        'meta_value'    => $user_id,
        'payment_method'=> 'weo_gateway',
        'return'        => 'objects',
      ]);
      $buyer_orders = wc_get_orders([
        'limit'         => -1,
        'customer'      => $user_id,
        'payment_method'=> 'weo_gateway',
        'return'        => 'objects',
      ]);

      $seen = [];

      foreach ($vendor_orders as $order) {
        $order_data = $this->prepare_escrow_order_data($order, 'vendor');
        $orders['seller'][] = $order_data;
        $seen[$order->get_id()] = $order_data;
      }

      foreach ($buyer_orders as $order) {
        if (isset($seen[$order->get_id()])) {
          $buyer_data = $seen[$order->get_id()];
          $buyer_data['role'] = 'buyer';
          $orders['buyer'][] = $buyer_data;
          continue;
        }
        $orders['buyer'][] = $this->prepare_escrow_order_data($order, 'buyer');
      }
    }

    $products = [];
    if ($treuhand_available || $context === 'admin') {
      $product_query = [
        'limit'      => -1,
        'status'     => ['publish'],
        'author'     => $user_id,
        'meta_key'   => '_weo_escrow_product',
        'meta_value' => 'yes',
      ];

      $product_objects = wc_get_products($product_query);
      $payout_address  = weo_get_payout_address($user_id);

      foreach ($product_objects as $product) {
        $products[] = [
          'id'              => $product->get_id(),
          'name'            => $product->get_name(),
          'permalink'       => $product->get_permalink(),
          'edit_url'        => $this->get_product_edit_link($product->get_id()),
          'escrow_enabled'  => get_post_meta($product->get_id(), '_weo_escrow_product', true) === 'yes',
          'price_html'      => $product->get_price_html(),
          'stock_status'    => $product->get_stock_status(),
          'payout_address'  => $payout_address,
        ];
      }
    }

    $tabs = [
      'settings'      => __('Einstellungen', 'weo'),
      'products'      => __('Produkte', 'weo'),
      'orders-seller' => __('Bestellungen (Verkäufer)', 'weo'),
      'orders-buyer'  => __('Bestellungen (Käufer)', 'weo'),
    ];

    $settings = [
      'xpub'            => get_user_meta($user_id,'weo_vendor_xpub',true),
      'payout'          => weo_get_payout_address($user_id),
      'escrow_enabled'  => get_user_meta($user_id,'weo_vendor_escrow_enabled',true),
    ];

    return [
      'context'             => $context,
      'available'           => (bool)$treuhand_available,
      'tabs'                => $tabs,
      'active_tab'          => $active_tab,
      'settings'            => $settings,
      'products'            => $products,
      'orders'              => $orders,
      'psbt_notice'         => $psbt_notice,
    ];
  }

  private function prepare_escrow_order_data($order, $role) {
    $addr = $order->get_meta('_weo_escrow_addr');
    $oid  = weo_sanitize_order_id((string)$order->get_order_number());
    $state = 'unknown';
    $funding = null;

    if ($addr && $oid) {
      $status = weo_api_get('/orders/'.rawurlencode($oid).'/status');
      if (!is_wp_error($status)) {
        $state = $status['state'] ?? 'unknown';
        $funding = $status['funding'] ?? null;
      }
    }

    return [
      'id'         => $order->get_id(),
      'number'     => $order->get_order_number(),
      'addr'       => $addr,
      'state'      => $state,
      'funding'    => $funding,
      'shipped'    => intval($order->get_meta('_weo_shipped')),
      'received'   => intval($order->get_meta('_weo_received')),
      'buyer_id'   => $order->get_user_id(),
      'vendor_id'  => intval($order->get_meta('_weo_vendor_id')),
      'payout_txid'=> $order->get_meta('_weo_payout_txid'),
      'role'       => $role,
    ];
  }

  private function get_product_edit_link($product_id) {
    if (function_exists('sk_edit_product_url')) {
      return sk_edit_product_url($product_id);
    }

    return get_edit_post_link($product_id, '');
  }

  private function render_treuhand_view_markup($data) {
    if (!is_array($data) || empty($data)) return '';

    ob_start();
    if (function_exists('sk_get_template_part')) {
      sk_get_template_part('global/sk-notice');
    }

    $treuhand_data = $data;
    $file = WEO_DIR.'templates/sk-treuhand-tabs.php';
    if (file_exists($file)) {
      include $file;
    }

    return ob_get_clean();
  }

  public function render_treuhand_shortcode() {
    $content = $this->render_treuhand_content();
    if (is_array($content)) {
      return $this->render_treuhand_view_markup($content);
    }

    return $content;
  }

  private function current_user_is_vendor() {
    return current_user_can('vendor') || current_user_can('seller');
  }

  private function vendor_features_enabled() {
    return function_exists('weo_vendor_escrow_allowed') ? weo_vendor_escrow_allowed() : true;
  }

  private function current_user_is_admin() {
    return current_user_can('manage_woocommerce') || current_user_can('manage_options');
  }

  private function admin_features_enabled() {
    return function_exists('weo_admin_treuhand_enabled') ? weo_admin_treuhand_enabled() : false;
  }

  private function treuhand_globally_enabled() {
    return $this->vendor_features_enabled() || $this->admin_features_enabled();
  }

  private function current_user_treuhand_context() {
    if (!is_user_logged_in()) return '';

    if ($this->admin_features_enabled() && $this->current_user_is_admin()) {
      return 'admin';
    }

    if ($this->vendor_features_enabled() && $this->current_user_is_vendor()) {
      return 'vendor';
    }

    return '';
  }

  private function user_has_treuhand_access($user_id) {
    $user_id = intval($user_id);
    if ($user_id <= 0) return false;

    if ($this->vendor_features_enabled()) {
      if (user_can($user_id, 'vendor') || user_can($user_id, 'seller')) {
        return true;
      }
    }

    if ($this->admin_features_enabled() && user_can($user_id, 'manage_woocommerce')) {
      return true;
    }

    return false;
  }

  public function product_field($post, $post_id) {
    $context = $this->current_user_treuhand_context();
    if (!$context) return;
    $val = get_post_meta($post_id,'_weo_escrow_product',true);
    ?>
    <div class="sk-form-group">
      <label for="_weo_escrow_product">
        <input type="checkbox" name="_weo_escrow_product" id="_weo_escrow_product" value="yes" <?php checked($val,'yes'); ?>>
        <?php esc_html_e('Escrow Service für dieses Produkt anbieten','weo'); ?>
      </label>
    </div>
    <?php
  }

  public function save_product_meta($post_id) {
    $context = $this->current_user_treuhand_context();
    if (!$context) {
      delete_post_meta($post_id,'_weo_escrow_product');
      return;
    }

    $enabled = isset($_POST['_weo_escrow_product']) ? 'yes' : '';
    if ($enabled) update_post_meta($post_id,'_weo_escrow_product','yes');
    else delete_post_meta($post_id,'_weo_escrow_product');
  }

  public function is_purchasable($purchasable, $product) {
    if (!$purchasable) return $purchasable;

    $product_id = $product->get_id();
    $product_on = get_post_meta($product_id,'_weo_escrow_product',true) === 'yes';

    if (!$product_on) {
      if ($product->is_type('product_pack')) {
        return $purchasable;
      }

      $advertisement_product_id = (int)get_option('sk_advertisement_product_id');
      if ($advertisement_product_id && $product_id === $advertisement_product_id) {
        return $purchasable;
      }

      return false;
    }

    $vendor_id = get_post_field('post_author',$product_id);
    if (!$vendor_id) return false;

    if (!$this->user_has_treuhand_access($vendor_id)) {
      return false;
    }

    $vendor_on = get_user_meta($vendor_id,'weo_vendor_escrow_enabled',true);
    if (!$vendor_on) return false;

    return $purchasable;
  }

  public function maybe_hide_add_to_cart($html, $product, $args) {
    return $product->is_purchasable() ? $html : '';
  }

  private function add_notice($msg, $type) {
    if (function_exists('sk_add_notice')) {
      sk_add_notice($msg, $type);
    } else {
      wc_add_notice($msg, $type);
    }
  }

  /** Fallback – trag hier eine Vendor-Payout-Adresse ein, falls nicht separat gepflegt */
  private function fallback_vendor_payout_address($order_id) {
    $order = wc_get_order($order_id);
    if ($order) {
      $vendor_id = $order->get_meta('_weo_vendor_id');
      if (!$vendor_id) {
        foreach ($order->get_items('line_item') as $item) {
          $pid = $item->get_product_id();
          $vendor_id = get_post_field('post_author',$pid);
          if ($vendor_id) break;
        }
        if ($vendor_id) { $order->update_meta_data('_weo_vendor_id',$vendor_id); $order->save(); }
      }
      if ($vendor_id) {
        $payout = weo_get_payout_address($vendor_id);
        if ($payout) return $payout;
      }
    }
    $fallback = get_option('weo_vendor_payout_fallback','');
    if ($fallback) return $fallback;
    wc_add_notice(__('Keine Fallback-Payout-Adresse konfiguriert.','weo'),'error');
    throw new Exception('Fallback vendor payout address missing');
  }
}