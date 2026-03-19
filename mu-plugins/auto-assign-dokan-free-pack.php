<?php
/**
 * Plugin Name: Auto-Assign Dokan Free Pack + Ensure Free When Others Expire (no cron)
 * Description: Weist neuen Vendoren beim Onboarding automatisch das Free-Pack (0€-Order) zu. Reaktiviert das Free-Pack automatisch, wenn kein anderes aktives Paket mehr vorhanden ist (z. B. wenn ein gekauftes Abo abläuft). Auslösung nur bei Onboarding, Login und (throttled) Page-Load – kein Cron, keine periodischen Renewals.
 * Author: wario
 * Version: 1.2.2
 */

if (!defined('ABSPATH')) exit;

// ===============================
// NEU: E-Mail-Unterdrückung
// ===============================
// Diese Filter blocken WooCommerce-Transaktionsmails NUR dann,
// wenn die Order das Meta _freepack_silent = 1 trägt (unsere Auto-Free-Orders).
add_filter('woocommerce_email_enabled_new_order',                 'freepack_silent_emails', 10, 2);
add_filter('woocommerce_email_enabled_customer_processing_order', 'freepack_silent_emails', 10, 2);
add_filter('woocommerce_email_enabled_customer_completed_order',  'freepack_silent_emails', 10, 2);
add_filter('woocommerce_email_enabled_customer_invoice',          'freepack_silent_emails', 10, 2);

// Optional zusätzlich (falls je relevant):
// add_filter('woocommerce_email_enabled_customer_on_hold_order',    'freepack_silent_emails', 10, 2);
// add_filter('woocommerce_email_enabled_customer_refunded_order',   'freepack_silent_emails', 10, 2);

/**
 * Gibt false zurück (Mail aus), wenn die Order unser Silent-Meta trägt.
 *
 * @param bool                 $enabled
 * @param WC_Order|false|null  $order
 * @return bool
 */
function freepack_silent_emails($enabled, $order) {
    if (!$enabled) return false;
    if (empty($order) || !is_object($order)) return $enabled;

    try {
        // HPOS/klassisch kompatibel
        if ((int) $order->get_meta('_freepack_silent') === 1) {
            return false;
        }
    } catch (\Throwable $e) {
        return $enabled; // auf Nummer sicher: nichts global deaktivieren
    }
    return $enabled;
}

final class FreePack_AutoAssign_EnsureFree {

    // === KONFIG =================================================================
    /** HIER deine Free-Pack Produkt-ID */
    const FREE_PACK_PRODUCT_ID = 1206;

    /** Throttle für Page-Load-Prüfung (pro User) in Sekunden */
    const PAGECHECK_COOLDOWN = 900; // 15 Minuten

    /** Leichtes Lock, um Doppelorders zu vermeiden */
    const LOCK_META = '_freepack_assign_lock_until';
    const LOCK_TTL  = 300; // 5 Minuten

    /** Meta für Throttle */
    const META_LAST_PAGECHECK = '_freepack_last_pagecheck';

    public static function init(): void {
        // Onboarding
        add_action('user_register',            [__CLASS__, 'on_user_register'], 20);
        add_action('sk_new_seller_created', [__CLASS__, 'on_new_seller'], 10);

        // Bei Login prüfen (leichtgewichtig)
        add_action('wp_login', [__CLASS__, 'on_login'], 10, 2);

        // Bei Page-Load im Frontend + Backend-Dashboard prüfen (gedrosselt)
        add_action('wp',         [__CLASS__, 'on_pageload_front'], 1);
        add_action('admin_init', [__CLASS__, 'on_pageload_admin']);
    }

    // === HOOKS =================================================================
    public static function on_user_register(int $user_id): void {
        self::maybe_assign_onboarding($user_id);
    }

    public static function on_new_seller($user_id): void {
        self::maybe_assign_onboarding((int) $user_id);
    }

    public static function on_login($user_login, $user): void {
        $uid = (int) $user->ID;
        if (!$uid) return;
        if (!self::is_vendor($uid)) return;
        // Direkt beim Login sicherstellen, dass bei fehlendem/abgelaufenem Paket das Free aktiv ist
        self::ensure_free_if_no_active_other($uid);
    }

    public static function on_pageload_front(): void {
        if (is_admin()) return; // nur Frontend
        if (!is_user_logged_in()) return;

        $uid = get_current_user_id();
        if (!$uid) return;
        if (!self::is_vendor($uid)) return;

        // Nur auf Dokan-Dashboard-Seiten arbeiten und außerdem throttlen
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/dashboard') === false) return;

        $last = (int) get_user_meta($uid, self::META_LAST_PAGECHECK, true);
        if ($last && (time() - $last) < self::PAGECHECK_COOLDOWN) return;
        update_user_meta($uid, self::META_LAST_PAGECHECK, time());

        self::ensure_free_if_no_active_other($uid);
    }

    public static function on_pageload_admin(): void {
        if (!is_user_logged_in()) return;

        $uid = get_current_user_id();
        if (!$uid) return;
        if (!self::is_vendor($uid)) return;

        // Throttle auch im Backend (z. B. Vendor-Dashboard)
        $last = (int) get_user_meta($uid, self::META_LAST_PAGECHECK, true);
        if ($last && (time() - $last) < self::PAGECHECK_COOLDOWN) return;
        update_user_meta($uid, self::META_LAST_PAGECHECK, time());

        self::ensure_free_if_no_active_other($uid);
    }

    // === CORE LOGIK ============================================================
    /**
     * Onboarding: nur wenn noch gar kein Paket gesetzt ist, das Free-Pack zuweisen.
     */
    protected static function maybe_assign_onboarding(int $user_id): void {
        if (!$user_id) return;
        if (!self::is_vendor($user_id)) return;

        $existing_pack_id = (int) get_user_meta($user_id, 'product_package_id', true);
        if ($existing_pack_id > 0) return; // schon ein Paket vorhanden

        self::assign_free_pack_via_order($user_id);
    }

    /**
     * Hauptfunktion: Wenn KEIN anderes aktives Paket läuft, stelle sicher,
     * dass das Free-Pack aktiv ist (einmalige 0€-Order).
     */
    protected static function ensure_free_if_no_active_other(int $user_id): void {
        $status = self::get_pack_status($user_id);

        // 1) Gar kein Paket -> Free zuweisen
        if ($status['pack_id'] === 0) {
            self::assign_free_pack_via_order($user_id);
            return;
        }

        // 2) Aktives Paket?
        if ($status['has_active']) {
            return; // nichts tun
        }

        // 3) Paket gesetzt, aber NICHT aktiv -> Free reaktivieren
        self::assign_free_pack_via_order($user_id);
    }

    /**
     * Liest den aktuellen Paketstatus eines Users.
     */
    protected static function get_pack_status(int $user_id): array {
        $pack_id = (int) get_user_meta($user_id, 'product_package_id', true);
        $raw     = get_user_meta($user_id, 'product_pack_enddate', true);

        $end_ts = null;
        if ($raw === 'unlimited') {
            $end_ts = PHP_INT_MAX;
        } elseif (is_numeric($raw)) {
            $end_ts = (int) $raw;
            if ($end_ts > 20000000000) { // Millisekunden -> Sekunden
                $end_ts = (int) floor($end_ts / 1000);
            }
        } elseif (!empty($raw)) {
            $parsed = strtotime($raw);
            if ($parsed !== false) $end_ts = $parsed;
        }

        $has_active = ($pack_id > 0 && $end_ts && $end_ts > time());

        return [
            'pack_id'    => $pack_id,
            'end_ts'     => $end_ts,
            'has_active' => $has_active,
        ];
    }

    // === HELPERS ===============================================================
    protected static function is_vendor(int $user_id): bool {
        if (function_exists('sk_is_user_seller')) return sk_is_user_seller($user_id);
        $u = get_user_by('id', $user_id);
        return $u ? user_can($u, 'skdar') : false;
    }

    protected static function acquire_lock(int $user_id): bool {
        $until = (int) get_user_meta($user_id, self::LOCK_META, true);
        if ($until && $until > time()) return false;
        update_user_meta($user_id, self::LOCK_META, time() + self::LOCK_TTL);
        return true;
    }

    protected static function release_lock(int $user_id): void {
        delete_user_meta($user_id, self::LOCK_META);
    }

    /**
     * Legt eine 0€-Order für das Free-Pack an und wendet das Paket auf den User an.
     * Schutz gegen Doppelbestellungen via Lock + Vorabprüfung.
     */
    protected static function assign_free_pack_via_order(int $user_id): bool {
        if (!class_exists('WC_Order') || !function_exists('wc_create_order')) return false;
        if (!self::acquire_lock($user_id)) return false;

        try {
            // Falls in der Zwischenzeit ein aktives Paket gesetzt wurde -> abbrechen
            $status = self::get_pack_status($user_id);
            if ($status['has_active']) { self::release_lock($user_id); return false; }

            $product = wc_get_product(self::FREE_PACK_PRODUCT_ID);
            if (!$product || !$product->exists()) {
                error_log('[FreePack Ensure] Produkt nicht gefunden: ' . self::FREE_PACK_PRODUCT_ID);
                self::release_lock($user_id);
                return false;
            }

            $order = wc_create_order(['customer_id' => $user_id]);
            $order->add_product($product, 1);

            // Hart auf 0 setzen (failsafe)
            foreach ($order->get_items() as $item) {
                if ((int) $item->get_product_id() === (int) self::FREE_PACK_PRODUCT_ID) {
                    $item->set_subtotal(0);
                    $item->set_total(0);
                    $item->save();
                }
            }

            $order->calculate_totals();

            // ===============================
            // NEU: Silent-Flag setzen (VOR payment_complete), dann speichern
            // ===============================
            $order->update_meta_data('_freepack_silent', 1);
            $order->save();

            // Jetzt normal abschließen – dadurch würden Mails entstehen,
            // aber unsere Filter oben blocken sie bei _freepack_silent=1.
            $order->payment_complete();

            $order->add_order_note('Ensure-Free: Free Dokan pack (re)assigned.');

            self::apply_pack_to_user($user_id, (int) self::FREE_PACK_PRODUCT_ID, (int) $order->get_id());

            self::release_lock($user_id);
            return true;

        } catch (\Exception $e) {
            self::release_lock($user_id);
            error_log('[FreePack Ensure] Fehler: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Setzt die relevanten Dokan-Metas. Wenn _pack_validity leer/0 => unlimited.
     */
    protected static function apply_pack_to_user(int $user_id, int $product_id, int $order_id): bool {
        $valid_days = (int) get_post_meta($product_id, '_pack_validity', true);
        $start_ts   = time();
        $end_ts     = $valid_days > 0 ? $start_ts + ($valid_days * DAY_IN_SECONDS) : PHP_INT_MAX;

        update_user_meta($user_id, 'product_id', $product_id);
        update_user_meta($user_id, 'product_package_id', $product_id);
        update_user_meta($user_id, 'product_order_id', $order_id);
        update_user_meta($user_id, 'product_no_with_pack', 0);
        update_user_meta($user_id, 'product_pack_startdate', gmdate('Y-m-d H:i:s', $start_ts));
        update_user_meta(
            $user_id,
            'product_pack_enddate',
            $end_ts === PHP_INT_MAX ? 'unlimited' : gmdate('Y-m-d H:i:s', $end_ts)
        );
        update_user_meta($user_id, 'can_post_product', 1);
        update_user_meta($user_id, '_customer_recurring_subscription', false);

        // Verkauf freischalten, falls leer
        $selling = get_user_meta($user_id, 'sk_enable_selling', true);
        if (!$selling) {
            update_user_meta($user_id, 'sk_enable_selling', 'yes');
        }

        do_action('sk_vendor_subscription_applied_programmatically', $user_id, $product_id, $order_id);
        return true;
    }
}

FreePack_AutoAssign_EnsureFree::init();
