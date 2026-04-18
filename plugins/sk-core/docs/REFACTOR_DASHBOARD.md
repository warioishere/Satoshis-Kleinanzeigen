# Vendor-Dashboard Refactor — Plan (v3)

Stand: 2026-04-18 · Branch: `dev` · Betroffener Code: `plugins/sk-core/` + `plugins/sats-escrow/`

**Änderungen gegenüber v1/v2:** Settings-Tabs ins gleiche Registry (Parent/Child), Dead-Code-Cleanup (VendorNavMenuChecker + NewDashboard) als separate Phase, `sats-escrow` wird **mitmigriert** (keine BC-Schicht im sk-core). `DashboardModule::config()` wird dynamisch (lazy) evaluiert, damit Runtime-bedingte Konfiguration (wie bei sats-escrow) möglich bleibt.

---

## 1. Ausgangslage

Das Vendor-Dashboard wurde aus Dokan geforkt und stark erweitert. Menu- und Settings-Tab-Registrierung läuft über **zwei separate Hook-Familien**, die von jedem Modul in nahezu identischer Boilerplate aufgerufen werden.

### 1.1 Hook-Inventar

**Menu-Navigation (Haupt-Dashboard):**
| Hook | Fires in | Zweck |
|---|---|---|
| `sk_get_dashboard_nav` (filter) | `includes/functions-dashboard-navigation.php:67` | Sidebar-Nav-Items sammeln |
| `sk_dashboard_nav_active` (filter) | Main.php Template | Aktuelles Menu markieren |
| `sk_query_var_filter` (filter) | `Rewrites.php` | URL-Slug als Query-Var registrieren |
| `sk_load_custom_template` (action) | `Dashboard.php:103` (shortcode) | Template rendern |

**Settings-Tabs (Sub-Navigation):**
| Hook | Fires in | Zweck |
|---|---|---|
| `sk_get_dashboard_settings_nav` (filter) | `Settings.php` (Template) | Tab-Items sammeln |
| `sk_dashboard_settings_heading_title` (filter) | `Settings.php:64` | Tab-Überschrift |
| `sk_dashboard_settings_helper_text` (filter) | `Settings.php:90` | Tab-Hilfetext |
| `sk_render_settings_content` (action) | `Settings.php:~130` | Tab-Content rendern |

### 1.2 Modul-Boilerplate (Beispiel Merkliste)

`includes/Dashboard/Modules/Merkliste.php:12-17`:
```php
public function __construct() {
    add_action( 'wp_enqueue_scripts',      [ $this, 'enqueue' ] );
    add_filter( 'sk_get_dashboard_nav',    [ $this, 'register_nav' ] );
    add_filter( 'sk_dashboard_nav_active', [ $this, 'set_active' ], 10, 3 );
    add_filter( 'sk_query_var_filter',     [ $this, 'add_query_var' ] );
    add_action( 'sk_load_custom_template', [ $this, 'load_template' ] );
    // + AJAX handlers …
}
```

Diese vier Nav-Hooks wiederholen sich **1:1 in 8+ Modulen**:
- `includes/Dashboard/Modules/Merkliste.php:13-16`
- `includes/Dashboard/Modules/Gesuche.php:14-17`
- `includes/Dashboard/Modules/VendorChat.php:21-24`
- `modules/follow-store/includes/class-sk-follow-store-vendor-dashboard.php:13-14`
- `modules/sk-payments/includes/Dashboard/TransactionsPage.php:10-13`
- `modules/sk-feed/includes/Dashboard.php:11-12`
- `modules/sk-auth/module.php:134-137`
- `modules/subscription/module.php:94,96,113`

### 1.3 Quantifizierte Probleme

- **~50 Zeilen Duplikat pro Modul** (`register_nav`, `set_active`, `add_query_var`, `load_template`).
- **8 Module × 4 identische Hook-Registrierungen = 32 Hook-Calls** statt einer deklarativen Liste.
- **`set_active()`-Logik** (3× ähnliche `strpos`/`in_array`/`get_query_var` Checks) wörtlich kopiert in Merkliste:68-75, Gesuche:68-79, VendorChat:148-157.
- **`add_query_var()` Einzeiler** (`$vars[] = 'slug'; return $vars;`) in 8 Dateien.
- **Settings-Tabs** nutzen ein inkonsistentes Hook-System (Heading/Helper/Content separat) plus Hard-Code-Branches in `Settings.php:56-64`.

---

## 2. Zielbild

Eine zentrale `DashboardRegistry` + abstrakte `DashboardModule`-Basisklasse. Jedes Modul registriert sich deklarativ mit **einem** Array; die 4 Hook-Calls und ihre Callback-Methoden werden durch die Basisklasse abstrahiert.

**Menus und Settings-Tabs werden über dieselbe Registry verwaltet** — unterschieden durch ein `parent`-Feld. So entsteht **eine** konsistente API statt zwei separater Hook-Familien.

### 2.1 Neue API — Vor/Nachher

**Vorher (Merkliste.php, 85 Zeilen):**
```php
class Merkliste {
    public function __construct() {
        add_filter( 'sk_get_dashboard_nav',    [ $this, 'register_nav' ] );
        add_filter( 'sk_dashboard_nav_active', [ $this, 'set_active' ], 10, 3 );
        add_filter( 'sk_query_var_filter',     [ $this, 'add_query_var' ] );
        add_action( 'sk_load_custom_template', [ $this, 'load_template' ] );
        add_action( 'wp_ajax_sk_merkliste_remove', [ $this, 'ajax_remove' ] );
        // … weitere AJAX hooks
    }
    public function register_nav( $nav ) { $nav['merkliste'] = [ … ]; return $nav; }
    public function set_active( $active, $request, $active_menu ) { … 10 lines … }
    public function add_query_var( $vars ) { $vars[] = 'merkliste'; return $vars; }
    public function load_template( $query_vars ) {
        if ( isset( $query_vars['merkliste'] ) ) sk_get_template_part( 'dashboard/merkliste/dashboard-merkliste' );
    }
    // + AJAX handlers …
}
```

**Nachher (~15 Zeilen + Ajax):**
```php
class Merkliste extends DashboardModule {
    protected function config(): array {
        return [
            'slug'       => 'merkliste',
            'title'      => __( 'Merkliste', 'sk-core' ),
            'icon'       => '<i class="fas fa-thumbtack"></i>',
            'url_slug'   => 'merkliste',
            'pos'        => 56,
            'permission' => 'sk_view_overview_menu',
            'template'   => 'dashboard/merkliste/dashboard-merkliste',
        ];
    }
    protected function register_extras(): void {
        add_action( 'wp_ajax_sk_merkliste_remove', [ $this, 'ajax_remove' ] );
        // + weitere AJAX handlers
    }
    // NUR noch die echte Business-Logik (AJAX handlers)
}
```

### 2.1.1 Settings-Tab-Beispiel (gleiche API)

```php
class StoreSettingsTabs extends DashboardModule {
    protected function config(): array {
        return [
            'slug'    => 'settings-store',          // logische ID im Registry
            'parent'  => 'settings',                // → wird als Tab unter /settings/ gerendert
            'url_key' => 'store',                   // → matcht ?settings=store
            'title'   => __( 'Store', 'sk-core' ),
            'icon'    => '<i class="fas fa-store"></i>',
            'heading' => __( 'Settings', 'sk-core' ),
            'helper'  => __( 'Configure your store.', 'sk-core' ),
            'template'=> 'settings/store-form',
            'permission' => 'sk_view_store_store_menu',
        ];
    }
}
```

Registry prüft `parent`:
- **Ohne `parent`**: Eintrag landet in Haupt-Nav, Template lädt via `sk_load_custom_template`.
- **Mit `parent`**: Eintrag landet in Sub-Nav des Parents, Heading/Helper/Template laufen über `sk_render_settings_content`.

### 2.2 `DashboardModule` Basisklasse (Skizze)

```php
namespace SK\Core\Dashboard;

abstract class DashboardModule {

    public function __construct() {
        DashboardRegistry::register_module( $this );
        $this->register_extras();
    }

    /**
     * Wird lazy vom Registry evaluiert (erst wenn der Filter fired).
     * Darf `null` oder `[]` zurückgeben, um die Registrierung zu überspringen
     * (z. B. wenn Feature nur für bestimmte User sichtbar ist).
     *
     * @return array|null { slug, title, icon, url_slug, pos, permission, template, parent?, heading?, helper? }
     */
    abstract public function config(): ?array;

    /** Override für zusätzliche Hooks (AJAX, enqueue, etc.) */
    protected function register_extras(): void {}
}
```

**Warum lazy?** Manche Module (z. B. `sats-escrow`) entscheiden zur Render-Zeit anhand des User-Kontexts, ob das Menu erscheint und welche Permission nötig ist. Registry ruft `config()` pro Filter-Durchlauf auf.

### 2.3 `DashboardRegistry`

```php
class DashboardRegistry {
    private static array $menus = [];
    private static array $tabs  = [];

    public static function register( array $config ): void {
        self::$menus[ $config['slug'] ] = $config;
    }
    public static function register_tab( array $config ): void { … }
    public static function get_menus(): array { … }
    public static function get_tab( string $slug ): ?array { … }

    public static function bootstrap(): void {
        add_filter( 'sk_get_dashboard_nav',       [ self::class, 'inject_menus' ] );
        add_filter( 'sk_dashboard_nav_active',    [ self::class, 'inject_active' ], 10, 3 );
        add_filter( 'sk_query_var_filter',        [ self::class, 'inject_query_vars' ] );
        add_action( 'sk_load_custom_template',    [ self::class, 'dispatch_template' ] );
        add_filter( 'sk_get_dashboard_settings_nav', [ self::class, 'inject_tabs' ] );
        add_action( 'sk_render_settings_content', [ self::class, 'dispatch_tab' ] );
    }
}
```

Bootstrap läuft **einmalig** in `ModuleLoader.__construct()` VOR der Modul-Instanziierung.

---

## 3. Migrations-Phasen

### Phase 1 — Foundation (Branch: `refactor/dashboard-registry`)

**Dauer:** 2 h · **Risiko:** Niedrig (nur neuer Code, nichts entfernt)

1. `includes/Dashboard/DashboardRegistry.php` anlegen.
2. `includes/Dashboard/DashboardModule.php` (abstrakte Basisklasse).
3. `DashboardRegistry::bootstrap()` in `ModuleLoader::__construct()` ganz oben aufrufen.
4. Unit-Test: leeres Registry darf bestehende Funktionalität nicht brechen (bisherige Hooks koexistieren).

**Acceptance:** Dashboard lädt identisch, keine neuen Einträge in Sidebar.

---

### Phase 2 — Proof of Concept (1 Modul migrieren)

**Dauer:** 1 h · **Risiko:** Niedrig (einzelnes Modul)

- `Merkliste.php` auf `extends DashboardModule` umstellen.
- Alle 4 Nav-Hook-Registrierungen + zugehörige Methoden entfernen.
- AJAX-Handler in `register_extras()` verschieben.

**Acceptance:**
- URL `/mein-konto/dashboard/merkliste/` lädt Template.
- Sidebar zeigt Merkliste-Eintrag an korrekter Position.
- Menu ist aktiv, wenn URL matcht.
- AJAX-Löschen funktioniert.

---

### Phase 3 — Übrige Dashboard-Module (8 Stück)

**Dauer:** 3,5 h · **Risiko:** Niedrig pro Modul, aber Volumen

Reihenfolge (von trivial zu komplex):
1. `Gesuche.php` (90% identisch zu Merkliste)
2. `modules/sk-feed/includes/Dashboard.php` (einfach)
3. `modules/follow-store/includes/class-sk-follow-store-vendor-dashboard.php`
4. `modules/sk-payments/includes/Dashboard/TransactionsPage.php`
5. `VendorChat.php` (mehr AJAX, aber Pattern identisch)
6. `modules/sk-auth/module.php` (Auth-Seiten)
7. `modules/subscription/module.php` (**Achtung:** mehrere `add_new_page` Calls + eigener Filter `sk_get_dashboard_nav_template_dependency` → prüfen ob Config oder Extras)
8. `plugins/sats-escrow/includes/class-escrow-sk.php` — **extern, aber mitmigrieren**:
   - `WEO_SK` extends `\SK\Core\Dashboard\DashboardModule`.
   - `config()` dynamisch: Rückgabe `null` wenn `current_user_treuhand_context()` falsy.
   - Permission-Berechnung (admin vs. vendor) in `config()` behalten.
   - `register_query_var`-Gate (`treuhand_globally_enabled()`) → ebenfalls in `config()` prüfen.
   - Alte 4 Hooks (`sk_get_dashboard_nav`, `sk_query_var_filter`, `sk_dashboard_nav_active`, `sk_load_custom_template`) entfernen.
   - Andere Hooks (`sk_product_edit_after_pricing`, `sk_process_product_meta`, `woocommerce_*`) bleiben unberührt (nicht Teil des Dashboard-Patterns).

**Acceptance je Modul:** alle 4 URLs laden korrekt, Sidebar-Eintrag erscheint, aktiv-Status stimmt. Bei sats-escrow: Treuhand-Menu erscheint nur für relevante User (admin + Vendor mit aktiven Treuhand-Produkten).

---

### Phase 4 — Settings-Tabs in dieselbe Registry migrieren

**Dauer:** 3 h · **Risiko:** Mittel (berührt `StoreSettings.php` + `Settings.php` Template-Controller)

1. `DashboardRegistry` um `parent`-Feld-Handling erweitern (keine separate `register_tab()` API — gleiche Methode).
2. `StoreSettings.php:86-120` (`load_settings_menu`): statt via Filter die Tabs deklarativ als `DashboardModule`-Subklassen anlegen (z. B. `SettingsStoreTab`, `SettingsPaymentTab`, `SettingsSocialTab`, `SettingsShippingTab`, `SettingsSeoTab`) — jede mit `'parent' => 'settings'`.
3. Hard-coded Branches in `Settings.php:56-64` durch Registry-Lookup ersetzen: `DashboardRegistry::resolve_tab( $wp->query_vars['settings'] )`.
4. `DigitalProduct.php:32` (`remove_shipping_settings_menu` Priority 99): via `DashboardRegistry::unregister('settings-shipping')` ersetzen.
5. Alte Settings-Hooks (`sk_get_dashboard_settings_nav`, `sk_dashboard_settings_heading_title`, `sk_dashboard_settings_helper_text`, `sk_render_settings_content`) werden entfernt, nachdem alle Tabs im Registry sind.

**Acceptance:**
- Alle Settings-Tabs (store, payment, social, shipping, seo) funktionieren wie bisher.
- `DigitalProduct`-Store versteckt shipping-Tab korrekt.
- `/mein-konto/dashboard/settings/shipping/` lädt richtiges Template.
- Settings-Tabs-Sortierung korrekt.

---

### Phase 5 — Dead-Code-Cleanup (optional)

**Dauer:** 1 h · **Risiko:** Niedrig — aber **erst nach gründlichem Verify**

React-UI wurde entfernt, aber folgende Relikte hängen noch im Code:

- `includes/VendorNavMenuChecker.php` — `convert_to_react_menu()` (Priority 999) wird durch `NewDashboard.php:16` (`add_filter('sk_is_dashboard_nav_dependency_resolved', '__return_false', PHP_INT_MAX)`) bereits neutralisiert.
- `includes/Dashboard/Templates/NewDashboard.php` — Klasse heißt "New" aber rendert PHP-Template `dashboard/dashboard`. In `Manager.php:17` instanziiert. URL `/mein-konto/dashboard/new/`.
- `assets/js/sk-dashboard-nav.js` — AJAX-Live-Nav-Script, vermutlich für React-Nav gedacht.

**Verify vorab:**
- Ist `/mein-konto/dashboard/new/` irgendwo verlinkt? (Grep in Templates + JS.)
- Wird `sk-dashboard-nav.js` noch gebraucht für das aktuelle Nav-Verhalten?

**Wenn nicht gebraucht:** Klassen + Manager-Eintrag entfernen, JS-File löschen, React-Filter aus VendorNavMenuChecker entfernen (oder ganze Datei entsorgen).

**Wenn unklar:** Phase überspringen, als Follow-Up-Ticket.

---

### Phase 6 — Cleanup (Old Hooks komplett entfernen)

**Dauer:** 1 h · **Risiko:** Niedrig (alle Konsumenten sind zu diesem Zeitpunkt migriert)

- `apply_filters('sk_get_dashboard_nav', ...)` in `functions-dashboard-navigation.php:67` durch direkten `DashboardRegistry::get_menus()`-Aufruf ersetzen.
- `apply_filters('sk_dashboard_nav_active', ...)` und `apply_filters('sk_query_var_filter', ...)` entsprechend ersetzen.
- `do_action('sk_load_custom_template', ...)` in `Dashboard.php:103` durch Registry-Dispatch.
- Settings-Hook-Familie (`sk_get_dashboard_settings_nav`, `sk_dashboard_settings_heading_title`, `sk_dashboard_settings_helper_text`, `sk_render_settings_content`) komplett löschen.
- Redundante Methoden (`set_active`-Copies) aus alten Dateien rausoperieren.
- `sats-escrow/` Review: bleiben irgendwo noch Reste der alten 4 Hook-Callbacks? Entfernen.
- CHANGELOG aktualisieren.

---

## 4. Test-Strategie

**Manueller Test-Walkthrough auf Staging** (pro Phase):

1. **Auth:** Login LNURL (Breez/WoS/Phoenix) + Nostr-Extension + E-Mail.
2. **Navigation:** Jeder Sidebar-Eintrag → URL lädt → Template stimmt → Eintrag ist aktiv markiert.
3. **Deep-Links:** `/mein-konto/dashboard/SLUG/` direkt aufrufen (nicht nur via Klick).
4. **Settings-Tabs:** store, payment (Lightning/Bank/Paypal), social, shipping, seo.
5. **DigitalProduct:** Store mit digitalem Produkt → shipping-Tab muss unsichtbar sein.
6. **AJAX:** Merkliste Löschen, Gesuch erstellen, VendorChat-Nachricht, Feed-Post, Like, Report.
7. **sats-escrow:** Treuhand-Menu lädt `/mein-konto/dashboard/treuhand/` korrekt, erscheint nur für admins und Vendoren mit Treuhand-Kontext, admin-vs-vendor Permission stimmt.
8. **Nostr-Signing:** Feed-Post erstellen → Kind-1 Event publiziert? Produkt erstellen → Kind-30402?

**Regressions-Kandidaten:**
- Sortierung der Sidebar-Einträge (`pos` Keys).
- Gäste-Links bei ausgeloggten Usern.
- Permission-Checks (`sk_view_…_menu`).
- Nostr-Identity sollte durch den Refactor nicht berührt werden (separat).

---

## 5. Rollback

Jede Phase ist ein eigener Commit. Rückkehr via `git revert` pro Phase möglich. Phase 1 ist reiner Additiv (null Risiko). Phase 4 hat den höchsten Blast-Radius (`StoreSettings.php`).

---

## 6. Beantwortete Fragen / Entscheidungen

1. **Third-Party-Kompatibilität** → Keine BC-Schicht. `sats-escrow` wird in Phase 3 mitmigriert (Schritt 8). Die alten Hooks (`sk_get_dashboard_nav` etc.) werden in Phase 6 komplett entfernt, nachdem alle Konsumenten umgestellt sind.

2. **Settings-Tabs** → In **dieselbe Registry** über `parent`-Feld. Eine API für alles.

3. **React-Dashboard** → Ist bereits entfernt. Relikte (`VendorNavMenuChecker.php`, `NewDashboard.php`, `sk-dashboard-nav.js`) werden in Phase 5 optional entsorgt.

4. **Testing-Priorität** → Breit testen, alles muss wie vorher funktionieren. Keine spezifische Workflow-Priorität.

5. **Nostr-Identity** → Ist bereits implementiert (`modules/sk-auth/includes/NostrIdentity.php`). Refactor berührt die Nostr-Funktionalität nicht.

---

## 7. Aufwand & Empfehlung

| Phase | Was | Dauer | Risiko |
|---|---|---|---|
| 1 | Foundation (Registry + Base-Class) | 2 h | Niedrig (additiv) |
| 2 | PoC Merkliste | 1 h | Niedrig |
| 3 | Restliche 7 sk-core Module + sats-escrow | 3,5 h | Niedrig (repetitiv) |
| 4 | Settings-Tabs in Registry | 3 h | Mittel |
| 5 | Dead-Code-Cleanup (React-Relikte) | 1 h | Niedrig (optional) |
| 6 | Final-Cleanup (Old Hooks komplett raus) | 1 h | Niedrig |
| **Σ** | | **~11,5 h** | |

**Empfehlung:** Phase 1 + 2 (~3 h) als ersten Sprint → auf Staging beobachten → bei Erfolg Phase 3 in einem Zug → danach Phase 4 als separate Arbeitseinheit.

Phase 5 (Dead-Code) erst, wenn Phase 1-4 stabil laufen und React wirklich bestätigt entfernt ist.

---

## 8. Nicht-Ziel

- Kein Umbau des React-Dashboards (`NewDashboard.php`) in dieser Runde.
- Keine Änderung an Template-Dateien selbst (`dashboard-merkliste.php` etc.) — die bleiben 1:1.
- Kein Weg von Hooks komplett — die WordPress-API bleibt Hook-basiert, Registry ist ein Layer darüber.
