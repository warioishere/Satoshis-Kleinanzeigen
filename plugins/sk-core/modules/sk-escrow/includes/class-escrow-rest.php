<?php
if (!defined('ABSPATH')) exit;

class WEO_REST {
  public function __construct() {
    add_action('rest_api_init', [$this,'routes']);
  }

  public function routes() {
    register_rest_route('weo/v1', '/webhook', [
      'methods'  => 'POST',
      'callback' => [$this,'handle'],
      'permission_callback' => [$this,'verify'],
    ]);
  }

  public function verify($req) {
    $secret = weo_get_option('hmac_secret','');
    $ts  = intval($req->get_header('x-weo-ts'));
    $sig = $req->get_header('x-weo-sign');
    if (!$secret || !$ts || !$sig) return new WP_Error('forbidden','missing signature',['status'=>401]);
    if (abs(time()-$ts) > 300) return new WP_Error('forbidden','stale timestamp',['status'=>401]);
    $body = $req->get_body();
    $calc = hash_hmac('sha256', $ts.$body, $secret);
    if (!hash_equals($calc, $sig)) return new WP_Error('forbidden','bad signature',['status'=>401]);
    return true;
  }

  public function handle($req) {
    $data = $req->get_json_params();
    $order_id_str = $data['order_id'] ?? '';
    if (!$order_id_str) return new WP_REST_Response(['ok'=>false],400);

    $order = self::locate_order($order_id_str);
    if (!$order) return new WP_REST_Response(['ok'=>false],404);

    /*
     * Only ever touch escrow orders. The webhook can move an order to
     * completed or refunded, so a wrong or spoofed identifier must not be
     * able to reach a regular WooCommerce order.
     */
    if ($order->get_payment_method() !== 'weo_gateway') {
      return new WP_REST_Response(['ok'=>false],404);
    }

    /*
     * A settled order never moves back. The signature window is five
     * minutes, so a captured "escrow_funded" could otherwise be replayed
     * to reopen a completed order.
     */
    $settled = $order->has_status(['completed', 'refunded']);

    $event = $data['event'] ?? '';
    switch ($event) {
      case 'escrow_funded':
        if (!$settled) $order->update_status('processing','Escrow funded');
        break;
      case 'settled':
        if (!$settled) $order->update_status('completed','Escrow ausgezahlt');
        break;
      case 'refunded':
        if (!$settled) $order->update_status('refunded','Escrow refund');
        break;
      case 'dispute_opened':
        if (!$settled) $order->update_status('on-hold','Dispute geöffnet');
        break;
    }
    return new WP_REST_Response(['ok'=>true],200);
  }

  /**
   * Find the order a webhook refers to.
   *
   * The escrow API is handed get_order_number(), which equals the order id
   * only as long as nothing filters woocommerce_order_number. The previous
   * lookup passed that string straight to wc_get_order(), so a prefixed or
   * sequential number would have been cast to an id and could have hit a
   * different order. A numeric id is therefore only accepted when the order
   * it returns actually carries that number.
   *
   * @return WC_Order|null
   */
  private static function locate_order($identifier) {
    $identifier = trim((string) $identifier);

    if ('' === $identifier) return null;

    // An order key (wc_order_…) is unambiguous.
    $by_key = wc_get_order_id_by_order_key($identifier);
    if ($by_key) {
      $order = wc_get_order($by_key);
      if ($order) return $order;
    }

    if (ctype_digit($identifier)) {
      $order = wc_get_order((int) $identifier);
      if ($order && (string) $order->get_order_number() === $identifier) {
        return $order;
      }
    }

    // No verified match. Answering 404 is the safe outcome — acting on a
    // guessed order could complete or refund the wrong one.
    return null;
  }
}
