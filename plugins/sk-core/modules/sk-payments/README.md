# SK Payments — Non-Custodial Bitcoin Payments für Satoshis Kleinanzeigen

## Übersicht

SK Payments ist ein natives sk-core Modul das Vendoren ermöglicht, Bitcoin-Zahlungen direkt zu empfangen — sowohl über Lightning als auch Onchain. Kein Custodian, keine Zwischenhändler. Käufer zahlen direkt an die Wallet des Verkäufers.

## Features

### Lightning Payments
- **NWC (Nostr Wallet Connect / NIP-47)** — Automatische Invoice-Erstellung und Zahlungsverifizierung über Nostr Relays. Unterstützt Alby Hub, LNbits, etc.
- **LNDHub** — REST API Client kompatibel mit BlueWallet, LNbits, Alby, BTCPay Server. Authentifizierung via Invoice-URL.
- **Lightning-Adresse / LNURL** — Fallback wenn weder NWC noch LNDHub konfiguriert. Unterstützt LUD-21 (automatische Verifizierung) und manuelle Bestätigung.

### Onchain Payments
- **Statische BTC-Adresse** — Einfachste Variante. Unterstützt bc1q (SegWit), bc1p (Taproot), P2SH (3...), Legacy (1...).
- **xpub/ypub/zpub (BIP32 HD Wallet)** — Für jeden Kauf wird automatisch eine neue Empfangsadresse abgeleitet. Maximale Privacy. Derivation-Pfad: m/0/{index}.
- **Blockchain-Verifizierung** — Automatische Zahlungsprüfung via eigenem Fulcrum Electrum Server (Primary) mit mempool.space REST API als Fallback.

### Produktseite
- **Sofortkauf-Button** — Erscheint auf jeder Produktseite wenn der Vendor Lightning und/oder Onchain konfiguriert hat.
- **Payment-Method-Auswahl** — Hat der Vendor beides konfiguriert, wählt der Käufer: Lightning (sofort, niedrige Gebühren) oder Onchain (Bitcoin-Adresse).
- **Onchain-Modal** — Zeigt BTC-Adresse, QR-Code (BIP21), Copy-Button, "In Wallet öffnen" Link. Auto-Polling auf Blockchain-Bestätigung.

### Chat-Integration (optional)
- Wenn VendorChat aktiv: Lightning-Kaufanfragen und Invoices werden als Chat-Nachrichten gerendert.
- Onchain-Zahlungen werden ebenfalls im Chat angezeigt mit Adresse, QR-Code und mempool.space Link.
- Wenn VendorChat inaktiv: Sofortkauf-Button funktioniert trotzdem direkt auf der Produktseite.

### Admin Dashboard
- **SK Payments Übersicht** — Systemstatus, Statistiken, alle Transaktionen mit Filter.
- **Dispute Management** — Admin kann gemeldete Probleme prüfen, Reputation blockieren oder Dispute ablehnen.
- **Settings** — 3 Switcher im PHP Dashboard unter "SK Payments": Payments aktivieren, Reputation aktivieren, Chat-Integration.

### Vendor Dashboard
- **Käufe/Verkäufe** — Transaktionsliste mit Status, Sats-Betrag, Fiat-Gegenwert, Lightning/Onchain Badge.
- Onchain-TXs mit direktem mempool.space TX-Link.
- Vendor kann Zahlungen manuell bestätigen, Käufer kann Erhalt bestätigen oder Problem melden.

## Store Settings (Vendor)

Die Felder erscheinen direkt im Store-Profil (templates/settings/store-form.php):

1. **Onchain-Zahlungen empfangen**
   - BTC-Adresse (statisch) — mit Format-Validierung und Test-Button
   - xpub/ypub/zpub — verschlüsselt gespeichert, Test-Button zeigt erste abgeleitete Adresse

2. **Lightning-Zahlungen empfangen**
   - NWC Connection-String — verschlüsselt gespeichert, Verbindungstest via Nostr Relay
   - LNDHub URL — verschlüsselt gespeichert, Authentifizierungstest gegen Server
   - Lightning-Adresse — LNURL-Resolve Test mit min/max Sats Anzeige

Alle Felder haben einen "Verbindung testen" Button der:
- Client-side das Protokoll-Format prüft (Regex)
- Server-side die echte Verbindung testet (AJAX)

## Technische Architektur

```
modules/sk-payments/
├── module.php                          # Entry Point, Namespace SK\Modules\Payments
├── includes/
│   ├── Activator.php                   # DB-Tabellen erstellen, Cron
│   ├── StoreSettings.php               # Save-Handler, AJAX-Tests, Helper-Methoden
│   ├── ProductPage.php                 # Sofortkauf-Button, Onchain-Flow
│   ├── Admin/
│   │   ├── AdminPage.php               # WP Admin Submenu + Dispute Resolution
│   │   └── AdminSettings.php           # PHP Dashboard Settings (Switcher)
│   ├── Chat/
│   │   └── ChatIntegration.php         # VendorChat Lightning-Nachrichten + AJAX
│   ├── Dashboard/
│   │   └── TransactionsPage.php        # Vendor Dashboard Käufe/Verkäufe
│   ├── NWC/
│   │   └── Client.php                  # Nostr Wallet Connect (NIP-47)
│   ├── LNDHub/
│   │   └── Client.php                  # LNDHub REST API
│   ├── LNURL/
│   │   ├── Resolver.php                # Lightning-Adresse / LNURL auflösen
│   │   ├── Bolt11Parser.php            # Payment-Hash aus bolt11 extrahieren
│   │   └── ExchangeRate.php            # BTC/EUR/CHF Kurse (mempool.space + Yadio)
│   ├── Onchain/
│   │   ├── XpubDerivation.php          # BIP32 Public Key Derivation (secp256k1)
│   │   └── BlockchainChecker.php       # Fulcrum Electrum Protocol + mempool.space
│   └── REST/
│       └── LightningController.php     # REST API Endpoints (/sk/v1/lightning/*)
├── templates/
│   ├── admin-overview.php              # Admin Übersicht
│   └── dashboard-transactions.php      # Vendor Transaktionsliste
└── assets/
    ├── css/sk-lightning.css             # Styles
    └── js/
        ├── sk-lightning-pay.js          # Chat Message Rendering + Polling
        └── sk-payments-product.js       # Sofortkauf Button + Onchain Modal
```

## Datenbank

Nutzt die bestehende Tabelle `wp_sk_lightning_payments`:
- `context` Feld unterscheidet: `chat`, `direct`, `onchain`
- `verify_url` speichert bei Onchain die BTC-Empfangsadresse
- `preimage` speichert bei Onchain die txid (für mempool.space Verlinkung)

## REST API Endpoints

| Endpoint | Methode | Beschreibung |
|----------|---------|-------------|
| `/sk/v1/lightning/invoice` | POST | Lightning Invoice erstellen |
| `/sk/v1/lightning/confirm` | POST | Zahlung manuell bestätigen (Vendor) |
| `/sk/v1/lightning/check-payment` | GET | Lightning-Zahlung prüfen (NWC/LNDHub/LUD-21) |
| `/sk/v1/lightning/check-onchain` | GET | Onchain-Zahlung prüfen (Fulcrum/mempool) |
| `/sk/v1/lightning/confirm-delivery` | POST | Erhalt bestätigen (Käufer) |
| `/sk/v1/lightning/verify-preimage` | POST | Preimage einreichen (Käufer) |
| `/sk/v1/lightning/proof/{vendor_id}` | GET | Öffentliche Reputation-Proofs |
| `/sk/v1/lightning/rate` | GET | BTC/EUR/CHF Kurs |

## Abhängigkeiten

- **sk-core** (Host-Plugin)
- **PHP GMP Extension** (für xpub BIP32 Derivation)
- **nostr-php** (via nostr-auto-poster Plugin, für NWC)
- **websocket/client** (via nostr-auto-poster Plugin, für NWC)

## Blockchain-Verifizierung

1. **Fulcrum** (ssl://private-fulcrum.yourdevice.ch:50002) — Eigener Electrum Server, kein Rate-Limit, Mempool-Zugang
2. **mempool.space** REST API — Öffentlicher Fallback
