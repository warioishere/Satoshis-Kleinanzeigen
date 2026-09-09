<?php
if (!defined('ABSPATH')) exit;

use SK\Modules\Escrow\Actions;
use SK\Modules\Escrow\Rows;

/**
 * Webhook from the escrow API. Every event only triggers a state pull
 * (Actions::sync), so the row never trusts the event body beyond the order
 * id.
 */
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
    $row  = Rows::get_by_order_id((string) ($data['order_id'] ?? ''));
    if (!$row) return new WP_REST_Response(['ok'=>false],404);

    Actions::sync($row);

    return new WP_REST_Response(['ok'=>true],200);
  }
}
