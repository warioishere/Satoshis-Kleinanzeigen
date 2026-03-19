# SK Reputation — Sybil-resistentes Reputationssystem

## Übersicht

SK Reputation ist ein eigenständiges sk-core Modul das ein kryptographisch verifizierbares Reputationssystem für den Marketplace bereitstellt. Basiert auf tatsächlichen Bitcoin-Zahlungen (Lightning + Onchain) aus dem SK Payments Modul.

## Wie es funktioniert

### Reputation-Flow

1. Käufer bezahlt via Lightning oder Onchain → Payment-Status wird `confirmed`
2. `reputation_at` wird auf confirmed_at + 7 Tage gesetzt
3. **Weg A**: Käufer klickt "Produkt erhalten" → Reputation wird sofort gutgeschrieben
4. **Weg B**: 7 Tage vergehen ohne Aktion → Cron creditiert automatisch
5. Vor Gutschrift: Sybil-Checks + Validierung

### Sybil-Detection

Jede Zahlung wird auf Manipulation geprüft bevor sie als Reputation zählt:

| Check | Was geprüft wird | Flag |
|-------|-------------------|------|
| **IP-Overlap** | Käufer und Verkäufer haben gleichen IP-Hash | `same_network` |
| **Circular Payment** | A bezahlt B UND B bezahlt A innerhalb 30 Tagen | `circular_payment` |
| **Ring Detection** | A→B, B→C, C→A innerhalb 7 Tagen | `ring_detected` |
| **Burst Detection** | >5 Zahlungen von Accounts <14 Tage alt in 24h | `burst_new_accounts` |

Bei Burst-Detection wird der Admin per E-Mail benachrichtigt.

### Validierungs-Kriterien

Eine Zahlung qualifiziert sich für Reputation wenn:

- Produkt existiert und war >= 24h vor Zahlung veröffentlicht
- Betrag >= 1.000 Sats
- Käufer-Account >= 7 Tage alt
- Keine Sybil-Flags

### Reputation Score

Gewichteter Score aus drei Faktoren:
- **Unique Buyers** — max 500 Punkte (10 pro Buyer)
- **Valide Transaktionen** — max 250 Punkte (5 pro TX)
- **Volumen** — max 250 Punkte (1 pro 10.000 Sats)

### Badges

| Verifizierte TXs | Badge | Label |
|-------------------|-------|-------|
| >= 5 | ⚡ | Lightning Starter |
| >= 25 | ⚡⚡ | Lightning Händler |
| >= 100 | ⚡⚡⚡ | Lightning Veteran |

### Proof Page

Öffentliche Seite unter `/store/{vendor}/lightning-proof/` die alle verifizierten Transaktionen zeigt:

- **Lightning-Proofs**: Payment-Hash + bolt11 Invoice → verifizierbar auf lightningdecoder.com
- **Onchain-Proofs**: BTC-Adresse + TX-Link auf mempool.space
- **JSON API**: `/wp-json/sk/v1/lightning/proof/{vendor_id}` für maschinelle Verifizierung

Motto: **Don't trust, verify.**

## Technische Architektur

```
modules/sk-reputation/
├── module.php                    # Entry Point, Namespace SK\Modules\Reputation
├── includes/
│   ├── Calculator.php            # Sybil-Detection + Score-Berechnung
│   ├── Cron.php                  # 6h Cron: Auto-Credit + Invoice Expiry
│   └── ProofPage.php             # Store-Tab + Proof Template
└── templates/
    └── store-lightning-proof.php  # Öffentliche Proof-Seite
```

## Datenbank

### wp_sk_lightning_payments (gelesen)
- `reputation_valid` — 1 wenn Reputation gutgeschrieben
- `reputation_flags` — JSON Array der Sybil-Flags
- `reputation_at` — Zeitpunkt ab dem Reputation creditiert wird
- `context` — `chat`, `direct`, `onchain` (alle zählen gleich)

### wp_sk_reputation_scores (geschrieben)
- `vendor_id` — Primary Key
- `total_transactions`, `valid_transactions`, `unique_buyers`
- `total_volume_sats`, `valid_volume_sats`
- `reputation_score` — Gewichteter Score
- `last_calculated_at`

## Cron

Läuft alle 6 Stunden (`sk_recalculate_reputation_scores`):
1. Findet Zahlungen wo `reputation_at` erreicht und Käufer nichts getan hat
2. Validiert jede Zahlung (Sybil-Checks)
3. Creditiert oder flaggt
4. Recalculated Vendor-Scores
5. Expired alte pending Invoices (>15 Min)

## Admin Settings

Unabhängig aktivierbar/deaktivierbar im PHP Dashboard unter "SK Payments" → "Reputation-System aktivieren" (Switcher).

## Abhängigkeit

- **sk-core** (Host-Plugin)
- **SK Payments Modul** — optional aber empfohlen. Ohne Payments gibt es keine Zahlungen zum Tracken. ProofPage prüft graceful ob StoreSettings-Klasse existiert.
